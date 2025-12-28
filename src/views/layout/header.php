<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Trainer Bobby' ?></title>
    <link rel="icon" href="/images/logo.png">
    <link rel="stylesheet" href="/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/style.css') ?>">
</head>
<body>
<header class="main-header">
    <div class="container">
        <a href="/" class="brand">
            <img src="/images/logo.png" alt="Trainer Bobby" class="brand-logo">
        </a>
        <nav>
            <?php if (isset($_SESSION['user_id'])): ?>
                <span style="margin-right: 1rem;"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                <a href="/logout" class="btn btn-sm btn-outline">Uitloggen</a>
            <?php else: ?>
                <a href="/login" class="btn btn-sm btn-outline">Inloggen</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
