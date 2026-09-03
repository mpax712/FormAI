const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests/Browser',
    use: { baseURL: process.env.APP_URL || 'http://127.0.0.1:8000', trace: 'retain-on-failure' },
    webServer: process.env.CI ? { command: 'php artisan serve --host=127.0.0.1 --port=8000', url: 'http://127.0.0.1:8000', reuseExistingServer: false } : undefined,
});
