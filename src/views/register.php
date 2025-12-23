<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registreren - Voetbaltraining</title>
</head>
<body>
    <h1>Registreren met Invite Code</h1>
    
    <?php if (isset($error)): ?>
        <p style="color: red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="/register">
        <div>
            <label for="invite_code">Invite Code (van je team):</label><br>
            <input type="text" id="invite_code" name="invite_code" required value="<?= htmlspecialchars($_POST['invite_code'] ?? '') ?>">
        </div>
        <br>
        <div>
            <label for="name">Volledige naam:</label><br>
            <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <br>
        <div>
            <label for="username">Gebruikersnaam:</label><br>
            <input type="text" id="username" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <br>
        <div>
            <label for="password">Wachtwoord:</label><br>
            <input type="password" id="password" name="password" required>
        </div>
        <br>
        <button type="submit">Registreren</button>
    </form>

    <p>Heb je al een account? <a href="/login">Log in</a></p>
    <p><a href="/">Terug naar home</a></p>
</body>
</html>
