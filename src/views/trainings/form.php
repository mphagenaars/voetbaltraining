<?php
$isEdit = isset($training);
$formTitle = $isEdit ? 'Training Bewerken' : 'Nieuwe Training';
$descValue = $isEdit ? $training['description'] : '';
$dateValue = $isEdit ? ($training['training_date'] ?? '') : date('Y-m-d');
$currentExercises = $isEdit ? ($training['exercises'] ?? []) : [];
$saveLabel = $isEdit ? 'Training opslaan' : 'Training aanmaken';

$allExercisesForPicker = array_values(array_map(static function (array $exercise): array {
    return [
        'id' => (int)($exercise['id'] ?? 0),
        'title' => trim((string)($exercise['title'] ?? 'Onbekende oefening')),
        'duration' => isset($exercise['duration']) ? (int)$exercise['duration'] : 0,
    ];
}, is_array($allExercises ?? null) ? $allExercises : []));
?>

<div class="app-bar">
    <div class="app-bar-start">
        <a href="/trainings" class="btn-icon-round" title="Terug" aria-label="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title"><?= e($formTitle) ?></h1>
    </div>
</div>

<form method="POST" id="training-form" class="tb-training-builder-form">
    <?= Csrf::renderInput() ?>

    <div class="card tb-training-builder-card">
        <div class="tb-training-builder-grid">
            <div class="form-group tb-training-builder-date-group">
                <label for="training_date">Datum *</label>
                <input type="date" id="training_date" name="training_date" required value="<?= e($dateValue) ?>">
            </div>

            <div class="form-group tb-training-builder-description-group">
                <label for="description">Beschrijving</label>
                <textarea id="description" name="description" rows="3"><?= e($descValue) ?></textarea>
            </div>
        </div>
    </div>

    <section class="tb-training-builder-section">
        <div class="tb-training-builder-section-header">
            <div>
                <h3 class="tb-training-builder-section-title">Geselecteerde oefeningen</h3>
                <p class="tb-training-builder-section-meta">Gebruik de plus-knop om oefeningen toe te voegen. Sleep voor volgorde en gebruik iconen per blok voor acties.</p>
            </div>
        </div>

        <div class="card tb-training-builder-list-card" id="selected-list">
            <p class="tb-training-builder-empty text-muted" id="empty-msg"<?= !empty($currentExercises) ? ' hidden' : '' ?>>Nog geen oefeningen gekozen.</p>

            <?php foreach ($currentExercises as $index => $exercise): ?>
                <?php
                $exerciseId = (int)($exercise['id'] ?? 0);
                $exerciseTitle = (string)($exercise['title'] ?? 'Onbekende oefening');
                $goalValue = (string)($exercise['training_goal'] ?? '');
                $durationValue = isset($exercise['training_duration']) ? (string)$exercise['training_duration'] : '';
                $goalInputId = 'training-goal-' . $exerciseId . '-' . $index;
                $durationInputId = 'training-duration-' . $exerciseId . '-' . $index;
                ?>
                <article class="selected-item tb-training-exercise-item" data-exercise-id="<?= $exerciseId ?>">
                    <div class="drag-handle tb-training-drag-handle" title="Sleep om te sorteren" aria-label="Sleep om te sorteren">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                    </div>

                    <div class="tb-training-exercise-content">
                        <div class="tb-training-exercise-header">
                            <div class="tb-training-exercise-title"><?= e($exerciseTitle) ?></div>
                            <div class="tb-training-exercise-actions">
                                <a href="/exercises/view?id=<?= $exerciseId ?>" class="tb-icon-button tb-training-exercise-open" title="Bekijk oefening" aria-label="Bekijk oefening" target="_blank" rel="noopener">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </a>
                                <button type="button" class="tb-icon-button tb-icon-button--danger tb-training-exercise-remove" title="Verwijderen" aria-label="Verwijderen">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                </button>
                            </div>
                        </div>

                        <label class="tb-training-input-label" for="<?= e($goalInputId) ?>">Doel binnen training</label>
                        <textarea id="<?= e($goalInputId) ?>" class="tb-training-goal-input" name="goals[]" rows="2" placeholder="Doel van deze oefening binnen deze training"><?= e($goalValue) ?></textarea>
                    </div>

                    <input type="hidden" name="exercises[]" value="<?= $exerciseId ?>">

                    <div class="tb-training-duration-wrap">
                        <label class="tb-training-input-label" for="<?= e($durationInputId) ?>">Min</label>
                        <input id="<?= e($durationInputId) ?>" class="tb-training-duration-input" type="number" min="0" step="1" name="durations[]" value="<?= e($durationValue) ?>" placeholder="min" title="Duur in minuten">
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="tb-training-builder-save">
        <button type="submit" class="tb-button tb-button--primary tb-button--lg"><?= e($saveLabel) ?></button>
    </div>
