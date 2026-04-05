<div class="container">
    <link rel="stylesheet" href="/css/match-view.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/match-view.css') ?>">
    <link rel="stylesheet" href="/css/speelwijze-editor.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/speelwijze-editor.css') ?>">

    <div class="app-bar">
        <div class="app-bar-start">
            <a href="/" class="tb-icon-button btn-icon-round" title="Terug naar dashboard" aria-label="Terug naar dashboard">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </a>
            <h1 class="app-bar-title">Tactiekstudio</h1>
        </div>
    </div>

    <div class="card" style="margin-bottom: 1rem;">
        <p>
            Team: <strong><?= e((string)($team['name'] ?? 'Onbekend team')) ?></strong>
        </p>
        <p style="margin-top: 0.3rem; color: var(--text-muted);">
            Bekijk hier wedstrijdsituaties, voeg trainingssituaties toe en beheer je speelwijzen.
        </p>
    </div>

    <input type="hidden" id="csrf_token" value="<?= Csrf::getToken() ?>">

    <!-- Tab navigation -->
    <div class="tactics-tabs tb-segmented">
        <button type="button" class="tactics-tab tb-segmented__item tb-segmented__item--active active" data-tab="tactiekbord" aria-pressed="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
            Tactiekbord
        </button>
        <button type="button" class="tactics-tab tb-segmented__item" data-tab="speelwijzen" aria-pressed="false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            Speelwijzen
        </button>
    </div>

    <!-- Tactiekbord tab -->
    <div id="tab-tactiekbord" class="tactics-tab-panel active">
    <?php
    $tacticsBoardTitle = 'Tactiekbord';
    $tacticsBoardData = $tactics ?? [];
    $tacticsContextMode = 'team';
    $tacticsTeamId = (int)($team['id'] ?? 0);
    $tacticsMatchId = 0;
    $tacticsSaveEndpoint = '/tactics/save';
    $tacticsDeleteEndpoint = '/tactics/delete';
    $tacticsExportEndpoint = '/tactics/export-video';
    $tacticsListSort = 'server';
    $tacticsShowSourceMeta = true;
    require __DIR__ . '/../partials/tactics_board.php';
    ?>
    </div>

    <!-- Speelwijzen tab -->
    <div id="tab-speelwijzen" class="tactics-tab-panel">
    <div class="card">
        <h2 style="font-size: 1.1rem; margin-bottom: 0.8rem;">Speelwijzen</h2>
        <script type="application/json" id="speelwijze-data"><?= json_encode($speelwijzen ?? [], JSON_UNESCAPED_UNICODE) ?></script>
        <?php
        $teamId = (int)($team['id'] ?? 0);
        require __DIR__ . '/../partials/speelwijze_editor.php';
        ?>
    </div>
    </div>
</div>

<script>
// Tactics Studio tab switching with unsaved-changes guard
(function() {
    const tabs = document.querySelectorAll('.tactics-tab');
    const panels = document.querySelectorAll('.tactics-tab-panel');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.tab;
            const current = document.querySelector('.tactics-tab.active')?.dataset.tab;
            if (target === current) return;

            // Warn if leaving speelwijzen with unsaved changes
            if (current === 'speelwijzen' && window._speelwijzeDirty) {
                if (!confirm('Je hebt onopgeslagen wijzigingen in je speelwijze. Wil je toch wisselen?')) {
                    return;
                }
            }

            tabs.forEach(t => {
                t.classList.remove('active', 'tb-segmented__item--active');
                t.setAttribute('aria-pressed', 'false');
            });
            panels.forEach(p => p.classList.remove('active'));
            tab.classList.add('active', 'tb-segmented__item--active');
            tab.setAttribute('aria-pressed', 'true');
            document.getElementById('tab-' + target)?.classList.add('active');
            sessionStorage.setItem('tacticsTab', target);
        });
    });

    // Restore tab from sessionStorage
    const saved = sessionStorage.getItem('tacticsTab');
    if (saved) {
        const savedTab = document.querySelector('.tactics-tab[data-tab="' + saved + '"]');
        if (savedTab && !savedTab.classList.contains('active')) {
            tabs.forEach(t => {
                t.classList.remove('active', 'tb-segmented__item--active');
                t.setAttribute('aria-pressed', 'false');
            });
            panels.forEach(p => p.classList.remove('active'));
            savedTab.classList.add('active', 'tb-segmented__item--active');
            savedTab.setAttribute('aria-pressed', 'true');
            document.getElementById('tab-' + saved)?.classList.add('active');
        }
    }
})();
</script>

<script src="/js/speelwijze-editor.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/speelwijze-editor.js') ?>"></script>
<script src="/js/konva.min.js"></script>
<script src="/js/konva-shared-core.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/konva-shared-core.js') ?>"></script>
<script src="/js/match-tactics/playback-plan.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/match-tactics/playback-plan.js') ?>"></script>
<script src="/js/match-tactics/playback-runtime.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/match-tactics/playback-runtime.js') ?>"></script>
<script src="/js/match-tactics/animation-panel.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/match-tactics/animation-panel.js') ?>"></script>
<script src="/js/match-tactics/video-export.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/match-tactics/video-export.js') ?>"></script>
<script src="/js/match-tactics/tactics-store.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/match-tactics/tactics-store.js') ?>"></script>
<script src="/js/match-tactics/autosave-queue.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/match-tactics/autosave-queue.js') ?>"></script>
<script src="/js/match-tactics/tactics-api.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/match-tactics/tactics-api.js') ?>"></script>
<script src="/js/match-tactics/tactics-editor-session.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/match-tactics/tactics-editor-session.js') ?>"></script>
<script src="/js/match-tactics/board-controls.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/match-tactics/board-controls.js') ?>"></script>
<script src="/js/match-tactics/shirt-color-menu.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/match-tactics/shirt-color-menu.js') ?>"></script>
<script src="/js/match-tactics/animation-authoring.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/match-tactics/animation-authoring.js') ?>"></script>
<script src="/js/match-tactics.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/match-tactics.js') ?>"></script>
