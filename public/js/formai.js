const token = document.querySelector('meta[name="csrf-token"]')?.content;
document.querySelector('a[href="#activity-settings"]')?.addEventListener('click', () => {
    const settings = document.querySelector('#activity-settings');
    if (settings) settings.open = true;
});
document.querySelectorAll('[data-auto-submit-switch]').forEach((form) => {
    const input = form.querySelector('input[role="switch"]');
    const button = form.querySelector('button[type="submit"]');
    if (!input) return;
    if (button) button.classList.add('d-none');
    input.addEventListener('change', () => form.requestSubmit());
});
let humanReviewDirty = false;
const humanReviewForm = document.querySelector('[data-review-form]');
const bulkRepublishForm = document.querySelector('[data-bulk-republish]');
const updateBulkRequired = (card) => {
    const selected = card.querySelector('[data-result-select]')?.checked ?? false;
    card.querySelectorAll('input[type="number"]').forEach((input) => { input.required = selected; });
};
bulkRepublishForm?.querySelectorAll('[data-result-submission]').forEach(updateBulkRequired);
const markHumanReviewDirty = (event) => {
    if (event.target.matches('[name^="grades["]')) humanReviewDirty = true;
};
humanReviewForm?.addEventListener('input', markHumanReviewDirty);
humanReviewForm?.addEventListener('change', markHumanReviewDirty);
humanReviewForm?.addEventListener('submit', () => { humanReviewDirty = false; });
bulkRepublishForm?.addEventListener('input', (event) => {
    if (!event.target.matches('[name^="grades["]')) return;
    const card = event.target.closest('[data-result-submission]');
    const checkbox = card?.querySelector('[data-result-select]');
    if (checkbox) checkbox.checked = true;
    if (card) updateBulkRequired(card);
    humanReviewDirty = true;
});
bulkRepublishForm?.addEventListener('change', (event) => {
    if (event.target.matches('[data-result-select]')) updateBulkRequired(event.target.closest('[data-result-submission]'));
    humanReviewDirty = true;
});
bulkRepublishForm?.addEventListener('submit', () => { humanReviewDirty = false; });
window.addEventListener('beforeunload', (event) => {
    if (!humanReviewDirty) return;
    event.preventDefault();
    event.returnValue = '';
});
document.querySelectorAll('[data-grading-nav], .grading-back-link').forEach((link) => link.addEventListener('click', (event) => {
    if (!humanReviewDirty) return;
    if (!window.confirm('Há alterações na correção que ainda não foram publicadas. Sair sem publicá-las?')) {
        event.preventDefault();
        return;
    }
    humanReviewDirty = false;
}));

document.querySelectorAll('[data-ai-submit]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const selected = [...document.querySelectorAll('[data-delivery-select]:checked')];
        if (form.hasAttribute('data-ai-batch') && selected.length === 0) { event.preventDefault(); return; }
        const count = selected.reduce((sum, box) => sum + Number(box.dataset.essayCount), 0);
        const confirmation = form.hasAttribute('data-ai-batch')
            ? `Solicitar correção para ${selected.length} entregas e ${count} questões dissertativas com as opções deste lote?`
            : (form.dataset.aiConfirm || 'Confirmar análise com as opções escolhidas? Um pedido diferente pode consumir créditos adicionais.');
        if (!window.confirm(confirmation)) {
            event.preventDefault();
            return;
        }
        const linkedButton = form.id ? document.querySelector(`button[form="${CSS.escape(form.id)}"]`) : null;
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
    let checking = false;

    const showFailure = (failureMessage) => {
        polling = false;
        progress?.classList.add('d-none');
        if (!errorPanel) return;
        errorPanel.classList.remove('d-none');
        const detail = errorPanel.querySelector('[data-ai-error-message]');
        if (detail) detail.textContent = failureMessage;
    };

    const checkStatus = async () => {
        if (!polling || document.hidden || checking) return;
        checking = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 12000);
        try {
            const response = await fetch(tracker.dataset.statusUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('Não foi possível consultar o andamento da correção.');
            const data = await response.json();
            const stateTitles = { queued: 'Na fila', waiting: 'Aguardando limite da API', processing: 'Analisando', fallback: 'Tentando provedor alternativo', retrying: 'Nova tentativa agendada' };
            const stateTitle = tracker.querySelector('[data-ai-title]');
            if (stateTitle && stateTitles[data.state]) stateTitle.textContent = stateTitles[data.state];
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
                if (humanReviewDirty) {
                    if (message) message.textContent = 'Sugestões concluídas. Publique sua revisão antes de atualizar a página; seus campos não foram substituídos.';
                } else {
                    setTimeout(() => { if (!humanReviewDirty) window.location.reload(); }, 900);
                }
            }
        } catch (error) {
            showFailure(`${error.name === 'AbortError' ? 'A consulta demorou demais.' : error.message} Atualize a página para tentar novamente; a correção manual continua disponível.`);
        } finally {
            clearTimeout(timeout);
            checking = false;
        }
    };

    checkStatus();
    const interval = window.setInterval(() => {
        if (!polling) return window.clearInterval(interval);
        checkStatus();
    }, 2500);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) checkStatus(); });
});

