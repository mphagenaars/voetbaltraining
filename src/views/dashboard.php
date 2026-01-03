<h1>Dashboard</h1>

<h2>Oefenstof</h2>
<a href="/exercises" class="action-card">
    <div>
        <h3>Database</h3>
        <p>Toegang tot de centrale database met oefenstof.</p>
    </div>
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
</a>

<?php if (isset($_SESSION['current_team'])): ?>
    <div style="margin-bottom: 1.5rem;">
        <div style="margin-bottom: 1rem; display: flex; align-items: center; gap: 1rem;">
            <?php if (!empty($_SESSION['current_team']['logo_path'])): ?>
                <img src="/<?= e($_SESSION['current_team']['logo_path']) ?>" alt="Club Logo" style="width: 48px; height: 48px; object-fit: contain;">
            <?php endif; ?>
            <div>
                <h2 style="margin: 0;">Huidig team: <?= e($_SESSION['current_team']['name']) ?></h2>
                <div style="display: flex; gap: 1.5rem; color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
                    <span>Rol: <?= e($_SESSION['current_team']['role']) ?></span>
                    <?php if (!empty($_SESSION['current_team']['season'])): ?>
                        <span>Seizoen: <?= e($_SESSION['current_team']['season']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div style="display: grid; gap: 0.5rem;">
            <a href="/players" class="action-card" style="margin-bottom: 0;">
                <div>
                    <h3>Spelers</h3>
                    <p>Beheer de spelerslijst en gegevens.</p>
                </div>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
            <a href="/trainings" class="action-card" style="margin-bottom: 0;">
                <div>
                    <h3>Trainingen</h3>
                    <p>Plan en bekijk trainingen.</p>
                </div>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
            <a href="/matches" class="action-card" style="margin-bottom: 0;">
                <div>
                    <h3>Wedstrijden</h3>
                    <p>Wedstrijdschema en uitslagen.</p>
                </div>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
        </div>
    </div>
<?php else: ?>
    <div style="margin-bottom: 2rem;">
        <h2>Kies een team</h2>
        <?php if (empty($teams)): ?>
            <div class="card">
                <p>Je bent nog geen lid van een team.</p>
                <a href="/team/create" class="btn">Nieuw Team Maken</a>
            </div>
        <?php else: ?>
            <div class="grid-2">
                <?php foreach ($teams as $team): ?>
                    <?php
                        $roleParts = [];
                        if (!empty($team['is_coach'])) $roleParts[] = 'Coach';
                        if (!empty($team['is_trainer'])) $roleParts[] = 'Trainer';
                        $roleString = implode(' & ', $roleParts);
                    ?>
                    <form method="POST" action="/team/select" style="display: block; width: 100%;">
                        <?= Csrf::renderInput() ?>
                        <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                        <input type="hidden" name="team_name" value="<?= e($team['name']) ?>">
                        <input type="hidden" name="team_invite_code" value="<?= e($team['invite_code']) ?>">
                        
                        <button type="submit" class="action-card" style="height: 100%;">
                            <div style="display: flex; align-items: flex-start; gap: 1rem; text-align: left; flex: 1;">
                                <?php if (!empty($team['logo_path'])): ?>
                                    <img src="/<?= e($team['logo_path']) ?>" alt="Club Logo" style="width: 40px; height: 40px; object-fit: contain; flex-shrink: 0;">
                                <?php endif; ?>
                                <div>
                                    <h3 style="margin-top: 0; font-size: 1.1rem;"><?= e($team['name']) ?></h3>
                                    <?php if (!empty($team['club']) || !empty($team['season'])): ?>
                                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.25rem;">
                                            <?= e(implode(' • ', array_filter([$team['club'] ?? '', $team['season'] ?? '']))) ?>
                                        </p>
                                    <?php endif; ?>
                                    <p style="font-size: 0.9rem;"><?= e($roleString) ?></p>
                                </div>
                            </div>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>


