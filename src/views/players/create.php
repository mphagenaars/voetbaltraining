<div class="app-bar">
    <div class="app-bar-start">
        <a href="/players" class="btn-icon-round" title="Terug" aria-label="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Nieuwe Speler</h1>
    </div>
</div>

<div class="tb-card tb-entity-card">
    <p>Voeg een nieuwe speler toe aan <strong><?= e($_SESSION['current_team']['name']) ?></strong>.</p>

    <form method="POST" action="/players/create" class="tb-entity-form">
        <?= Csrf::renderInput() ?>

        <div class="form-group">
            <label for="name">Naam speler</label>
            <input type="text" id="name" name="name" required placeholder="Naam speler">
        </div>

        <div class="tb-form-actions">
            <button type="submit" class="tb-button tb-button--primary">Speler opslaan</button>
            <a href="/players" class="tb-button tb-button--secondary">Annuleren</a>
        </div>
    </form>
</div>
