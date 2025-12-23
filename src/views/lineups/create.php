<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="container">
    <h1>Nieuwe Opstelling</h1>
    
    <form action="/lineups/create" method="POST" class="form-vertical">
        <div class="form-group">
            <label for="name">Naam Opstelling:</label>
            <input type="text" id="name" name="name" required class="form-control">
        </div>
        
        <div class="form-group">
            <label for="formation">Formatie:</label>
            <select id="formation" name="formation" class="form-control">
                <option value="4-3-3">4-3-3</option>
                <option value="4-4-2">4-4-2</option>
                <option value="3-4-3">3-4-3</option>
                <option value="5-3-2">5-3-2</option>
            </select>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Aanmaken</button>
            <a href="/lineups" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
