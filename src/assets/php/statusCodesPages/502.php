<?php
// 502 Bad Gateway
if (!headers_sent()) {
    http_response_code(502);
    header('Retry-After: 300');
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/statusCodePage.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/statusCodePage.css') ?>">
    <title>Forbindelsesfejl - Veneficia</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <main class="status-page">
        <a class="brand" href="/"><?= uiIcon('crescent') ?>Veneficia</a>
        <p class="status-code"><span class="visually-hidden">Fejl </span>502</p>
        <h1>Noget gik galt undervejs</h1>
        <p class="status-message">Serveren fik et ugyldigt svar. Prøv igen.</p>
        <div class="status-actions">
            <a class="button" href="/">Til forsiden</a>
            <a class="button button--outlined" href="/products">Se produkterne</a>
        </div>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
