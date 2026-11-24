<?php
// 403 Forbidden
if (!headers_sent()) {
    http_response_code(403);
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/statusCodePage.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/statusCodePage.css') ?>">
    <title>Ingen adgang - Veneficia</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <main class="status-page">
        <a class="brand" href="/"><?= uiIcon('crescent') ?>Veneficia</a>
        <p class="status-code"><span class="visually-hidden">Fejl </span>403</p>
        <h1>Du har ikke adgang til denne side</h1>
        <p class="status-message">Enten er du ikke logget ind, eller også har din konto ikke adgang til siden.</p>
        <div class="status-actions">
            <a class="button" href="/">Til forsiden</a>
            <a class="button button--outlined" href="/products">Se produkterne</a>
        </div>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
