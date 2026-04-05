<div class="app-bar">
    <div class="app-bar-start">
        <a href="/admin" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Gebruikersbeheer</h1>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div id="new-user-form" class="card tb-admin-new-user-card hidden">
    <h2 class="tb-admin-new-user-title">Nieuwe gebruiker aanmaken</h2>
    <form action="/admin/create-user" method="POST">
        <?= Csrf::renderInput() ?>
        <div class="tb-admin-new-user-grid">
            <div class="form-group tb-compact-form-group">
                <label>Naam</label>
                <input type="text" name="name" class="form-control" required placeholder="Voor- en achternaam">
            </div>
            <div class="form-group tb-compact-form-group">
                <label>Gebruikersnaam</label>
                <input type="text" name="username" class="form-control" required placeholder="Inlognaam" autocomplete="off">
            </div>
            <div class="form-group tb-compact-form-group">
                <label>Wachtwoord</label>
                <input type="password" name="password" class="form-control" required placeholder="Minimaal 8 tekens" autocomplete="new-password">
            </div>
            <button type="submit" class="tb-button tb-button--primary tb-button--sm btn-inline-icon tb-nowrap">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Aanmaken
            </button>
        </div>
    </form>
</div>

<div class="card">
    <div class="tb-table-wrap">
        <table class="tb-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Gebruikersnaam</th>
                    <th>Naam</th>
                    <th>Admin</th>
                    <th>AI Toegang</th>
                    <th class="tb-table-cell-right">Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= e($user['username']) ?></td>
                        <td><?= e($user['name']) ?></td>
                        <td>
                            <?php if (!empty($user['is_admin'])): ?>
                                <span class="badge">Ja</span>
                            <?php else: ?>
                                <span class="tb-muted">Nee</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($user['ai_access_enabled'])): ?>
                                <span class="tb-table-status-pill tb-table-status-pill--ok">Aan</span>
                            <?php else: ?>
                                <span class="tb-muted">Uit</span>
                            <?php endif; ?>
                        </td>
                        <td class="tb-table-cell-right">
                            <div class="tb-table-actions">
                                <form action="/admin/update-user-ai-access" method="POST" class="tb-no-margin">
                                    <?= Csrf::renderInput() ?>
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="hidden" name="ai_access_enabled" value="<?= !empty($user['ai_access_enabled']) ? 0 : 1 ?>">
                                    <button type="submit" class="tb-icon-button<?= !empty($user['ai_access_enabled']) ? ' tb-admin-ai-toggle--on' : '' ?>" title="<?= !empty($user['ai_access_enabled']) ? 'Zet AI uit' : 'Zet AI aan' ?>" aria-label="<?= !empty($user['ai_access_enabled']) ? 'Zet AI uit' : 'Zet AI aan' ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a4 4 0 0 0-4 4v2H6a2 2 0 0 0-2 2v5a8 8 0 0 0 16 0v-5a2 2 0 0 0-2-2h-2V6a4 4 0 0 0-4-4z"></path><path d="M9 12h6"></path></svg>
                                    </button>
                                </form>

                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                    <form action="/admin/toggle-admin" method="POST" class="tb-no-margin">
                                        <?= Csrf::renderInput() ?>
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="is_admin" value="<?= !empty($user['is_admin']) ? 0 : 1 ?>">
                                        <button type="submit" class="tb-icon-button<?= !empty($user['is_admin']) ? ' tb-admin-toggle--on' : '' ?>" title="<?= !empty($user['is_admin']) ? 'Ontneem Admin Rechten' : 'Maak Admin' ?>" aria-label="<?= !empty($user['is_admin']) ? 'Ontneem Admin Rechten' : 'Maak Admin' ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <a href="/admin/user-teams?user_id=<?= $user['id'] ?>" class="tb-icon-button" title="Teams beheren" aria-label="Teams beheren">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                </a>

                                <button type="button" class="tb-icon-button" title="Wachtwoord resetten" aria-label="Wachtwoord resetten"
                                    onclick="document.getElementById('reset-pw-<?= $user['id'] ?>').classList.toggle('hidden')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                </button>

                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                    <form action="/admin/delete-user" method="POST" onsubmit="return confirm('Weet je zeker dat je deze gebruiker wilt verwijderen?');" class="tb-no-margin">
                                        <?= Csrf::renderInput() ?>
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="tb-icon-button tb-icon-button--danger" title="Verwijderen" aria-label="Verwijderen">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2-2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr id="reset-pw-<?= $user['id'] ?>" class="hidden tb-admin-user-reset-row">
                        <td colspan="6">
                            <form action="/admin/reset-user-password" method="POST" class="tb-admin-user-reset-form">
                                <?= Csrf::renderInput() ?>
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <span class="tb-muted">
                                    Nieuw wachtwoord voor <strong><?= e($user['name']) ?></strong>:
                                </span>
                                <input type="password" name="new_password" class="form-control"
                                       placeholder="Minimaal 8 tekens" autocomplete="new-password">
                                <button type="submit" class="tb-button tb-button--secondary tb-button--sm">Instellen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<button
    type="button"
    id="tb-admin-users-create-toggle"
    class="tb-fab"
    title="Nieuwe gebruiker"
    aria-label="Nieuwe gebruiker"
    aria-controls="new-user-form"
    aria-expanded="false"
>
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
    </svg>
</button>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleButton = document.getElementById('tb-admin-users-create-toggle');
    const createFormCard = document.getElementById('new-user-form');
    if (!toggleButton || !createFormCard) {
        return;
    }

    toggleButton.addEventListener('click', function () {
        const willOpen = createFormCard.classList.contains('hidden');
        createFormCard.classList.toggle('hidden');
        toggleButton.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

        if (willOpen) {
            createFormCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            const firstInput = createFormCard.querySelector('input[name="name"]');
            if (firstInput) {
                window.setTimeout(function () {
                    firstInput.focus();
                }, 220);
            }
        }
    });
});
</script>
