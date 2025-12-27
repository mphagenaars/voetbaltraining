<?php
$totalDuration = 0;
foreach ($training['exercises'] as $ex) {
    $totalDuration += $ex['training_duration'] ?: $ex['duration'] ?: 0;
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <div>
        <h1><?= htmlspecialchars($training['title']) ?></h1>
        <p class="text-muted"><?= htmlspecialchars($training['description'] ?? '') ?></p>
    </div>
    <div style="text-align: right;">
        <span style="font-size: 1.2rem; font-weight: bold;">⏱️ <?= $totalDuration ?> min</span>
        <div style="margin-top: 0.5rem;">
            <button onclick="window.print()" class="btn btn-sm btn-outline">🖨️ Print / PDF</button>
        </div>
    </div>
</div>

<div class="training-timeline">
    <?php foreach ($training['exercises'] as $index => $exercise): ?>
        <div class="card" style="margin-bottom: 1rem; border-left: 5px solid var(--primary-color);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <h3 style="margin-top: 0;">
                    <span style="color: var(--primary-color); margin-right: 0.5rem;">#<?= $index + 1 ?></span>
                    <?= htmlspecialchars($exercise['title']) ?>
                </h3>
                <span style="background: #eee; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: bold;">
                    <?= $exercise['training_duration'] ?: $exercise['duration'] ?: '?' ?> min
                </span>
            </div>

            <?php if (!empty($exercise['image_path'])): ?>
                <div style="margin: 1rem 0; text-align: center;">
                    <img src="/uploads/<?= htmlspecialchars($exercise['image_path']) ?>" alt="<?= htmlspecialchars($exercise['title']) ?>" style="max-width: 100%; max-height: 300px; border-radius: 4px;">
                </div>
            <?php endif; ?>

            <div style="margin-top: 0.5rem;">
                <?= nl2br(htmlspecialchars($exercise['description'] ?? '')) ?>
            </div>

            <?php if (!empty($exercise['requirements'])): ?>
                <div style="margin-top: 1rem; padding-top: 0.5rem; border-top: 1px solid #eee; font-size: 0.9rem; color: #666;">
                    <strong>Benodigdheden:</strong> <?= htmlspecialchars($exercise['requirements']) ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<style>
@media print {
    header, .btn { display: none !important; }
    .card { break-inside: avoid; border: 1px solid #ccc; box-shadow: none; }
    body { background: white; }
}
</style>


