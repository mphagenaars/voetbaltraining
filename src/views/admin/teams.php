<div class="header-actions">
    <h1>Team Beheer</h1>
    <a href="/admin" class="btn btn-outline">Terug</a>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Clubs -->
    <div class="card">
        <h2>Clubs</h2>
        <form action="/admin/teams/add-club" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
            <?= Csrf::renderInput() ?>
            <div style="display: flex; gap: 0.5rem;">
                <input type="text" name="name" placeholder="Nieuwe club..." required style="flex: 1;">
                <button type="submit" class="btn">Toevoegen</button>
            </div>
            <input type="file" name="logo" accept="image/*" style="font-size: 0.9rem;">
        </form>

        <ul class="list-group">
            <?php foreach ($clubs as $club): ?>
                <li class="list-group-item" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <?php if (!empty($club['logo_path'])): ?>
                            <img src="/<?= e($club['logo_path']) ?>" alt="Logo" style="width: 24px; height: 24px; object-fit: contain;">
                        <?php else: ?>
                            <div style="width: 24px; height: 24px; background: #eee; border-radius: 4px;"></div>
                        <?php endif; ?>
                        <span><?= e($club['name']) ?></span>
                    </div>
                    <form action="/admin/teams/delete-club" method="POST" onsubmit="return confirm('Weet je zeker dat je deze club wilt verwijderen?');" style="margin: 0;">
                        <?= Csrf::renderInput() ?>
                        <input type="hidden" name="id" value="<?= $club['id'] ?>">
                        <button type="submit" class="btn-icon btn-icon-danger" title="Verwijderen">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Seizoenen -->
    <div class="card">
        <h2>Seizoenen</h2>
        <form action="/admin/teams/add-season" method="POST" style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
            <?= Csrf::renderInput() ?>
            <input type="text" name="name" placeholder="Nieuw seizoen (bijv. 2026-2027)..." required style="flex: 1;">
            <button type="submit" class="btn">Toevoegen</button>
        </form>

        <ul class="list-group">
            <?php foreach ($seasons as $season): ?>
                <li class="list-group-item" style="display: flex; justify-content: space-between; align-items: center;">
                    <span><?= e($season['name']) ?></span>
                    <form action="/admin/teams/delete-season" method="POST" onsubmit="return confirm('Weet je zeker dat je dit seizoen wilt verwijderen?');" style="margin: 0;">
                        <?= Csrf::renderInput() ?>
                        <input type="hidden" name="id" value="<?= $season['id'] ?>">
                        <button type="submit" class="btn-icon btn-icon-danger" title="Verwijderen">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="card" style="margin-top: 2rem;">
    <h2>Alle Teams</h2>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; border-bottom: 2px solid #eee;">
                    <th style="padding: 0.75rem;">Naam</th>
                    <th style="padding: 0.75rem;">Club</th>
                    <th style="padding: 0.75rem;">Seizoen</th>
                    <th style="padding: 0.75rem;">Leden</th>
                    <th style="padding: 0.75rem;">Code</th>
                    <th style="padding: 0.75rem; text-align: right;">Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($teams)): ?>
                    <tr>
                        <td colspan="6" style="padding: 1rem; text-align: center; color: var(--text-muted);">Geen teams gevonden.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($teams as $team): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 0.75rem; font-weight: 500;"><?= e($team['name']) ?></td>
                            <td style="padding: 0.75rem;"><?= e($team['club'] ?: '-') ?></td>
                            <td style="padding: 0.75rem;"><?= e($team['season'] ?: '-') ?></td>
                            <td style="padding: 0.75rem;"><?= $team['member_count'] ?></td>
                            <td style="padding: 0.75rem;"><code style="background: #f8f9fa; padding: 2px 6px; border-radius: 4px;"><?= e($team['invite_code']) ?></code></td>
                            <td style="padding: 0.75rem; text-align: right;">
                                <a href="/admin/teams/edit?id=<?= $team['id'] ?>" class="btn-icon" title="Bewerken" style="display: inline-block; margin-right: 0.5rem;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </a>
                                <form action="/admin/teams/delete-team" method="POST" onsubmit="return confirm('Weet je zeker dat je dit team wilt verwijderen? Alle data (oefeningen, wedstrijden, spelers) van dit team gaat verloren!');" style="display: inline;">
                                    <?= Csrf::renderInput() ?>
                                    <input type="hidden" name="id" value="<?= $team['id'] ?>">
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Verwijderen">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
