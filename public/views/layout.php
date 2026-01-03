<!DOCTYPE html>
<html lang="it" class="<?= $isDark ? 'theme-dark' : 'theme-light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Controllo Accessi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
</head>
<body class="ui-concept">
<div class="container py-4">
    <div class="app-shell">
        <?php include __DIR__ . '/partials/hero.php'; ?>

        <div class="p-4">
            <?php if ($view === 'home'): ?>
                <?php include __DIR__ . '/partials/home.php'; ?>
            <?php elseif ($view === 'history' && $canViewHistory): ?>
                <?php include __DIR__ . '/partials/history.php'; ?>
            <?php elseif ($view === 'audit' && $canViewAudit): ?>
                <?php include __DIR__ . '/partials/audit.php'; ?>
            <?php endif; ?>

            <?php include __DIR__ . '/partials/alerts.php'; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/modals.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.2.0/dist/signature_pad.umd.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
