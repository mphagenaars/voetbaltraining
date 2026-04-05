<div class="container">
    <div class="app-bar">
        <div class="app-bar-start">
            <a href="/" class="btn-icon-round" title="Terug" aria-label="Terug">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </a>
            <h1 class="app-bar-title">Spelers</h1>
        </div>
    </div>

    <div class="card tb-players-card">
        <div class="tb-players-header">
            <h2>Selectie</h2>
            <?php if (!empty($players)): ?>
                <span class="tb-players-count"><?= (int)count($players) ?> spelers</span>
            <?php endif; ?>
        </div>

        <?php if (empty($players)): ?>
            <p class="tb-players-empty">Nog geen spelers toegevoegd.</p>
        <?php else: ?>
            <div class="tb-player-list">
                <?php foreach ($players as $player): ?>
                    <article class="tb-player-item">
                        <div class="tb-player-main">
                            <span class="tb-player-name"><?= e($player['name']) ?></span>
                        </div>
                        <div class="tb-player-actions">
                            <a href="/players/edit?id=<?= $player['id'] ?>" class="tb-icon-button" title="Bewerken" aria-label="Bewerken">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </a>
                            <form action="/players/delete" method="POST" class="tb-player-inline-form" onsubmit="return confirm('Weet je zeker dat je deze speler wilt verwijderen?');">
                                <?= Csrf::renderInput() ?>
                                <input type="hidden" name="id" value="<?= $player['id'] ?>">
                                <button type="submit" class="tb-icon-button tb-icon-button--danger" title="Verwijderen" aria-label="Verwijderen">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<a href="/players/create" class="tb-fab tb-player-add-fab" title="Nieuwe speler" aria-label="Nieuwe speler">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
</a>
