<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Trainer Bobby' ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚽</text></svg>">
    <link rel="stylesheet" href="/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/style.css') ?>">
</head>
<body>
<header class="main-header">
    <div class="container">
        <a href="/" class="brand">⚽ Trainer Bobby</a>
        <nav style="display: flex; align-items: center; gap: 1.5rem;">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if (!empty($_SESSION['is_admin'])): ?>
                    <a href="/admin" style="text-decoration: none; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        Admin
                    </a>
                <?php endif; ?>
                
                <div class="dropdown">
                    <a href="#" style="text-decoration: none; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        <strong><?= e($_SESSION['user_name']) ?></strong>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </a>
                    <div class="dropdown-content">
                        <a href="/account">Profiel</a>
                        <a href="/team/create">Nieuw Team</a>
                        <a href="/logout">Uitloggen</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/login" class="btn btn-sm btn-outline">Inloggen</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