const autosaveFlushers = [];
const studentProgress = document.querySelector('[data-student-progress]');
const refreshStudentProgress = () => {
    if (!studentProgress) return;
    studentProgress.textContent = String([...document.querySelectorAll('[data-autosave]')].filter((form) => {
        const essay = form.querySelector('textarea[name="response_text"]');
        return essay ? essay.value.trim() !== '' : form.querySelector('input[type="radio"]:checked, input[type="checkbox"]:checked') !== null;
    }).length);
};
document.querySelectorAll('[data-autosave]').forEach((form) => {
    let timer, inFlight = null, dirty = false;
    const status = form.querySelector('[data-save-status]');
    const retryButton = form.querySelector('[data-save-retry]');
    const version = form.querySelector('[name="version"]');
    const save = async () => {
        if (inFlight) return inFlight;
        inFlight = (async () => {
            while (dirty) {
                dirty = false;
                const body = new FormData(form);
                status.textContent = 'Salvando...';
                status.className = 'autosave-status small text-secondary';
                retryButton?.classList.add('d-none');
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 15000);
                let response, data;
                try {
                    response = await fetch(form.action, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'X-HTTP-Method-Override': 'PUT', 'Accept': 'application/json' }, body, signal: controller.signal });
                    try { data = await response.json(); } catch (error) {
                        if (error.name === 'AbortError') throw error;
                        data = null;
                    }
                } catch (error) {
                    const failure = new Error(error.name === 'AbortError' ? 'O salvamento demorou demais. Confira sua conexão e tente novamente.' : 'Não foi possível conectar para salvar. Confira sua conexão e tente novamente.');
                    failure.retryable = true;
                    throw failure;
                } finally {
                    clearTimeout(timeout);
                }
                if (!response.ok) {
                    const failure = new Error(data?.message || ([401, 419].includes(response.status) ? 'Sua sessão expirou. Recarregue a página para entrar novamente.' : 'Não foi possível salvar. Tente novamente.'));
                    failure.retryable = response.status === 429 || response.status >= 500;
                    throw failure;
                }
                if (!Number.isInteger(data?.version)) throw new Error('Não foi possível confirmar o salvamento. Recarregue a página antes de enviar.');
                version.value = data.version;
                status.textContent = 'Salvo agora';
                status.className = 'autosave-status small text-success';
            }
        })();
        try {
            await inFlight;
        } catch (error) {
            dirty = true;
            status.textContent = error.message;
            status.className = 'autosave-status small text-danger';
            retryButton?.classList.toggle('d-none', !error.retryable);
            throw error;
        } finally {
            inFlight = null;
        }
    };
    const scheduleSave = delay => {
        dirty = true;
        clearTimeout(timer);
        status.textContent = 'Alterações pendentes';
        status.className = 'autosave-status small text-secondary';
        retryButton?.classList.add('d-none');
        timer = setTimeout(() => { save().catch(() => {}); }, delay);
    };
    form.addEventListener('input', () => { scheduleSave(700); refreshStudentProgress(); });
    form.addEventListener('change', () => { scheduleSave(100); refreshStudentProgress(); });
    retryButton?.addEventListener('click', () => { clearTimeout(timer); save().catch(() => {}); });
    autosaveFlushers.push(async () => { clearTimeout(timer); await save(); });
});
refreshStudentProgress();

document.querySelectorAll('[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
}));

