<div class="app-bar">
    <div class="app-bar-start">
        <a href="/players" class="btn-icon-round" title="Terug" aria-label="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Speler bewerken</h1>
    </div>
</div>

<div class="tb-card tb-entity-card">
    <form action="/players/update" method="POST" class="tb-entity-form">
        <?= Csrf::renderInput() ?>
        <input type="hidden" name="id" value="<?= $player['id'] ?>">

        <div class="form-group">
            <label for="name">Naam</label>
            <input type="text" id="name" name="name" value="<?= e($player['name']) ?>" required>
        </div>

        <div class="tb-form-actions">
            <button type="submit" class="tb-button tb-button--primary">Wijzigingen opslaan</button>
            <a href="/players" class="tb-button tb-button--secondary">Annuleren</a>
        </div>
    </form>
</div>
