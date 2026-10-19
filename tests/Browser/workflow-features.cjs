const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const baseUrl = process.env.REVIEW_URL || 'http://127.0.0.1:8000';
assert(['127.0.0.1', 'localhost'].includes(new URL(baseUrl).hostname), 'Review is local-only.');

(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    const failures = [];
    page.on('pageerror', error => failures.push(error.message));
    page.on('response', response => {
        if (response.status() >= 500) failures.push(`HTTP ${response.status()} ${response.url()}`);
    });

    try {
        await page.goto(`${baseUrl}/admin/login`);
        await page.locator('input[type=email]').fill(process.env.REVIEW_EMAIL);
        await page.locator('input[type=password]').fill(process.env.REVIEW_PASSWORD);
        await page.locator('button[type=submit]').click();
        await page.waitForURL(url => url.pathname === '/admin');

        await page.goto(`${baseUrl}/admin/global-document-search`);
        await page.locator('input[type=search]').fill('Dinas');
        await page.waitForResponse(response => response.url().includes('/livewire/update'));
        await page.getByText('Surat masuk', { exact: true }).first().waitFor();

        await page.goto(`${baseUrl}/admin/legalisirs/${process.env.REVIEW_LEGALISIR_ID || 1}`);
        await page.getByText('Pelacakan & verifikasi').waitFor();
        await page.getByText('Lampiran privat & versi').waitFor();
        await page.getByText('Timeline aktivitas').waitFor();
        const trackingUrl = await page.getByRole('link', { name: 'Buka pelacakan' }).getAttribute('href');
        assert(trackingUrl, 'Tracking URL is missing');

        await page.screenshot({ path: 'storage/app/ui-workflow-preview.png', fullPage: true });
        await page.goto(trackingUrl);
        await page.getByText('Verifikasi tanpa data pribadi').waitFor();
        assert.equal(await page.locator('img[alt="QR verifikasi status legalisir"]').count(), 1);

        await page.goto(`${baseUrl}/admin/surat-masuks/${process.env.REVIEW_SURAT_MASUK_ID || 1}`);
        await page.getByText('Disposisi & tenggat').waitFor();
        await page.getByText('Surat terkait').waitFor();

        await page.goto(`${baseUrl}/admin/surat-keluars/${process.env.REVIEW_SURAT_KELUAR_ID || 1}`);
        await page.getByText('Pengiriman', { exact: true }).waitFor();

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`${baseUrl}/admin/legalisirs/${process.env.REVIEW_LEGALISIR_ID || 1}`);
        await page.getByText('Pelacakan & verifikasi').waitFor();
        const hasHorizontalOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
        assert.equal(hasHorizontalOverflow, false, 'Mobile detail page has horizontal overflow');
        await page.screenshot({ path: 'storage/app/ui-workflow-mobile-preview.png', fullPage: true });

        assert.deepEqual(failures, []);
        console.log('Workflow browser review passed: search, detail panels, private history, public tracking, and QR.');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error.stack || error.message);
    process.exitCode = 1;
});
