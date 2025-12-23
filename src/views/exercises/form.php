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
                <input type="number" id="players" name="players" value="<?= htmlspecialchars((string)($exercise['players'] ?? '')) ?>">
            </div>

            <div class="form-group">
                <label for="duration">Duur (minuten)</label>
                <input type="number" id="duration" name="duration" value="<?= htmlspecialchars((string)($exercise['duration'] ?? '')) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="requirements">Benodigdheden</label>
            <input type="text" id="requirements" name="requirements" value="<?= htmlspecialchars($exercise['requirements'] ?? '') ?>" placeholder="Bijv. 10 pionnen, 5 hesjes">
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

        <div class="form-group">
            <label for="image">Afbeelding (optioneel)</label>
            <?php if (!empty($exercise['image_path'])): ?>
                <div style="margin-bottom: 0.5rem;">
                    <img src="/uploads/<?= htmlspecialchars($exercise['image_path']) ?>" alt="Huidige afbeelding" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            <?php endif; ?>
            <input type="file" id="image" name="image" accept="image/*">
            <small style="color: #666;">Toegestane formaten: JPG, PNG, WEBP</small>
        </div>

        <div style="margin-top: 1rem;">
            <button type="submit" class="btn"><?= $isEdit ? 'Opslaan' : 'Aanmaken' ?></button>
            <a href="/exercises" class="btn btn-outline">Annuleren</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
