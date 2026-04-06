<?php
$isEdit = isset($match) && is_array($match);
$formTitle = $isEdit ? 'Wedstrijd Bewerken' : 'Nieuwe Wedstrijd';
$submitLabel = $isEdit ? 'Opslaan' : 'Aanmaken';
$formData = is_array($formData ?? null) ? $formData : [];

$matchTimestamp = $isEdit ? strtotime((string)($match['date'] ?? '')) : false;
$defaultDate = $matchTimestamp !== false ? date('Y-m-d\TH:i', $matchTimestamp) : date('Y-m-d\TH:i');
$rawDate = trim((string)($formData['date'] ?? $defaultDate));
$timestamp = strtotime($rawDate);
$dateValue = $timestamp !== false ? date('Y-m-d\TH:i', $timestamp) : $defaultDate;

$opponentValue = trim((string)($formData['opponent'] ?? ($isEdit ? (string)($match['opponent'] ?? '') : '')));
$isHomeValue = (int)($formData['is_home'] ?? ($isEdit ? (int)($match['is_home'] ?? 1) : 1));
$formationValue = trim((string)($formData['formation'] ?? ($isEdit ? (string)($match['formation'] ?? '11-vs-11') : '11-vs-11')));
if ($formationValue === '') {
    $formationValue = '11-vs-11';
}
$formationTemplateIdValue = trim((string)($formData['formation_template_id'] ?? ($isEdit ? (string)($match['formation_template_id'] ?? '') : '')));

$formations = [
    '6-vs-6' => '6 tegen 6',
    '8-vs-8' => '8 tegen 8',
    '11-vs-11' => '11 tegen 11',
];
if (!array_key_exists($formationValue, $formations)) {
    $formations[$formationValue] = $formationValue;
}

$speelwijzenList = $speelwijzen ?? [];

$action = $isEdit ? '/matches/edit?id=' . (int)$match['id'] : '/matches/create';
$backUrl = $isEdit ? '/matches/view?id=' . (int)$match['id'] : '/matches';
?>

<div class="app-bar">
    <div class="app-bar-start">
        <a href="<?= $backUrl ?>" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title"><?= $formTitle ?></h1>
    </div>
</div>

<form action="<?= $action ?>" method="POST">
    <?= Csrf::renderInput() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int)$match['id'] ?>">
    <?php endif; ?>

    <div class="tb-card tb-form-card-narrow">
        <div class="form-group">
            <label for="opponent">Tegenstander *</label>
            <input type="text" id="opponent" name="opponent" required class="form-control" value="<?= e($opponentValue) ?>">
        </div>

        <div class="form-group">
            <label for="date">Datum & Tijd *</label>
            <input type="datetime-local" id="date" name="date" required class="form-control" value="<?= e($dateValue) ?>">
        </div>

        <div class="form-group">
            <label>Locatie</label>
            <div class="tb-radio-group">
                <label><input type="radio" name="is_home" value="1" <?= $isHomeValue === 1 ? 'checked' : '' ?>> Thuis</label>
                <label><input type="radio" name="is_home" value="0" <?= $isHomeValue === 0 ? 'checked' : '' ?>> Uit</label>
            </div>
        </div>

        <div class="form-group">
            <label for="formation_template_id">Speelwijze</label>
            <select id="formation_template_id" name="formation_template_id" class="form-control">
                <option value="">-- Handmatige formatie --</option>
                <?php
                $ownTemplates = array_filter($speelwijzenList, fn($s) => !empty($s['team_id']));
                $sharedTemplates = array_filter($speelwijzenList, fn($s) => empty($s['team_id']) || !empty($s['is_shared']));
                if (!empty($ownTemplates)): ?>
                    <optgroup label="Team">
                        <?php foreach ($ownTemplates as $sw): ?>
                            <option value="<?= (int)$sw['id'] ?>" data-format="<?= e($sw['format']) ?>" <?= $formationTemplateIdValue === (string)$sw['id'] ? 'selected' : '' ?>><?= e($sw['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif;
                if (!empty($sharedTemplates)): ?>
                    <optgroup label="Gedeeld">
                        <?php foreach ($sharedTemplates as $sw): ?>
                            <option value="<?= (int)$sw['id'] ?>" data-format="<?= e($sw['format']) ?>" <?= $formationTemplateIdValue === (string)$sw['id'] ? 'selected' : '' ?>><?= e($sw['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="formation">Formatie</label>
            <select id="formation" name="formation" class="form-control">
                <?php foreach ($formations as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $formationValue === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <script>
        (function() {
            const templateSelect = document.getElementById('formation_template_id');
            const formationSelect = document.getElementById('formation');
            if (!templateSelect || !formationSelect) return;

            function onTemplateChange() {
                const opt = templateSelect.selectedOptions[0];
                const format = opt?.dataset?.format || '';
                if (format) {
                    for (const o of formationSelect.options) {
                        if (o.value === format) { o.selected = true; break; }
                    }
                    formationSelect.disabled = true;
                } else {
                    formationSelect.disabled = false;
                }
            }

            templateSelect.addEventListener('change', onTemplateChange);
            onTemplateChange();

            // Re-enable disabled selects before submit so values are sent
            formationSelect.closest('form')?.addEventListener('submit', () => {
                formationSelect.disabled = false;
            });
        })();
        </script>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $submitLabel ?></button>
            <a href="<?= $backUrl ?>" class="btn btn-secondary">Annuleren</a>
        </div>
    </div>
</form>
