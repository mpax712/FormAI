const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const { readFileSync } = require('node:fs');
const { injectAxe } = require('axe-playwright');
let pages;
test.beforeAll(() => {
    const args = JSON.parse(process.env.FORMAI_TEST_PHP_ARGS || '[]');
    pages = JSON.parse(execFileSync('php', [...args, 'tests/Browser/render-teacher-pages.php'], { encoding: 'utf8', maxBuffer: 8 * 1024 * 1024, env: { ...process.env, PAO_DISABLE: '1' } }));
});
test.beforeEach(async ({ page }) => {
    // Real Bootstrap assets, locally cached for the test run; no API or application server.
    await page.route('https://cdn.jsdelivr.net/**', route => {
        const file = route.request().url().includes('.css') ? 'bootstrap.min.css' : 'bootstrap.bundle.min.js';
        return route.fulfill({ contentType: file.endsWith('css') ? 'text/css' : 'text/javascript', body: readFileSync((process.env.FORMAI_BROWSER_ASSETS || '/tmp/formai-v2-browser') + '/' + file) });
    });
    await page.route('http://formai.test/**', route => {
        const path = new URL(route.request().url()).pathname;
        if (path === '/css/formai.css') return route.fulfill({ contentType: 'text/css', body: readFileSync('public/css/formai.css') });
        if (path === '/css/mobile-app.css') return route.fulfill({ contentType: 'text/css', body: readFileSync('public/css/mobile-app.css') });
        if (path === '/js/formai.js') return route.fulfill({ contentType: 'text/javascript', body: readFileSync('public/js/formai.js') });
        const name = path.slice(1) || 'landing';
        return route.fulfill({ status: pages[name] ? 200 : 404, contentType: 'text/html', body: pages[name] || '' });
    });
});

