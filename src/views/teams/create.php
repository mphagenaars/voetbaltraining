<div class="app-bar">
    <div class="app-bar-start">
        <a href="/account/teams" class="btn-icon-round" title="Terug" aria-label="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Nieuw Team</h1>
    </div>
</div>

<div class="tb-card tb-entity-card">
    <p>Maak een nieuw team aan waar jij de coach van bent.</p>

    <?php
    $competitionCategories = is_array($competitionCategories ?? null) ? $competitionCategories : [];
    $selectedClub = trim((string)($_POST['club'] ?? ''));
    $selectedSeason = trim((string)($_POST['season'] ?? ''));
    $teamNameValue = trim((string)($_POST['name'] ?? ''));
    $selectedCompetitionCategory = Team::normalizeCompetitionCategory((string)($_POST['competition_category'] ?? ''));
    if ($selectedCompetitionCategory === '' && $teamNameValue !== '') {
        $selectedCompetitionCategory = Team::inferCompetitionCategoryFromTeamName($teamNameValue);
    }
    ?>

    <form method="POST" action="/team/create" class="tb-entity-form">
        <?= Csrf::renderInput() ?>

        <div class="form-group">
            <label for="club">Club (optioneel)</label>
            <select id="club" name="club">
                <option value="">-- Selecteer Club --</option>
                <?php if (!empty($clubs)): ?>
                    <?php foreach ($clubs as $club): ?>
                        <option value="<?= e($club['name']) ?>" <?= $selectedClub === (string)$club['name'] ? 'selected' : '' ?>><?= e($club['name']) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="season">Seizoen (optioneel)</label>
            <select id="season" name="season">
                <option value="">-- Selecteer Seizoen --</option>
                <?php if (!empty($seasons)): ?>
                    <?php foreach ($seasons as $season): ?>
                        <option value="<?= e($season['name']) ?>" <?= $selectedSeason === (string)$season['name'] ? 'selected' : '' ?>><?= e($season['name']) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="competition_category">Leeftijdscategorie</label>
            <select id="competition_category" name="competition_category">
                <option value="">-- Afleiden uit teamnaam --</option>
                <?php foreach ($competitionCategories as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $selectedCompetitionCategory === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="name">Team naam</label>
            <input type="text" id="name" name="name" required placeholder="Bijv. JO11-1" value="<?= e($teamNameValue) ?>">
        </div>

        <div class="tb-form-actions">
            <button type="submit" class="tb-button tb-button--primary">Team aanmaken</button>
            <a href="/account/teams" class="tb-button tb-button--secondary">Annuleren</a>
        </div>
    </form>
</div>
