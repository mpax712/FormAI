const { test, expect } = require('@playwright/test');
const { execFileSync, spawn } = require('node:child_process');
const { mkdtempSync, writeFileSync, readFileSync, rmSync } = require('node:fs');
const { tmpdir } = require('node:os');
const { injectAxe, checkA11y } = require('axe-playwright');
test.setTimeout(60000);
let server, directory, origin, testEnv;
const phpArgs = JSON.parse(process.env.FORMAI_TEST_PHP_ARGS || '[]');

test.beforeAll(async ({}, worker) => {
    directory = mkdtempSync(tmpdir() + '/formai-tutorial-');
    writeFileSync(directory + '/database.sqlite', '');
    origin = `http://127.0.0.1:${18700 + worker.workerIndex}`;
    testEnv = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: origin, APP_KEY: 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=', APP_CONFIG_CACHE: directory + '/config.php', APP_ROUTES_CACHE: directory + '/routes.php', DB_CONNECTION: 'sqlite', DB_DATABASE: directory + '/database.sqlite', DB_URL: '', DB_FALLBACK_ENABLED: 'false', SESSION_DRIVER: 'database', SESSION_SECURE_COOKIE: 'false', CACHE_STORE: 'array', MAIL_MAILER: 'array', PAO_DISABLE: '1', BCRYPT_ROUNDS: '4' };
    execFileSync('php', [...phpArgs, 'tests/Browser/prepare-tutorial.php'], { env: testEnv, timeout: 20000 });
    server = spawn('php', [...phpArgs, '-S', new URL(origin).host, '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], { env: testEnv, cwd: process.cwd() + '/public', stdio: ['ignore', 'ignore', 'pipe'] });
    let serverErrors = '';
    server.stderr.on('data', chunk => { serverErrors += chunk.toString(); });
    await expect.poll(async () => { try { return (await fetch(origin + '/login', { signal: AbortSignal.timeout(2000) })).status; } catch { return 0; } }, { timeout: 15000 }).toBe(200).catch(error => { throw new Error(error.message + '\n' + serverErrors); });
});
test.afterAll(() => { server?.kill(); if (directory) rmSync(directory, { recursive: true, force: true }); });
test.beforeEach(async ({ page }) => {
    execFileSync('php', [...phpArgs, 'tests/Browser/reset-tutorial.php'], { env: testEnv, timeout: 20000 });
    await page.route('https://cdn.jsdelivr.net/**', route => {
        const file = route.request().url().includes('.css') ? 'bootstrap.min.css' : 'bootstrap.bundle.min.js';
        return route.fulfill({ contentType: file.endsWith('css') ? 'text/css' : 'text/javascript', body: readFileSync((process.env.FORMAI_BROWSER_ASSETS || '/tmp/formai-v2-browser') + '/' + file) });
    });
    await login(page);
});
async function login(page) {
    await page.goto(origin + '/login');
    await page.getByLabel('E-mail', { exact: true }).fill('tutorial@example.test');
    await page.getByLabel('Senha', { exact: true }).fill('TutorialTest123!');
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await expect(page).toHaveURL(origin + '/dashboard');
}
const action = (page, name) => page.locator('.tour-panel').getByRole('button', { name, exact: true });
async function step(page, number) { await expect(page.locator('.tour-progress')).toHaveText(`Passo ${number} de 14`); }
async function start(page) { await action(page, 'Começar tutorial').click(); await step(page, 1); }
async function next(page, number) { await action(page, 'Próximo').click(); await step(page, number); }
async function toInteractive(page) { await start(page); await next(page, 2); await next(page, 3); await next(page, 4); }
async function replay(page) { await page.goto(origin + '/perfil'); await page.locator('[data-tour-restart]').click(); await expect(page.getByRole('dialog')).toBeVisible(); }

test('new teacher opens automatically, highlights the dashboard and advances', async ({ page }) => {
    await expect(page.getByRole('dialog')).toBeVisible();
    await expect(page.locator('[data-tour-help]')).toHaveCount(0);
    await start(page);
    const target = await page.locator('[data-tour="dashboard"]').boundingBox();
    const ring = await page.locator('.tour-ring').boundingBox();
    expect(Math.abs(target.x - ring.x)).toBeLessThanOrEqual(6);
    await next(page, 2);
    await action(page, 'Voltar').click(); await step(page, 1);
});

test('real required clicks carry the step across pages without saving it', async ({ page }) => {
    let markerWrites = 0;
    page.on('request', request => { if (request.url().endsWith('/tutorial/visto') && request.method() === 'POST') markerWrites++; });
    await toInteractive(page);
    await expect(action(page, 'Próximo')).toHaveCount(0);
    await page.locator('[data-tour="activities-menu"]').click();
    await expect(page).toHaveURL(origin + '/professor/atividades'); await step(page, 5);
    await page.locator('[data-tour="new-activity"]').click();
    await expect(page).toHaveURL(origin + '/professor/atividades/create'); await step(page, 6);
    expect(markerWrites).toBeLessThanOrEqual(1);
    await page.reload(); await expect(page.locator('.tour-progress')).toHaveText('Primeiros passos');
});

test('pause, Escape and replay from profile work without progress writes', async ({ page }) => {
    await action(page, 'Agora não').click();
    await page.reload(); await expect(page.getByRole('dialog')).toBeHidden();
    await replay(page); await start(page);
    await page.keyboard.press('Escape'); await expect(page.getByRole('dialog')).toBeHidden();
    await replay(page); await start(page);
});

test('teacher without data can finish, skip and replay', async ({ page }) => {
    await toInteractive(page);
    await page.locator('[data-tour="activities-menu"]').click(); await step(page, 5);
    await page.locator('[data-tour="new-activity"]').click(); await step(page, 6);
    for (let i = 7; i <= 14; i++) await next(page, i);
    await action(page, 'Concluir tutorial').click(); await page.reload();
    await expect(page.getByRole('dialog')).toBeHidden();
    await replay(page); await start(page);
    await action(page, 'Pular tutorial').click(); await page.reload();
    await expect(page.getByRole('dialog')).toBeHidden();
});

test('mobile highlight stays clickable and keyboard reaches it', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await toInteractive(page);
    const target = page.locator('[data-tour="activities-menu"]');
    await expect(target).toBeVisible();
    const tr = await target.boundingBox(), pr = await page.locator('.tour-panel').boundingBox();
    expect(tr.y + tr.height).toBeLessThan(pr.y);
    for (let i = 0; i < 4; i++) await page.keyboard.press('Tab');
    await expect(target).toBeFocused();
    await page.keyboard.press('Enter'); await step(page, 5);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});

test('missing target falls back without JavaScript errors', async ({ page }) => {
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await start(page); await page.locator('[data-tour="classrooms-menu"]').evaluate(el => el.remove());
    await next(page, 2);
    await expect(page.locator('#tour-description')).toContainText('não está visível');
    await next(page, 3); expect(errors).toEqual([]);
});

test('motion appears only on interactive steps and is cancelled on close', async ({ page }) => {
    const root = page.locator('#teacher-tutorial');
    await expect(root).toHaveClass(/tour-entering/);
    await start(page); await expect(root).toHaveClass(/tour-confirmed/);
    await expect(root).not.toHaveClass(/tour-interactive/);
    await next(page, 2); await next(page, 3); await next(page, 4);
    await expect(root).toHaveClass(/tour-interactive/);
    await expect(page.locator('[data-tour="activities-menu"]')).toHaveClass(/tour-target-interactive/);
    await page.locator('[data-tour="activities-menu"]').click(); await step(page, 5);
    await action(page, 'Pausar').click();
    await expect(root).toHaveJSProperty('hidden', true);
    await expect(root).not.toHaveClass(/tour-interactive|tour-saving|tour-confirmed/);
});

test('reduced motion keeps all actions and removes decorative animation', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await toInteractive(page);
    await expect(page.locator('#teacher-tutorial')).not.toHaveClass(/tour-interactive/);
    await expect(page.locator('.tour-instruction')).toBeVisible();
    await expect(page.locator('.tour-arrow')).toBeHidden();
    await page.locator('[data-tour="activities-menu"]').click(); await step(page, 5);
    await page.locator('[data-tour="new-activity"]').click(); await step(page, 6);
    for (let i = 7; i <= 14; i++) await next(page, i);
    await action(page, 'Concluir tutorial').click(); await expect(page.getByRole('dialog')).toBeHidden();
});

