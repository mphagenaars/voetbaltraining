<?php
$isEdit = isset($exercise);
?>

<div class="app-bar">
    <div class="app-bar-start">
        <a href="/exercises" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title"><?= $isEdit ? 'Oefening bewerken' : 'Nieuwe oefening' ?></h1>
    </div>
</div>

<div class="card">
    <form method="POST" enctype="multipart/form-data">
        <?= Csrf::renderInput() ?>
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
<script src="/js/editor.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/editor.js') ?>"></script>

<script>
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
});
</script>
