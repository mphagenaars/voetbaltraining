<div class="header-actions">
    <h1>Oefenstof Opties Beheren</h1>
    <a href="/admin" class="btn btn-outline">Terug naar Admin</a>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php
$categories = [
    'team_task' => 'Teamtaken',
    'objective' => 'Doelstellingen',
    'football_action' => 'Voetbalhandelingen'
];

$placeholders = [
    'team_task' => 'teamtaak',
    'objective' => 'doelstelling',
    'football_action' => 'handeling'
];
?>

<div class="grid-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">

    <?php foreach ($categories as $key => $label): ?>
    <div class="card">
        <h2><?= $label ?></h2>
        <ul class="list-group sortable-list" data-category="<?= $key ?>" style="margin-bottom: 1rem; list-style: none; padding: 0;">
            <?php foreach ($options[$key] as $opt): ?>
                <li class="list-group-item sortable-item" id="option-<?= $opt['id'] ?>" data-id="<?= $opt['id'] ?>" draggable="true" style="padding: 0.5rem 0; border-bottom: 1px solid #eee; cursor: grab; background: #fff;">
                    <div class="display-mode" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 0;">
                            <span class="drag-handle" style="cursor: grab; color: #ccc; flex-shrink: 0;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="12" r="1"></circle><circle cx="9" cy="5" r="1"></circle><circle cx="9" cy="19" r="1"></circle><circle cx="15" cy="12" r="1"></circle><circle cx="15" cy="5" r="1"></circle><circle cx="15" cy="19" r="1"></circle></svg>
                            </span>
                            <span style="word-break: break-word;"><?= e($opt['name']) ?></span>
                        </div>
                        <div class="actions" style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                            <button type="button" class="btn-icon" onclick="toggleEdit(<?= $opt['id'] ?>)" title="Bewerken">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                            <form action="/admin/options/delete" method="POST" onsubmit="return confirm('Weet je zeker dat je deze optie wilt verwijderen?');" style="margin: 0;">
                                <?= Csrf::renderInput('/admin/options') ?>
                                <input type="hidden" name="id" value="<?= $opt['id'] ?>">
                                <button type="submit" class="btn-icon btn-icon-danger" title="Verwijderen">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    <form action="/admin/options/update" method="POST" class="edit-mode" style="display: none; width: 100%; gap: 0.5rem; align-items: center;">
                        <?= Csrf::renderInput('/admin/options') ?>
                        <input type="hidden" name="id" value="<?= $opt['id'] ?>">
                        <input type="text" name="name" value="<?= e($opt['name']) ?>" class="form-control" style="flex: 1;">
                        <button type="submit" class="btn btn-sm btn-outline" title="Opslaan">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="toggleEdit(<?= $opt['id'] ?>)" title="Annuleren">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <form action="/admin/options/create" method="POST" style="display: flex; gap: 0.5rem;">
            <?= Csrf::renderInput('/admin/options') ?>
            <input type="hidden" name="category" value="<?= $key ?>">
            <input type="text" name="name" class="form-control" placeholder="Nieuwe <?= $placeholders[$key] ?>" required style="flex: 1;">
            <button type="submit" class="btn btn-outline">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            </button>
        </form>
    </div>
    <?php endforeach; ?>

</div>

<script>
function toggleEdit(id) {
    const item = document.getElementById('option-' + id);
    const displayMode = item.querySelector('.display-mode');
    const editMode = item.querySelector('.edit-mode');
    
    if (displayMode.style.display === 'none') {
        displayMode.style.display = 'flex';
        editMode.style.display = 'none';
    } else {
        displayMode.style.display = 'none';
        editMode.style.display = 'flex';
        editMode.querySelector('input[name="name"]').focus();
    }
}

// Drag and Drop Logic
document.addEventListener('DOMContentLoaded', () => {
    const draggables = document.querySelectorAll('.sortable-item');
    const containers = document.querySelectorAll('.sortable-list');

    draggables.forEach(draggable => {
        draggable.addEventListener('dragstart', () => {
            draggable.classList.add('dragging');
        });

        draggable.addEventListener('dragend', () => {
            draggable.classList.remove('dragging');
            saveOrder(draggable.closest('.sortable-list'));
        });
    });

    containers.forEach(container => {
        container.addEventListener('dragover', e => {
            e.preventDefault();
            const afterElement = getDragAfterElement(container, e.clientY);
            const draggable = document.querySelector('.dragging');
            if (draggable) {
                if (afterElement == null) {
                    container.appendChild(draggable);
                } else {
                    container.insertBefore(draggable, afterElement);
                }
            }
        });
    });
});

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.sortable-item:not(.dragging)')];

    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

function saveOrder(container) {
    const items = container.querySelectorAll('.sortable-item');
    const ids = Array.from(items).map(item => item.dataset.id);
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;

    const formData = new FormData();
    ids.forEach(id => formData.append('ids[]', id));
    formData.append('csrf_token', csrfToken);

    fetch('/admin/options/reorder', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Error saving order:', data.error);
            alert('Fout bij opslaan volgorde.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
</script>
