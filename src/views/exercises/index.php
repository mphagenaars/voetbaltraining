<?php
$pageTitle = 'Oefenstof - Trainer Bobby';
require __DIR__ . '/../layout/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <h1>Oefenstof</h1>
    <a href="/exercises/create" class="btn">Nieuwe Oefening</a>
</div>

<div class="card" style="margin-bottom: 1rem;">
    <form method="GET" action="/exercises" style="display: flex; gap: 1rem; align-items: flex-end;">
        <div style="flex-grow: 1; margin-bottom: 0;">
            <label for="q">Zoeken</label>
            <input type="text" id="q" name="q" value="<?= htmlspecialchars($query ?? '') ?>" placeholder="Titel of beschrijving...">
        </div>
        <div style="min-width: 200px; margin-bottom: 0;">
            <label for="tag">Filter op label</label>
            <select id="tag" name="tag" style="width: 100%;">
                <option value="">Alle labels</option>
                <?php foreach ($allTags as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= ($tagFilter ?? null) === $t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-outline">Zoeken</button>
        <?php if (!empty($query) || !empty($tagFilter)): ?>
            <a href="/exercises" class="btn btn-outline" style="border: none;">Reset</a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($exercises)): ?>
    <div class="card">
        <p>Geen oefeningen gevonden.</p>
    </div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($exercises as $exercise): ?>
            <div class="card">
                <?php if (!empty($exercise['image_path'])): ?>
                    <div style="margin-bottom: 1rem; text-align: center;">
                        <img src="/uploads/<?= htmlspecialchars($exercise['image_path']) ?>" alt="<?= htmlspecialchars($exercise['title']) ?>" style="max-width: 100%; max-height: 200px; border-radius: 4px;">
                    </div>
                <?php endif; ?>
                <h3><?= htmlspecialchars($exercise['title']) ?></h3>
                <p><?= nl2br(htmlspecialchars(substr($exercise['description'] ?? '', 0, 100))) ?>...</p>
                
                <div style="margin-top: 1rem; font-size: 0.9rem; color: #666;">
                    <?php if ($exercise['players']): ?>
                        <span>👥 <?= $exercise['players'] ?> spelers</span>
                    <?php endif; ?>
                    <?php if ($exercise['duration']): ?>
                        <span style="margin-left: 0.5rem;">⏱️ <?= $exercise['duration'] ?> min</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($exercise['tags'])): ?>
                    <div style="margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.25rem;">
                        <?php foreach ($exercise['tags'] as $tag): ?>
                            <span style="background: #eee; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.8rem; color: #555;">
                                <?= htmlspecialchars($tag['name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <a href="/exercises/edit?id=<?= $exercise['id'] ?>" class="btn btn-sm btn-outline">Bewerken</a>
                    <form method="POST" action="/exercises/delete" onsubmit="return confirm('Weet je zeker dat je deze oefening wilt verwijderen?');" style="margin: 0;">
                        <input type="hidden" name="id" value="<?= $exercise['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline" style="color: var(--danger-color); border-color: var(--danger-color);">Verwijderen</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
