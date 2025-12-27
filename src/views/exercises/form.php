<?php
$isEdit = isset($exercise);
?>

<h1><?= $isEdit ? 'Oefening bewerken' : 'Nieuwe oefening' ?></h1>

<div class="card">
    <form method="POST" enctype="multipart/form-data">
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
            <label for="training_objective">Doelstelling</label>
            <select id="training_objective" name="training_objective">
                <option value="">Selecteer doelstelling</option>
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
                foreach ($objectives as $obj) {
                    $selected = ($exercise['training_objective'] ?? '') === $obj ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($obj) . "\" $selected>" . htmlspecialchars($obj) . "</option>";
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label for="football_action">Voetbalhandeling</label>
            <select id="football_action" name="football_action">
                <option value="">Selecteer voetbalhandeling</option>
                <?php
                $actions = [
                    'kijken',
                    'dribbelen',
                    'passen',
                    'schieten',
                    'cheeta',
                    'brug maken',
                    'lijntje doorknippen',
                    'jagen'
                ];
                foreach ($actions as $action) {
                    $selected = ($exercise['football_action'] ?? '') === $action ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($action) . "\" $selected>" . htmlspecialchars($action) . "</option>";
                }
                ?>
            </select>
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
                                <button type="button" id="btn-field-portrait" class="tool-btn" title="Heel veld (staand)">
                                    <div style="width: 20px; height: 30px; background: #4CAF50; border: 1px solid #fff; position: relative;">
                                        <div style="position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: #fff;"></div>
                                        <div style="position: absolute; top: 50%; left: 50%; width: 6px; height: 6px; border: 1px solid #fff; border-radius: 50%; transform: translate(-50%, -50%);"></div>
                                    </div>
                                </button>
                                <button type="button" id="btn-field-landscape" class="tool-btn" title="Heel veld (liggend)">
                                    <div style="width: 30px; height: 20px; background: #4CAF50; border: 1px solid #fff; position: relative;">
                                        <div style="position: absolute; left: 50%; top: 0; bottom: 0; width: 1px; background: #fff;"></div>
                                        <div style="position: absolute; top: 50%; left: 50%; width: 6px; height: 6px; border: 1px solid #fff; border-radius: 50%; transform: translate(-50%, -50%);"></div>
                                    </div>
                                </button>
                                <button type="button" id="btn-field-square" class="tool-btn" title="Half veld">
                                    <div style="width: 26px; height: 26px; background: #4CAF50; border: 1px solid #fff; position: relative;">
                                        <div style="position: absolute; bottom: 0; left: 50%; width: 10px; height: 5px; border: 1px solid #fff; transform: translateX(-50%); border-bottom: none;"></div>
                                        <div style="position: absolute; top: -8px; left: 50%; width: 14px; height: 14px; border: 1px solid #fff; border-radius: 50%; transform: translateX(-50%);"></div>
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
                                <div class="draggable-item" draggable="true" data-type="shirt_blue"><img src="/images/assets/shirt_blue.svg" alt="Speler Blauw"></div>
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
<script src="/js/editor.js"></script>
