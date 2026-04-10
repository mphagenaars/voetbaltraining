<div class="app-bar">
    <div class="app-bar-start">
        <a href="/admin" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Team Beheer</h1>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="tb-alert tb-alert--success"><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="tb-alert tb-alert--danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Clubs -->
    <div class="tb-card">
        <h2>Clubs</h2>
        <form action="/admin/teams/add-club" method="POST" enctype="multipart/form-data" class="tb-stack-form">
            <?= Csrf::renderInput() ?>
            <div class="tb-inline-row">
                <input type="text" name="name" placeholder="Nieuwe club..." required class="tb-grow">
                <button type="submit" class="btn-icon-round" title="Toevoegen">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                </button>
            </div>
            <div>
                <label for="club_logo" class="tb-file-label">Club logo (optioneel)</label>
                <input type="file" id="club_logo" name="logo" accept="image/*" class="tb-file-input">
            </div>
        </form>

        <ul class="list-group">
            <?php foreach ($clubs as $club): ?>
                <li class="list-group-item tb-inline-row tb-inline-row--between">
                    <div class="tb-inline-row">
                        <?php if (!empty($club['logo_path'])): ?>
                            <img src="/<?= e($club['logo_path']) ?>" alt="Logo" class="tb-admin-logo">
                        <?php else: ?>
                            <div class="tb-admin-logo-placeholder"></div>
                        <?php endif; ?>
                        <span><?= e($club['name']) ?></span>
                    </div>
                    <form action="/admin/teams/delete-club" method="POST" onsubmit="return confirm('Weet je zeker dat je deze club wilt verwijderen?');" class="tb-no-margin">
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
    <div class="tb-card">
        <h2>Seizoenen</h2>
        <form action="/admin/teams/add-season" method="POST" class="tb-inline-row">
            <?= Csrf::renderInput() ?>
            <input type="text" name="name" placeholder="Nieuw seizoen (bijv. 2026-2027)..." required class="tb-grow">
            <button type="submit" class="btn-icon-round" title="Toevoegen">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            </button>
        </form>

        <ul class="list-group">
            <?php foreach ($seasons as $season): ?>
                <li class="list-group-item tb-inline-row tb-inline-row--between">
                    <span><?= e($season['name']) ?></span>
                    <form action="/admin/teams/delete-season" method="POST" onsubmit="return confirm('Weet je zeker dat je dit seizoen wilt verwijderen?');" class="tb-no-margin">
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

<div class="tb-card tb-admin-section-gap">
    <h2>Alle Teams</h2>
    <div class="tb-table-wrap">
        <table class="tb-table">
            <thead>
                <tr>
                    <th>Naam</th>
                    <th>Categorie</th>
                    <th>Wedstrijdvorm</th>
                    <th>Club</th>
                    <th>Seizoen</th>
                    <th>Leden</th>
                    <th>Code</th>
                    <th class="tb-table-cell-right">Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($teams)): ?>
                    <tr>
                        <td colspan="8" class="tb-table-empty">Geen teams gevonden.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($teams as $team): ?>
                        <?php
                        $category = Team::resolveCompetitionCategory($team);
                        $matchFormat = Team::resolveMatchFormatForTeam($team);
                        ?>
                        <tr>
                            <td><strong><?= e($team['name']) ?></strong></td>
                            <td><?= e($category !== '' ? $category : '-') ?></td>
                            <td><?= e(Team::matchFormatLabel($matchFormat)) ?></td>
                            <td><?= e($team['club'] ?: '-') ?></td>
                            <td><?= e($team['season'] ?: '-') ?></td>
                            <td><?= $team['member_count'] ?></td>
                            <td><code class="tb-table-code"><?= e($team['invite_code']) ?></code></td>
                            <td class="tb-table-cell-right">
                                <span class="tb-admin-team-actions">
                                <a href="/admin/team-members?team_id=<?= $team['id'] ?>" class="btn-icon" title="Leden beheren">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                </a>
                                <a href="/admin/teams/edit?id=<?= $team['id'] ?>" class="btn-icon" title="Bewerken">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </a>
                                <form action="/admin/teams/delete-team" method="POST" onsubmit="return confirm('Weet je zeker dat je dit team wilt verwijderen? Alle data (oefeningen, wedstrijden, spelers) van dit team gaat verloren!');" class="tb-no-margin">
                                    <?= Csrf::renderInput() ?>
                                    <input type="hidden" name="id" value="<?= $team['id'] ?>">
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Verwijderen">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </form>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
