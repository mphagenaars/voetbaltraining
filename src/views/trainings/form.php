<?php
$isEdit = isset($training);
$formTitle = $isEdit ? 'Training Bewerken' : 'Nieuwe Training';
$titleValue = $isEdit ? $training['title'] : '';
$descValue = $isEdit ? $training['description'] : '';
$dateValue = $isEdit ? ($training['training_date'] ?? '') : date('Y-m-d');
$currentExercises = $isEdit ? ($training['exercises'] ?? []) : [];
?>
<style>
    .selected-item {
        transition: transform 0.1s ease, box-shadow 0.1s ease;
    }
    .selected-item.dragging {
        opacity: 0.5;
        background: #f8f9fa;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        position: relative;
        z-index: 10;
    }
    .drag-handle {
        cursor: grab;
        touch-action: none;
        color: #adb5bd;
        padding: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .drag-handle:active {
        cursor: grabbing;
        color: var(--primary);
    }
    .drag-handle:hover {
        color: var(--text-main);
    }
</style>
<div class="app-bar">
    <div class="app-bar-start">
        <a href="/trainings" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title"><?= $formTitle ?></h1>
    </div>
</div>

<form method="POST" id="training-form">
    <?= Csrf::renderInput() ?>
    <div class="card">
        <div style="display: flex; gap: 1rem; align-items: flex-end; margin-bottom: 1rem;">
            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                <label for="training_date">Datum *</label>
                <input type="date" id="training_date" name="training_date" required value="<?= e($dateValue) ?>">
            </div>
            <button type="submit" class="btn-icon" title="Opslaan" style="margin-bottom: 4px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            </button>
        </div>

        <div class="form-group">
            <label for="description">Beschrijving</label>
            <textarea id="description" name="description" rows="3"><?= e($descValue) ?></textarea>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
        <div>
            <h3>Bibliotheek</h3>
            <div class="card" style="max-height: 600px; overflow-y: auto;">
                <input type="text" id="search-exercises" placeholder="Zoek oefening..." style="margin-bottom: 1rem; width: 100%;">
                <div id="library-list">
                    <?php foreach ($allExercises as $exercise): ?>
                        <div class="exercise-item" data-id="<?= $exercise['id'] ?>" data-title="<?= e($exercise['title']) ?>" data-duration="<?= $exercise['duration'] ?? 0 ?>" style="padding: 0.5rem; border-bottom: 1px solid #eee; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                            <span><?= e($exercise['title']) ?></span>
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
                    <div class="selected-item" style="padding: 0.5rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; gap: 1rem; background: #fff;">
                        <div class="drag-handle" title="Sleep om te sorteren">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                        </div>
                        <span style="flex-grow: 1;"><?= e($ex['title']) ?></span>
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

    // Handle clicks for remove
    selectedList.addEventListener('click', function(e) {
        const target = e.target;
        const item = target.closest('.selected-item');
        if (!item) return;

        if (target.classList.contains('remove-btn') || target.closest('.remove-btn')) {
            item.remove();
            checkEmpty();
        }
    });

    /* --- Drag and Drop Logic (Mouse & Touch) --- */
    let dragSrc = null;

    // Start handlers
    selectedList.addEventListener('mousedown', handleDragStart);
    selectedList.addEventListener('touchstart', handleDragStart, {passive: false});

    function handleDragStart(e) {
        const handle = e.target.closest('.drag-handle');
        if (!handle) return;
        
        const item = handle.closest('.selected-item');
        if (!item) return;

        // Prevent default touch actions (scrolling) only when touching the handle
        if (e.type === 'touchstart') e.preventDefault();

        dragSrc = item;
        item.classList.add('dragging');

        // Add global move/end listeners
        document.addEventListener('mousemove', handleDragMove);
        document.addEventListener('touchmove', handleDragMove, {passive: false});
        document.addEventListener('mouseup', handleDragEnd);
        document.addEventListener('touchend', handleDragEnd);
    }

    function handleDragMove(e) {
        if (!dragSrc) return;
        
        // Prevent accidental scrolling/refresh
        e.preventDefault();

        const clientY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;
        
        // Get all items except the one being dragged
        const siblings = [...selectedList.querySelectorAll('.selected-item:not(.dragging)')];
        
        // Find the sibling halfway point that we are crossing
        const nextSibling = siblings.find(sibling => {
            const box = sibling.getBoundingClientRect();
            // If cursor is above the vertical center of this sibling, insert before it
            return clientY <= box.top + box.height / 2;
        });

        // DOM operation: moving the element automatically reorders it in the document flow
        if (nextSibling) {
            selectedList.insertBefore(dragSrc, nextSibling);
        } else {
            selectedList.appendChild(dragSrc);
        }
    }

    function handleDragEnd(e) {
        if (dragSrc) {
            dragSrc.classList.remove('dragging');
            dragSrc = null;
        }
        // Clean up
        document.removeEventListener('mousemove', handleDragMove);
        document.removeEventListener('touchmove', handleDragMove);
        document.removeEventListener('mouseup', handleDragEnd);
        document.removeEventListener('touchend', handleDragEnd);
    }

    function addExerciseToTraining(id, title, duration) {
        if (emptyMsg) emptyMsg.style.display = 'none';

        const div = document.createElement('div');
        div.className = 'selected-item';
        div.style.cssText = 'padding: 0.5rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; gap: 1rem; background: #fff;';

        div.innerHTML = `
            <div class="drag-handle" title="Sleep om te sorteren">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </div>
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


