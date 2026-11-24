<?php
// 503 Service Unavailable
if (!headers_sent()) {
    http_response_code(503);
    header('Retry-After: 300');
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/statusCodePage.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/statusCodePage.css') ?>">
    <title>Midlertidigt lukket - Veneficia</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <main class="status-page">
        <a class="brand" href="/"><?= uiIcon('crescent') ?>Veneficia</a>
        <p class="status-code"><span class="visually-hidden">Fejl </span>503</p>
        <h1>Vi er midlertidigt nede</h1>
        <p class="status-message">Siden er nede på grund af vedligeholdelse eller travlhed. Prøv igen om et par minutter.</p>
        <div class="status-actions">
            <a class="button" href="/">Til forsiden</a>
            <a class="button button--outlined" href="/products">Se produkterne</a>
        </div>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
