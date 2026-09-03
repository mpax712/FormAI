const token = document.querySelector('meta[name="csrf-token"]')?.content;

document.querySelectorAll('[data-ai-submit]').forEach((form) => {
    form.addEventListener('submit', () => {
        const linkedButton = form.id ? document.querySelector(`[form="${CSS.escape(form.id)}"]`) : null;
        const button = form.querySelector('button[type="submit"]') || linkedButton;
        if (!button) return;
        button.disabled = true;
        button.classList.add('is-ai-loading');
        const label = button.querySelector('[data-button-label]');
        if (label) label.textContent = form.dataset.aiLabel || 'Aguarde...';
        button.insertAdjacentHTML('afterbegin', '<span class="ai-spinner ai-spinner-button" aria-hidden="true"></span>');
        button.setAttribute('aria-busy', 'true');
    });
});

document.querySelectorAll('[data-ai-tracker]').forEach((tracker) => {
    const progress = tracker.querySelector('[data-ai-progress]');
    const errorPanel = tracker.querySelector('[data-ai-error]');
    const message = tracker.querySelector('[data-ai-message]');
    let polling = true;

    const showFailure = (failureMessage) => {
        polling = false;
        progress?.classList.add('d-none');
        if (!errorPanel) return;
        errorPanel.classList.remove('d-none');
        const detail = errorPanel.querySelector('[data-ai-error-message]');
        if (detail) detail.textContent = failureMessage;
    };

    const checkStatus = async () => {
        if (!polling || document.hidden) return;
        try {
            const response = await fetch(tracker.dataset.statusUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('Não foi possível consultar o andamento da correção.');
            const data = await response.json();
            if (message) {
                const count = data.requested ? ` (${data.processed} de ${data.requested})` : '';
                message.textContent = `${data.message}${count}`;
            }
            if (data.state === 'failed') {
                showFailure(data.errors?.map((error) => error.message).join(' · ') || data.message);
            } else if (data.state === 'completed') {
                polling = false;
                progress?.classList.add('is-complete');
                const title = progress?.querySelector('[data-ai-title]');
                if (title) title.textContent = 'Correção da IA concluída';
                setTimeout(() => window.location.reload(), 900);
            }
        } catch (error) {
            showFailure(`${error.message} Atualize a página para tentar novamente; a correção manual continua disponível.`);
        }
    };

    checkStatus();
    const interval = window.setInterval(() => {
        if (!polling) return window.clearInterval(interval);
        checkStatus();
    }, 2500);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) checkStatus(); });
});

document.querySelectorAll('[data-autosave]').forEach((form) => {
    let timer;
    const status = form.querySelector('[data-save-status]');
    const version = form.querySelector('[name="version"]');
    const input = form.querySelector('textarea, input[type="radio"]');
    const save = async () => {
        const body = new FormData(form);
        status.textContent = 'Salvando...';
        try {
            const response = await fetch(form.action, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'X-HTTP-Method-Override': 'PUT', 'Accept': 'application/json' }, body });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Nao foi possivel salvar.');
            version.value = data.version;
            status.textContent = 'Salvo agora';
            status.className = 'autosave-status small text-success';
        } catch (error) {
            status.textContent = error.message;
            status.className = 'autosave-status small text-danger';
        }
    };
    form.addEventListener('input', () => { clearTimeout(timer); status.textContent = 'Alteracoes pendentes'; timer = setTimeout(save, 700); });
    form.addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(save, 100); });
});

document.querySelectorAll('[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
}));

document.querySelectorAll('[data-copy-text]').forEach((button) => button.addEventListener('click', async () => {
    const originalLabel = button.textContent;
    try {
        await navigator.clipboard.writeText(button.dataset.copyText);
        button.textContent = button.dataset.copyFeedback || 'Copiado';
        setTimeout(() => { button.textContent = originalLabel; }, 1800);
    } catch {
        window.prompt('Copie o código:', button.dataset.copyText);
    }
}));

