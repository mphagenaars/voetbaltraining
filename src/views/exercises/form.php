<?php
$isEdit = isset($exercise);
?>

<h1><?= $isEdit ? 'Oefening bewerken' : 'Nieuwe oefening' ?></h1>

<div class="card">
    <form method="POST" enctype="multipart/form-data">
        <?= Csrf::renderInput() ?>
        <div class="form-group">
            <label for="title">Titel *</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($exercise['title'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="team_task">Teamtaak</label>
            <select id="team_task" name="team_task">
                <option value="">Selecteer teamtaak</option>
                <option value="Aanvallen" <?= ($exercise['team_task'] ?? '') === 'Aanvallen' ? 'selected' : '' ?>>Aanvallen</option>
                <option value="Omschakelen" <?= ($exercise['team_task'] ?? '') === 'Omschakelen' ? 'selected' : '' ?>>Omschakelen</option>
                <option value="Verdedigen" <?= ($exercise['team_task'] ?? '') === 'Verdedigen' ? 'selected' : '' ?>>Verdedigen</option>
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
                    $objectives = [
                        'Creëren van kansen',
                        'Dieptespel in opbouw verbeteren',
                        'Positiespel in opbouw verbeteren',
                        'Scoren verbeteren',
                        'Uitspelen van één tegen één situatie verbeteren',
                        'Omschakelen bij veroveren van de bal verbeteren',
                        'Omschakelen op moment van balverlies verbeteren',
                        'Storen en veroveren van de bal verbeteren',
                        'Verdedigen van dieptespel verbeteren',
                        'Verdedigen van één tegen één situatie verbeteren',
                        'Verdedigen wanneer de tegenstander kansen creëert verbeteren',
                        'Voorkomen van doelpunten verbeteren'
                    ];
                    
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
                        echo '<input type="checkbox" name="training_objective[]" value="' . htmlspecialchars($obj) . '" ' . $checked . ' onchange="updateTrigger(\'wrapper-objective\')"> ';
                        echo htmlspecialchars($obj);
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
                    $actions = [
                        'Kijken',
                        'Dribbelen',
                        'Passen',
                        'Schieten',
                        'Cheeta',
                        'Brug maken',
                        'Lijntje doorknippen',
                        'Jagen'
                    ];

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
                        echo '<input type="checkbox" name="football_action[]" value="' . htmlspecialchars($action) . '" ' . $checked . ' onchange="updateTrigger(\'wrapper-action\')"> ';
                        echo htmlspecialchars($action);
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
                        <input type="number" id="min_players" name="min_players" value="<?= htmlspecialchars((string)($exercise['min_players'] ?? '')) ?>" placeholder="Min" style="width: 100%; flex: 1;">
                        <button type="button" class="stepper-btn" onclick="updateNumber('min_players', 1)">+</button>
                    </div>
                    <div class="number-stepper" style="margin-bottom: 0;">
                        <button type="button" class="stepper-btn" onclick="updateNumber('max_players', -1)">-</button>
                        <input type="number" id="max_players" name="max_players" value="<?= htmlspecialchars((string)($exercise['max_players'] ?? '')) ?>" placeholder="Max" style="width: 100%; flex: 1;">
                        <button type="button" class="stepper-btn" onclick="updateNumber('max_players', 1)">+</button>
                    </div>
                </div>
            </div>

            <div><!-- Spacer --></div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="duration">Duur (minuten)</label>
                <div class="number-stepper" style="margin-bottom: 0;">
                    <button type="button" class="stepper-btn" onclick="updateNumber('duration', -5)">-</button>
                    <input type="number" id="duration" name="duration" value="<?= htmlspecialchars((string)($exercise['duration'] ?? '')) ?>" style="width: 100%; flex: 1;">
                    <button type="button" class="stepper-btn" onclick="updateNumber('duration', 5)">+</button>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Beschrijving</label>
            <textarea id="description" name="description" rows="5"><?= htmlspecialchars($exercise['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="variation">Moeilijker / makkelijker maken</label>
            <textarea id="variation" name="variation" rows="3"><?= htmlspecialchars($exercise['variation'] ?? '') ?></textarea>
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
                                <div class="draggable-item" draggable="true" data-type="ball"><img src="/images/assets/ball.svg" alt="Bal"></div>
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
            <input type="hidden" name="drawing_data" id="drawing_data" value="<?= htmlspecialchars($exercise['drawing_data'] ?? '') ?>">
            <input type="hidden" name="drawing_image" id="drawing_image">
            <input type="hidden" name="field_type" id="field_type" value="<?= htmlspecialchars($exercise['field_type'] ?? 'square') ?>">
        </div>

        <div style="margin-top: 1rem;">
            <button type="submit" class="btn"><?= $isEdit ? 'Opslaan' : 'Aanmaken' ?></button>
            <a href="/exercises" class="btn btn-outline">Annuleren</a>
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
