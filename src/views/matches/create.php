<div class="container">
    <h1>Nieuwe Wedstrijd</h1>
    
    <form action="/matches/create" method="POST" class="form-vertical">
        <?= Csrf::renderInput() ?>
        <div class="form-group">
            <label for="opponent">Tegenstander:</label>
            <input type="text" id="opponent" name="opponent" required class="form-control">
        </div>

        <div class="form-group">
            <label for="date">Datum & Tijd:</label>
            <input type="datetime-local" id="date" name="date" required class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
        </div>

        <div class="form-group">
            <label>Locatie:</label>
            <div>
                <label><input type="radio" name="is_home" value="1" checked> Thuis</label>
                <label><input type="radio" name="is_home" value="0"> Uit</label>
            </div>
        </div>
        
        <div class="form-group">
            <label for="formation">Formatie:</label>
            <select id="formation" name="formation" class="form-control">
                <option value="6-vs-6">6 tegen 6</option>
                <option value="8-vs-8">8 tegen 8</option>
                <option value="11-vs-11">11 tegen 11</option>
            </select>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Aanmaken</button>
            <a href="/matches" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
