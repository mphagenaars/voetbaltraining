<h1>Dashboard</h1>

<?php if (isset($_SESSION['current_team'])): ?>
    <div class="alert alert-info">
        <h2 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Huidig team: <?= htmlspecialchars($_SESSION['current_team']['name']) ?></h2>
        <p>Rol: <?= htmlspecialchars($_SESSION['current_team']['role']) ?></p>
        <p>Invite code: <code><?= htmlspecialchars($_SESSION['current_team']['invite_code']) ?></code></p>
        
        <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="/exercises" class="btn btn-sm">Oefenstof</a>
            <a href="/trainings" class="btn btn-sm">Trainingen</a>
            <a href="/players" class="btn btn-sm">Spelers</a>
            <a href="/lineups" class="btn btn-sm">Opstellingen</a>
        </div>
    </div>
<?php else: ?>
    <div class="alert" style="background-color: #fff3e0; color: #e65100; border-color: #ffe0b2;">
        <p>Selecteer een team om te beginnen.</p>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Mijn Teams</h2>
    <?php if (empty($teams)): ?>
        <p>Je bent nog geen lid van een team.</p>
    <?php else: ?>
        <ul style="list-style: none; padding: 0;">
            <?php foreach ($teams as $team): ?>
                <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <span>
                        <strong><?= htmlspecialchars($team['name']) ?></strong> 
                        <span class="text-muted">(<?= htmlspecialchars($team['role']) ?>)</span>
                    </span>
                    
                    <?php if (!isset($_SESSION['current_team']) || $_SESSION['current_team']['id'] !== $team['id']): ?>
                        <form method="POST" action="/team/select" style="margin: 0;">
                            <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                            <input type="hidden" name="team_name" value="<?= htmlspecialchars($team['name']) ?>">
                            <input type="hidden" name="team_role" value="<?= htmlspecialchars($team['role']) ?>">
                            <input type="hidden" name="team_invite_code" value="<?= htmlspecialchars($team['invite_code']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline">Selecteer</button>
                        </form>
                    <?php else: ?>
                        <span class="btn btn-sm" style="cursor: default; opacity: 0.7;">Geselecteerd</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Nieuw team aanmaken</h3>
    <form method="POST" action="/team/create" style="display: flex; gap: 1rem; align-items: flex-end;">
        <div style="flex-grow: 1; margin-bottom: 0;">
            <label for="name">Team naam</label>
            <input type="text" id="name" name="name" required placeholder="Bijv. JO11-1">
        </div>
        <button type="submit" class="btn">Aanmaken</button>
    </form>
</div>
