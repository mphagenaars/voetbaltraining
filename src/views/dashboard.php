<h1>Dashboard</h1>

<a href="/exercises" class="action-card">
    <div>
        <h2>Oefenstof</h2>
        <p>Toegang tot de centrale database met oefenstof.</p>
    </div>
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
</a>

<?php if (isset($_SESSION['current_team'])): ?>
    <div style="margin-bottom: 1.5rem;">
        <div style="margin-bottom: 1rem;">
            <h2 style="font-size: 1.25rem; margin-bottom: 0.25rem;">Huidig team: <?= e($_SESSION['current_team']['name']) ?></h2>
            <div style="display: flex; gap: 1.5rem; color: var(--text-muted); font-size: 0.9rem;">
                <span>Rol: <?= e($_SESSION['current_team']['role']) ?></span>
                <span>Code: <code style="background: #e9ecef; padding: 2px 6px; border-radius: 4px;"><?= e($_SESSION['current_team']['invite_code']) ?></code></span>
            </div>
        </div>
        
        <div style="display: grid; gap: 0.5rem;">
            <a href="/players" class="action-card" style="margin-bottom: 0; padding: 0.75rem;">
                <span style="font-weight: 500;">Spelers</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
            <a href="/trainings" class="action-card" style="margin-bottom: 0; padding: 0.75rem;">
                <span style="font-weight: 500;">Trainingen</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
            <a href="/matches" class="action-card" style="margin-bottom: 0; padding: 0.75rem;">
                <span style="font-weight: 500;">Wedstrijden</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
        </div>
    </div>
<?php endif; ?>

<h2>Mijn Teams</h2>
<?php if (empty($teams)): ?>
    <div class="card">
        <p>Je bent nog geen lid van een team.</p>
    </div>
<?php else: ?>
    <?php foreach ($teams as $team): ?>
        <?php
            $roleParts = [];
            if (!empty($team['is_coach'])) $roleParts[] = 'Coach';
            if (!empty($team['is_trainer'])) $roleParts[] = 'Trainer';
            $roleString = implode(' & ', $roleParts);
            
            $isCurrent = isset($_SESSION['current_team']) && $_SESSION['current_team']['id'] === $team['id'];
        ?>
        
        <?php if (!$isCurrent): ?>
            <form method="POST" action="/team/select" style="display: block; width: 100%;">
                <?= Csrf::renderInput() ?>
                <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                <input type="hidden" name="team_name" value="<?= e($team['name']) ?>">
                <input type="hidden" name="team_invite_code" value="<?= e($team['invite_code']) ?>">
                
                <button type="submit" class="action-card">
                    <div>
                        <h3><?= e($team['name']) ?></h3>
                        <p><?= e($roleString) ?></p>
                    </div>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </form>
        <?php else: ?>
            <div class="action-card" style="cursor: default; border-color: var(--primary);">
                <div>
                    <h3 style="color: var(--primary);"><?= e($team['name']) ?></h3>
                    <p><?= e($roleString) ?></p>
                </div>
                <span class="text-success" title="Huidig team">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </span>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