document.querySelectorAll('.student-submit-form').forEach(form => form.addEventListener('submit', async event => {
    if (event.defaultPrevented) return;
    event.preventDefault();
    const button = form.querySelector('[type="submit"]');
    const status = form.querySelector('[data-submit-status]');
    const originalLabel = button.textContent;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.textContent = 'Salvando respostas...';
    if (status) status.textContent = 'Salvando respostas antes do envio.';
    try {
        await Promise.all(autosaveFlushers.map(flush => flush()));
        button.textContent = 'Enviando atividade...';
        if (status) status.textContent = 'Enviando atividade.';
        HTMLFormElement.prototype.submit.call(form);
    } catch {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.textContent = originalLabel;
        if (status) status.textContent = 'Não foi possível enviar. Revise o erro de salvamento e tente novamente.';
        document.querySelector('[data-save-status].text-danger')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
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
        form.querySelectorAll('[name^="bank_correction["]').forEach((select) => url.searchParams.set(select.name, select.value));
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
        if (questionCountLabel) questionCountLabel.textContent = cards.length === 1 ? 'item criado' : 'itens criados';
    };

    const toggleKind = (card) => {
        const type = card.querySelector('[data-question-type]')?.value;
        const essay = card.querySelector('[data-essay-fields]');
        const choice = card.querySelector('[data-choice-fields]');
        const score = card.querySelector('[data-score-field]');
        const scoreInput = score?.querySelector('input');
        const contextHint = card.querySelector('[data-context-hint]');
        const kindLabel = card.querySelector('[data-question-kind-label]');
        if (essay) essay.hidden = type !== 'essay';
        if (choice) choice.hidden = !['single_choice', 'multiple_choice'].includes(type);
        if (score) score.hidden = type === 'context';
        if (scoreInput) {
            scoreInput.disabled = type === 'context';
            scoreInput.required = type !== 'context';
            if (type === 'context') scoreInput.value = '0';
            else if (Number(scoreInput.value) <= 0) scoreInput.value = '1';
        }
        if (contextHint) contextHint.hidden = type !== 'context';
        card.querySelectorAll('[data-single-correct]').forEach((wrapper) => {
            wrapper.hidden = type !== 'single_choice';
            wrapper.querySelector('input').disabled = type !== 'single_choice';
        });
        card.querySelectorAll('[data-multiple-correct]').forEach((wrapper) => {
            wrapper.hidden = type !== 'multiple_choice';
            wrapper.querySelector('input').disabled = type !== 'multiple_choice';
        });
        const choiceHelp = card.querySelector('[data-choice-help]');
        if (choiceHelp) choiceHelp.textContent = type === 'multiple_choice'
            ? 'Marque pelo menos duas corretas. O aluno recebe os pontos somente ao selecionar exatamente todas as corretas.'
            : 'Preencha ao menos duas alternativas e marque uma correta.';
        if (kindLabel) kindLabel.textContent = ({ essay: 'Questão dissertativa', single_choice: 'Escolha única', multiple_choice: 'Múltipla escolha', context: 'Bloco de contexto' })[type] || 'Questão';
    };

    const updateRubricSummary = (card) => {
        const maximum = Number(card.querySelector('[name$="[max_score]"]')?.value || 0);
        const points = [...card.querySelectorAll('[data-rubric-points]')];
        const total = points.reduce((sum, input) => sum + Number(input.value || 0), 0);
        const remaining = maximum - total;
        points.forEach((input) => { input.max = String(maximum || 1000); });
        const summary = card.querySelector('[data-rubric-summary]');
        if (!summary) return;
        if (points.every((input) => input.value === '')) {
            summary.textContent = 'Sem critérios: a IA fará uma avaliação geral da resposta.';
            summary.className = 'form-text';
            return;
        }
        summary.textContent = Math.abs(remaining) < 0.001
            ? `Total: ${total.toFixed(2)} pontos — rubrica completa.`
            : `Total: ${total.toFixed(2)} pontos · ${Math.abs(remaining).toFixed(2)} ${remaining > 0 ? 'restantes' : 'acima do limite'}.`;
        summary.className = `form-text ${Math.abs(remaining) < 0.001 ? 'text-success' : 'text-danger'}`;
    };

    const initializeCard = (card) => {
        toggleKind(card);
        const criteriaToggle = card.querySelector('[data-criteria-toggle]');
        const criteriaPanel = card.querySelector('[data-criteria-panel]');
        const toggleCriteria = () => {
            if (!criteriaPanel || !criteriaToggle) return;
            criteriaPanel.hidden = !criteriaToggle.checked;
            criteriaPanel.disabled = !criteriaToggle.checked;
        };
        criteriaToggle?.addEventListener('change', toggleCriteria);
        toggleCriteria();
        updateRubricSummary(card);
        card.querySelector('[data-question-type]')?.addEventListener('change', () => toggleKind(card));
        card.querySelector('[name$="[max_score]"]')?.addEventListener('input', () => updateRubricSummary(card));
        card.querySelectorAll('[data-rubric-points]').forEach((input) => input.addEventListener('input', () => updateRubricSummary(card)));
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
document.querySelectorAll('[data-bank-editor]').forEach((form) => {
    const type = form.querySelector('[name="type"]');
    const content = form.querySelector('[data-bank-content]');
    const update = () => {
        content.hidden = !type.value;
        content.disabled = !type.value;
        form.querySelectorAll('[data-bank-kind]').forEach((panel) => {
            panel.hidden = panel.dataset.bankKind !== type.value;
            panel.disabled = panel.hidden;
        });
        form.querySelector('[name="expected_answer"]').required = type.value === 'essay';
    };
    type.addEventListener('change', update);
    form.querySelectorAll('[name$="[is_correct]"]').forEach((input) => input.addEventListener('change', () => {
        if (input.checked) form.querySelectorAll('[name$="[is_correct]"]').forEach((other) => { if (other !== input) other.checked = false; });
    }));
    update();
});

// Selection stays scoped to visible cards; changing filters clears it.
document.querySelectorAll('[data-deliveries]').forEach(root => {
    const cards = [...root.querySelectorAll('[data-delivery]')];
    const boxes = [...root.querySelectorAll('[data-delivery-select]')];
    const search = root.querySelector('[data-delivery-search]');
    const filter = root.querySelector('[data-delivery-filter]');
    const all = root.querySelector('[data-select-visible]');
    const button = root.querySelector('[data-batch-submit]');
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR');
    const update = () => {
        const visible = boxes.filter(box => !box.disabled && !box.closest('[data-delivery]').hidden);
        const selected = visible.filter(box => box.checked);
        root.querySelector('[data-selection-count]').textContent = `${selected.length} entregas · ${selected.reduce((n, box) => n + Number(box.dataset.essayCount), 0)} questões dissertativas`;
        all.checked = visible.length > 0 && selected.length === visible.length;
        all.indeterminate = selected.length > 0 && selected.length < visible.length;
        all.disabled = visible.length === 0;
        button.disabled = selected.length === 0 || button.dataset.aiEnabled !== '1';
    };
    const apply = () => {
        boxes.forEach(box => { box.checked = false; });
        cards.forEach(card => {
            const statusMatches = filter.value === 'all' || (filter.value === 'failed' ? card.dataset.hasFailure === '1' : card.dataset.deliveryState === filter.value);
            card.hidden = !statusMatches || !normalize(card.dataset.studentName).includes(normalize(search.value.trim()));
        });
        root.querySelector('[data-no-deliveries]').hidden = cards.length === 0 || cards.some(card => !card.hidden);
        update();
    };
    search.addEventListener('input', apply);
    filter.addEventListener('change', apply);
    boxes.forEach(box => box.addEventListener('change', update));
    all.addEventListener('change', () => { boxes.forEach(box => { box.checked = !box.disabled && !box.closest('[data-delivery]').hidden && all.checked; }); update(); });
    root.querySelector('[data-clear-selection]').addEventListener('click', () => { boxes.forEach(box => { box.checked = false; }); update(); });
    update();
});

document.querySelectorAll('[data-password-toggle]').forEach(button => button.addEventListener('click', () => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input) return;
    const showing = input.type === 'password';
    input.type = showing ? 'text' : 'password';
    button.textContent = showing ? 'Ocultar' : 'Mostrar';
    button.setAttribute('aria-label', `${showing ? 'Ocultar' : 'Mostrar'} ${input.labels?.[0]?.textContent?.trim().toLowerCase() || 'senha'}`);
    button.setAttribute('aria-pressed', String(showing));
    button.focus();
}));

document.querySelectorAll('[data-age-band]').forEach(select => {
    const field = select.form?.querySelector('[data-guardian-field]');
    const input = field?.querySelector('input');
    if (!field || !input) return;
    const update = () => {
        const needed = select.value === 'under_13';
        field.hidden = !needed;
        input.required = needed;
        if (!needed) input.value = '';
    };
    select.addEventListener('change', update);
    update();
});

// Guided onboarding. Definitions and destinations are supplied only by the backend.
(() => {
    const root = document.querySelector('[data-user-tutorial]');
    if (!root) return;
    const config = JSON.parse(root.dataset.config);
    const panel = root.querySelector('.tour-panel');
    const title = root.querySelector('#tour-title');
    const description = root.querySelector('#tour-description');
    const progress = root.querySelector('.tour-progress');
    const error = root.querySelector('.tour-error');
    const actions = root.querySelector('.tour-actions');
    const ring = root.querySelector('.tour-ring');
    const shades = [...root.querySelectorAll('[data-tour-shade]')];
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    const arrow = root.querySelector('.tour-arrow');
    const instruction = root.querySelector('.tour-instruction');
    const loading = root.querySelector('.tour-loading');
    const meter = root.querySelector('.tour-meter');
    const animations = new Set();
    let closing = false;
    const animate = (el, frames, duration = 240) => {
        if (motion.matches || !el.animate) return Promise.resolve();
        const animation = el.animate(frames, { duration, easing: 'cubic-bezier(.2,.7,.3,1)' });
        animations.add(animation);
        return animation.finished.catch(() => {}).finally(() => animations.delete(animation));
    };
    const stopEffects = () => {
        animations.forEach(animation => animation.cancel()); animations.clear();
        root.classList.remove('tour-entering', 'tour-confirmed', 'tour-celebrating', 'tour-interactive');
        target?.classList.remove('tour-target-active', 'tour-target-interactive');
        arrow.hidden = true;
    };
    const showLoading = text => {
        root.classList.add('tour-saving'); root.classList.remove('tour-interactive');
        arrow.hidden = true; loading.textContent = text; loading.hidden = false;
    };
    let step = 0, target = null, interactive = false;
    let session = null, observer = null, resizeObserver = null, frame = null, opener = null, busy = false;
    let inertElements = [], openedMenu = false, previousOverflow = '', mode = 'step';
    const visible = el => el && el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden';
    const current = () => config.steps[step];
    const onPage = () => location.pathname === new URL(current().url, location.href).pathname;
    const restoreInert = () => { inertElements.forEach(el => { el.inert = false; }); inertElements = []; };
    const isolate = () => {
        restoreInert();
        const keep = [root, ...(interactive && target ? [target] : [])];
        const visit = parent => [...parent.children].forEach(el => {
            if (keep.includes(el) || ['SCRIPT', 'STYLE', 'LINK'].includes(el.tagName)) return;
            if (keep.some(item => el.contains(item))) visit(el);
            else if (!el.inert) { el.inert = true; inertElements.push(el); }
        });
        visit(document.body);
    };
    const box = (el, x, y, w, h) => Object.assign(el.style, { left: `${x}px`, top: `${y}px`, width: `${Math.max(0, w)}px`, height: `${Math.max(0, h)}px` });
    const position = () => {
        if (root.hidden) return;
        const w = document.documentElement.clientWidth, h = innerHeight;
        const rect = visible(target) ? target.getBoundingClientRect() : null;
        const left = rect ? Math.max(0, Math.min(w, rect.left - 5)) : 0;
        const top = rect ? Math.max(0, Math.min(h, rect.top - 5)) : 0;
        const right = rect ? Math.max(left, Math.min(w, rect.right + 5)) : 0;
        const bottom = rect ? Math.max(top, Math.min(h, rect.bottom + 5)) : 0;
        box(shades[0], 0, 0, w, top);
        box(shades[1], 0, top, left, bottom - top);
        box(shades[2], right, top, w - right, bottom - top);
        box(shades[3], 0, bottom, w, h - bottom);
        ring.hidden = !rect;
        box(ring, left, top, right - left, bottom - top);
        panel.style.maxHeight = '';
        // Reserve a separate region for clickable targets, including short landscape screens.
        if (interactive && rect) {
            const below = h - bottom - 24, above = top - 24;
            panel.style.maxHeight = `${Math.max(80, Math.max(below, above))}px`;
        }
        const ph = panel.offsetHeight, pw = panel.offsetWidth;
        let x = Math.max(8, (w - pw) / 2), y = Math.max(8, (h - ph) / 2);
        if (rect) {
            x = Math.max(8, Math.min(w - pw - 8, left));
            y = bottom + 12;
            if (y + ph > h - 8) y = top - ph - 12;
            if (y < 8) { x = right + 12; y = Math.max(8, Math.min(h - ph - 8, top)); }
            if (x + pw > w - 8) { x = Math.max(8, w - pw - 8); y = h - ph - 8; }
        }
        if (w < 600 && rect) {
            x = 8;
            y = interactive && top > h - bottom ? 8 : h - ph - 8;
        }
        panel.style.left = `${x}px`;
        panel.style.top = `${Math.max(8, y)}px`;
        arrow.hidden = !interactive || busy || closing || motion.matches;
        if (!arrow.hidden) {
            const centerX = left + (right - left) / 2;
            const centerY = top + (bottom - top) / 2;
            const candidates = [
                { x: centerX - 16, y: bottom + 6, glyph: '↑' },
                { x: centerX - 16, y: top - 38, glyph: '↓' },
                { x: right + 6, y: centerY - 16, glyph: '←' },
                { x: left - 38, y: centerY - 16, glyph: '→' },
            ];
            const choice = candidates.find(candidate => {
                const inViewport = candidate.x >= 8 && candidate.y >= 8 && candidate.x + 32 <= w - 8 && candidate.y + 32 <= h - 8;
                const overlapsPanel = candidate.x + 32 > x && candidate.x < x + pw && candidate.y + 32 > y && candidate.y < y + ph;
                return inViewport && !overlapsPanel;
            });
            arrow.hidden = !choice;
            if (choice) {
                arrow.textContent = choice.glyph;
                arrow.style.left = `${choice.x}px`;
                arrow.style.top = `${choice.y}px`;
            }
        }
    };
    const schedule = () => { if (frame === null) frame = requestAnimationFrame(() => { frame = null; if (!root.hidden && target && !visible(target)) render(mode); else position(); }); };
    const activeKey = `formai:${config.role}-tour-active:${config.owner}`;
    const setActive = active => { try { if (active) sessionStorage.setItem(activeKey, '1'); else sessionStorage.removeItem(activeKey); } catch {} };
    const isActive = () => { try { return sessionStorage.getItem(activeKey) === '1'; } catch { return false; } };
    const handoff = next => {
        const url = new URL(config.steps[next].url, location.href);
        url.searchParams.set('tour_step', String(next));
        return url.href;
    };
    const markSeen = async () => {
        try {
            const response = await fetch(config.endpoint, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            if (!response.ok) throw new Error('seen');
        } catch {
            error.textContent = 'Não foi possível registrar a primeira visita. O tutorial poderá reaparecer em um próximo acesso.';
            error.hidden = false;
        }
    };
    const close = async () => {
        if (closing || busy) return;
        closing = true;
        setActive(false);
        stopEffects();
        root.classList.add('tour-closing');
        await Promise.all([animate(panel, [{ opacity: 1, transform: 'translateY(0) scale(1)' }, { opacity: 0, transform: 'translateY(8px) scale(.98)' }], 160), ...shades.map(el => animate(el, [{ opacity: 1 }, { opacity: 0 }], 160))]);
        session?.abort(); session = null;
        observer?.disconnect(); observer = null;
        resizeObserver?.disconnect(); resizeObserver = null;
        window.scrollTo({ top: scrollY, behavior: 'instant' });
        if (frame !== null) cancelAnimationFrame(frame);
        frame = null;
        restoreInert();
        document.body.style.overflow = previousOverflow;
        if (openedMenu) { document.getElementById('nav')?.classList.remove('show'); document.querySelector('.navbar-toggler')?.setAttribute('aria-expanded', 'false'); }
        openedMenu = false;
        root.hidden = true;
        root.getAnimations?.({ subtree: true }).forEach(animation => animation.cancel());
        root.classList.remove('tour-closing', 'tour-saving');
        closing = false;
        stopEffects();
        if (visible(opener)) opener.focus();
        else (document.querySelector('[data-tour-restart]') || document.querySelector('.nav-user'))?.focus();
    };
    const pause = () => close();
    const move = next => {
        if (busy) return;
        stopEffects(); step = next;
        if (!onPage()) {
            busy = true; showLoading('Abrindo o próximo passo…');
            location.assign(handoff(next));
        } else {
            render('step');
            root.classList.add('tour-confirmed');
        }
    };
    const button = (text, action, primary = false) => {
        const el = document.createElement('button');
        el.type = 'button'; el.className = primary ? 'btn btn-primary' : 'btn btn-outline-secondary';
        el.textContent = text; el.addEventListener('click', action); actions.append(el);
    };
    const render = nextMode => {
        window.scrollTo({ top: scrollY, behavior: 'instant' });
        const oldShades = shades.map(el => el.getBoundingClientRect());
        const oldRing = !ring.hidden ? ring.getBoundingClientRect() : null;
        stopEffects();
        mode = nextMode; restoreInert();
        target = null; interactive = false; actions.replaceChildren(); error.hidden = true;
        document.body.style.overflow = previousOverflow;
        if (mode === 'welcome') {
            title.textContent = 'Bem-vindo ao FormAI!'; description.textContent = config.welcome; progress.textContent = 'Primeiros passos';
            button('Começar tutorial', () => move(0), true); button('Agora não', pause);
        } else {
            const item = current();
            title.textContent = item.title; description.textContent = item.text; progress.textContent = `Passo ${step + 1} de ${config.steps.length}`;
            if (onPage() && item.target) {
                target = document.querySelector(`[data-tour="${item.target}"]`);
                const hiddenDetails = target?.closest('details:not([open])');
                if (hiddenDetails) hiddenDetails.open = true;
                const nav = target?.closest('#nav');
                if (nav && !visible(target)) { nav.classList.add('show'); openedMenu = true; document.querySelector('.navbar-toggler')?.setAttribute('aria-expanded', 'true'); }
                if (!visible(target)) {
                    console.info('[FormAI tutorial] Alvo indisponível:', item.target);
                    target = null;
                    description.textContent += ' Este recurso não está visível nesta página. Você pode continuar para o próximo tópico sem alterar dados.';
                }
            }
            interactive = Boolean(item.interaction && target);
            if (step > 0) button('Voltar', () => move(step - 1));
            if (!onPage()) {
                description.textContent = 'Abra a página deste tópico para continuar.';
                button('Ir para este tópico', () => location.assign(handoff(step)), true);
            } else if (step === config.steps.length - 1) {
                button('Concluir tutorial', close, true);
            } else if (!interactive) button('Próximo', () => move(step + 1), true);
            button('Pausar', pause);
            button('Pular tutorial', close);
        }
        root.classList.toggle('tour-interactive', interactive && !motion.matches);
        root.classList.toggle('tour-celebrating', mode === 'step' && step === config.steps.length - 1);
        target?.classList.add('tour-target-active');
        if (interactive && !motion.matches) target?.classList.add('tour-target-interactive');
        instruction.hidden = !interactive;
        instruction.textContent = interactive ? current().instruction : '';
        const percent = mode === 'step' ? Math.round((step + 1) / config.steps.length * 100) : 0;
        meter.setAttribute('aria-valuenow', String(percent));
        meter.firstElementChild.style.transform = `scaleX(${percent / 100})`;
        panel.setAttribute('aria-modal', target ? 'false' : 'true');
        if (!target) document.body.style.overflow = 'hidden';
        isolate(); position();
        resizeObserver?.disconnect();
        resizeObserver?.observe(panel);
        if (target) resizeObserver?.observe(target);
        panel.focus({ preventScroll: true });
        if (target && !target.closest('.site-header')) {
            const r = target.getBoundingClientRect();
            const headerHeight = document.querySelector('.site-header')?.getBoundingClientRect().height || 0;
            const availableBottom = parseFloat(panel.style.top);
            if (r.top < headerHeight + 12 || r.bottom > availableBottom - 12) {
                window.scrollTo({ top: Math.max(0, scrollY + r.top - headerHeight - 20), behavior: motion.matches ? 'instant' : 'smooth' });
            }
        }
        if (oldRing && target) shades.forEach((el, index) => {
            const old = oldShades[index], next = el.getBoundingClientRect();
            if (old.width && old.height && next.width && next.height) animate(el, [
                { transformOrigin: 'top left', transform: `translate(${old.x - next.x}px, ${old.y - next.y}px) scale(${old.width / next.width}, ${old.height / next.height})` },
                { transformOrigin: 'top left', transform: 'none' },
            ]);
        });
        if (oldRing && target && oldRing.width && oldRing.height) {
            const next = ring.getBoundingClientRect();
            if (next.width && next.height) animate(ring, [
                { transformOrigin: 'top left', transform: `translate(${oldRing.x - next.x}px, ${oldRing.y - next.y}px) scale(${oldRing.width / next.width}, ${oldRing.height / next.height})` },
                { transformOrigin: 'top left', transform: 'none' },
            ]);
        }
        root.classList.add('tour-entering');
        animate(panel, [{ opacity: 0, transform: 'translateY(8px) scale(.98)' }, { opacity: 1, transform: 'none' }]);
    };
    const open = nextMode => {
        if (session) return;
        setActive(true);
        opener = document.activeElement; previousOverflow = document.body.style.overflow;
        root.hidden = false; session = new AbortController();
        const options = { signal: session.signal };
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); pause(); }
            if (event.key !== 'Tab') return;
            const items = [...panel.querySelectorAll('button:not(:disabled)'), ...(interactive && visible(target) ? [target] : [])];
            if (!items.length) { event.preventDefault(); return; }
            const index = items.indexOf(document.activeElement);
            event.preventDefault();
            const nextIndex = index < 0 ? (event.shiftKey ? items.length - 1 : 0) : (index + (event.shiftKey ? -1 : 1) + items.length) % items.length;
            items[nextIndex].focus({ preventScroll: true });
        }, options);
        document.addEventListener('click', event => {
            if (closing || !interactive || !target?.contains(event.target)) return;
            if (busy) return;
            const next = step + 1;
            // Keep the real link activation, including keyboard activation, intact.
            const link = event.target.closest('a[href]');
            if (link && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey && target.contains(link) && new URL(link.href).pathname === new URL(config.steps[next].url).pathname) {
                link.href = handoff(next);
                busy = true; stopEffects(); showLoading('Abrindo o próximo passo…');
            } else if (!link) move(next);
        }, { ...options, capture: true });
        window.addEventListener('resize', schedule, options);
        window.addEventListener('orientationchange', schedule, options);
        document.addEventListener('scroll', schedule, { ...options, capture: true, passive: true });
        document.addEventListener('shown.bs.collapse', schedule, options);
        document.addEventListener('hidden.bs.collapse', schedule, options);
        resizeObserver = new ResizeObserver(schedule);
        observer = new MutationObserver(records => {
            if (records.every(record => root.contains(record.target))) return;
            if (target && !visible(target)) render(mode);
            else schedule();
        });
        observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'hidden'] });
        motion.addEventListener('change', () => {
            stopEffects();
            window.scrollTo({ top: scrollY, behavior: 'instant' });
            root.classList.toggle('tour-interactive', interactive && !motion.matches);
            target?.classList.add('tour-target-active');
            if (interactive && !motion.matches) target?.classList.add('tour-target-interactive');
            position();
        }, options);
        render(nextMode);
        shades.forEach(el => animate(el, [{ opacity: 0 }, { opacity: 1 }], 300));
    };
    const params = new URL(location.href).searchParams;
    const handoffStep = params.get('tour_step');
    const validStep = handoffStep !== null && /^(0|[1-9]\d*)$/.test(handoffStep)
        && Number(handoffStep) < config.steps.length
        && new URL(config.steps[Number(handoffStep)].url).pathname === location.pathname;
    const restart = params.get('tour') === 'restart' && location.pathname === new URL(config.steps[0].url).pathname;
    if (params.has('tour_step') || params.has('tour')) {
        params.delete('tour_step'); params.delete('tour');
        history.replaceState(history.state, '', location.pathname + (params.size ? `?${params}` : '') + location.hash);
    }
    if (validStep) { step = Number(handoffStep); open('step'); }
    else if (restart || config.autoOpen || isActive()) {
        open('welcome');
        if (config.autoOpen) markSeen();
    }
})();