test('restarting repeatedly has no duplicate handlers or page errors', async ({ page }) => {
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await action(page, 'Agora não').click();
    for (let i = 0; i < 3; i++) {
        await replay(page); await start(page); await next(page, 2);
        await page.keyboard.press('Escape'); await expect(page.getByRole('dialog')).toBeHidden();
    }
    expect(errors).toEqual([]);
});

test('welcome and click step remain accessible', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await injectAxe(page);
    await checkA11y(page, undefined, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } });
    await toInteractive(page);
    await checkA11y(page, undefined, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } });
});

test('dashboard aggregate cache avoids duplicate requests and expires after 30 seconds', async ({ page }) => {
    await action(page, 'Agora não').click();
    let calls = 0;
    page.on('request', request => { if (request.url().includes('/api/estatisticas') && request.url().includes('summary=1')) calls++; });
    await page.locator('[data-summary-refresh]').click();
    expect(calls).toBe(0);
    await page.evaluate(() => {
        const value = JSON.parse(sessionStorage.getItem('formai:dashboard-summary'));
        value.at = Date.now() - 31000;
        sessionStorage.setItem('formai:dashboard-summary', JSON.stringify(value));
    });
    await page.locator('[data-summary-refresh]').click();
    await expect(page.locator('[data-summary-status]')).toHaveText('Indicadores atualizados agora.');
    expect(calls).toBe(1);
    await page.locator('[data-summary-refresh]').click();
    expect(calls).toBe(1);
});

