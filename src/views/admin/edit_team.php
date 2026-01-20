<div class="app-bar">
    <div class="app-bar-start">
        <a href="/admin/teams" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Team Bewerken</h1>
    </div>
</div>

<div class="card">
    <form action="/admin/teams/update" method="POST" style="max-width: 500px;">
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
            <button type="submit" class="btn btn-primary">Opslaan</button>
        </div>
    </form>
</div>
