<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Voetbaltraining</title>
</head>
<body>
    <h1>Inloggen</h1>
    
    <?php if (isset($error)): ?>
        <p style="color: red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="/login">
        <div>
            <label for="username">Gebruikersnaam:</label><br>
            <input type="text" id="username" name="username" required autofocus>
        </div>
        <br>
        <div>
            <label for="password">Wachtwoord:</label><br>
            <input type="password" id="password" name="password" required>
        </div>
        <br>
        <button type="submit">Inloggen</button>
    </form>

    <p><a href="/">Terug naar home</a></p>
</body>
</html>
