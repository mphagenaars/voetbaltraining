<div class="app-bar">
    <div class="app-bar-start">
        <a href="/admin/teams" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Leden van <?= e($team['name']) ?></h1>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="tb-alert tb-alert--success"><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="tb-alert tb-alert--danger"><?= e($error) ?></div>
<?php endif; ?>

<?php $backUrl = '/admin/team-members?team_id=' . $team['id']; ?>

<div class="grid-2">
    <!-- Huidige leden -->
    <div class="tb-card">
        <h2>Huidige leden</h2>
        <?php if (empty($teamMembers)): ?>
            <p class="text-muted">Dit team heeft nog geen leden.</p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($teamMembers as $member): ?>
                    <li class="list-group-item tb-inline-row tb-inline-row--between">
                        <div>
                            <div><strong><?= e($member['name']) ?></strong></div>
                            <div class="tb-muted"><?= e($member['username']) ?></div>
                        </div>

                        <div class="tb-inline-row tb-inline-role-options">
                            <form action="/admin/update-team-role" method="POST" class="tb-inline-row tb-no-margin">
                                <?= Csrf::renderInput() ?>
                                <input type="hidden" name="user_id" value="<?= $member['id'] ?>">
                                <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                                <input type="hidden" name="redirect_to" value="<?= e($backUrl) ?>">

                                <label class="tb-checkbox-inline">
                                    <input type="checkbox" name="is_coach" value="1" <?= !empty($member['is_coach']) ? 'checked' : '' ?> onchange="this.form.submit()">
                                    Coach
                                </label>
                                <label class="tb-checkbox-inline">
                                    <input type="checkbox" name="is_trainer" value="1" <?= !empty($member['is_trainer']) ? 'checked' : '' ?> onchange="this.form.submit()">
                                    Trainer
                                </label>
                            </form>

                            <form action="/admin/remove-team-member" method="POST" class="tb-no-margin" onsubmit="return confirm('Weet je zeker dat je <?= e($member['name']) ?> uit dit team wilt verwijderen?');">
                                <?= Csrf::renderInput() ?>
                                <input type="hidden" name="user_id" value="<?= $member['id'] ?>">
                                <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                                <input type="hidden" name="redirect_to" value="<?= e($backUrl) ?>">
                                <button type="submit" class="btn-icon btn-icon-danger" title="Verwijderen uit team">
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

    <!-- Gebruiker toevoegen -->
    <div class="tb-card">
        <h2>Gebruiker toevoegen</h2>
        <?php if (empty($availableUsers)): ?>
            <p class="text-muted">Alle gebruikers zijn al lid van dit team.</p>
        <?php else: ?>
            <form action="/admin/add-team-member" method="POST">
                <?= Csrf::renderInput() ?>
                <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                <input type="hidden" name="redirect_to" value="<?= e($backUrl) ?>">

                <div class="form-group">
                    <label>Kies gebruiker</label>
                    <select name="user_id" required class="form-control">
                        <option value="">-- Selecteer gebruiker --</option>
                        <?php foreach ($availableUsers as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= e($user['name']) ?> (<?= e($user['username']) ?>)</option>
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
