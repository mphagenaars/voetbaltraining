<?php
$isEdit = isset($exercise);
$formMode = isset($formMode) && in_array($formMode, ['manual', 'ai'], true) ? $formMode : 'manual';
$baseFormPath = $isEdit ? ('/exercises/edit?id=' . (int)$exercise['id']) : '/exercises/create';
$manualModeUrl = $baseFormPath . (str_contains($baseFormPath, '?') ? '&' : '?') . 'mode=manual';
$aiModeUrl = $baseFormPath . (str_contains($baseFormPath, '?') ? '&' : '?') . 'mode=ai';
?>

<div class="app-bar">
    <div class="app-bar-start">
        <a href="/exercises" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title"><?= $isEdit ? 'Oefening bewerken' : 'Nieuwe oefening' ?></h1>
    </div>
</div>

<div class="card exercise-mode-card">
    <h2 class="exercise-mode-title">Kies hoe je wilt starten</h2>
    <p class="exercise-mode-subtitle">Je komt altijd uit op hetzelfde oefenformulier. Kies de manier die voor jou prettig werkt.</p>
    <div class="exercise-mode-switch">
        <a href="<?= e($manualModeUrl) ?>" class="exercise-mode-option<?= $formMode === 'manual' ? ' is-active' : '' ?>">
            <strong>Zelf invullen</strong>
            <span>Je maakt de oefening helemaal zelf</span>
        </a>
        <a href="<?= e($aiModeUrl) ?>" class="exercise-mode-option<?= $formMode === 'ai' ? ' is-active' : '' ?>">
            <strong>Met AI hulp</strong>
            <span>Beschrijf wat je zoekt en laat AI meedenken</span>
        </a>
    </div>
</div>

