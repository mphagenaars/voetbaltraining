<?php
$totalDuration = 0;
foreach ($training['exercises'] as $ex) {
    $totalDuration += $ex['training_duration'] ?: $ex['duration'] ?: 0;
}

$displayTitle = e($training['title']);
if (!empty($training['training_date'])) {
    $ts = strtotime($training['training_date']);
    $days = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];
    $months = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
    $displayTitle = $days[date('w', $ts)] . ', ' . date('j', $ts) . ' ' . $months[date('n', $ts) - 1] . ' ' . date('Y', $ts);
}
?>

<div class="app-bar">
    <div class="app-bar-start">
        <a href="/trainings" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title" style="font-size: 1.25rem;"><?= $displayTitle ?></h1>
    </div>
    <div class="app-bar-actions">
        <button onclick="shareTraining()" class="btn-icon-round" title="Delen">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
        </button>
        <button onclick="window.print()" class="btn-icon-round" title="Print / PDF">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        </button>
        <a href="/trainings/edit?id=<?= $training['id'] ?>" class="btn-icon-round" title="Bewerken">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
        </a>
    </div>
</div>

<div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: flex-start;">
    <p class="text-muted" style="margin: 0;"><?= e($training['description'] ?? '') ?></p>
    <span style="font-size: 1.1rem; font-weight: bold; white-space: nowrap; margin-left: 1rem;">⏱️ <?= $totalDuration ?> min</span>
</div>

<div class="training-timeline">
    <?php foreach ($training['exercises'] as $index => $exercise): ?>
        <div class="card" style="margin-bottom: 1rem; border-left: 5px solid var(--primary-color);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <h3 style="margin-top: 0;">
                    <span style="color: var(--primary-color); margin-right: 0.5rem;">#<?= $index + 1 ?></span>
                    <?= e($exercise['title']) ?>
                </h3>
                <span style="background: #eee; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: bold;">
                    <?= $exercise['training_duration'] ?: $exercise['duration'] ?: '?' ?> min
                </span>
            </div>

            <?php if (!empty($exercise['image_path'])): ?>
                <div style="margin: 1rem 0; text-align: center;">
                    <img src="/uploads/<?= e($exercise['image_path']) ?>" alt="<?= e($exercise['title']) ?>" style="max-width: 100%; max-height: 300px; border-radius: 4px;">
                </div>
            <?php endif; ?>

            <div style="margin-top: 0.5rem;">
                <?= nl2br(e($exercise['description'] ?? '')) ?>
            </div>

            <?php if (!empty($exercise['requirements'])): ?>
                <div style="margin-top: 1rem; padding-top: 0.5rem; border-top: 1px solid #eee; font-size: 0.9rem; color: #666;">
                    <strong>Benodigdheden:</strong> <?= e($exercise['requirements']) ?>
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

<script>
function shareTraining() {
    const shareData = {
        title: <?= json_encode($displayTitle) ?>,
        text: 'Bekijk deze training op Voetbaltraining',
        url: window.location.href,
    };

    if (navigator.share) {
        navigator.share(shareData)
            .catch((err) => console.log('Error sharing:', err));
    } else {
        navigator.clipboard.writeText(window.location.href).then(() => {
            alert('Link gekopieerd naar klembord!');
        });
    }
}
</script>


