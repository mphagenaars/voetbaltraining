<?php
$pageTitle = 'Home - Voetbaltraining';
require __DIR__ . '/layout/header.php';
?>

<div class="text-center" style="padding: 4rem 0;">
    <h1>Welkom bij de Voetbal Trainingsapp</h1>
    <p class="mb-2">De tool voor trainers om oefenstof te beheren en trainingen voor te bereiden.</p>
    
    <div class="mt-2">
        <a href="/login" class="btn">Inloggen</a>
        <a href="/register" class="btn btn-outline" style="margin-left: 0.5rem;">Registreren met code</a>
    </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
