const { test, expect } = require('@playwright/test');
const { injectAxe, checkA11y } = require('axe-playwright');

test('landing page has no automatically detectable accessibility violations', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { name: /Menos tempo corrigindo/i })).toBeVisible();
    await injectAxe(page);
    await checkA11y(page, undefined, { detailedReport: true, detailedReportOptions: { html: true } });
});
