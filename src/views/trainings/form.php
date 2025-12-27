<h1>Nieuwe Training</h1>

<form method="POST" id="training-form">
    <div class="card">
        <div class="form-group">
            <label for="title">Titel *</label>
            <input type="text" id="title" name="title" required>
        </div>

        <div class="form-group">
            <label for="description">Beschrijving</label>
            <textarea id="description" name="description" rows="3"></textarea>
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
                <p class="text-muted" id="empty-msg">Klik op een oefening om toe te voegen.</p>
            </div>
        </div>
    </div>

    <div style="margin-top: 2rem;">
        <button type="submit" class="btn">Training Opslaan</button>
        <a href="/trainings" class="btn btn-outline">Annuleren</a>
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


