<?php
/**
 * Speelwijze (formation template) editor partial.
 * Expected variables:
 *   $speelwijzen - array of formation templates
 *   $teamId - current team id
 */
$speelwijzen = $speelwijzen ?? [];
$teamId = $teamId ?? 0;
?>

<div id="speelwijze-editor" class="speelwijze-editor" data-team-id="<?= (int)$teamId ?>">
    <div class="speelwijze-layout">
        <!-- List panel -->
        <div class="speelwijze-list-panel">
            <div class="speelwijze-list-header">
                <span>Speelwijzen</span>
                <button type="button" id="btn-new-speelwijze" class="btn-icon-round tb-icon-button--accent" title="Nieuwe speelwijze">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                </button>
            </div>
            <div id="speelwijze-list" class="speelwijze-list">
                <!-- Populated by JS -->
            </div>
        </div>

        <!-- Editor panel -->
        <div id="speelwijze-detail" class="speelwijze-detail">
            <div class="speelwijze-form-row">
                <input type="text" id="speelwijze-name" class="form-control" placeholder="Naam (bijv. 4-3-3 aanvallend)" maxlength="60">
                <label class="speelwijze-shared-toggle" title="Gedeeld met alle teams">
                    <input type="checkbox" id="speelwijze-shared">
                    <span class="toggle-slider"></span>
                    <span class="toggle-label">Gedeeld</span>
                </label>
            </div>
            <div class="speelwijze-field-toolbar">
                <button type="button" id="btn-add-position" class="btn-icon-round tb-icon-button--accent" title="Positie toevoegen">
                    <svg width="18" height="18" viewBox="0 0 100 100" fill="none" aria-hidden="true">
                        <path d="M15,30 L30,10 L70,10 L85,30 L75,40 L70,35 L70,90 L30,90 L30,35 L25,40 Z" fill="currentColor"></path>
                    </svg>
                </button>
                <span id="speelwijze-position-count" class="speelwijze-badge">0 posities</span>
                <span id="speelwijze-format-badge" class="speelwijze-badge speelwijze-format"></span>
                <div class="tb-spacer"></div>
                <button type="button" id="btn-save-speelwijze" class="btn-icon-round" title="Opslaan">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                </button>
                <button type="button" id="btn-delete-speelwijze" class="btn-icon-round btn-danger-icon" title="Verwijderen">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                </button>
            </div>

            <!-- Football field -->
            <div class="field-container">
                <div id="speelwijze-field" class="football-field">
                    <!-- Field markings -->
                    <div class="field-line center-line"></div>
                    <div class="field-circle center-circle"></div>
                    <div class="field-area penalty-area-top"></div>
                    <div class="field-area penalty-area-bottom"></div>
                    <div class="field-area goal-area-top"></div>
                    <div class="field-area goal-area-bottom"></div>
                    <!-- Position markers injected by JS -->
                </div>
            </div>

            <!-- Position edit dialog (inline) -->
            <div id="speelwijze-pos-edit" class="speelwijze-pos-edit" style="display:none;">
                <input type="text" id="pos-edit-code" class="form-control form-control-sm" placeholder="Code (bijv. LV)" maxlength="5" style="width:70px;">
                <input type="text" id="pos-edit-label" class="form-control form-control-sm" placeholder="Label (bijv. Links verdediger)" maxlength="40" style="flex:1;">
                <button type="button" id="btn-pos-edit-ok" class="btn-icon-round" title="Bevestigen">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </button>
                <button type="button" id="btn-pos-edit-remove" class="btn-icon-round btn-danger-icon" title="Positie verwijderen">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
        </div>
    </div>
</div>
