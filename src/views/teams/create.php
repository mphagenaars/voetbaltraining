<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="dashboard-header">
    <h1>Nieuw Team</h1>
    <a href="/" class="btn btn-outline">Terug</a>
</div>

<div class="card">
    <p>Maak een nieuw team aan waar jij de coach van bent.</p>
    
    <form method="POST" action="/team/create" style="max-width: 400px;">
        <?= Csrf::renderInput() ?>
        
        <div class="form-group">
            <label for="name">Team naam</label>
            <input type="text" id="name" name="name" required placeholder="Bijv. JO11-1" class="form-control">
        </div>
        
        <div style="margin-top: 1rem;">
            <button type="submit" class="btn">Aanmaken</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
