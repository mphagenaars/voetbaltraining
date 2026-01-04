<div class="app-bar">
    <div class="app-bar-start">
        <a href="/" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Mijn Teams</h1>
    </div>
</div>

<?php
$activeTeams = array_filter($teams, fn($t) => empty($t['is_hidden']));
$hiddenTeams = array_filter($teams, fn($t) => !empty($t['is_hidden']));
?>

<?php if (empty($teams)): ?>
    <div class="card">
        <p>Je bent nog geen lid van een team.</p>
    </div>
<?php else: ?>
    
    <?php if (!empty($activeTeams)): ?>
        <div style="display: grid; gap: 1rem; margin-bottom: 2rem;">
            <?php foreach ($activeTeams as $team): ?>
                <?php
                    $roleParts = [];
                    if (!empty($team['is_coach'])) $roleParts[] = 'Coach';
                    if (!empty($team['is_trainer'])) $roleParts[] = 'Trainer';
                    $roleString = implode(' & ', $roleParts);
                    
                    $isCurrent = isset($_SESSION['current_team']) && $_SESSION['current_team']['id'] === $team['id'];
                ?>
                
                <div class="card" style="padding: 0; display: flex; overflow: hidden; <?= $isCurrent ? 'border-color: var(--primary);' : '' ?>">
                    <?php if (!$isCurrent): ?>
                        <form method="POST" action="/team/select" style="flex: 1;">
                            <?= Csrf::renderInput() ?>
                            <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                            <input type="hidden" name="team_name" value="<?= e($team['name']) ?>">
                            <input type="hidden" name="team_invite_code" value="<?= e($team['invite_code']) ?>">
                            
                            <button type="submit" style="width: 100%; height: 100%; background: none; border: none; padding: 1.5rem; text-align: left; display: flex; align-items: flex-start; gap: 1rem; cursor: pointer;">
                                <?php if (!empty($team['logo_path'])): ?>
                                    <img src="/<?= e($team['logo_path']) ?>" alt="Club Logo" style="width: 48px; height: 48px; object-fit: contain; flex-shrink: 0;">
                                <?php endif; ?>
                                <div>
                                    <h3 style="margin-top: 0; font-size: 1.1rem; color: var(--text-color);"><?= e($team['name']) ?></h3>
                                    <?php if (!empty($team['club']) || !empty($team['season'])): ?>
                                        <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.25rem;">
                                            <?= e(implode(' • ', array_filter([$team['club'] ?? '', $team['season'] ?? '']))) ?>
                                        </p>
                                    <?php endif; ?>
                                    <p style="color: var(--text-color);"><?= e($roleString) ?></p>
                                    <div style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-muted);">
                                        Code: <code style="background: #e9ecef; padding: 2px 6px; border-radius: 4px;"><?= e($team['invite_code']) ?></code>
                                    </div>
                                </div>
                            </button>
                        </form>
                    <?php else: ?>
                        <div style="flex: 1; padding: 1.5rem; display: flex; align-items: flex-start; gap: 1rem;">
                            <?php if (!empty($team['logo_path'])): ?>
                                <img src="/<?= e($team['logo_path']) ?>" alt="Club Logo" style="width: 48px; height: 48px; object-fit: contain; flex-shrink: 0;">
                            <?php endif; ?>
                            <div>
                                <h3 style="color: var(--primary); margin-top: 0; font-size: 1.1rem;"><?= e($team['name']) ?></h3>
                                <?php if (!empty($team['club']) || !empty($team['season'])): ?>
                                    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.25rem;">
                                        <?= e(implode(' • ', array_filter([$team['club'] ?? '', $team['season'] ?? '']))) ?>
                                    </p>
                                <?php endif; ?>
                                <p><?= e($roleString) ?></p>
                                <div style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-muted);">
                                    Code: <code style="background: #e9ecef; padding: 2px 6px; border-radius: 4px;"><?= e($team['invite_code']) ?></code>
                                </div>
                            </div>
                            <span class="text-success" title="Huidig team" style="margin-left: auto;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div style="border-left: 1px solid var(--border-color); display: flex; align-items: center;">
                        <form method="POST" action="/account/teams/toggle-visibility" style="margin: 0; height: 100%;">
                            <?= Csrf::renderInput() ?>
                            <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                            <input type="hidden" name="is_hidden" value="1">
                            <button type="submit" style="background: none; border: none; padding: 0 1.5rem; height: 100%; cursor: pointer; color: var(--text-muted);" title="Verbergen op dashboard">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($hiddenTeams)): ?>
        <h2 style="color: var(--text-muted); font-size: 1.2rem; margin-bottom: 1rem;">Verborgen Teams</h2>
        <div style="display: grid; gap: 1rem; opacity: 0.7;">
            <?php foreach ($hiddenTeams as $team): ?>
                <?php
                    $roleParts = [];
                    if (!empty($team['is_coach'])) $roleParts[] = 'Coach';
                    if (!empty($team['is_trainer'])) $roleParts[] = 'Trainer';
                    $roleString = implode(' & ', $roleParts);
                ?>
                
                <div class="card" style="padding: 0; display: flex; overflow: hidden; background: var(--bg-secondary);">
                    <div style="flex: 1; padding: 1.5rem; display: flex; align-items: flex-start; gap: 1rem;">
                        <?php if (!empty($team['logo_path'])): ?>
                            <img src="/<?= e($team['logo_path']) ?>" alt="Club Logo" style="width: 48px; height: 48px; object-fit: contain; flex-shrink: 0; filter: grayscale(100%);">
                        <?php endif; ?>
                        <div>
                            <h3 style="margin-top: 0; font-size: 1.1rem; color: var(--text-muted);"><?= e($team['name']) ?></h3>
                            <?php if (!empty($team['club']) || !empty($team['season'])): ?>
                                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.25rem;">
                                    <?= e(implode(' • ', array_filter([$team['club'] ?? '', $team['season'] ?? '']))) ?>
                                </p>
                            <?php endif; ?>
                            <p style="color: var(--text-muted);"><?= e($roleString) ?></p>
                        </div>
                    </div>

                    <div style="border-left: 1px solid var(--border-color); display: flex; align-items: center;">
                        <form method="POST" action="/account/teams/toggle-visibility" style="margin: 0; height: 100%;">
                            <?= Csrf::renderInput() ?>
                            <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                            <input type="hidden" name="is_hidden" value="0">
                            <button type="submit" style="background: none; border: none; padding: 0 1.5rem; height: 100%; cursor: pointer; color: var(--text-muted);" title="Zichtbaar maken">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>

<a href="/team/create" class="fab" title="Nieuw Team">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
</a>