<div class="card" id="exercise-card"<?php if ($formMode === 'ai'): ?> data-ai-phase="search"<?php endif; ?>>
    <form method="POST" enctype="multipart/form-data">
        <?= Csrf::renderInput() ?>
        <input type="hidden" name="form_mode" value="<?= e($formMode) ?>">

        <?php if ($formMode === 'ai'): ?>
        <div class="ai-search-section">
            <div id="ai-chat-panel" class="ai-chat-panel">
                <input type="hidden" id="ai-exercise-id" value="<?= (int)($exercise['id'] ?? 0) ?>">

                <div class="ai-chat-row" hidden aria-hidden="true">
                    <div class="ai-chat-field">
                        <label for="ai-model-select">AI-keuze</label>
                        <select id="ai-model-select" disabled>
                            <option value="">Laden...</option>
                        </select>
                    </div>
                </div>

                <div class="ai-chat-messages" id="ai-chat-messages"></div>
                <div class="ai-chat-status" id="ai-chat-status">AI wordt klaargezet...</div>

                <div class="ai-chat-input-row">
                    <textarea id="ai-message-input" rows="3" placeholder="Beschrijf de oefening die je zoekt..." disabled></textarea>
                    <input
                        type="file"
                        id="ai-screenshot-input"
                        accept="image/png,image/jpeg,image/webp"
                        multiple
                        hidden
                        aria-hidden="true"
                        tabindex="-1"
                        disabled
                    >
                    <div class="ai-chat-actions">
                        <button type="button" class="btn-icon-square ai-chat-icon-btn" id="ai-new-chat-btn" title="Nieuw gesprek" aria-label="Nieuw gesprek" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.13-3.36L23 10M1 14l5.36 4.36A9 9 0 0 0 20.49 15"></path></svg>
                        </button>
                        <button type="button" class="btn-icon-square ai-chat-icon-btn ai-chat-icon-btn-danger" id="ai-stop-btn" style="display:none;" title="Stop verzoek" aria-label="Stop verzoek">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="6" width="12" height="12" rx="2" ry="2"></rect></svg>
                        </button>
                        <button type="button" class="btn-icon-square ai-chat-icon-btn" id="ai-screenshot-clear-btn" style="display:none;" title="Wis screenshots" aria-label="Wis screenshots" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"></path><path d="M19 6l-1 14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1L5 6"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                        </button>
                        <button type="button" class="btn-icon-square ai-chat-icon-btn" id="ai-send-btn" title="Genereer voorstel" aria-label="Genereer voorstel" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                        </button>
                    </div>
                </div>

                <div class="ai-usage-summary" id="ai-usage-summary" hidden></div>
            </div>
        </div>
        <?php else: ?>
        <div class="exercise-mode-hint">
            AI gebruiken? Schakel bovenaan naar <strong>AI invoer</strong>.
        </div>
        <?php endif; ?>

        <div class="ai-design-section"<?php if ($formMode === 'ai'): ?> hidden<?php endif; ?>>

        <?php if ($formMode === 'ai'): ?>
        <div class="ai-design-toolbar">
            <button type="button" id="ai-back-to-search" class="btn btn-secondary btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Terug naar zoeken
            </button>
        </div>
        <?php endif; ?>

        <div id="youtube-preview-card" class="exercise-video-preview" hidden>
            <div class="exercise-video-preview-header">
                <h3>Bronvideo</h3>
                <a id="youtube-preview-link" class="exercise-video-preview-link" href="#" target="_blank" rel="noopener noreferrer">Open op YouTube</a>
            </div>
            <div class="exercise-video-preview-embed">
                <iframe
                    id="youtube-preview-frame"
                    src=""
                    title="Bronvideo preview"
                    loading="lazy"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen
                    referrerpolicy="strict-origin-when-cross-origin"
                ></iframe>
            </div>
        </div>

        <div class="exercise-details-header">
            <h2>Standaard oefenstof</h2>
            <p>Deze velden worden opgeslagen. In AI-modus kun je ze automatisch laten invullen en daarna handmatig bijsturen.</p>
        </div>

        <div class="form-group">
            <label for="title">Titel *</label>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="text" id="title" name="title" value="<?= e($exercise['title'] ?? '') ?>" required>
                <button type="submit" class="btn-icon" title="Opslaan">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label for="team_task">Teamtaak</label>
            <select id="team_task" name="team_task">
                <option value="">Selecteer teamtaak</option>
                <?php foreach (Exercise::getTeamTasks() as $task): ?>
                    <option value="<?= $task ?>" <?= ($exercise['team_task'] ?? '') === $task ? 'selected' : '' ?>><?= $task ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Doelstelling</label>
            <div class="multi-select-wrapper" id="wrapper-objective">
                <div class="multi-select-trigger" onclick="toggleMultiSelect('wrapper-objective')">
                    Selecteer doelstelling(en)
                </div>
                <div class="multi-select-options">
                    <?php
                    $objectives = Exercise::getObjectives();
                    
                    $currentObjectives = [];
                    if (!empty($exercise['training_objective'])) {
                        $decoded = json_decode($exercise['training_objective'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $currentObjectives = $decoded;
                        } else {
                            $currentObjectives = [$exercise['training_objective']];
                        }
                    }

                    foreach ($objectives as $obj) {
                        $checked = in_array($obj, $currentObjectives) ? 'checked' : '';
                        echo '<label class="multi-select-option">';
                        echo '<input type="checkbox" name="training_objective[]" value="' . e($obj) . '" ' . $checked . ' onchange="updateTrigger(\'wrapper-objective\')"> ';
                        echo e($obj);
                        echo '</label>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Voetbalhandeling</label>
            <div class="multi-select-wrapper" id="wrapper-action">
                <div class="multi-select-trigger" onclick="toggleMultiSelect('wrapper-action')">
                    Selecteer voetbalhandeling(en)
                </div>
                <div class="multi-select-options">
                    <?php
                    $actions = Exercise::getFootballActions();

                    $currentActions = [];
                    if (!empty($exercise['football_action'])) {
                        $decoded = json_decode($exercise['football_action'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $currentActions = $decoded;
                        } else {
                            $currentActions = [$exercise['football_action']];
                        }
                    }

                    foreach ($actions as $action) {
                        // Case-insensitive check for legacy data support
                        $isChecked = false;
                        foreach ($currentActions as $current) {
                            if (strtolower($current) === strtolower($action)) {
                                $isChecked = true;
                                break;
                            }
                        }
                        $checked = $isChecked ? 'checked' : '';
                        echo '<label class="multi-select-option">';
                        echo '<input type="checkbox" name="football_action[]" value="' . e($action) . '" ' . $checked . ' onchange="updateTrigger(\'wrapper-action\')"> ';
                        echo e($action);
                        echo '</label>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem; margin-bottom: 0.5rem;">
            <div class="form-group" style="grid-column: span 2; margin-bottom: 0;">
                <label>Aantal spelers (Min - Max)</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0;">
                    <div class="number-stepper" style="margin-bottom: 0;">
                        <button type="button" class="stepper-btn" onclick="updateNumber('min_players', -1)">-</button>
                        <input type="number" id="min_players" name="min_players" value="<?= e((string)($exercise['min_players'] ?? '')) ?>" placeholder="Min" style="width: 100%; flex: 1;">
                        <button type="button" class="stepper-btn" onclick="updateNumber('min_players', 1)">+</button>
                    </div>
                    <div class="number-stepper" style="margin-bottom: 0;">
                        <button type="button" class="stepper-btn" onclick="updateNumber('max_players', -1)">-</button>
                        <input type="number" id="max_players" name="max_players" value="<?= e((string)($exercise['max_players'] ?? '')) ?>" placeholder="Max" style="width: 100%; flex: 1;">
                        <button type="button" class="stepper-btn" onclick="updateNumber('max_players', 1)">+</button>
                    </div>
                </div>
            </div>

            <div><!-- Spacer --></div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="duration">Duur (minuten)</label>
                <div class="number-stepper" style="margin-bottom: 0;">
                    <button type="button" class="stepper-btn" onclick="updateNumber('duration', -5)">-</button>
                    <input type="number" id="duration" name="duration" value="<?= e((string)($exercise['duration'] ?? '')) ?>" style="width: 100%; flex: 1;">
                    <button type="button" class="stepper-btn" onclick="updateNumber('duration', 5)">+</button>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Beschrijving</label>
            <textarea id="description" name="description" rows="5"><?= e($exercise['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="variation">Moeilijker / makkelijker maken</label>
            <textarea id="variation" name="variation" rows="3"><?= e($exercise['variation'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="coach_instructions">Coach instructies</label>
            <textarea id="coach_instructions" name="coach_instructions" rows="3"><?= e($exercise['coach_instructions'] ?? '') ?></textarea>
            <small class="form-text">Aanwijzingen voor coaches om spelers optimaal te ondersteunen.</small>
        </div>

        <div class="form-group">
            <label for="source">Bron</label>
            <input type="text" id="source" name="source" value="<?= e($exercise['source'] ?? '') ?>" placeholder="Bijv. YouTube link, website URL of naam van boek">
        </div>

        <!-- Tekentool -->
        <div class="form-group">
            <label>Oefening tekenen</label>
            <div class="editor-wrapper">
                <div class="editor-toolbar" id="toolbar">
                    <!-- Row 1: Veldindeling & Setup -->
                    <div class="toolbar-row has-border">
                        <!-- Veldindeling Group -->
                        <div class="toolbar-group">
                            <div class="group-title">Veldindeling</div>
                            <div class="group-items">
                                <button type="button" id="btn-field-square" class="tool-btn" title="Vierkant veld">
                                    <div style="width: 26px; height: 26px; background: #4CAF50; border: 1px solid #fff; position: relative;">
                                    </div>
                                </button>
                                <button type="button" id="btn-field-portrait" class="tool-btn" title="Heel veld (staand)">
                                    <div style="width: 20px; height: 30px; background: #4CAF50; border: 1px solid #fff; position: relative;">
                                    </div>
                                </button>
                                <button type="button" id="btn-field-landscape" class="tool-btn" title="Heel veld (liggend)">
                                    <div style="width: 30px; height: 20px; background: #4CAF50; border: 1px solid #fff; position: relative;">
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Setup Group -->
                        <div class="toolbar-group push-right">
                            <div class="group-title">Setup</div>
                            <div class="group-items">
                                <div class="draggable-item" draggable="true" data-type="pawn"><img src="/images/assets/pawn.svg" alt="Pion"></div>
                                <div class="draggable-item" draggable="true" data-type="cone_white"><img src="/images/assets/cone_white.svg" alt="Hoedje Wit"></div>
                                <div class="draggable-item" draggable="true" data-type="cone_yellow"><img src="/images/assets/cone_yellow.svg" alt="Hoedje Geel"></div>
                                <div class="draggable-item" draggable="true" data-type="cone_orange"><img src="/images/assets/cone_orange.svg" alt="Hoedje Oranje"></div>
                                <div class="draggable-item" draggable="true" data-type="ball" style="font-size: 24px; display: flex; align-items: center; justify-content: center; cursor: grab;">⚽</div>
                                <div class="draggable-item" draggable="true" data-type="goal"><img src="/images/assets/goal.svg" alt="Doel"></div>
                                <div class="draggable-item" draggable="true" data-type="shirt_red_black"><img src="/images/assets/shirt_red_black.svg" alt="Speler Rood/Zwart"></div>
                                <div class="draggable-item" draggable="true" data-type="shirt_red_white"><img src="/images/assets/shirt_red_white.svg" alt="Speler Rood/Wit"></div>
                                <div class="draggable-item" draggable="true" data-type="shirt_orange"><img src="/images/assets/shirt_orange.svg" alt="Speler Oranje"></div>
                                <button type="button" id="tool-zone" class="tool-btn" title="Vak / Zone">
                                    <div style="width: 24px; height: 24px; border: 2px dashed #333; background: rgba(0,0,0,0.2);"></div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Voetbalacties & Bewerken -->
                    <div class="toolbar-row">
                        <!-- Voetbalacties Group -->
                        <div class="toolbar-group">
                            <div class="group-title">Voetbalacties</div>
                            <div class="group-items">
                                <button type="button" id="tool-arrow" class="tool-btn" title="Pass">
                                    <img src="/images/assets/icon_arrow.svg" alt="Pass">
                                </button>
                                <button type="button" id="tool-dashed" class="tool-btn" title="Lopen zonder bal">
                                    <img src="/images/assets/icon_arrow_dashed.svg" alt="Lopen zonder bal">
                                </button>
                                <button type="button" id="tool-zigzag" class="tool-btn" title="Dribbel">
                                    <img src="/images/assets/icon_arrow_zigzag.svg" alt="Dribbel">
                                </button>
                                <button type="button" id="tool-marker" class="tool-btn" title="Viltstift">
                                    <img src="/images/assets/icon_marker.svg" alt="Viltstift">
                                </button>
                            </div>
                        </div>

                        <!-- Bewerken Group -->
                        <div class="toolbar-group push-right">
                            <div class="group-title">Bewerken</div>
                            <div class="group-items">
                                <button type="button" id="tool-select" class="tool-btn" title="Selecteren">
                                    <img src="/images/assets/icon_select.svg" alt="Selecteren">
                                </button>
                                <button type="button" id="btn-to-back" class="tool-btn" title="Naar achtergrond">⬇️</button>
                                <button type="button" id="btn-delete-selected" class="tool-btn" title="Verwijder geselecteerde">🗑️</button>
                                <button type="button" id="btn-clear" class="btn btn-danger" style="height: 40px; align-self: center;">Wissen</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="container" class="editor-canvas-container"></div>
            </div>
            <input type="hidden" name="drawing_data" id="drawing_data" value="<?= e($exercise['drawing_data'] ?? '') ?>">
            <input type="hidden" name="drawing_image" id="drawing_image">
            <input type="hidden" name="field_type" id="field_type" value="<?= e($exercise['field_type'] ?? 'square') ?>">
        </div>

        </div><!-- /.ai-design-section -->
    </form>
</div>



<script>
function updateNumber(id, change) {
    const input = document.getElementById(id);
    let val = parseInt(input.value) || 0;
    val += change;
    if (val < 0) val = 0;
    input.value = val;
}
</script>

<script src="/js/konva.min.js"></script>
<script src="/js/konva-shared-core.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/konva-shared-core.js') ?>"></script>
<script src="/js/editor.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/editor.js') ?>"></script>
<?php if ($formMode === 'ai'): ?>
<script src="/js/exercise-ai-chat.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/exercise-ai-chat.js') ?>"></script>
<?php endif; ?>

<script>
function sanitizeYouTubeVideoId(id) {
    const value = String(id || '').trim();
    return /^[A-Za-z0-9_-]{11}$/.test(value) ? value : null;
}

function parseYouTubeIdFromUrl(candidate) {
    try {
        const url = new URL(candidate);
        const host = url.hostname.toLowerCase().replace(/^www\./, '');
        const parts = url.pathname.split('/').filter(Boolean);

        if (host === 'youtu.be') {
            return sanitizeYouTubeVideoId(parts[0] || '');
        }

        if (host.endsWith('youtube.com') || host.endsWith('youtube-nocookie.com')) {
            const fromQuery = sanitizeYouTubeVideoId(url.searchParams.get('v') || '');
            if (fromQuery) {
                return fromQuery;
            }

            if (parts[0] === 'embed' || parts[0] === 'shorts' || parts[0] === 'live') {
                return sanitizeYouTubeVideoId(parts[1] || '');
            }
        }
    } catch (e) {
        return null;
    }

    return null;
}

function extractYouTubeVideoId(rawValue) {
    const value = String(rawValue || '').trim();
    if (value === '') {
        return null;
    }

    const direct = sanitizeYouTubeVideoId(value);
    if (direct) {
        return direct;
    }

    const urlMatch = value.match(/https?:\/\/[^\s]+/i);
    if (urlMatch) {
        const parsed = parseYouTubeIdFromUrl(urlMatch[0]);
        if (parsed) {
            return parsed;
        }
    }

    const firstToken = value.split(/\s+/)[0] || '';
    const candidates = [firstToken];
    if (!/^https?:\/\//i.test(firstToken)) {
        candidates.push('https://' + firstToken);
    }

    for (const candidate of candidates) {
        const parsed = parseYouTubeIdFromUrl(candidate);
        if (parsed) {
            return parsed;
        }
    }

    return null;
}

function updateYouTubePreview() {
    const sourceInput = document.getElementById('source');
    const card = document.getElementById('youtube-preview-card');
    const frame = document.getElementById('youtube-preview-frame');
    const link = document.getElementById('youtube-preview-link');
    if (!sourceInput || !card || !frame || !link) {
        return;
    }

    const videoId = extractYouTubeVideoId(sourceInput.value);
    if (!videoId) {
        card.hidden = true;
        frame.removeAttribute('src');
        frame.dataset.videoId = '';
        link.removeAttribute('href');
        return;
    }

    const embedUrl = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(videoId) + '?rel=0&modestbranding=1';
    if (frame.dataset.videoId !== videoId) {
        frame.src = embedUrl;
        frame.dataset.videoId = videoId;
    }

    link.href = 'https://www.youtube.com/watch?v=' + encodeURIComponent(videoId);
    card.hidden = false;
}

function toggleMultiSelect(id) {
    const wrapper = document.getElementById(id);
    const options = wrapper.querySelector('.multi-select-options');
    const allOptions = document.querySelectorAll('.multi-select-options');
    
    // Close other open dropdowns
    allOptions.forEach(opt => {
        if (opt !== options) {
            opt.classList.remove('open');
        }
    });
    
    options.classList.toggle('open');
}

function updateTrigger(id) {
    const wrapper = document.getElementById(id);
    const checkboxes = wrapper.querySelectorAll('input[type="checkbox"]:checked');
    const trigger = wrapper.querySelector('.multi-select-trigger');
    
    if (checkboxes.length > 0) {
        const values = Array.from(checkboxes).map(cb => cb.parentElement.textContent.trim());
        trigger.textContent = values.join(', ');
    } else {
        // Default text based on ID
        if (id === 'wrapper-objective') {
            trigger.textContent = 'Selecteer doelstelling(en)';
        } else if (id === 'wrapper-action') {
            trigger.textContent = 'Selecteer voetbalhandeling(en)';
        }
    }
}

// Close when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.multi-select-wrapper')) {
        document.querySelectorAll('.multi-select-options').forEach(opt => {
            opt.classList.remove('open');
        });
    }
});

// Initialize triggers on load
document.addEventListener('DOMContentLoaded', function() {
    updateTrigger('wrapper-objective');
    updateTrigger('wrapper-action');

    const sourceInput = document.getElementById('source');
    if (sourceInput) {
        sourceInput.addEventListener('input', updateYouTubePreview);
        sourceInput.addEventListener('change', updateYouTubePreview);
    }
    updateYouTubePreview();
});
</script>