</form>

<button type="button" id="tb-open-exercise-picker" class="tb-fab tb-training-add-fab" title="Oefening toevoegen" aria-label="Oefening toevoegen">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
</button>

<div id="tb-training-picker" class="tb-training-picker" hidden>
    <div class="tb-training-picker-backdrop" data-close-picker></div>
    <div class="tb-training-picker-panel card" role="dialog" aria-modal="true" aria-labelledby="tb-training-picker-title">
        <div class="tb-training-picker-header">
            <h3 id="tb-training-picker-title">Oefening toevoegen</h3>
            <button type="button" class="tb-icon-button" data-close-picker title="Sluiten" aria-label="Sluiten">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <div class="tb-training-picker-search-wrap">
            <label for="tb-training-picker-search" class="tb-training-input-label">Zoek oefening</label>
            <input type="search" id="tb-training-picker-search" placeholder="Typ om te filteren op titel..." autocomplete="off">
        </div>

        <div id="tb-training-picker-list" class="tb-training-picker-list"></div>
    </div>
</div>

<script type="application/json" id="tb-all-exercises-json"><?= json_encode($allExercisesForPicker, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectedList = document.getElementById('selected-list');
    const emptyMsg = document.getElementById('empty-msg');
    const openPickerBtn = document.getElementById('tb-open-exercise-picker');
    const picker = document.getElementById('tb-training-picker');
    const pickerSearch = document.getElementById('tb-training-picker-search');
    const pickerList = document.getElementById('tb-training-picker-list');
    const pickerJsonEl = document.getElementById('tb-all-exercises-json');

    if (!selectedList || !emptyMsg || !openPickerBtn || !picker || !pickerSearch || !pickerList || !pickerJsonEl) {
        return;
    }

    let allExercises = [];
    try {
        const raw = JSON.parse(pickerJsonEl.textContent || '[]');
        if (Array.isArray(raw)) {
            allExercises = raw
                .map(function (item) {
                    return {
                        id: Number(item.id || 0),
                        title: String(item.title || '').trim(),
                        duration: Number(item.duration || 0)
                    };
                })
                .filter(function (item) {
                    return item.id > 0 && item.title !== '';
                })
                .sort(function (a, b) {
                    return a.title.localeCompare(b.title, 'nl', { sensitivity: 'base' });
                });
        }
    } catch (error) {
        allExercises = [];
    }

    function getSelectedExerciseIds() {
        const selectedIds = new Set();
        selectedList.querySelectorAll('input[name="exercises[]"]').forEach(function (input) {
            selectedIds.add(String(input.value));
        });
        return selectedIds;
    }

    function checkEmpty() {
        const hasItems = selectedList.querySelectorAll('.selected-item').length > 0;
        emptyMsg.hidden = hasItems;
    }

    function uniqueSuffix() {
        return Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
    }

    function createExerciseItem(exercise) {
        const suffix = uniqueSuffix();
        const goalId = 'training-goal-' + exercise.id + '-' + suffix;
        const durationId = 'training-duration-' + exercise.id + '-' + suffix;

        const item = document.createElement('article');
        item.className = 'selected-item tb-training-exercise-item';
        item.dataset.exerciseId = String(exercise.id);

        const dragHandle = document.createElement('div');
        dragHandle.className = 'drag-handle tb-training-drag-handle';
        dragHandle.title = 'Sleep om te sorteren';
        dragHandle.setAttribute('aria-label', 'Sleep om te sorteren');
        dragHandle.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>';

        const content = document.createElement('div');
        content.className = 'tb-training-exercise-content';

        const header = document.createElement('div');
        header.className = 'tb-training-exercise-header';

        const title = document.createElement('div');
        title.className = 'tb-training-exercise-title';
        title.textContent = exercise.title;

        const actions = document.createElement('div');
        actions.className = 'tb-training-exercise-actions';

        const openLink = document.createElement('a');
        openLink.href = '/exercises/view?id=' + encodeURIComponent(exercise.id);
        openLink.className = 'tb-icon-button tb-training-exercise-open';
        openLink.title = 'Bekijk oefening';
        openLink.setAttribute('aria-label', 'Bekijk oefening');
        openLink.target = '_blank';
        openLink.rel = 'noopener';
        openLink.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'tb-icon-button tb-icon-button--danger tb-training-exercise-remove';
        removeBtn.title = 'Verwijderen';
        removeBtn.setAttribute('aria-label', 'Verwijderen');
        removeBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>';

        actions.appendChild(openLink);
        actions.appendChild(removeBtn);
        header.appendChild(title);
        header.appendChild(actions);

        const goalLabel = document.createElement('label');
        goalLabel.className = 'tb-training-input-label';
        goalLabel.setAttribute('for', goalId);
        goalLabel.textContent = 'Doel binnen training';

        const goalTextarea = document.createElement('textarea');
        goalTextarea.id = goalId;
        goalTextarea.className = 'tb-training-goal-input';
        goalTextarea.name = 'goals[]';
        goalTextarea.rows = 2;
        goalTextarea.placeholder = 'Doel van deze oefening binnen deze training';

        content.appendChild(header);
        content.appendChild(goalLabel);
        content.appendChild(goalTextarea);

        const exerciseInput = document.createElement('input');
        exerciseInput.type = 'hidden';
        exerciseInput.name = 'exercises[]';
        exerciseInput.value = String(exercise.id);

        const durationWrap = document.createElement('div');
        durationWrap.className = 'tb-training-duration-wrap';

        const durationLabel = document.createElement('label');
        durationLabel.className = 'tb-training-input-label';
        durationLabel.setAttribute('for', durationId);
        durationLabel.textContent = 'Min';

        const durationInput = document.createElement('input');
        durationInput.id = durationId;
        durationInput.className = 'tb-training-duration-input';
        durationInput.type = 'number';
        durationInput.min = '0';
        durationInput.step = '1';
        durationInput.name = 'durations[]';
        durationInput.placeholder = 'min';
        durationInput.title = 'Duur in minuten';
        if (exercise.duration > 0) {
            durationInput.value = String(exercise.duration);
        }

        durationWrap.appendChild(durationLabel);
        durationWrap.appendChild(durationInput);

        item.appendChild(dragHandle);
        item.appendChild(content);
        item.appendChild(exerciseInput);
        item.appendChild(durationWrap);

        return item;
    }

    function setPickerOpen(nextState) {
        picker.hidden = !nextState;
        document.body.classList.toggle('tb-picker-open', nextState);
        if (nextState) {
            pickerSearch.focus();
            renderPickerList();
        }
    }

    function renderPickerList() {
        const query = pickerSearch.value.trim().toLowerCase();
        const selectedIds = getSelectedExerciseIds();
        const matches = allExercises.filter(function (exercise) {
            return exercise.title.toLowerCase().includes(query);
        });

        pickerList.innerHTML = '';

        if (matches.length === 0) {
            const emptyState = document.createElement('p');
            emptyState.className = 'tb-training-picker-empty text-muted';
            emptyState.textContent = 'Geen oefeningen gevonden.';
            pickerList.appendChild(emptyState);
            return;
        }

        const maxRows = 120;
        const visibleMatches = matches.slice(0, maxRows);

        visibleMatches.forEach(function (exercise) {
            const row = document.createElement('div');
            row.className = 'tb-training-picker-item';

            const main = document.createElement('div');
            main.className = 'tb-training-picker-item-main';

            const title = document.createElement('div');
            title.className = 'tb-training-picker-item-title';
            title.textContent = exercise.title;

            main.appendChild(title);

            if (exercise.duration > 0) {
                const meta = document.createElement('div');
                meta.className = 'tb-training-picker-item-meta';
                meta.textContent = 'Standaard duur: ' + exercise.duration + ' min';
                main.appendChild(meta);
            }

            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'tb-button tb-button--secondary tb-button--sm';

            if (selectedIds.has(String(exercise.id))) {
                addBtn.textContent = 'Toegevoegd';
                addBtn.disabled = true;
            } else {
                addBtn.textContent = 'Toevoegen';
                addBtn.addEventListener('click', function () {
                    selectedList.appendChild(createExerciseItem(exercise));
                    checkEmpty();
                    renderPickerList();
                    setPickerOpen(false);
                });
            }

            row.appendChild(main);
            row.appendChild(addBtn);
            pickerList.appendChild(row);
        });

        if (matches.length > maxRows) {
            const overflowHint = document.createElement('p');
            overflowHint.className = 'tb-training-picker-overflow text-muted';
            overflowHint.textContent = 'Toon maximaal ' + maxRows + ' resultaten. Verfijn je zoekterm.';
            pickerList.appendChild(overflowHint);
        }
    }

    openPickerBtn.addEventListener('click', function () {
        setPickerOpen(true);
    });

    picker.addEventListener('click', function (event) {
        if (event.target.closest('[data-close-picker]')) {
            setPickerOpen(false);
        }
    });

    pickerSearch.addEventListener('input', renderPickerList);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !picker.hidden) {
            setPickerOpen(false);
        }
    });

    selectedList.addEventListener('click', function (event) {
        const removeBtn = event.target.closest('.tb-training-exercise-remove');
        if (!removeBtn) {
            return;
        }

        const item = removeBtn.closest('.selected-item');
        if (!item) {
            return;
        }

        item.remove();
        checkEmpty();
        renderPickerList();
    });

    let dragSource = null;

    selectedList.addEventListener('mousedown', onDragStart);
    selectedList.addEventListener('touchstart', onDragStart, { passive: false });

    function onDragStart(event) {
        const handle = event.target.closest('.drag-handle');
        if (!handle) {
            return;
        }

        const item = handle.closest('.selected-item');
        if (!item) {
            return;
        }

        if (event.type === 'touchstart') {
            event.preventDefault();
        }

        dragSource = item;
        item.classList.add('dragging');

        document.addEventListener('mousemove', onDragMove);
        document.addEventListener('touchmove', onDragMove, { passive: false });
        document.addEventListener('mouseup', onDragEnd);
        document.addEventListener('touchend', onDragEnd);
    }

    function onDragMove(event) {
        if (!dragSource) {
            return;
        }

        event.preventDefault();

        const clientY = event.type.indexOf('touch') >= 0 ? event.touches[0].clientY : event.clientY;
        const siblings = Array.from(selectedList.querySelectorAll('.selected-item:not(.dragging)'));

        const nextSibling = siblings.find(function (sibling) {
            const box = sibling.getBoundingClientRect();
            return clientY <= box.top + box.height / 2;
        });

        if (nextSibling) {
            selectedList.insertBefore(dragSource, nextSibling);
        } else {
            selectedList.appendChild(dragSource);
        }
    }

    function onDragEnd() {
        if (dragSource) {
            dragSource.classList.remove('dragging');
            dragSource = null;
        }

        document.removeEventListener('mousemove', onDragMove);
        document.removeEventListener('touchmove', onDragMove);
        document.removeEventListener('mouseup', onDragEnd);
        document.removeEventListener('touchend', onDragEnd);
    }

    checkEmpty();
    renderPickerList();
});
</script>