for (const width of [320, 360, 390, 768, 1440, 720]) {
 test('primary screens reflow at width ' + width, async ({ page }) => {
        await page.setViewportSize({ width, height: width === 720 ? 450 : 1000 });
        await page.emulateMedia({ reducedMotion: 'reduce' });
    const failures = [];
    for (const name of ['dashboard', 'student', 'admin', 'deliveries', 'activityResults', 'questions', 'editor', 'activity', 'grading', 'classrooms', 'classroom', 'activities', 'profile', 'studentActivities', 'studentAnswer', 'studentResult', 'adminUsers', 'adminAcademic', 'landing', 'login', 'register', 'forgot', 'confirmPassword', 'twoFactor']) {
            await page.goto('http://formai.test/' + name);
            await expect(page.locator('main')).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${name} at ${width}`).toBe(true);
        }
 });
}

test('headings, labels and table text do not split ordinary words on narrow screens', async ({ page }) => {
    for (const width of [280, 320, 768, 1024, 1200, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        for (const name of ['activity', 'activityResults', 'activities', 'classroom', 'admin', 'adminUsers', 'landing']) {
            await page.goto('http://formai.test/' + name);
            const brokenWords = await page.evaluate(() => {
                const broken = [];
                const walker = document.createTreeWalker(document.querySelector('main') || document.body, NodeFilter.SHOW_TEXT);
                while (walker.nextNode()) {
                    const node = walker.currentNode;
                    if (!node.parentElement || node.parentElement.closest('script, style, svg, textarea, option, [hidden], [aria-hidden="true"]')) continue;
                    if (getComputedStyle(node.parentElement).display === 'none') continue;
                    for (const match of node.textContent.matchAll(/[\p{L}\p{N}]{3,}/gu)) {
                        const range = document.createRange();
                        range.setStart(node, match.index);
                        range.setEnd(node, match.index + match[0].length);
                        const rects = [...range.getClientRects()].filter(rect => rect.width > .2);
                        if (rects.length > 1 && Math.max(...rects.map(rect => rect.top)) - Math.min(...rects.map(rect => rect.top)) > 2) {
                            broken.push(match[0]);
                        }
                    }
                }
                return broken.slice(0, 10);
            });
            expect(brokenWords, `${name} at ${width}px`).toEqual([]);
        }
    }
});

test('activity actions remain visible on tablet and return to a table on desktop', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 900 });
    await page.goto('http://formai.test/activities');
    await expect(page.locator('[data-tour="activity-list"] thead')).toBeHidden();
    await expect(page.locator('[data-tour="activity-list"] .mobile-cell-label').first()).toBeVisible();
    const actionsFit = await page.locator('[data-tour="activity-list"] .mobile-actions').first().evaluate(actions => {
        const card = actions.closest('tr').getBoundingClientRect();
        return [...actions.querySelectorAll('a, button')].every(control => control.getBoundingClientRect().right <= card.right + 1);
    });
    expect(actionsFit).toBe(true);

    await page.setViewportSize({ width: 1200, height: 900 });
    await page.goto('http://formai.test/activities');
    await expect(page.locator('[data-tour="activity-list"] thead')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
});

test('individual options and long errors stay within the delivery card', async ({ page }) => {
    for (const width of [360, 768, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('http://formai.test/deliveries');
        const details = page.locator('.v2-individual-ai').first();
        await details.locator('summary').click();
        await expect(details.locator('select').first()).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
        const contained = await details.evaluate(el => {
            const card = el.closest('[data-delivery]').getBoundingClientRect();
            return [...el.querySelectorAll('select, button')].every(child => {
                const r = child.getBoundingClientRect();
                return r.left >= card.left && r.right <= card.right + 1;
            });
        });
        expect(contained).toBe(true);
    }
});

test('grading navigation warns before leaving an edited review', async ({ page }) => {
    await page.goto('http://formai.test/grading');
    const next = page.locator('[data-grading-nav]').last();
    await expect(next).toBeVisible();
    await page.locator('[name^="grades["][name$="[score]"]').first().fill('7');
    page.once('dialog', async dialog => {
        expect(dialog.message()).toContain('alterações na correção');
        await dialog.dismiss();
    });
    await next.click();
    await expect(page).toHaveURL('http://formai.test/grading');
});

test('batch selection respects visible results and confirms exact counts', async ({ page }) => {
    await page.goto('http://formai.test/deliveries');
    const button = page.locator('[data-batch-submit]');
    await expect(button).toBeDisabled();
    await page.locator('[data-select-visible]').check();
    await expect(page.locator('[data-selection-count]')).toHaveText('2 entregas · 2 questões dissertativas');
    await page.locator('[data-delivery-search]').fill('ANA');
    await expect(button).toBeDisabled();
    await expect(page.locator('[data-delivery]:visible')).toHaveCount(1);
    await page.locator('[data-select-visible]').check();
    await expect(page.locator('[data-selection-count]')).toHaveText('1 entregas · 1 questões dissertativas');
    const body = await page.locator('#selected-deliveries').evaluate(form => [...new FormData(form).getAll('submission_ids[]')]);
    expect(body).toHaveLength(1);
    page.once('dialog', async dialog => {
        expect(dialog.message()).toContain('1 entregas e 1 questões dissertativas');
        await dialog.dismiss();
    });
    await button.click();
    await expect(button).toBeEnabled();
    await page.locator('[data-delivery-filter]').selectOption('failed');
    await expect(button).toBeDisabled();
    await expect(page.locator('[data-no-deliveries]')).toBeVisible();
    await page.locator('[data-delivery-search]').fill('');
    await expect(page.locator('[data-delivery]:visible')).toHaveCount(1);
    await page.locator('[data-select-visible]').check();
    await page.locator('[data-clear-selection]').click();
    await expect(button).toBeDisabled();
});

test('student sees save failure, can retry, and final submission waits for the save', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 720 });
    let attempts = 0;
    await page.route('**/aluno/entregas/**/questoes/**', async route => {
        attempts++;
        if (attempts === 1) {
            await new Promise(resolve => setTimeout(resolve, 400));
            return route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'Banco temporariamente indisponível.' }) });
        }
        return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ saved: true, version: 2 }) });
    });
    await page.goto('http://formai.test/studentAnswer');
    await page.locator('[data-autosave] textarea').fill('Resposta revisada pelo aluno.');
    page.once('dialog', dialog => dialog.accept());
    await page.locator('.student-submit-form button').click();
    await expect(page.locator('.student-submit-form button')).toBeDisabled();
    await expect(page.locator('.student-submit-form button')).toHaveText('Salvando respostas...');
    await expect(page.locator('[data-save-status]')).toHaveText('Banco temporariamente indisponível.');
    await expect(page.locator('.student-submit-form button')).toBeEnabled();
    await expect(page.locator('[data-save-retry]')).toBeVisible();
    await expect.poll(() => page.locator('[data-save-retry]').evaluate(el =>
        el.getBoundingClientRect().bottom <= document.querySelector('.mobile-app-nav').getBoundingClientRect().top
    )).toBe(true);
    await page.locator('[data-save-retry]').click();
    await expect(page.locator('[data-save-status]')).toHaveText('Salvo agora');
    await expect(page.locator('[data-save-retry]')).toBeHidden();
});

test('guest consent controls have comfortable mobile touch targets', async ({ page }) => {
    for (const width of [320, 360, 390]) {
        await page.setViewportSize({ width, height: 720 });
        for (const [name, id] of [['login', 'remember'], ['register', 'terms']]) {
            await page.goto('http://formai.test/' + name);
            const label = page.locator(`label[for="${id}"]`);
            expect((await label.boundingBox()).height).toBeGreaterThanOrEqual(44);
            await label.click();
            await expect(page.locator('#' + id)).toBeChecked();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
        }
    }
});

test('dashboard refresh recovers when the database request stops responding', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 720 });
    await page.clock.install();
    await page.addInitScript(() => {
        const originalFetch = window.fetch.bind(window);
        window.fetch = (url, options = {}) => String(url).includes('estatisticas')
            ? new Promise((resolve, reject) => options.signal.addEventListener('abort', () => reject(new DOMException('Tempo esgotado', 'AbortError')), { once: true }))
            : originalFetch(url, options);
    });
    await page.goto('http://formai.test/dashboard');
    await page.evaluate(() => sessionStorage.removeItem('formai:dashboard-summary'));
    const button = page.locator('[data-summary-refresh]');
    await button.click();
    await expect(button).toBeDisabled();
    await page.clock.fastForward(12001);
    await expect(button).toBeEnabled();
    await expect(page.locator('[data-summary-status]')).toContainText('Não foi possível atualizar');
});

test('dashboards and delivery controls have accessible names, contrast and keyboard focus', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const failures = [];
    for (const name of ['dashboard', 'student', 'admin', 'deliveries', 'login', 'register', 'profile', 'adminUsers', 'adminAcademic', 'confirmPassword', 'twoFactor']) {
        await page.goto('http://formai.test/' + name);
        await injectAxe(page);
        const violations = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'] } })).violations);
        failures.push(...violations.map(v => ({ page: name, id: v.id, elements: v.nodes.map(n => n.target) })));
    }
    expect(failures).toEqual([]);
    await page.goto('http://formai.test/deliveries');
    await page.locator('.v2-individual-ai summary').first().focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('.v2-individual-ai').first()).toHaveAttribute('open', '');
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
    await page.screenshot({ path: 'test-results/ui-v2-deliveries.png', fullPage: true });
    await page.goto('http://formai.test/dashboard');
    expect(await page.locator('.v2-filters').evaluate(el => el.getBoundingClientRect().height)).toBeLessThan(300);
    await page.screenshot({ path: 'test-results/ui-v2-dashboard.png', fullPage: true });
});

test('signed-in mobile navigation and action bars remain usable on small and short screens', async ({ page }) => {
    for (const [name, labels] of [['dashboard', ['Início', 'Turmas', 'Questões', 'Atividades']], ['student', ['Início', 'Atividades']], ['admin', ['Início', 'Usuários', 'Acadêmico']]]) {
        for (const viewport of [{ width: 320, height: 720 }, { width: 390, height: 450 }]) {
            await page.setViewportSize(viewport);
            await page.goto('http://formai.test/' + name);
            const nav = page.getByRole('navigation', { name: 'Navegação principal no celular' });
            await expect(nav).toBeVisible();
            await expect(nav.getByRole('link')).toHaveCount(labels.length);
            await expect(nav.locator('[aria-current="page"]')).toHaveCount(1);
            expect((await nav.getByRole('link').allTextContents()).map(label => label.trim())).toEqual(labels);
            expect(await nav.getByRole('link').evaluateAll(links => links.every(link => link.getBoundingClientRect().height >= 44))).toBe(true);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
        }
    }
    await page.setViewportSize({ width: 390, height: 450 });
    for (const name of ['activity', 'grading']) {
        await page.goto('http://formai.test/' + name);
        expect(await page.locator(name === 'activity' ? '.question-add-toolbar' : '.grading-save-bar').first()
            .evaluate(el => getComputedStyle(el).position)).toBe('static');
        const action = page.locator(name === 'activity' ? '.activity-form-actions button' : '.grading-save-bar button').last();
        await action.scrollIntoViewIfNeeded();
        const unobscured = await action.evaluate(el => el.getBoundingClientRect().bottom <= document.querySelector('.mobile-app-nav').getBoundingClientRect().top);
        expect(unobscured).toBe(true);
    }
    await page.setViewportSize({ width: 768, height: 900 });
    await page.goto('http://formai.test/dashboard');
    await expect(page.getByRole('navigation', { name: 'Navegação principal no celular' })).toBeHidden();
});

test('mobile signed-in pages pass automated WCAG checks', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 720 });
    for (const name of ['dashboard', 'activities', 'classroom', 'grading', 'studentAnswer', 'adminUsers']) {
        await page.goto('http://formai.test/' + name);
        await injectAxe(page);
        const violations = await page.evaluate(async () => (await axe.run(document, {
            runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'] },
        })).violations);
        expect(violations.map(v => ({ id: v.id, targets: v.nodes.map(node => node.target) })), name).toEqual([]);
    }
});

test('signed-in content reflows with 200 percent text at 320 pixels', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 720 });
    for (const name of ['dashboard', 'activities', 'classroom', 'grading', 'student', 'studentAnswer', 'adminUsers']) {
        await page.goto('http://formai.test/' + name);
        await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), name).toBe(true);
        expect(await page.locator('.mobile-app-nav').evaluate(el => el.scrollWidth <= el.clientWidth + 1), name).toBe(true);
    }
});

test('landing additions stay accessible, responsive and static with reduced motion', async ({ page }) => {
    for (const width of [360, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto('http://formai.test/landing');
        await expect(page.getByRole('heading', { name: 'Mais clareza em cada etapa do ensino.' })).toBeVisible();
        if (width === 360) {
            const headingWidth = await page.locator('#process-title').evaluate(el => el.getBoundingClientRect().width);
            expect(headingWidth).toBeGreaterThan(290);
        }
        await injectAxe(page);
        const violations = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } })).violations);
        expect(violations.map(v => ({ id: v.id, targets: v.nodes.map(node => node.target) }))).toEqual([]);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
        expect(await page.locator('.landing-benefits-grid article').first().evaluate(el => parseFloat(getComputedStyle(el).transitionDuration))).toBeLessThan(0.02);
    }
});