document.querySelectorAll('form[action$="/logout"]').forEach(form => form.addEventListener('submit', () => {
    try {
        Object.keys(sessionStorage).filter(key => /^formai:(teacher|student)-tour-active:/.test(key)).forEach(key => sessionStorage.removeItem(key));
        sessionStorage.removeItem('formai:dashboard-summary');
    } catch {}
}));

// Only the three aggregate counters are retained in this tab; private activity data stays server-side.
(() => {
    const root = document.querySelector('[data-dashboard-summary]');
    const button = document.querySelector('[data-summary-refresh]');
    const status = document.querySelector('[data-summary-status]');
    if (!root || !button || !status) return;
    const filters = JSON.parse(root.dataset.filters);
    const cacheKey = `${root.dataset.user}:${JSON.stringify(filters)}`;
    const keys = ['activities', 'awaiting_review', 'published'];
    const current = Object.fromEntries(keys.map(key => [key, Number(root.querySelector(`[data-summary-key="${key}"]`).textContent)]));
    const write = value => { try { sessionStorage.setItem('formai:dashboard-summary', JSON.stringify(value)); } catch {} };
    const read = () => { try { return JSON.parse(sessionStorage.getItem('formai:dashboard-summary')); } catch { return null; } };
    write({ key: cacheKey, at: Date.now(), summary: current });
    let pending = false;
    button.addEventListener('click', async () => {
        if (pending) return;
        const cached = read();
        if (cached?.key === cacheKey && Date.now() - cached.at < 30000) {
            status.textContent = 'Indicadores atualizados (cache recente).';
            return;
        }
        pending = true; button.disabled = true; status.textContent = 'Atualizando indicadores…';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 12000);
        try {
            const url = new URL(root.dataset.summaryUrl, location.href);
            Object.entries(filters).forEach(([key, value]) => { if (value !== null && key !== 'page') url.searchParams.set(key, value); });
            url.searchParams.set('summary', '1');
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
            if (!response.ok) throw new Error('fetch');
            const summary = (await response.json()).summary;
            if (!keys.every(key => Number.isSafeInteger(summary?.[key]) && summary[key] >= 0)) throw new Error('data');
            keys.forEach(key => { root.querySelector(`[data-summary-key="${key}"]`).textContent = summary[key]; });
            write({ key: cacheKey, at: Date.now(), summary: Object.fromEntries(keys.map(key => [key, summary[key]])) });
            status.textContent = 'Indicadores atualizados agora.';
        } catch {
            status.textContent = 'Não foi possível atualizar. Os indicadores anteriores continuam visíveis.';
        } finally { clearTimeout(timeout); pending = false; button.disabled = false; }
    });
})();
