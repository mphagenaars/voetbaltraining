<?php
$isEdit = isset($training);
$formTitle = $isEdit ? 'Training Bewerken' : 'Nieuwe Training';
$titleValue = $isEdit ? $training['title'] : '';
$descValue = $isEdit ? $training['description'] : '';
$dateValue = $isEdit ? ($training['training_date'] ?? '') : date('Y-m-d');
$currentExercises = $isEdit ? ($training['exercises'] ?? []) : [];
?>
<div class="header-actions">
    <h1><?= $formTitle ?></h1>
    <a href="/trainings" class="btn btn-outline">Terug</a>
</div>

<form method="POST" id="training-form">
    <?= Csrf::renderInput() ?>
    <div class="card">
        <div style="display: flex; gap: 1rem; align-items: flex-end; margin-bottom: 1rem;">
            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                <label for="training_date">Datum *</label>
                <input type="date" id="training_date" name="training_date" required value="<?= htmlspecialchars($dateValue) ?>">
            </div>
            <button type="submit" class="btn-icon" title="Opslaan" style="margin-bottom: 4px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            </button>
        </div>

        <div class="form-group">
            <label for="description">Beschrijving</label>
            <textarea id="description" name="description" rows="3"><?= htmlspecialchars($descValue) ?></textarea>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
        <div>
            <h3>Bibliotheek</h3>
            <div class="card" style="max-height: 600px; overflow-y: auto;">
                <input type="text" id="search-exercises" placeholder="Zoek oefening..." style="margin-bottom: 1rem; width: 100%;">
                <div id="library-list">
                    <?php foreach ($allExercises as $exercise): ?>
                        <div class="exercise-item" data-id="<?= $exercise['id'] ?>" data-title="<?= htmlspecialchars($exercise['title']) ?>" data-duration="<?= $exercise['duration'] ?? 0 ?>" style="padding: 0.5rem; border-bottom: 1px solid #eee; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                            <span><?= htmlspecialchars($exercise['title']) ?></span>
                            <span class="btn btn-sm btn-outline">+</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div>
            <h3>Geselecteerde Oefeningen</h3>
            <div class="card" id="selected-list" style="min-height: 200px;">
                <p class="text-muted" id="empty-msg" style="<?= !empty($currentExercises) ? 'display: none;' : '' ?>">Klik op een oefening om toe te voegen.</p>
                <?php foreach ($currentExercises as $ex): ?>
                    <div class="selected-item" style="padding: 0.5rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                        <span style="flex-grow: 1;"><?= htmlspecialchars($ex['title']) ?></span>
                        <input type="hidden" name="exercises[]" value="<?= $ex['id'] ?>">
                        <input type="number" name="durations[]" value="<?= $ex['training_duration'] ?? '' ?>" style="width: 60px; padding: 0.25rem;" placeholder="min">
                        <span class="btn btn-sm btn-outline remove-btn" style="color: red; border-color: red;">X</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const libraryList = document.getElementById('library-list');
    const selectedList = document.getElementById('selected-list');
    const emptyMsg = document.getElementById('empty-msg');
    const searchInput = document.getElementById('search-exercises');
    const form = document.getElementById('training-form');

    // Search functionality
    searchInput.addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        const items = libraryList.querySelectorAll('.exercise-item');
        items.forEach(item => {
            const title = item.dataset.title.toLowerCase();
            item.style.display = title.includes(term) ? 'flex' : 'none';
        });
    });

    // Add exercise
    libraryList.addEventListener('click', function(e) {
        const item = e.target.closest('.exercise-item');
        if (!item) return;

        const id = item.dataset.id;
        const title = item.dataset.title;
        const defaultDuration = item.dataset.duration;

        addExerciseToTraining(id, title, defaultDuration);
    });

    // Remove exercise
    selectedList.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-btn')) {
            e.target.closest('.selected-item').remove();
            checkEmpty();
        }
    });

    function addExerciseToTraining(id, title, duration) {
        if (emptyMsg) emptyMsg.style.display = 'none';

        const div = document.createElement('div');
        div.className = 'selected-item';
        div.style.padding = '0.5rem';
        div.style.borderBottom = '1px solid #eee';
        div.style.display = 'flex';
        div.style.justifyContent = 'space-between';
        div.style.alignItems = 'center';
        div.style.gap = '1rem';

        div.innerHTML = `
            <span style="flex-grow: 1;">${title}</span>
            <input type="hidden" name="exercises[]" value="${id}">
            <input type="number" name="durations[]" value="${duration}" style="width: 60px; padding: 0.25rem;" placeholder="min">
            <span class="btn btn-sm btn-outline remove-btn" style="color: red; border-color: red;">X</span>
        `;

        selectedList.appendChild(div);
    }

    function checkEmpty() {
        if (selectedList.children.length === 0 || (selectedList.children.length === 1 && selectedList.children[0].id === 'empty-msg')) {
            if (emptyMsg) emptyMsg.style.display = 'block';
        }
    }
});
</script>


