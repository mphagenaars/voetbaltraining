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

<div class="card">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="text-align: left; border-bottom: 2px solid #eee;">
                <th style="padding: 10px;">ID</th>
                <th style="padding: 10px;">Gebruikersnaam</th>
                <th style="padding: 10px;">Naam</th>
                <th style="padding: 10px;">Admin</th>
                <th style="padding: 10px; text-align: right;">Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 10px;"><?= $user['id'] ?></td>
                    <td style="padding: 10px;"><?= e($user['username']) ?></td>
                    <td style="padding: 10px;"><?= e($user['name']) ?></td>
                    <td style="padding: 10px;">
                        <?php if (!empty($user['is_admin'])): ?>
                            <span class="badge">Ja</span>
                        <?php else: ?>
                            <span style="color: #6c757d;">Nee</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 10px;">
                        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 0.5rem;">
                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <form action="/admin/toggle-admin" method="POST" style="margin:0;">
                                    <?= Csrf::renderInput() ?>
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="hidden" name="is_admin" value="<?= !empty($user['is_admin']) ? 0 : 1 ?>">
                                    <button type="submit" class="btn-icon" title="<?= !empty($user['is_admin']) ? 'Ontneem Admin Rechten' : 'Maak Admin' ?>" style="<?= !empty($user['is_admin']) ? 'color: var(--primary);' : '' ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <a href="/admin/user-teams?user_id=<?= $user['id'] ?>" class="btn-icon" title="Teams Beheren">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            </a>

                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <form action="/admin/delete-user" method="POST" onsubmit="return confirm('Weet je zeker dat je deze gebruiker wilt verwijderen?');" style="margin:0;">
                                    <?= Csrf::renderInput() ?>
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Verwijderen">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2-2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

