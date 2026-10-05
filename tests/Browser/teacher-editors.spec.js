const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const { readFileSync } = require('node:fs');

let pages;
test.beforeAll(() => {
    const phpArgs = JSON.parse(process.env.FORMAI_TEST_PHP_ARGS || '[]');
    pages = JSON.parse(execFileSync('php', [...phpArgs, 'tests/Browser/render-teacher-pages.php'], { encoding: 'utf8', maxBuffer: 4 * 1024 * 1024 }));
});

test.beforeEach(async ({ page }) => {
    await page.route('http://formai.test/**', async (route) => {
        const path = new URL(route.request().url()).pathname;
        if (path === '/css/formai.css') return route.fulfill({ contentType: 'text/css', body: readFileSync('public/css/formai.css', 'utf8') });
        if (path === '/css/mobile-app.css') return route.fulfill({ contentType: 'text/css', body: readFileSync('public/css/mobile-app.css', 'utf8') });
        if (path === '/js/formai.js') return route.fulfill({ contentType: 'text/javascript', body: readFileSync('public/js/formai.js', 'utf8') });
        const key = { '/professor/questoes': 'questions', '/professor/questoes/create': 'editor', '/professor/atividades/create': 'activity', '/dashboard': 'dashboard', '/professor/entregas/demo/corrigir': 'grading' }[path];
        return route.fulfill({ status: key ? 200 : 404, contentType: 'text/html', body: pages[key] || '' });
    });
});

test('question editor selects type before content and submits only applicable fields', async ({ page }) => {
    await page.goto('http://formai.test/professor/questoes/create');
    await expect(page.locator('[data-bank-content]')).toBeHidden();
    await page.locator('#type').selectOption('essay');
    await expect(page.locator('#body')).toBeVisible();
    await expect(page.locator('#teacher_instruction')).toBeVisible();
    await expect(page.locator('[data-bank-kind="single_choice"]')).toBeHidden();
    await page.locator('#type').selectOption('single_choice');
    await expect(page.locator('#teacher_instruction')).toBeHidden();
    const correct = page.locator('[name$="[is_correct]"]');
    await correct.nth(1).check();
    await expect(correct.nth(0)).not.toBeChecked();
    expect(await page.locator('[data-bank-editor]').evaluate(form => [...new FormData(form).keys()].some(key => key.startsWith('rubric') || key === 'teacher_instruction'))).toBe(false);
});

test('criteria switch preserves edits while excluding disabled criteria from submission', async ({ page }) => {
    await page.goto('http://formai.test/professor/atividades/create');
    const card = page.locator('[data-question-card]').first();
    const toggle = card.locator('[data-criteria-toggle]');
    await expect(card.locator('[data-criteria-panel]')).toBeHidden();
    await toggle.check();
    const label = card.locator('[name$="[label]"]').first();
    await label.fill('Clareza');
    await toggle.uncheck();
    expect(await page.locator('[data-activity-builder]').evaluate(form => [...new FormData(form).keys()].some(key => key.includes('[rubric]')))).toBe(false);
    await toggle.check();
    await expect(label).toHaveValue('Clareza');
    await page.locator('[data-add-question="essay"]').click();
    await expect(page.locator('[data-question-card]').last().locator('[data-criteria-panel]')).toBeHidden();
    await page.locator('.bank-picker summary').click();
    await expect(page.locator('[name^="bank_correction["]')).toBeVisible();
});

test('context and multiple choice controls stay readable and use the right fields on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 720 });
    await page.goto('http://formai.test/professor/atividades/create');
    await page.locator('[data-add-question="context"]').click();
    const context = page.locator('[data-question-card]').last();
    await expect(context.locator('[data-score-field]')).toBeHidden();
    await expect(context.locator('[data-choice-fields]')).toBeHidden();
    await expect(context.locator('[name$="[max_score]"]')).toBeDisabled();
    await page.locator('[data-add-question="multiple_choice"]').click();
    const multiple = page.locator('[data-question-card]').last();
    await expect(multiple.locator('[data-multiple-correct]').first()).toBeVisible();
    await expect(multiple.locator('[data-single-correct]').first()).toBeHidden();
    await multiple.locator('[data-multiple-correct] input').nth(0).check();
    await multiple.locator('[data-multiple-correct] input').nth(2).check();
    const selected = await multiple.evaluate(card => [...new FormData(card.closest('form')).entries()]
        .filter(([key]) => key.includes('[correct_options][]')).map(([, value]) => value));
    expect(selected).toEqual(['0', '2']);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
});

test('teacher pages fit mobile and desktop viewports without horizontal overflow', async ({ page }) => {
    for (const width of [320, 390, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        for (const path of ['/professor/questoes', '/professor/questoes/create', '/professor/atividades/create', '/dashboard', '/professor/entregas/demo/corrigir']) {
            await page.goto('http://formai.test' + path);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${path} at ${width}px`).toBe(true);
        }
    }
});

test('AI choices belong to the question request, not the human review form', async ({ page }) => {
    await page.goto('http://formai.test/professor/entregas/demo/corrigir');
    await page.locator('[name="feedback_detail"]').selectOption('detailed');
    await page.locator('[name="intelligence_profile"]').selectOption('advanced');
    const values = await page.locator('[data-ai-submit]').evaluate(form => Object.fromEntries(new FormData(form)));
    expect(values.feedback_detail).toBe('detailed');
    expect(values.intelligence_profile).toBe('advanced');
    const review = await page.locator('#correcao-manual').evaluate(form => Object.fromEntries(new FormData(form)));
    expect(review).not.toHaveProperty('feedback_detail');
    page.once('dialog', dialog => dialog.dismiss());
    const button = page.locator('[data-ai-trigger]');
    await button.click();
    await expect(button).toBeEnabled();
});
