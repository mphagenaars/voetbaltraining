<div class="container">
    <div class="header-actions">
        <h1>Spelers</h1>
        <div style="display: flex; gap: 0.5rem;">
            <a href="/players/create" class="btn btn-outline">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Nieuwe Speler
            </a>
            <a href="/" class="btn btn-outline">Terug</a>
        </div>
    </div>

    <div class="card">
        <h2>Selectie</h2>
        <?php if (empty($players)): ?>
            <p>Nog geen spelers toegevoegd.</p>
        <?php else: ?>
            <div class="player-list">
                <?php foreach ($players as $player): ?>
                    <div class="player-item">
                        <span class="player-name"><?= e($player['name']) ?></span>
                        <div class="player-actions">
                            <a href="/players/edit?id=<?= $player['id'] ?>" class="btn-icon" title="Bewerken">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </a>
                            <form action="/players/delete" method="POST" onsubmit="return confirm('Weet je zeker dat je deze speler wilt verwijderen?');" style="display:inline;">
                                <?= Csrf::renderInput() ?>
                                <input type="hidden" name="id" value="<?= $player['id'] ?>">
                                <button type="submit" class="btn-icon btn-icon-danger" title="Verwijderen">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.player-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.player-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
}

.player-name {
    font-weight: 500;
}

.player-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-icon {
    background: none;
    border: none;
    cursor: pointer;
    color: #6c757d;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
}

.btn-icon:hover {
    color: #007bff;
}

.btn-icon-danger:hover {
    color: #dc3545;
}
</style>


