<div class="header-actions">
    <h1>Team Bewerken</h1>
    <a href="/admin/teams" class="btn btn-outline">Terug</a>
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
            <button type="submit" class="btn">Opslaan</button>
        </div>
    </form>
</div>
