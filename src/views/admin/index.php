<div class="dashboard-header">
    <h1>Gebruikersbeheer</h1>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
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
                    <td style="padding: 10px;"><?= htmlspecialchars($user['username']) ?></td>
                    <td style="padding: 10px;"><?= htmlspecialchars($user['name']) ?></td>
                    <td style="padding: 10px;">
                        <?php if (!empty($user['is_admin'])): ?>
                            <span class="badge">Ja</span>
                        <?php else: ?>
                            <span style="color: #6c757d;">Nee</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 10px;">
                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 0.5rem;">
                                <form action="/admin/toggle-admin" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= Csrf::getToken() ?>">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="hidden" name="is_admin" value="<?= !empty($user['is_admin']) ? 0 : 1 ?>">
                                    <button type="submit" class="btn btn-sm btn-outline btn-min-w">
                                        <?= !empty($user['is_admin']) ? 'Ontneem Rechten' : 'Maak Admin' ?>
                                    </button>
                                </form>

                                <a href="/admin/user-teams?user_id=<?= $user['id'] ?>" class="btn btn-sm btn-outline btn-min-w">Teams</a>

                                <form action="/admin/delete-user" method="POST" onsubmit="return confirm('Weet je zeker dat je deze gebruiker wilt verwijderen?');">
                                    <input type="hidden" name="csrf_token" value="<?= Csrf::getToken() ?>">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-min-w">Verwijderen</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

