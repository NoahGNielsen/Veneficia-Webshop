<?php
// 410 Gone
if (!headers_sent()) {
    http_response_code(410);
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/statusCodePage.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/statusCodePage.css') ?>">
    <title>Siden er fjernet - Veneficia</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <main class="status-page">
        <a class="brand" href="/"><?= uiIcon('crescent') ?>Veneficia</a>
        <p class="status-code"><span class="visually-hidden">Fejl </span>410</p>
        <h1>Siden er fjernet</h1>
        <p class="status-message">Siden er fjernet for altid og kommer ikke tilbage.</p>
        <div class="status-actions">
            <a class="button" href="/">Til forsiden</a>
            <a class="button button--outlined" href="/products">Se produkterne</a>
        </div>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
