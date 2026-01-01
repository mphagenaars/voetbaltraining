<div class="header-actions">
    <h1>Nieuwe Speler</h1>
    <a href="/players" class="btn btn-outline">Terug</a>
</div>

<div class="card">
    <p>Voeg een nieuwe speler toe aan <strong><?= e($_SESSION['current_team']['name']) ?></strong>.</p>
    
    <form method="POST" action="/players/create" style="max-width: 400px;">
        <?= Csrf::renderInput() ?>
        
        <div class="form-group">
            <label for="name">Naam speler</label>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="text" id="name" name="name" required placeholder="Naam speler">
                <button type="submit" class="btn-icon" title="Opslaan">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                </button>
            </div>
        </div>
    </form>
</div>