test('password controls reveal and hide without clearing values or submitting', async ({ page }) => {
    await action(page, 'Agora não').click();
    await page.locator('form[action$="/logout"] button').click();
    await expect(page).toHaveURL(origin + '/');
    await page.goto(origin + '/login');
    const password = page.locator('#password');
    await password.fill('TutorialTest123!');
    const toggle = page.getByRole('button', { name: 'Mostrar senha' });
    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(password).toHaveAttribute('type', 'text');
    await expect(password).toHaveValue('TutorialTest123!');
    await expect(page.getByRole('button', { name: 'Ocultar senha' })).toHaveAttribute('aria-pressed', 'true');
    await page.keyboard.press('Enter');
    await expect(password).toHaveAttribute('type', 'password');
    await expect(page).toHaveURL(origin + '/login');
    await page.goto(origin + '/register');
    await page.locator('#password_confirmation').fill('TutorialTest123!');
    await page.getByRole('button', { name: 'Mostrar confirmar senha' }).click();
    await expect(page.locator('#password_confirmation')).toHaveAttribute('type', 'text');
    await expect(page.locator('#password_confirmation')).toHaveValue('TutorialTest123!');
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});

test('student without activities can complete and replay the guided tour', async ({ page }) => {
    await action(page, 'Agora não').click();
    await page.locator('form[action$="/logout"] button').click();
    await page.goto(origin + '/login');
    await page.getByLabel('E-mail', { exact: true }).fill('aluno-tutorial@example.test');
    await page.getByLabel('Senha', { exact: true }).fill('TutorialTest123!');
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await expect(page).toHaveURL(origin + '/dashboard');
    await expect(page.locator('#student-tutorial').getByRole('dialog')).toBeVisible();
    await action(page, 'Começar tutorial').click();
    await expect(page.locator('.tour-progress')).toHaveText('Passo 1 de 9');
    await action(page, 'Próximo').click();
    await expect(page.locator('.tour-progress')).toHaveText('Passo 2 de 9');
    await action(page, 'Próximo').click();
    await expect(page.locator('.tour-progress')).toHaveText('Passo 3 de 9');
    await page.locator('[data-tour="student-activities-menu"]').click();
    await expect(page).toHaveURL(origin + '/aluno/atividades');
    await expect(page.locator('.tour-progress')).toHaveText('Passo 4 de 9');
    for (let i = 5; i <= 9; i++) await action(page, 'Próximo').click();
    await action(page, 'Concluir tutorial').click();
    await page.goto(origin + '/perfil');
    await page.locator('[data-tour-restart]').click();
    await expect(page.locator('#student-tutorial').getByRole('dialog')).toBeVisible();
});
