<div class="app-bar">
    <div class="app-bar-start">
        <h1 class="app-bar-title">Dashboard</h1>
    </div>
</div>

<?php $actionCardChevron = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>'; ?>

<div class="tb-dashboard-sections">
    <section class="tb-dashboard-section">
        <h2 class="tb-dashboard-heading">Oefenstof</h2>
        <a href="/exercises" class="action-card tb-dashboard-link">
            <div>
                <h3>Database</h3>
                <p>Toegang tot de centrale database met oefenstof.</p>
            </div>
            <?= $actionCardChevron ?>
        </a>
    </section>

    <?php if (isset($_SESSION['current_team'])): ?>
        <section class="tb-dashboard-section card tb-dashboard-team-card">
            <div class="tb-dashboard-team-header">
                <?php if (!empty($_SESSION['current_team']['logo_path'])): ?>
                    <img src="/<?= e($_SESSION['current_team']['logo_path']) ?>" alt="Club Logo" class="tb-dashboard-team-logo">
                <?php endif; ?>
                <div>
                    <h2 class="tb-dashboard-team-title">Huidig team: <?= e($_SESSION['current_team']['name']) ?></h2>
                    <div class="tb-dashboard-team-meta">
                        <span>Rol: <?= e($_SESSION['current_team']['role']) ?></span>
                        <?php if (!empty($_SESSION['current_team']['season'])): ?>
                            <span>Seizoen: <?= e($_SESSION['current_team']['season']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tb-dashboard-link-list">
                <a href="/players" class="action-card tb-dashboard-link">
                    <div>
                        <h3>Spelers</h3>
                        <p>Beheer de spelerslijst en gegevens.</p>
                    </div>
                    <?= $actionCardChevron ?>
                </a>
                <a href="/trainings" class="action-card tb-dashboard-link">
                    <div>
                        <h3>Trainingen</h3>
                        <p>Plan en bekijk trainingen.</p>
                    </div>
                    <?= $actionCardChevron ?>
                </a>
                <a href="/tactics" class="action-card tb-dashboard-link">
                    <div>
                        <h3>Tactiekstudio</h3>
                        <p>Gebruik het tactiekbord.</p>
                    </div>
                    <?= $actionCardChevron ?>
                </a>
                <a href="/matches" class="action-card tb-dashboard-link">
                    <div>
                        <h3>Wedstrijden</h3>
                        <p>Wedstrijdschema en uitslagen.</p>
                    </div>
                    <?= $actionCardChevron ?>
                </a>
                <a href="/matches/reports" class="action-card tb-dashboard-link">
                    <div>
                        <h3>Rapportage</h3>
                        <p>Statistieken per speler.</p>
                    </div>
                    <?= $actionCardChevron ?>
                </a>
            </div>
        </section>
    <?php else: ?>
        <section class="tb-dashboard-section">
            <h2 class="tb-dashboard-heading">Kies een team</h2>
            <?php if (empty($teams)): ?>
                <div class="card tb-dashboard-empty-card">
                    <p>Je bent nog geen lid van een team.</p>
                    <p class="text-muted">Ga naar Mijn Teams om een team aan te maken of te selecteren.</p>
                </div>
            <?php else: ?>
                <div class="tb-dashboard-team-select-grid">
                    <?php foreach ($teams as $team): ?>
                        <?php
                            $roleParts = [];
                            if (!empty($team['is_coach'])) $roleParts[] = 'Coach';
                            if (!empty($team['is_trainer'])) $roleParts[] = 'Trainer';
                            $roleString = implode(' & ', $roleParts);
                            $teamMeta = implode(' • ', array_filter([$team['club'] ?? '', $team['season'] ?? '']));
                        ?>
                        <form method="POST" action="/team/select" class="tb-dashboard-team-select-form">
                            <?= Csrf::renderInput() ?>
                            <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                            <input type="hidden" name="team_name" value="<?= e($team['name']) ?>">
                            <input type="hidden" name="team_invite_code" value="<?= e($team['invite_code']) ?>">

                            <button type="submit" class="action-card tb-dashboard-team-select">
                                <div class="tb-dashboard-team-select-main">
                                    <?php if (!empty($team['logo_path'])): ?>
                                        <img src="/<?= e($team['logo_path']) ?>" alt="Club Logo" class="tb-dashboard-team-select-logo">
                                    <?php endif; ?>
                                    <div>
                                        <h3 class="tb-dashboard-team-select-title"><?= e($team['name']) ?></h3>
                                        <?php if ($teamMeta !== ''): ?>
                                            <p class="tb-dashboard-team-select-meta"><?= e($teamMeta) ?></p>
                                        <?php endif; ?>
                                        <p class="tb-dashboard-team-select-role"><?= e($roleString) ?></p>
                                    </div>
                                </div>
                                <?= $actionCardChevron ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
