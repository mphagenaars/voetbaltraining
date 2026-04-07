<div class="app-bar">
    <div class="app-bar-start">
        <a href="/admin/users" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Teams van <?= e($user['name']) ?></h1>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="tb-alert tb-alert--success"><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="tb-alert tb-alert--danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Huidige Teams -->
    <div class="tb-card">
        <h2>Huidige Teams</h2>
        <?php if (empty($userTeams)): ?>
            <p class="text-muted">Deze gebruiker zit nog niet in een team.</p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($userTeams as $team): ?>
                    <li class="list-group-item tb-inline-row tb-inline-row--between">
                        <div>
                            <?= e($team['name']) ?>
                        </div>
                        
                        <div class="tb-inline-row tb-inline-role-options">
                            <form action="/admin/update-team-role" method="POST" class="tb-inline-row tb-no-margin">
                                <?= Csrf::renderInput() ?>
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                                
                                <label class="tb-checkbox-inline">
                                    <input type="checkbox" name="is_coach" value="1" <?= !empty($team['is_coach']) ? 'checked' : '' ?> onchange="this.form.submit()">
                                    Coach
                                </label>
                                <label class="tb-checkbox-inline">
                                    <input type="checkbox" name="is_trainer" value="1" <?= !empty($team['is_trainer']) ? 'checked' : '' ?> onchange="this.form.submit()">
                                    Trainer
                                </label>
                            </form>

                            <form action="/admin/remove-team-member" method="POST" class="tb-no-margin" onsubmit="return confirm('Weet je zeker dat je deze gebruiker uit het team wilt verwijderen?');">
                                <?= Csrf::renderInput() ?>
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                                <button type="submit" class="tb-icon-button tb-icon-button--danger" title="Verwijderen" aria-label="Verwijderen">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Toevoegen aan Team -->
    <div class="tb-card">
        <h2>Toevoegen aan Team</h2>
        <?php if (empty($availableTeams)): ?>
            <p class="text-muted">Geen teams beschikbaar om toe te voegen.</p>
        <?php else: ?>
            <form action="/admin/add-team-member" method="POST">
                <?= Csrf::renderInput() ?>
                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                
                <div class="form-group">
                    <label>Kies Team</label>
                    <select name="team_id" required class="form-control">
                        <option value="">-- Selecteer Team --</option>
                        <?php foreach ($availableTeams as $team): ?>
                            <option value="<?= $team['id'] ?>"><?= e($team['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Rollen</label>
                    <div class="tb-inline-role-options">
                        <label class="tb-checkbox-inline">
                            <input type="checkbox" name="is_coach" value="1" checked> Coach
                        </label>
                        <label class="tb-checkbox-inline">
                            <input type="checkbox" name="is_trainer" value="1"> Trainer
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="tb-button tb-button--primary btn-inline-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Toevoegen
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
