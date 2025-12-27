<div class="container">
    <h1>Nieuwe Opstelling</h1>
    
    <form action="/lineups/create" method="POST" class="form-vertical">
        <?= Csrf::renderInput() ?>
        <div class="form-group">
            <label for="name">Naam Opstelling:</label>
            <input type="text" id="name" name="name" required class="form-control">
        </div>
        
        <div class="form-group">
            <label for="formation">Formatie:</label>
            <select id="formation" name="formation" class="form-control">
                <option value="6-vs-6">6 tegen 6 op een kwart veld</option>
                <option value="8-vs-8">8 tegen 8 op een half veld</option>
                <option value="11-vs-11">11 tegen 11 op een heel veld</option>
            </select>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Aanmaken</button>
            <a href="/lineups" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>


