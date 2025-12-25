<?php
$isEdit = isset($exercise);
$pageTitle = ($isEdit ? 'Oefening bewerken' : 'Nieuwe oefening') . ' - Trainer Bobby';
require __DIR__ . '/../layout/header.php';
?>

<h1><?= $isEdit ? 'Oefening bewerken' : 'Nieuwe oefening' ?></h1>

<div class="card">
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="title">Titel *</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($exercise['title'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="description">Beschrijving</label>
            <textarea id="description" name="description" rows="5"><?= htmlspecialchars($exercise['description'] ?? '') ?></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="players">Aantal spelers</label>
                <div class="number-stepper">
                    <button type="button" class="stepper-btn" onclick="updateNumber('players', -1)">-</button>
                    <input type="number" id="players" name="players" value="<?= htmlspecialchars((string)($exercise['players'] ?? '')) ?>">
                    <button type="button" class="stepper-btn" onclick="updateNumber('players', 1)">+</button>
                </div>
            </div>

            <div class="form-group">
                <label for="duration">Duur (minuten)</label>
                <div class="number-stepper">
                    <button type="button" class="stepper-btn" onclick="updateNumber('duration', -5)">-</button>
                    <input type="number" id="duration" name="duration" value="<?= htmlspecialchars((string)($exercise['duration'] ?? '')) ?>">
                    <button type="button" class="stepper-btn" onclick="updateNumber('duration', 5)">+</button>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="tags">Labels (gescheiden door komma's)</label>
            <?php
            $tagString = '';
            if (isset($exercise['tags']) && is_array($exercise['tags'])) {
                $tagNames = array_map(fn($t) => $t['name'], $exercise['tags']);
                $tagString = implode(', ', $tagNames);
            }
            ?>
            <input type="text" id="tags" name="tags" value="<?= htmlspecialchars($tagString) ?>" placeholder="Bijv. Aanvallen, Omschakelen, Techniek">
        </div>

        <!-- Tekentool -->
        <div class="form-group">
            <label>Oefening tekenen</label>
            <div class="editor-wrapper">
                <div class="editor-toolbar" id="toolbar">
                    <div class="draggable-item" draggable="true" data-type="pawn"><img src="/images/assets/pawn.svg" alt="Pion"></div>
                    <div class="draggable-item" draggable="true" data-type="cone_white"><img src="/images/assets/cone_white.svg" alt="Hoedje Wit"></div>
                    <div class="draggable-item" draggable="true" data-type="cone_yellow"><img src="/images/assets/cone_yellow.svg" alt="Hoedje Geel"></div>
                    <div class="draggable-item" draggable="true" data-type="cone_orange"><img src="/images/assets/cone_orange.svg" alt="Hoedje Oranje"></div>
                    <div class="draggable-item" draggable="true" data-type="ball"><img src="/images/assets/ball.svg" alt="Bal"></div>
                    <div class="draggable-item" draggable="true" data-type="goal"><img src="/images/assets/goal.svg" alt="Doel"></div>
                    <div class="draggable-item" draggable="true" data-type="shirt_blue"><img src="/images/assets/shirt_blue.svg" alt="Speler Blauw"></div>
                    <div class="draggable-item" draggable="true" data-type="shirt_orange"><img src="/images/assets/shirt_orange.svg" alt="Speler Oranje"></div>
                    <!-- Tools -->
                    <div style="border-left: 1px solid #ccc; margin: 0 10px;"></div>
                    
                    <button type="button" id="tool-arrow" class="tool-btn" title="Pass">
                        <img src="/images/assets/icon_arrow.svg" alt="Pass">
                    </button>
                    <button type="button" id="tool-dashed" class="tool-btn" title="Lopen zonder bal">
                        <img src="/images/assets/icon_arrow_dashed.svg" alt="Lopen zonder bal">
                    </button>
                    <button type="button" id="tool-zigzag" class="tool-btn" title="Dribbel">
                        <img src="/images/assets/icon_arrow_zigzag.svg" alt="Dribbel">
                    </button>

                    <div style="border-left: 1px solid #ccc; margin: 0 10px; margin-left: auto;"></div>

                    <button type="button" id="tool-select" class="btn btn-sm">Selecteren</button>
                    <button type="button" id="btn-clear" class="btn btn-sm btn-danger">Wissen</button>
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

<?php require __DIR__ . '/../layout/footer.php'; ?>

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
