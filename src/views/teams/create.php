<div class="header-actions">
    <h1>Nieuw Team</h1>
    <a href="/account/teams" class="btn btn-outline">Terug</a>
</div>

<div class="card">
    <p>Maak een nieuw team aan waar jij de coach van bent.</p>
    
    <form method="POST" action="/team/create" style="max-width: 400px;">
        <?= Csrf::renderInput() ?>
        
        <div class="form-group">
            <label for="club">Club (optioneel)</label>
            <select id="club" name="club" class="form-control">
                <option value="">-- Selecteer Club --</option>
                <?php if (!empty($clubs)): ?>
                    <?php foreach ($clubs as $club): ?>
                        <option value="<?= e($club['name']) ?>"><?= e($club['name']) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="season">Seizoen (optioneel)</label>
            <select id="season" name="season" class="form-control">
                <option value="">-- Selecteer Seizoen --</option>
                <?php if (!empty($seasons)): ?>
                    <?php foreach ($seasons as $season): ?>
                        <option value="<?= e($season['name']) ?>"><?= e($season['name']) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="name">Team naam</label>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="text" id="name" name="name" required placeholder="Bijv. JO11-1" class="form-control">
                <button type="submit" class="btn-icon" title="Opslaan">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                </button>
            </div>
        </div>
    </form>
</div>
