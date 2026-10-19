// Local-only browser regression for blank pages and failed Livewire table queries.
const { createRequire } = require('node:module');
const assert = require('node:assert/strict');
const load = createRequire(__filename);
const { chromium } = load(process.env.PLAYWRIGHT_MODULE || 'playwright');

const baseUrl = process.env.REVIEW_URL || 'http://127.0.0.1:8017';
const paths = (process.env.REVIEW_PATHS || '/admin/surat-masuks,/admin/surat-keluars,/admin/legalisirs')
    .split(',')
    .map(path => path.trim())
    .filter(Boolean);

assert(['127.0.0.1', 'localhost'].includes(new URL(baseUrl).hostname), 'Review is local-only.');
assert(process.env.REVIEW_EMAIL && process.env.REVIEW_PASSWORD, 'Review credentials are required.');

(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();
    const failures = [];
    let expectedFailureResponses = 0;

    page.on('pageerror', error => failures.push(`JavaScript: ${error.message}`));
    page.on('response', response => {
        if (response.status() >= 500) {
            if (expectedFailureResponses > 0 && response.url().includes('/livewire/update')) {
                expectedFailureResponses--;
                return;
            }
            failures.push(`HTTP ${response.status()}: ${response.url()}`);
        }
    });

    try {
        await page.goto(`${baseUrl}/admin/login`);
        await page.locator('input[type=email]').fill(process.env.REVIEW_EMAIL);
        await page.locator('input[type=password]').fill(process.env.REVIEW_PASSWORD);
        await page.locator('button[type=submit]').click();
        await page.waitForURL(url => url.pathname === '/admin');

        for (let cycle = 1; cycle <= 2; cycle++) {
            for (const path of paths) {
                await page.goto(`${baseUrl}/admin`);
                const link = page.locator(`a[href="${baseUrl}${path}"]`).first();
                await link.waitFor({ state: 'visible' });
                await Promise.all([
                    page.waitForURL(url => url.pathname === path),
                    link.click(),
                ]);

                await page.locator('.fi-page').waitFor({ state: 'visible' });
                assert(
                    (await page.locator('.fi-main').innerText()).trim().length > 20,
                    `${path} rendered an empty main area`,
                );

                const search = page.locator('.fi-ta-search-field input').first();
                if (await search.count()) {
                    const searchResponse = page.waitForResponse(response =>
                        response.url().includes('/livewire/update') && response.request().method() === 'POST',
                    );
                    await search.fill('query-yang-tidak-ada-123');
                    assert.equal((await searchResponse).status(), 200, `${path} search query failed`);
                    await page.locator('.fi-page').waitFor({ state: 'visible' });

                    const clearResponse = page.waitForResponse(response =>
                        response.url().includes('/livewire/update') && response.request().method() === 'POST',
                    );
                    await search.fill('');
                    assert.equal((await clearResponse).status(), 200, `${path} clear search failed`);
                }

                const actionTrigger = page.locator('.fi-ta-actions .fi-dropdown-trigger button:visible').first();
                if (await actionTrigger.count()) {
                    await actionTrigger.click();
                    const menu = page.locator('.fi-dropdown-panel:visible').last();
                    await menu.waitFor({ state: 'visible' });
                    await page.keyboard.press('Escape');
                    await menu.waitFor({ state: 'hidden' });
                }

                assert.equal(await page.locator('[data-tu-request-error]').count(), 0, `${path} used recovery UI`);
            }
        }

        // Simulate one failed Livewire query. The client must recover with one
        // hard reload and render the same page instead of leaving a blank shell.
        await page.goto(`${baseUrl}${paths[0]}`);
        expectedFailureResponses = 1;
        await page.route('**/livewire/update', route => route.fulfill({
            status: 500,
            contentType: 'text/plain',
            body: 'Simulated query failure',
        }), { times: 1 });
        const recoveredNavigation = page.waitForEvent('framenavigated', frame => frame === page.mainFrame());
        await page.locator('.fi-ta-search-field input').first().fill('paksa-pemulihan');
        await recoveredNavigation;
        await page.locator('.fi-page').waitFor({ state: 'visible' });
        assert(
            (await page.locator('.fi-main').innerText()).trim().length > 20,
            'Automatic query recovery rendered a blank page',
        );

        assert.deepEqual(failures, []);
        console.log(`Navigation reliability passed for ${paths.length} pages over 2 cycles, including automatic query recovery.`);
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error.stack || error.message);
    process.exitCode = 1;
});