document.querySelectorAll('[data-activity-builder]').forEach((form) => {
    const list = form.querySelector('[data-question-list]');
    const templates = new Map(
        [...document.querySelectorAll('[data-question-template]')]
            .map((template) => [template.dataset.questionTemplate, template]),
    );
    const emptyState = form.querySelector('[data-question-empty]');
    const addButtons = [...form.querySelectorAll('[data-add-question]')];
    const questionCount = form.querySelector('[data-question-count]');
    const questionCountLabel = form.querySelector('[data-question-count-label]');
    let nextIndex = Date.now();

    const bankUrl = (baseUrl = window.location.href) => {
        const url = new URL(baseUrl, window.location.origin);
        [...url.searchParams.keys()].filter((key) => key === 'bank_questions' || key.startsWith('bank_questions['))
            .forEach((key) => url.searchParams.delete(key));
        url.searchParams.set('bank_selection', '1');
        form.querySelectorAll('[name="bank_questions[]"]:checked').forEach((checkbox) => {
            url.searchParams.append('bank_questions[]', checkbox.value);
        });
        const search = form.querySelector('[data-bank-search]');
        if (search?.value.trim()) url.searchParams.set('bank_q', search.value.trim());
        else url.searchParams.delete('bank_q');
        return url.toString();
    };

    const loadBank = async (baseUrl, resetPage = false) => {
        const url = new URL(bankUrl(baseUrl), window.location.origin);
        if (resetPage) url.searchParams.delete('bank_page');
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) throw new Error('Não foi possível carregar o banco de questões.');
        const documentCopy = new DOMParser().parseFromString(await response.text(), 'text/html');
        const nextBody = documentCopy.querySelector('[data-bank-picker-body]');
        const currentBody = form.querySelector('[data-bank-picker-body]');
        if (!nextBody || !currentBody) throw new Error('A resposta do banco de questões é inválida.');
        const nextCount = documentCopy.querySelector('[data-bank-count]');
        const currentCount = form.querySelector('[data-bank-count]');
        if (nextCount && currentCount) currentCount.textContent = nextCount.textContent;
        currentBody.replaceWith(nextBody);
    };

    const refresh = () => {
        const cards = [...list.querySelectorAll('[data-question-card]')];
        cards.forEach((card, index) => {
            const number = card.querySelector('[data-question-number]');
            if (number) number.textContent = index + 1;
        });
        emptyState.hidden = cards.length > 0;
        if (questionCount) questionCount.textContent = cards.length;
        if (questionCountLabel) questionCountLabel.textContent = cards.length === 1 ? 'questão criada' : 'questões criadas';
    };

    const toggleKind = (card) => {
        const type = card.querySelector('[data-question-type]')?.value;
        const essay = card.querySelector('[data-essay-fields]');
        const choice = card.querySelector('[data-choice-fields]');
        const kindLabel = card.querySelector('[data-question-kind-label]');
        if (essay) essay.hidden = type !== 'essay';
        if (choice) choice.hidden = type !== 'single_choice';
        if (kindLabel) kindLabel.textContent = type === 'essay' ? 'Questão dissertativa' : 'Questão de alternativa';
    };

    const initializeCard = (card) => {
        toggleKind(card);
        card.querySelector('[data-question-type]')?.addEventListener('change', () => toggleKind(card));
        card.querySelector('[data-remove-question]')?.addEventListener('click', () => {
            card.remove();
            refresh();
        });
    };

    list.querySelectorAll('[data-question-card]').forEach(initializeCard);
    addButtons.forEach((button) => button.addEventListener('click', () => {
        const type = button.dataset.addQuestion;
        const template = templates.get(type);
        if (!template) return;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        const card = wrapper.firstElementChild;
        list.append(card);
        initializeCard(card);
        refresh();
        card.querySelector('[name$="[body]"]')?.focus({ preventScroll: true });
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }));

    form.querySelector('[data-confirm-publish]')?.addEventListener('click', (event) => {
        if (!window.confirm('Publicar agora? As perguntas serão congeladas e a atividade ficará disponível para a turma.')) {
            event.preventDefault();
        }
    });

    form.addEventListener('click', (event) => {
        const searchButton = event.target.closest('[data-bank-search-button]');
        const pageLink = event.target.closest('[data-bank-pagination] a');
        if (!searchButton && !pageLink) return;
        event.preventDefault();
        loadBank(pageLink?.href, Boolean(searchButton)).catch((error) => window.alert(error.message));
    });
    form.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.matches('[data-bank-search]')) {
            event.preventDefault();
            loadBank(undefined, true).catch((error) => window.alert(error.message));
        }
    });

    refresh();
});
