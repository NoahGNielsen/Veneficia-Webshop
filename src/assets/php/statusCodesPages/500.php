<?php
// 500 Internal Server Error
if (!headers_sent()) {
    http_response_code(500);
    header('Retry-After: 300');
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/statusCodePage.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/statusCodePage.css') ?>">
    <title>Noget gik galt - Veneficia</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <main class="status-page">
        <a class="status-brand" href="/"><img src="<?= htmlspecialchars($headerVersion('/assets/images/logo.svg')) ?>" alt="Veneficia" width="112" height="142"></a>
        <p class="status-code"><span class="visually-hidden">Fejl </span>500</p>
        <h1>Noget gik galt hos os</h1>
        <p class="status-message">Der skete en fejl hos os. Prøv igen om lidt.</p>
        <div class="status-actions">
            <a class="button" href="/">Til forsiden</a>
            <a class="button button--outlined" href="/products">Se produkterne</a>
        </div>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
