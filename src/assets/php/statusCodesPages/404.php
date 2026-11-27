<?php
// 404 Not Found
if (!headers_sent()) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/statusCodePage.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/statusCodePage.css') ?>">
    <title>Siden findes ikke - Veneficia</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <main class="status-page">
        <a class="status-brand" href="/"><img src="<?= htmlspecialchars($headerVersion('/assets/images/logo.svg')) ?>" alt="Veneficia" width="112" height="142"></a>
        <p class="status-code"><span class="visually-hidden">Fejl </span>404</p>
        <h1>Vi kan ikke finde siden</h1>
        <p class="status-message">Siden findes ikke, eller den er blevet flyttet.</p>
        <div class="status-actions">
            <a class="button" href="/">Til forsiden</a>
            <a class="button button--outlined" href="/products">Se produkterne</a>
        </div>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
