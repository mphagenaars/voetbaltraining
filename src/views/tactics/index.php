<div class="container">
    <link rel="stylesheet" href="/css/match-view.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/match-view.css') ?>">

    <div class="app-bar">
        <div class="app-bar-start">
            <a href="/" class="btn-icon-round" title="Terug naar dashboard">
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
            Bekijk hier wedstrijdsituaties en voeg fictieve trainingssituaties toe in hetzelfde tactiekbord.
        </p>
    </div>

    <input type="hidden" id="csrf_token" value="<?= Csrf::getToken() ?>">

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
