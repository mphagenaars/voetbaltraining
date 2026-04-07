<div class="app-bar">
    <div class="app-bar-start">
        <a href="/admin/teams" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Team Bewerken</h1>
    </div>
</div>

<div class="tb-card">
    <form action="/admin/teams/update" method="POST" class="tb-admin-form-limited">
        <?= Csrf::renderInput() ?>
        <input type="hidden" name="id" value="<?= $team['id'] ?>">

        <div class="form-group">
            <label for="name">Team Naam</label>
            <input type="text" id="name" name="name" value="<?= e($team['name']) ?>" required class="form-control">
        </div>

        <div class="form-group">
            <label for="club">Club</label>
            <select id="club" name="club" class="form-control">
                <option value="">-- Geen Club --</option>
                <?php foreach ($clubs as $club): ?>
                    <option value="<?= e($club['name']) ?>" <?= $team['club'] === $club['name'] ? 'selected' : '' ?>>
                        <?= e($club['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="season">Seizoen</label>
            <select id="season" name="season" class="form-control">
                <option value="">-- Geen Seizoen --</option>
                <?php foreach ($seasons as $season): ?>
                    <option value="<?= e($season['name']) ?>" <?= $team['season'] === $season['name'] ? 'selected' : '' ?>>
                        <?= e($season['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="tb-button tb-button--primary btn-inline-icon" title="Opslaan">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                Opslaan
            </button>
        </div>
    </form>
</div>
