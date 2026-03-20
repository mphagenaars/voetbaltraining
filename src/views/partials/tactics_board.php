<?php
$tacticsBoardTitle = isset($tacticsBoardTitle) ? trim((string)$tacticsBoardTitle) : 'Tactiekbord';
$tacticsBoardSubtitle = isset($tacticsBoardSubtitle) ? trim((string)$tacticsBoardSubtitle) : '';
$tacticsBoardData = isset($tacticsBoardData) && is_array($tacticsBoardData) ? $tacticsBoardData : [];
$tacticsContextMode = isset($tacticsContextMode) ? trim((string)$tacticsContextMode) : 'match';
$tacticsTeamId = isset($tacticsTeamId) ? (int)$tacticsTeamId : 0;
$tacticsMatchId = isset($tacticsMatchId) ? (int)$tacticsMatchId : 0;
$tacticsSaveEndpoint = isset($tacticsSaveEndpoint) ? trim((string)$tacticsSaveEndpoint) : '/matches/tactics/save';
$tacticsDeleteEndpoint = isset($tacticsDeleteEndpoint) ? trim((string)$tacticsDeleteEndpoint) : '/matches/tactics/delete';
$tacticsExportEndpoint = isset($tacticsExportEndpoint) ? trim((string)$tacticsExportEndpoint) : '/matches/tactics/export-video';
$tacticsListSort = isset($tacticsListSort) ? trim((string)$tacticsListSort) : 'sort_order';
$tacticsShowSourceMeta = !empty($tacticsShowSourceMeta);
?>

<div
    class="card match-tactics-card"
    id="match-tactics-root"
    data-context-mode="<?= e($tacticsContextMode) ?>"
    data-team-id="<?= $tacticsTeamId > 0 ? $tacticsTeamId : '' ?>"
    data-match-id="<?= $tacticsMatchId > 0 ? $tacticsMatchId : '' ?>"
    data-save-endpoint="<?= e($tacticsSaveEndpoint) ?>"
    data-delete-endpoint="<?= e($tacticsDeleteEndpoint) ?>"
    data-export-endpoint="<?= e($tacticsExportEndpoint) ?>"
    data-list-sort="<?= e($tacticsListSort) ?>"
    data-show-source-meta="<?= $tacticsShowSourceMeta ? '1' : '0' ?>"
>
    <div class="match-tactics-header">
        <h3><?= e($tacticsBoardTitle) ?></h3>
        <?php if ($tacticsBoardSubtitle !== ''): ?>
            <p><?= e($tacticsBoardSubtitle) ?></p>
        <?php endif; ?>
    </div>

    <input
        type="hidden"
        id="match_tactics_data"
        value="<?= e((string)json_encode($tacticsBoardData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
    >

    <div class="match-tactics-layout">
        <aside class="match-tactics-sidebar">
            <div class="match-tactics-sidebar-head">
                <h4>Situaties</h4>
                <button type="button" id="tactics-new-btn" class="btn-icon tactics-action-icon tactics-action-icon-add" title="Nieuwe situatie" aria-label="Nieuwe situatie">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                </button>
            </div>
            <div id="tactics-list" class="match-tactics-list"></div>
        </aside>

        <section class="match-tactics-main">
            <div class="match-tactics-form-row">
                <div class="form-group tactics-title-group">
                    <label for="tactics-title">Titel</label>
                    <input type="text" id="tactics-title" maxlength="120" placeholder="Bijv. Hoge pressing links">
                </div>

                <div class="form-group tactics-minute-group">
                    <label for="tactics-minute">Minuut</label>
                    <input type="number" id="tactics-minute" min="0" max="130" placeholder="-">
                </div>

                <div class="match-tactics-actions">
                    <button type="button" id="tactics-delete-btn" class="btn-icon tactics-action-icon tactics-action-icon-delete" title="Situatie verwijderen" aria-label="Situatie verwijderen">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>
                    </button>
                </div>
            </div>
            <div class="match-tactics-status-row">
                <span id="tactics-save-status" class="match-tactics-status"></span>
                <button type="button" id="tactics-save-retry-btn" class="match-tactics-retry-btn" hidden>Opnieuw proberen</button>
            </div>

            <div class="match-tactics-editor-shell">
                <div class="match-tactics-toolbar editor-toolbar" id="tactics-toolbar">
                    <div class="toolbar-row">
                        <div class="match-tactics-toolbar-group">
                            <span class="match-tactics-toolbar-label">Objecten</span>
                            <div class="group-items">
                                <div class="draggable-item tactics-draggable-item tactics-draggable-item-ball" draggable="true" data-type="ball" title="Bal">⚽</div>
                                <div class="draggable-item tactics-draggable-item" draggable="true" data-type="shirt_red_black"><img src="/images/assets/shirt_red_black.svg" alt="Speler Zwart/Rood"></div>
                                <div class="draggable-item tactics-draggable-item" draggable="true" data-type="shirt_red_white"><img src="/images/assets/shirt_red_white.svg" alt="Speler Wit/Rood"></div>
                            </div>
                        </div>

                        <div class="match-tactics-toolbar-group">
                            <span class="match-tactics-toolbar-label">Tekenen</span>
                            <div class="group-items">
                                <button type="button" id="tactics-tool-marker" class="tool-btn" title="Viltstift pijl">
                                    <svg class="tactics-marker-pen-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4 20l4.8-1L19 8.8 15.2 5 5 15.2 4 20z"></path>
                                        <path d="M13.8 6.4l3.8 3.8"></path>
                                        <path d="M3.5 20.5h4.2"></path>
                                    </svg>
                                </button>
                                <button type="button" id="tactics-marker-color-black" class="tool-btn tactics-marker-option-btn" title="Witte lijn">
                                    <span class="tactics-marker-swatch tactics-marker-swatch-black" aria-hidden="true"></span>
                                </button>
                                <button type="button" id="tactics-marker-color-red" class="tool-btn tactics-marker-option-btn" title="Rode lijn">
                                    <span class="tactics-marker-swatch tactics-marker-swatch-red" aria-hidden="true"></span>
                                </button>
                                <button type="button" id="tactics-marker-style-solid" class="tool-btn tactics-marker-option-btn" title="Volle lijn">
                                    <span class="tactics-marker-line-preview" aria-hidden="true"></span>
                                </button>
                                <button type="button" id="tactics-marker-style-dashed" class="tool-btn tactics-marker-option-btn" title="Stippellijn">
                                    <span class="tactics-marker-line-preview is-dashed" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>

                        <div class="match-tactics-toolbar-group is-right">
                            <span class="match-tactics-toolbar-label">Bewerken</span>
                            <div class="group-items">
                                <button type="button" id="tactics-tool-select" class="tool-btn" title="Selecteren">
                                    <img src="/images/assets/icon_select.svg" alt="Selecteren">
                                </button>
                                <button type="button" id="tactics-btn-to-back" class="tool-btn" title="Naar achtergrond">⬇️</button>
                                <button type="button" id="tactics-btn-delete-selected" class="tool-btn" title="Verwijder geselecteerde">🗑️</button>
                                <button type="button" id="tactics-btn-clear" class="btn-icon tactics-action-icon tactics-action-icon-delete" title="Alles wissen" aria-label="Alles wissen">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><line x1="8" y1="8" x2="16" y2="16"></line><line x1="16" y1="8" x2="8" y2="16"></line></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="tactics-container" class="editor-canvas-container match-tactics-canvas"></div>
            </div>
        </section>
    </div>
</div>
