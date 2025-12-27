<div class="container">
    <h1>Speler bewerken</h1>
    
    <div class="card">
        <form action="/players/update" method="POST">
            <input type="hidden" name="id" value="<?= $player['id'] ?>">
            
            <div class="form-group">
                <label for="name">Naam</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($player['name']) ?>" required class="form-control">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Opslaan</button>
                <a href="/players" class="btn btn-secondary">Annuleren</a>
            </div>
        </form>
    </div>
</div>


