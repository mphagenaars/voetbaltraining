<div class="app-bar">
    <div class="app-bar-start">
        <a href="/" class="btn-icon-round" title="Terug" aria-label="Terug">
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
    <div class="card tb-team-empty-card">
        <p>Je bent nog geen lid van een team.</p>
    </div>
<?php else: ?>

    <?php if (!empty($activeTeams)): ?>
        <section class="tb-team-section">
            <h2 class="tb-team-section-title">Actieve teams</h2>
            <div class="tb-team-list">
                <?php foreach ($activeTeams as $team): ?>
                    <?php
                    $roleParts = [];
                    if (!empty($team['is_coach'])) {
                        $roleParts[] = 'Coach';
                    }
                    if (!empty($team['is_trainer'])) {
                        $roleParts[] = 'Trainer';
                    }
                    $roleString = !empty($roleParts) ? implode(' & ', $roleParts) : 'Lid';
                    $teamMeta = implode(' • ', array_filter([
                        (string)($team['club'] ?? ''),
                        (string)($team['season'] ?? ''),
                    ]));
                    $isCurrent = isset($_SESSION['current_team']) && (int)$_SESSION['current_team']['id'] === (int)$team['id'];
                    ?>
                    <article class="card tb-team-card<?= $isCurrent ? ' tb-team-card--current' : '' ?>">
                        <div class="tb-team-card-main">
                            <?php if (!empty($team['logo_path'])): ?>
                                <img src="/<?= e($team['logo_path']) ?>" alt="Clublogo van <?= e($team['name']) ?>" class="tb-team-logo">
                            <?php endif; ?>

                            <div class="tb-team-card-content">
                                <div class="tb-team-card-title-row">
                                    <h3 class="tb-team-card-title"><?= e($team['name']) ?></h3>
                                    <?php if ($isCurrent): ?>
                                        <span class="tb-team-current-pill">Actief team</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($teamMeta !== ''): ?>
                                    <p class="tb-team-card-meta"><?= e($teamMeta) ?></p>
                                <?php endif; ?>

                                <p class="tb-team-card-role"><?= e($roleString) ?></p>
                                <p class="tb-team-card-code">Code: <code><?= e($team['invite_code']) ?></code></p>
                            </div>
                        </div>

                        <div class="tb-team-card-actions">
                            <?php if (!$isCurrent): ?>
                                <form method="POST" action="/team/select" class="tb-team-action-form">
                                    <?= Csrf::renderInput() ?>
                                    <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                                    <input type="hidden" name="team_name" value="<?= e($team['name']) ?>">
                                    <input type="hidden" name="team_invite_code" value="<?= e($team['invite_code']) ?>">
                                    <button type="submit" class="tb-button tb-button--primary tb-button--sm">Selecteer</button>
                                </form>
                            <?php else: ?>
                                <span class="tb-team-current-icon" title="Huidig team" aria-label="Huidig team">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                </span>
                            <?php endif; ?>

                            <form method="POST" action="/account/teams/toggle-visibility" class="tb-team-action-form">
                                <?= Csrf::renderInput() ?>
                                <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                                <input type="hidden" name="is_hidden" value="1">
                                <button type="submit" class="tb-icon-button" title="Verbergen op dashboard" aria-label="Verbergen op dashboard">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($hiddenTeams)): ?>
        <section class="tb-team-section">
            <h2 class="tb-team-section-title tb-team-section-title--muted">Verborgen teams</h2>
            <div class="tb-team-list">
                <?php foreach ($hiddenTeams as $team): ?>
                    <?php
                    $roleParts = [];
                    if (!empty($team['is_coach'])) {
                        $roleParts[] = 'Coach';
                    }
                    if (!empty($team['is_trainer'])) {
                        $roleParts[] = 'Trainer';
                    }
                    $roleString = !empty($roleParts) ? implode(' & ', $roleParts) : 'Lid';
                    $teamMeta = implode(' • ', array_filter([
                        (string)($team['club'] ?? ''),
                        (string)($team['season'] ?? ''),
                    ]));
                    ?>
                    <article class="card tb-team-card tb-team-card--hidden">
                        <div class="tb-team-card-main">
                            <?php if (!empty($team['logo_path'])): ?>
                                <img src="/<?= e($team['logo_path']) ?>" alt="Clublogo van <?= e($team['name']) ?>" class="tb-team-logo tb-team-logo--hidden">
                            <?php endif; ?>

                            <div class="tb-team-card-content">
                                <h3 class="tb-team-card-title"><?= e($team['name']) ?></h3>

                                <?php if ($teamMeta !== ''): ?>
                                    <p class="tb-team-card-meta"><?= e($teamMeta) ?></p>
                                <?php endif; ?>

                                <p class="tb-team-card-role"><?= e($roleString) ?></p>
                            </div>
                        </div>

                        <div class="tb-team-card-actions">
                            <form method="POST" action="/account/teams/toggle-visibility" class="tb-team-action-form">
                                <?= Csrf::renderInput() ?>
                                <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                                <input type="hidden" name="is_hidden" value="0">
                                <button type="submit" class="tb-icon-button" title="Zichtbaar maken" aria-label="Zichtbaar maken">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

<?php endif; ?>

<a href="/team/create" class="tb-fab tb-team-add-fab" title="Nieuw team" aria-label="Nieuw team">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
</a>
