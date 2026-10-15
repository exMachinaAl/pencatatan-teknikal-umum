// Optional browser smoke test. Uses a local Playwright installation and existing Edge.
// REVIEW_EMAIL / REVIEW_PASSWORD enable read-only authenticated UI checks.
// No records, backups, or database schema are created/changed by this script.
const { createRequire } = require('node:module');
const { mkdir } = require('node:fs/promises');
const path = require('node:path');
const assert = require('node:assert/strict');
const load = createRequire(__filename);
const { chromium } = load(process.env.PLAYWRIGHT_MODULE || 'playwright');
const baseUrl = process.env.REVIEW_URL || 'http://127.0.0.1:8017';
assert(['127.0.0.1', 'localhost'].includes(new URL(baseUrl).hostname), 'Review is local-only.');
const artifactDir = path.resolve(__dirname, '../../storage/app/ui-review');

(async () => {
    await mkdir(artifactDir, { recursive: true });
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const failures = [];
    const results = [];
    try {
        for (const theme of ['light', 'dark']) {
            const context = await browser.newContext({ colorScheme: theme });
            await context.addInitScript((theme) => {
                localStorage.setItem('theme', theme);
                localStorage.setItem('isOpen', 'true'); // Must still start collapsed.
            }, theme);
            const page = await context.newPage();
            page.on('pageerror', error => failures.push(error.message));
            for (const width of [360, 390, 768, 1024, 1440]) {
                await page.setViewportSize({ width, height: 900 });
                const response = await page.goto(`${baseUrl}/admin/login`);
                assert.equal(response.status(), 200);
                await page.locator('.tu-login-page').waitFor();
                await page.evaluate(() => document.fonts.ready);
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Login overflows at ${width} / ${theme}`);
                await page.screenshot({ path: path.join(artifactDir, `login-${theme}-${width}.png`), fullPage: true });
                results.push(`Login ${theme} ${width}: no horizontal overflow`);
            }
            if (process.env.REVIEW_EMAIL && process.env.REVIEW_PASSWORD) {
                await page.locator('input[type=email]').fill(process.env.REVIEW_EMAIL);
                await page.locator('input[type=password]').fill(process.env.REVIEW_PASSWORD);
                await page.locator('button[type=submit]').click();
                await page.waitForURL(url => url.pathname === '/admin', { timeout: 15000 });
                for (const width of [360, 390, 768, 1024, 1440]) {
                    await page.setViewportSize({ width, height: 900 });
                    await page.goto(`${baseUrl}/admin`);
                    await page.locator('.tu-dashboard').waitFor();
                    await page.waitForFunction(() => window.Alpine?.store('sidebar')?.isOpen === false);
                    await page.waitForFunction(() => getComputedStyle(document.querySelector('.fi-main-ctn')).opacity === '1');
                    await page.locator('.fi-sidebar-close-overlay').waitFor({ state: 'hidden' });
                    await page.evaluate(() => document.fonts.ready);
                    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Dashboard overflows at ${width} / ${theme}`);
                    assert.equal(await page.locator('.tu-letter-card').count(), 2);
                    await page.screenshot({ path: path.join(artifactDir, `dashboard-${theme}-${width}.png`), fullPage: true, animations: 'disabled' });
                    await page.locator('.fi-topbar-open-sidebar-btn').click();
                    await page.waitForFunction(() => window.Alpine.store('sidebar').isOpen === true);
                    // Navigating back must collapse it again despite persistent state.
                    await page.reload();
                    await page.waitForFunction(() => window.Alpine.store('sidebar').isOpen === false);
                    results.push(`Dashboard ${theme} ${width}: no overflow, 2 cards, hamburger opens and resets`);
                }
                const detailLinks = await page.locator('.tu-letter-card .tu-text-link').evaluateAll(links => links.map(link => link.href));
                for (const href of detailLinks) {
                    const response = await page.goto(href);
                    assert.equal(response.status(), 200);
                    await page.locator('.fi-page').waitFor();
                }
                results.push(`Letter links ${theme}: HTTP 200, no page errors`);
            }
            await context.close();
        }
        assert.deepEqual(failures, [], 'JavaScript page errors');
        console.log(results.join('\n'));
        console.log(`Screenshots: ${artifactDir}`);
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error.message);
    process.exitCode = 1;
});
