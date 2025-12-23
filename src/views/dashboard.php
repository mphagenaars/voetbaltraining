<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Voetbaltraining</title>
</head>
<body>
    <h1>Welkom <?= htmlspecialchars($_SESSION['user_name']) ?></h1>
    <p><a href="/logout">Uitloggen</a></p>

    <?php if (isset($_SESSION['current_team'])): ?>
        <div style="background-color: #e0f7fa; padding: 10px; border-radius: 5px;">
            <h2>Huidig team: <?= htmlspecialchars($_SESSION['current_team']['name']) ?></h2>
            <p>Rol: <?= htmlspecialchars($_SESSION['current_team']['role']) ?></p>
            <p>Invite code: <code><?= htmlspecialchars($_SESSION['current_team']['invite_code']) ?></code></p>
        </div>
    <?php else: ?>
        <div style="background-color: #fff3e0; padding: 10px; border-radius: 5px;">
            <p>Selecteer een team om te beginnen.</p>
        </div>
    <?php endif; ?>

    <h2>Mijn Teams</h2>
    <?php if (empty($teams)): ?>
        <p>Je bent nog geen lid van een team.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($teams as $team): ?>
                <li>
                    <strong><?= htmlspecialchars($team['name']) ?></strong> (<?= htmlspecialchars($team['role']) ?>)
                    <?php if (!isset($_SESSION['current_team']) || $_SESSION['current_team']['id'] !== $team['id']): ?>
                        <form method="POST" action="/team/select" style="display:inline;">
                            <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                            <input type="hidden" name="team_name" value="<?= htmlspecialchars($team['name']) ?>">
                            <input type="hidden" name="team_role" value="<?= htmlspecialchars($team['role']) ?>">
                            <input type="hidden" name="team_invite_code" value="<?= htmlspecialchars($team['invite_code']) ?>">
                            <button type="submit">Selecteer</button>
                        </form>
                    <?php else: ?>
                        <span>(Geselecteerd)</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3>Nieuw team aanmaken</h3>
    <form method="POST" action="/team/create">
        <label for="name">Team naam:</label>
        <input type="text" id="name" name="name" required>
        <button type="submit">Aanmaken</button>
    </form>
</body>
</html>
