document.addEventListener('DOMContentLoaded', function () {
    const panel = document.getElementById('ai-chat-panel');
    if (!panel) {
        return;
    }

    const form = panel.closest('form');
    if (!form) {
        return;
    }

    const csrfInput = form.querySelector('input[name="csrf_token"]');
    const csrfToken = csrfInput ? csrfInput.value : '';

    const modelSelect = document.getElementById('ai-model-select');
    const messageInput = document.getElementById('ai-message-input');
    const sendBtn = document.getElementById('ai-send-btn');
    const stopBtn = document.getElementById('ai-stop-btn');
    const newChatBtn = document.getElementById('ai-new-chat-btn');
    const messagesEl = document.getElementById('ai-chat-messages');
    const statusEl = document.getElementById('ai-chat-status');
    const usageSummaryEl = document.getElementById('ai-usage-summary');
    const screenshotInput = document.getElementById('ai-screenshot-input');
    const screenshotClearBtn = document.getElementById('ai-screenshot-clear-btn');

    const fieldTypeInput = document.getElementById('field_type');
    const drawingDataInput = document.getElementById('drawing_data');
    const exerciseIdInput = document.getElementById('ai-exercise-id');

    const exerciseCard = document.getElementById('exercise-card');
    const backToSearchBtn = document.getElementById('ai-back-to-search');
    const MIN_EDITOR_CANVAS_SIZE = 120;
    const EDITOR_LAYOUT_RETRY_MS = 80;
    const EDITOR_LAYOUT_MAX_RETRIES = 6;

    function hasUsableEditorCanvasSize() {
        var canvasContainer = document.getElementById('container');
        if (!canvasContainer) {
            return false;
        }

        return canvasContainer.offsetWidth >= MIN_EDITOR_CANVAS_SIZE && canvasContainer.offsetHeight >= MIN_EDITOR_CANVAS_SIZE;
    }

    function refreshEditorLayoutWithRetries(remainingRetries) {
        if (!window.exerciseEditorApi || typeof window.exerciseEditorApi.refreshLayout !== 'function') {
            return;
        }

        window.exerciseEditorApi.refreshLayout();

        if (remainingRetries <= 0 || hasUsableEditorCanvasSize()) {
            return;
        }

        window.setTimeout(function () {
            refreshEditorLayoutWithRetries(remainingRetries - 1);
        }, EDITOR_LAYOUT_RETRY_MS);
    }

    function switchPhase(phase) {
        if (exerciseCard) {
            exerciseCard.dataset.aiPhase = phase;
        }
        var designSection = document.querySelector('.ai-design-section');
        if (designSection) {
            designSection.hidden = (phase === 'search');
        }

        setScreenshotUploadEnabled(phase === 'design');

        if (phase === 'design') {
            window.setTimeout(function () {
                refreshEditorLayoutWithRetries(EDITOR_LAYOUT_MAX_RETRIES);
            }, 0);
        }
    }

    let currentSessionId = null;
    let activeController = null;
    let loadingTimeoutId = null;
    let controlsEnabled = false;
    let isLoading = false;
    let sessionCostEur = 0;
    let screenshotUploadEnabled = false;
    let selectedScreenshotFiles = [];
    const MAX_SCREENSHOT_FILES = 4;
    const ALLOWED_SCREENSHOT_TYPES = new Set(['image/png', 'image/jpeg', 'image/webp']);

    function setStatus(text, isError = false) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = text;
        statusEl.style.color = isError ? '#c62828' : 'var(--text-muted)';
    }

    function updateControls() {
        const canInteract = controlsEnabled && !isLoading;
        modelSelect.disabled = !canInteract;
        messageInput.disabled = !canInteract;
        newChatBtn.disabled = !canInteract;
        sendBtn.disabled = !canInteract;
        if (screenshotInput) {
            screenshotInput.disabled = !canInteract || !screenshotUploadEnabled;
        }
        if (screenshotClearBtn) {
            screenshotClearBtn.disabled = !canInteract || getSelectedScreenshotCount() === 0;
        }
    }

    function appendMessage(role, text) {
        if (!messagesEl) {
            return;
        }
        const item = document.createElement('div');
        item.className = 'ai-chat-message ai-chat-message-' + role;
        const bubble = document.createElement('div');
        bubble.className = 'ai-chat-bubble';
        bubble.textContent = text;
        item.appendChild(bubble);
        messagesEl.appendChild(item);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendWarning(warnings) {
        if (!Array.isArray(warnings) || warnings.length === 0) {
            return;
        }
        appendMessage('system', warnings.join(' '));
    }

    function appendSources(sources) {
        if (!Array.isArray(sources) || sources.length === 0) {
            return;
        }

        const lines = ['Bronnen:'];
        sources.forEach(function (source, index) {
            const title = source && source.title ? String(source.title) : 'Onbekende titel';
            const url = source && source.url ? String(source.url) : '';
            lines.push(String(index + 1) + '. ' + title + (url ? ' - ' + url : ''));
        });

        appendMessage('system', lines.join('\n'));
    }

    function getSelectedScreenshotFiles() {
        return selectedScreenshotFiles.slice();
    }

    function getSelectedScreenshotCount() {
        return getSelectedScreenshotFiles().length;
    }

    function updateScreenshotSummary() {
        var files = getSelectedScreenshotFiles();
        var shouldShowClear = screenshotUploadEnabled && files.length > 0;

        if (screenshotClearBtn) {
            screenshotClearBtn.style.display = shouldShowClear ? 'inline-flex' : 'none';
            screenshotClearBtn.disabled = !controlsEnabled || isLoading || files.length === 0;
            if (files.length > 0) {
                var label = files.length + ' screenshot' + (files.length === 1 ? '' : 's') + ' wissen';
                screenshotClearBtn.title = label;
                screenshotClearBtn.setAttribute('aria-label', label);
            } else {
                screenshotClearBtn.title = 'Wis screenshots';
                screenshotClearBtn.setAttribute('aria-label', 'Wis screenshots');
            }
        }
    }

    function clearSelectedScreenshots() {
        selectedScreenshotFiles = [];
        if (screenshotInput) {
            screenshotInput.value = '';
        }
        updateScreenshotSummary();
        updateControls();
    }

    function setScreenshotUploadEnabled(enabled) {
        screenshotUploadEnabled = !!enabled;
        if (!screenshotUploadEnabled) {
            clearSelectedScreenshots();
        }
        updateScreenshotSummary();
        updateControls();
    }

    function toNamedScreenshotFile(file, fallbackIndex) {
        if (!(file instanceof File)) {
            return null;
        }

        var type = String(file.type || '').toLowerCase();
        if (!ALLOWED_SCREENSHOT_TYPES.has(type)) {
            return null;
        }

        var originalName = String(file.name || '').trim();
        if (originalName !== '') {
            return file;
        }

        var extension = type === 'image/png' ? 'png' : (type === 'image/webp' ? 'webp' : 'jpg');
        return new File([file], 'screenshot-' + fallbackIndex + '.' + extension, {
            type: type,
            lastModified: Date.now(),
        });
    }

    function addSelectedScreenshots(files) {
        var added = 0;
        var rejected = 0;
        var overflow = 0;

        (files || []).forEach(function (rawFile) {
            var file = toNamedScreenshotFile(rawFile, selectedScreenshotFiles.length + 1);
            if (!(file instanceof File)) {
                rejected++;
                return;
            }

            var alreadyAdded = selectedScreenshotFiles.some(function (existing) {
                return existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified;
            });
            if (alreadyAdded) {
                return;
            }

            if (selectedScreenshotFiles.length >= MAX_SCREENSHOT_FILES) {
                overflow++;
                return;
            }

            selectedScreenshotFiles.push(file);
            added++;
        });

        updateScreenshotSummary();
        updateControls();

        return {
            added: added,
            rejected: rejected,
            overflow: overflow,
        };
    }

    function extractPastedImageFiles(event) {
        var clipboard = event && event.clipboardData ? event.clipboardData : null;
        if (!clipboard || !clipboard.items) {
            return [];
        }

        var files = [];
        Array.from(clipboard.items).forEach(function (item) {
            if (!item || item.kind !== 'file') {
                return;
            }
            var file = item.getAsFile();
            if (!(file instanceof File)) {
                return;
            }
            files.push(file);
        });
        return files;
    }

    function focusScreenshotUpload() {
        if (!screenshotInput) {
            return;
        }
        if (!screenshotUploadEnabled) {
            setStatus('Screenshots zijn pas nodig als de chat daar om vraagt.');
            return;
        }
        screenshotInput.focus();
        screenshotInput.click();
    }

    function hasDrawingRefinementIntent(text) {
        var normalized = String(text || '').toLowerCase();
        if (normalized === '') {
            return false;
        }

        return /(tekening|konva|opstelling|pijl|pijlen|looplijn|looplijnen|positie|posities|veld|vak|kegel|kegels|pion|pionnen|doel|doeltjes)/.test(normalized);
    }

    function resolveMessageMode(text, screenshotCount) {
        var phase = exerciseCard && exerciseCard.dataset ? String(exerciseCard.dataset.aiPhase || 'search') : 'search';

        if (phase === 'design') {
            if (screenshotCount > 0) {
                return 'refine_drawing';
            }
            return hasDrawingRefinementIntent(text) ? 'refine_drawing' : 'refine_text';
        }

        return screenshotCount > 0 ? 'screenshot_recovery' : 'search';
    }

    function renderTranslatabilityStars(value) {
        var rating = Math.round(Number(value));
        if (!Number.isFinite(rating) || rating <= 0) {
            return '';
        }

        rating = Math.max(1, Math.min(5, rating));
        return '★★★★★'.slice(0, rating) + '☆☆☆☆☆'.slice(0, 5 - rating);
    }

    function appendSourceReview(review) {
        if (!messagesEl || !review || typeof review !== 'object') {
            return;
        }

        var container = document.createElement('div');
        container.className = 'ai-source-review';

        var header = document.createElement('div');
        header.className = 'ai-source-review-header';
        header.textContent = 'Vertaalbaarheid';
        container.appendChild(header);

        var stars = renderTranslatabilityStars(review.translatability_rating);
        if (stars !== '') {
            var starsEl = document.createElement('div');
            starsEl.className = 'ai-source-review-meta';
            var ratingLabel = review.translatability_label ? String(review.translatability_label) : '';
            starsEl.textContent = 'AI-inschatting: ' + stars + (ratingLabel !== '' ? ' (' + ratingLabel + ')' : '');
            container.appendChild(starsEl);
        }

        if (review.summary) {
            var summary = document.createElement('div');
            summary.className = 'ai-source-review-summary';
            summary.textContent = String(review.summary);
            container.appendChild(summary);
        }

        messagesEl.appendChild(container);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function setLoading(loading) {
        isLoading = loading;
        stopBtn.style.display = loading ? 'inline-flex' : 'none';
        if (loading) {
            setStatus('Ik ben bezig...');
            loadingTimeoutId = window.setTimeout(function () {
                appendMessage('system', 'Duurt langer dan verwacht, even geduld...');
            }, 30000);
        } else if (loadingTimeoutId) {
            window.clearTimeout(loadingTimeoutId);
            loadingTimeoutId = null;
        }
        updateControls();
    }

    function setEnabled(enabled) {
        controlsEnabled = enabled;
        updateControls();
    }

    function formatMoney(value) {
        if (value === null || value === undefined) {
            return 'onbeperkt';
        }
        return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(Number(value));
    }

    function formatCount(n) {
        if (n >= 1000000) return (n / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
        if (n >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
        return String(n);
    }

    function updateSessionCost() {
        if (!usageSummaryEl) {
            return;
        }
        usageSummaryEl.innerHTML = '<span>Kosten deze oefening: ' + formatMoney(sessionCostEur) + '</span>';
    }

    async function requestJson(url, options) {
        const response = await fetch(url, options);
        const rawText = await response.text();
        let data = null;
        if (rawText !== '') {
            try {
                data = JSON.parse(rawText);
            } catch (parseError) {
                data = null;
            }
        }
        if (!data || typeof data !== 'object') {
            const err = new Error('Er ging iets mis met het antwoord. Probeer het opnieuw.');
            err.status = response.status;
            err.payload = { raw: rawText.slice(0, 200) };
            throw err;
        }
        if (!response.ok || data.ok === false) {
            const error = data.error || 'Er is een fout opgetreden.';
            const err = new Error(error);
            err.status = response.status;
            err.payload = data;
            throw err;
        }
        return data;
    }

    async function loadModels() {
        try {
            const data = await requestJson('/ai/models', { method: 'GET' });
            modelSelect.innerHTML = '';
            data.models.forEach(function (model) {
                const option = document.createElement('option');
                option.value = model.model_id;
                option.textContent = model.label;
                if (model.is_default) {
                    option.selected = true;
                }
                modelSelect.appendChild(option);
            });
            if (!data.models.length) {
                setEnabled(false);
                setStatus('AI is nu even niet beschikbaar.', true);
                return false;
            }
            setEnabled(true);
            setStatus('Beschrijf de oefening die je zoekt.');
            return true;
        } catch (error) {
            setEnabled(false);
            setStatus(error.message, true);
            appendMessage('system', error.message);
            return false;
        }
    }

    function getCheckedValues(name) {
        var values = [];
        document.querySelectorAll('input[name="' + name + '[]"]:checked').forEach(function (cb) {
            values.push(cb.value);
        });
        return values;
    }

    function collectFormState() {
        var val = function (id) { var el = document.getElementById(id); return el ? el.value : ''; };
        var state = {
            title: val('title'),
            description: val('description'),
            variation: val('variation'),
            coach_instructions: val('coach_instructions'),
            source: val('source'),
            team_task: val('team_task'),
            objectives: getCheckedValues('training_objective'),
            actions: getCheckedValues('football_action'),
            min_players: val('min_players'),
            max_players: val('max_players'),
            duration: val('duration'),
            field_type: val('field_type'),
            has_drawing: !!(drawingDataInput && drawingDataInput.value && drawingDataInput.value !== ''),
        };
        return state;
    }

    function collectMessagePayload(messageText, extraFields, options) {
        const payload = new FormData();
        const includeScreenshots = !options || options.includeScreenshots !== false;
        payload.append('csrf_token', csrfToken);
        payload.append('message', (messageText || '').trim());
        payload.append('model_id', modelSelect.value);

        if (currentSessionId) {
            payload.append('session_id', String(currentSessionId));
        }
        if (exerciseIdInput && exerciseIdInput.value) {
            payload.append('exercise_id', exerciseIdInput.value);
        }
        if (fieldTypeInput && fieldTypeInput.value) {
            payload.append('field_type', fieldTypeInput.value);
        }
        payload.append('form_state', JSON.stringify(collectFormState()));

        if (extraFields && typeof extraFields === 'object') {
            Object.keys(extraFields).forEach(function (key) {
                payload.append(key, String(extraFields[key]));
            });
        }

        if (includeScreenshots) {
            getSelectedScreenshotFiles().forEach(function (file) {
                payload.append('screenshot_files[]', file, file.name || 'screenshot');
            });
        }

        return payload;
    }

    function setMultiSelectValues(name, values) {
        const wanted = new Set((values || []).map(function (item) {
            return String(item).toLowerCase();
        }));
        document.querySelectorAll('input[name="' + name + '[]"]').forEach(function (checkbox) {
            checkbox.checked = wanted.has(String(checkbox.value).toLowerCase());
        });
    }

    function applyFields(fields) {
        if (!fields) {
            return;
        }
        const setValue = function (id, value) {
            const el = document.getElementById(id);
            if (el && value !== undefined && value !== null) {
                var nextValue = String(value);
                if (String(el.value) !== nextValue) {
                    el.value = nextValue;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    el.value = nextValue;
                }
            }
        };

        if (fields.title !== undefined) setValue('title', fields.title);
        if (fields.description !== undefined) setValue('description', fields.description);
        if (fields.variation !== undefined) setValue('variation', fields.variation);
        if (fields.coach_instructions !== undefined) setValue('coach_instructions', fields.coach_instructions);
        if (fields.source !== undefined) setValue('source', fields.source);
        if (fields.min_players !== undefined) setValue('min_players', fields.min_players);
        if (fields.max_players !== undefined) setValue('max_players', fields.max_players);
        if (fields.duration !== undefined) setValue('duration', fields.duration);

        if (fields.team_task !== undefined && fields.team_task !== null) {
            const teamTask = document.getElementById('team_task');
            if (teamTask) {
                teamTask.value = fields.team_task;
            }
        }

        if (fields.objectives !== undefined) {
            setMultiSelectValues('training_objective', fields.objectives);
        }
        if (fields.actions !== undefined) {
            setMultiSelectValues('football_action', fields.actions);
        }

        if (typeof updateTrigger === 'function') {
            updateTrigger('wrapper-objective');
            updateTrigger('wrapper-action');
        }

        if (fields.field_type) {
            if (fieldTypeInput) {
                fieldTypeInput.value = fields.field_type;
            }
            if (window.exerciseEditorApi && typeof window.exerciseEditorApi.setFieldType === 'function') {
                window.exerciseEditorApi.setFieldType(fields.field_type);
            }
        }
    }

    function applyDrawing(drawing) {
        if (!drawing) {
            return;
        }
        var nodeCount = Number(drawing.node_count || 0);
        if (!Number.isFinite(nodeCount) || nodeCount <= 0) {
            setStatus('De bestaande tekening is behouden omdat de AI-tekening leeg was.');
            return;
        }
        if (drawingDataInput && drawing.drawing_data) {
            drawingDataInput.value = drawing.drawing_data;
        }
        if (fieldTypeInput && drawing.field_type) {
            fieldTypeInput.value = drawing.field_type;
        }
        if (window.exerciseEditorApi && typeof window.exerciseEditorApi.loadDrawingData === 'function' && drawing.drawing_data) {
            window.exerciseEditorApi.loadDrawingData(drawing.drawing_data, drawing.field_type);
        }
    }

    function appendVideoChoices(choices, options) {
        if (!messagesEl || !Array.isArray(choices) || choices.length === 0) {
            return;
        }

        var selectionOrigin = options && options.selectionOrigin ? String(options.selectionOrigin) : 'search_results';
        var recoveryTriggerCode = options && options.recoveryTriggerCode ? String(options.recoveryTriggerCode) : '';
        var container = document.createElement('div');
        container.className = 'ai-video-choices';

        choices.forEach(function (video, index) {
            var card = document.createElement('div');
            card.className = 'ai-video-card';
            if (video.is_recommended) card.classList.add('ai-video-card-recommended');

            var body = document.createElement('div');
            body.className = 'ai-video-card-body';

            var header = document.createElement('div');
            header.className = 'ai-video-card-header';
            header.textContent = video.title || 'Onbekende video';

            var meta = document.createElement('div');
            meta.className = 'ai-video-card-meta';
            var metaParts = [];
            if (video.channel) metaParts.push(video.channel);
            if (video.duration_formatted) metaParts.push(video.duration_formatted);
            if (video.view_count) metaParts.push(formatCount(video.view_count) + ' views');
            if (video.like_count) metaParts.push(formatCount(video.like_count) + ' likes');
            meta.textContent = metaParts.join(' \u00b7 ');

            body.appendChild(header);
            body.appendChild(meta);

            if (video.ai_reason) {
                var reason = document.createElement('div');
                reason.className = 'ai-video-card-reason';
                reason.textContent = video.ai_reason;
                body.appendChild(reason);
            }

            var showTechnical = video.is_selectable === false && (video.technical_summary || video.technical_label);
            if (showTechnical) {
                var technical = document.createElement('div');
                technical.className = 'ai-video-card-technical';
                technical.textContent = String(video.technical_summary || video.technical_label || '');
                body.appendChild(technical);
            }

            var chapterCount = 0;
            if (video.technical_preflight && Number.isFinite(Number(video.technical_preflight.chapter_count))) {
                chapterCount = Math.max(0, Math.round(Number(video.technical_preflight.chapter_count)));
            } else if (Array.isArray(video.chapters)) {
                chapterCount = video.chapters.length;
            }
            if (chapterCount > 0) {
                var chapterInfo = document.createElement('div');
                chapterInfo.className = 'ai-video-card-evidence-summary';
                chapterInfo.textContent = chapterCount + ' hoofdstuk' + (chapterCount === 1 ? '' : 'ken');
                body.appendChild(chapterInfo);
            }

            var stars = renderTranslatabilityStars(video.translatability_rating);
            if (stars !== '') {
                var rating = document.createElement('div');
                rating.className = 'ai-video-card-evidence-summary';
                var ratingLabel = video.translatability_label ? String(video.translatability_label) : '';
                rating.textContent = 'Vertaalbaarheid: ' + stars + (ratingLabel !== '' ? ' (' + ratingLabel + ')' : '');
                body.appendChild(rating);
            }

            var showEvidenceChip = Boolean(video.source_evidence_label) && video.source_evidence_sufficient === true;
            if (showEvidenceChip) {
                var evidence = document.createElement('div');
                evidence.className = 'ai-video-card-evidence';

                var chips = document.createElement('div');
                chips.className = 'ai-video-card-chips';
                var evidenceChip = document.createElement('span');
                var level = video.source_evidence_level ? String(video.source_evidence_level) : 'low';
                evidenceChip.className = 'ai-video-chip ai-video-chip-evidence ai-video-chip-evidence-' + level;
                evidenceChip.textContent = String(video.source_evidence_label);
                chips.appendChild(evidenceChip);
                evidence.appendChild(chips);

                body.appendChild(evidence);
            }

            card.appendChild(body);

            var actions = document.createElement('div');
            actions.className = 'ai-video-card-actions';

            var viewBtn = document.createElement('a');
            viewBtn.href = video.url || '#';
            viewBtn.target = '_blank';
            viewBtn.rel = 'noopener noreferrer';
            viewBtn.className = 'ai-video-btn ai-video-btn-view';
            viewBtn.textContent = '\u25b6 Bekijk';

            var useBtn = document.createElement('button');
            useBtn.type = 'button';
            useBtn.className = 'ai-video-btn ai-video-btn-use';
            useBtn.textContent = video.is_selectable === false ? 'Nu niet bruikbaar' : 'Kies deze';
            useBtn.dataset.videoId = video.video_id || '';
            useBtn.dataset.videoTitle = video.title || '';
            useBtn.dataset.selectionOrigin = selectionOrigin;
            useBtn.dataset.recoveryTriggerCode = recoveryTriggerCode;
            if (video.is_selectable === false) {
                useBtn.disabled = true;
            } else {
                useBtn.addEventListener('click', function () {
                    disableVideoChoiceButtons();
                    sendVideoSelection(this.dataset.videoId, this.dataset.videoTitle, {
                        selectionOrigin: this.dataset.selectionOrigin || 'search_results',
                        recoveryTriggerCode: this.dataset.recoveryTriggerCode || '',
                    });
                });
            }

            actions.appendChild(viewBtn);
            actions.appendChild(useBtn);
            card.appendChild(actions);
            container.appendChild(card);
        });

        var retryBtn = document.createElement('button');
        retryBtn.type = 'button';
        retryBtn.className = 'ai-video-btn ai-video-btn-retry';
        retryBtn.textContent = '\uD83D\uDD04 Anders zoeken';
        retryBtn.addEventListener('click', function () {
            messageInput.focus();
            messageInput.placeholder = 'Omschrijf het iets anders...';
        });
        container.appendChild(retryBtn);

        messagesEl.appendChild(container);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function disableVideoChoiceButtons() {
        if (!messagesEl) return;
        messagesEl.querySelectorAll('.ai-video-btn-use').forEach(function (btn) {
            btn.disabled = true;
        });
    }

    function appendSegmentChoices(choices, videoId) {
        if (!messagesEl || !Array.isArray(choices) || choices.length === 0) {
            return;
        }

        var container = document.createElement('div');
        container.className = 'ai-segment-choices';

        choices.forEach(function (segment) {
            var card = document.createElement('div');
            card.className = 'ai-segment-card';
            if (segment.type === 'variation') card.classList.add('ai-segment-card-variation');

            var body = document.createElement('div');
            body.className = 'ai-segment-card-body';

            var header = document.createElement('div');
            header.className = 'ai-segment-card-header';

            var typeBadge = document.createElement('span');
            typeBadge.className = 'ai-segment-badge ai-segment-badge-' + (segment.type || 'drill');
            typeBadge.textContent = segment.type === 'variation' ? 'Variatie' : 'Oefening';

            var titleSpan = document.createElement('span');
            titleSpan.textContent = segment.title || 'Segment';

            header.appendChild(typeBadge);
            header.appendChild(titleSpan);

            var meta = document.createElement('div');
            meta.className = 'ai-segment-card-meta';
            var metaParts = [];
            if (segment.start_formatted && segment.end_formatted) {
                metaParts.push(segment.start_formatted + ' \u2013 ' + segment.end_formatted);
            }
            if (segment.duration_seconds) {
                var mins = Math.floor(segment.duration_seconds / 60);
                var secs = segment.duration_seconds % 60;
                metaParts.push((mins > 0 ? mins + ' min ' : '') + secs + ' sec');
            }
            if (segment.confidence) {
                var confLabel = { high: 'duidelijk', medium: 'redelijk duidelijk', low: 'nog onzeker' };
                metaParts.push(confLabel[segment.confidence] || segment.confidence);
            }
            meta.textContent = metaParts.join(' \u00b7 ');

            body.appendChild(header);
            body.appendChild(meta);

            if (Array.isArray(segment.chapter_titles) && segment.chapter_titles.length > 0) {
                var chapters = document.createElement('div');
                chapters.className = 'ai-segment-card-chapters';
                chapters.textContent = segment.chapter_titles.join(' \u2192 ');
                body.appendChild(chapters);
            }

            card.appendChild(body);

            var actions = document.createElement('div');
            actions.className = 'ai-segment-card-actions';

            var useBtn = document.createElement('button');
            useBtn.type = 'button';
            useBtn.className = 'ai-segment-btn ai-segment-btn-use';
            useBtn.textContent = 'Gebruik dit onderdeel';
            useBtn.dataset.segmentId = String(segment.segment_id || 0);
            useBtn.dataset.segmentTitle = segment.title || 'Segment';
            useBtn.dataset.videoId = videoId || '';

            useBtn.addEventListener('click', function () {
                disableSegmentChoiceButtons();
                sendSegmentSelection(this.dataset.videoId, this.dataset.segmentId, this.dataset.segmentTitle);
            });

            actions.appendChild(useBtn);
            card.appendChild(actions);
            container.appendChild(card);
        });

        messagesEl.appendChild(container);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendConceptRecoveryOption(recovery) {
        if (!messagesEl || !recovery || !recovery.video_id) {
            return;
        }

        var container = document.createElement('div');
        container.className = 'ai-concept-recovery';

        var text = document.createElement('div');
        text.className = 'ai-concept-recovery-text';
        text.textContent = recovery.message || 'Ik kan ook eerst een eerste opzet maken.';
        container.appendChild(text);

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'ai-video-btn ai-video-btn-concept';
        button.textContent = 'Maak eerste opzet';
        button.addEventListener('click', function () {
            sendVideoSelection(recovery.video_id, recovery.video_title || 'deze video', {
                selectionOrigin: 'concept_recovery',
                recoveryTriggerCode: recovery.trigger_code || '',
                conceptMode: true,
            });
        });
        container.appendChild(button);

        messagesEl.appendChild(container);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendScreenshotRecoveryOption(recovery) {
        if (!messagesEl || !recovery || !recovery.video_id || !screenshotInput) {
            return;
        }

        var container = document.createElement('div');
        container.className = 'ai-screenshot-recovery';

        var text = document.createElement('div');
        text.className = 'ai-screenshot-recovery-text';
        text.textContent = recovery.message || 'Plak 2 tot 4 screenshots met Ctrl+V als de video bij jou wel afspeelt.';
        container.appendChild(text);

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'ai-video-btn ai-video-btn-screenshot';
        button.textContent = 'Kies bestanden';
        button.addEventListener('click', function () {
            setScreenshotUploadEnabled(true);
            focusScreenshotUpload();
            setStatus(recovery.upload_hint || 'Plak 2 tot 4 screenshots met Ctrl+V of kies bestanden.');
        });
        container.appendChild(button);

        messagesEl.appendChild(container);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function disableSegmentChoiceButtons() {
        if (!messagesEl) return;
        messagesEl.querySelectorAll('.ai-segment-btn-use').forEach(function (btn) {
            btn.disabled = true;
        });
    }

    function handleChatRequestError(error) {
        if (error.name === 'AbortError') {
            appendMessage('system', 'Gestopt.');
            setStatus('Gestopt.', true);
            return;
        }

        var payload = error && error.payload && typeof error.payload === 'object' ? error.payload : {};
        if (payload.session_id) {
            currentSessionId = payload.session_id;
        }

        var hasRecoveryChoices = Array.isArray(payload.recovery_video_choices) && payload.recovery_video_choices.length > 0;
        var hasConceptRecovery = payload.concept_recovery && payload.concept_recovery.video_id;
        var hasScreenshotRecovery = payload.screenshot_recovery && payload.screenshot_recovery.video_id;

        if (hasRecoveryChoices || hasConceptRecovery || hasScreenshotRecovery) {
            appendMessage('assistant', error.message);
            if (payload.source_review) {
                appendSourceReview(payload.source_review);
            }
            if (hasRecoveryChoices) {
                appendVideoChoices(payload.recovery_video_choices, {
                    selectionOrigin: 'recovery',
                    recoveryTriggerCode: payload.code || '',
                });
            }
            if (hasConceptRecovery) {
                appendConceptRecoveryOption(payload.concept_recovery);
            }
            if (hasScreenshotRecovery) {
                setScreenshotUploadEnabled(true);
                appendScreenshotRecoveryOption(payload.screenshot_recovery);
            } else {
                var phase = exerciseCard && exerciseCard.dataset ? String(exerciseCard.dataset.aiPhase || 'search') : 'search';
                setScreenshotUploadEnabled(phase === 'design');
            }
            setStatus(payload.screenshot_message || payload.recovery_message || payload.concept_message || 'Kies hoe je verder wilt.');
            return;
        }

        var phase = exerciseCard && exerciseCard.dataset ? String(exerciseCard.dataset.aiPhase || 'search') : 'search';
        setScreenshotUploadEnabled(phase === 'design');
        appendMessage('system', error.message);
        setStatus(error.message, true);
    }

    async function sendSegmentSelection(videoId, segmentId, segmentTitle) {
        if (!videoId || !segmentId || isLoading) return;

        disableSegmentChoiceButtons();
        appendMessage('user', 'Maak een oefening van: ' + segmentTitle);

        var payload = collectMessagePayload('', {
            mode: 'generate_segment',
            selected_video_id: videoId,
            selected_segment_id: segmentId,
        }, {
            includeScreenshots: false,
        });
        activeController = new AbortController();
        setLoading(true);

        try {
            var data = await requestJson('/ai/chat/message', {
                method: 'POST',
                body: payload,
                signal: activeController.signal,
            });

            setScreenshotUploadEnabled(false);
            currentSessionId = data.session_id;
            appendMessage('assistant', data.message.content || '');

            if (data.suggestions) {
                if (data.suggestions.text) {
                    applyFields(data.suggestions.text.fields);
                }
                if (data.suggestions.drawing) {
                    applyDrawing(data.suggestions.drawing);
                }
                appendWarning(data.suggestions.warnings || []);
            }

            appendSources(data.sources_used || []);
            if (data.source_review) {
                appendSourceReview(data.source_review);
            }

            if (data.usage) {
                sessionCostEur += Number(data.usage.billable_cost_eur || 0);
                updateSessionCost();
            }
            setStatus('Oefening klaar.');
            switchPhase('design');
        } catch (error) {
            handleChatRequestError(error);
        } finally {
            activeController = null;
            setLoading(false);
        }
    }

    async function sendVideoSelection(videoId, videoTitle, options) {
        if (!videoId || isLoading) return;

        disableVideoChoiceButtons();
        appendMessage('user', 'Maak een oefening van: ' + videoTitle);

        var payload = collectMessagePayload('', {
            mode: 'generate',
            selected_video_id: videoId,
            selection_origin: options && options.selectionOrigin ? String(options.selectionOrigin) : 'search_results',
            recovery_trigger_code: options && options.recoveryTriggerCode ? String(options.recoveryTriggerCode) : '',
        }, {
            includeScreenshots: false,
        });
        if (options && options.conceptMode) {
            payload.append('concept_mode', '1');
        }
        activeController = new AbortController();
        setLoading(true);

        try {
            var data = await requestJson('/ai/chat/message', {
                method: 'POST',
                body: payload,
                signal: activeController.signal,
            });

            setScreenshotUploadEnabled(false);
            currentSessionId = data.session_id;
            appendMessage('assistant', data.message.content || '');

            if (data.phase === 'concept_questions') {
                setStatus('Beantwoord de vragen voor een eerste opzet.');
            } else if (data.phase === 'segment_choices' && Array.isArray(data.segment_choices)) {
                appendSegmentChoices(data.segment_choices, data.video_id || videoId);
                setStatus('Kies het onderdeel waarmee je verder wilt.');
            } else {
                if (data.suggestions) {
                    if (data.suggestions.text) {
                        applyFields(data.suggestions.text.fields);
                    }
                    if (data.suggestions.drawing) {
                        applyDrawing(data.suggestions.drawing);
                    }
                    appendWarning(data.suggestions.warnings || []);
                }

                appendSources(data.sources_used || []);
                if (data.source_review) {
                    appendSourceReview(data.source_review);
                }

                if (data.usage) {
                    sessionCostEur += Number(data.usage.billable_cost_eur || 0);
                    updateSessionCost();
                }
                setStatus(data.concept_mode && data.concept_mode.active ? 'Eerste opzet klaar.' : 'Oefening klaar.');
                switchPhase('design');
            }
        } catch (error) {
            handleChatRequestError(error);
        } finally {
            activeController = null;
            setLoading(false);
        }
    }

    async function sendMessage(overrideText) {
        const text = (typeof overrideText === 'string' ? overrideText : messageInput.value).trim();
        const screenshotCount = getSelectedScreenshotCount();
        const mode = resolveMessageMode(text, screenshotCount);
        if (text === '' && screenshotCount === 0) {
            setStatus(mode === 'refine_drawing' ? 'Typ wat je in de tekening wilt aanpassen.' : 'Typ eerst een bericht.', true);
            return;
        }

        const payload = collectMessagePayload(text, {
            mode: mode,
        }, {
            includeScreenshots: screenshotCount > 0 && (mode === 'screenshot_recovery' || mode === 'refine_drawing'),
        });
        appendMessage('user', text !== '' ? text : 'Ik upload ' + screenshotCount + ' screenshot' + (screenshotCount === 1 ? '' : 's') + '.');
        messageInput.value = '';
        activeController = new AbortController();
        setLoading(true);

        try {
            const data = await requestJson('/ai/chat/message', {
                method: 'POST',
                body: payload,
                signal: activeController.signal,
            });

            clearSelectedScreenshots();
            var currentPhase = exerciseCard && exerciseCard.dataset ? String(exerciseCard.dataset.aiPhase || 'search') : 'search';
            setScreenshotUploadEnabled(currentPhase === 'design');

            currentSessionId = data.session_id;
            appendMessage('assistant', data.message.content || '');

            if (data.phase === 'search_results' && Array.isArray(data.video_choices)) {
                appendVideoChoices(data.video_choices, { selectionOrigin: 'search_results' });
                if (data.usage) {
                    sessionCostEur += Number(data.usage.billable_cost_eur || 0);
                    updateSessionCost();
                }
                setStatus('Kies een video om verder te gaan.');
            } else if (data.phase === 'segment_choices' && Array.isArray(data.segment_choices)) {
                appendSegmentChoices(data.segment_choices, data.video_id || '');
                setStatus('Kies het onderdeel waarmee je verder wilt.');
            } else {
                if (data.suggestions) {
                    if (data.suggestions.text) {
                        applyFields(data.suggestions.text.fields);
                    }
                    if (data.suggestions.drawing) {
                        applyDrawing(data.suggestions.drawing);
                    }
                    appendWarning(data.suggestions.warnings || []);
                }

                appendSources(data.sources_used || []);
                if (data.source_review) {
                    appendSourceReview(data.source_review);
                }

                if (data.usage) {
                    sessionCostEur += Number(data.usage.billable_cost_eur || 0);
                    updateSessionCost();
                }
                setStatus('Antwoord ontvangen.');

                if (data.suggestions) {
                    switchPhase('design');
                }
            }
        } catch (error) {
            handleChatRequestError(error);
        } finally {
            activeController = null;
            setLoading(false);
        }
    }

    sendBtn.addEventListener('click', sendMessage);
    messageInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });
    messageInput.addEventListener('paste', function (event) {
        var pastedImages = extractPastedImageFiles(event);
        if (pastedImages.length === 0) {
            return;
        }

        if (!screenshotUploadEnabled) {
            setStatus('Screenshots kun je toevoegen zodra ik erom vraag.');
            return;
        }

        event.preventDefault();
        var result = addSelectedScreenshots(pastedImages);
        if (result.added > 0) {
            setStatus(result.added + ' screenshot' + (result.added === 1 ? '' : 's') + ' toegevoegd. Stuur je bericht om verder te gaan.');
        }
        if (result.overflow > 0) {
            setStatus('Maximaal ' + MAX_SCREENSHOT_FILES + ' screenshots per bericht.', true);
        }
    });

    stopBtn.addEventListener('click', function () {
        if (activeController) {
            activeController.abort();
        }
    });

    newChatBtn.addEventListener('click', function () {
        currentSessionId = null;
        sessionCostEur = 0;
        messagesEl.innerHTML = '';
        setScreenshotUploadEnabled(false);
        updateSessionCost();
        switchPhase('search');
        setStatus('Nieuw gesprek gestart.');
    });

    if (backToSearchBtn) {
        backToSearchBtn.addEventListener('click', function () {
            switchPhase('search');
            messageInput.focus();
        });
    }

    if (screenshotInput) {
        screenshotInput.addEventListener('change', function () {
            if (!screenshotUploadEnabled) {
                screenshotInput.value = '';
                return;
            }

            var selectedFiles = screenshotInput.files ? Array.from(screenshotInput.files) : [];
            var result = addSelectedScreenshots(selectedFiles);
            screenshotInput.value = '';

            if (result.added > 0) {
                setStatus(result.added + ' screenshot' + (result.added === 1 ? '' : 's') + ' toegevoegd. Stuur je bericht om verder te gaan.');
            }
            if (result.overflow > 0) {
                setStatus('Maximaal ' + MAX_SCREENSHOT_FILES + ' screenshots per bericht.', true);
            }
        });
        updateScreenshotSummary();
    }

    if (screenshotClearBtn) {
        screenshotClearBtn.addEventListener('click', clearSelectedScreenshots);
    }

    (async function init() {
        setScreenshotUploadEnabled(false);
        const modelsOk = await loadModels();
        if (modelsOk) {
            updateSessionCost();
        }
    })();
});
