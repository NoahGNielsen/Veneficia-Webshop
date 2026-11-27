<?php
// 423 Locked
if (!headers_sent()) {
    http_response_code(423);
    header('Retry-After: 300');
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/statusCodePage.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/statusCodePage.css') ?>">
    <title>Låst - Veneficia</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <main class="status-page">
        <a class="status-brand" href="/"><img src="<?= htmlspecialchars($headerVersion('/assets/images/logo.svg')) ?>" alt="Veneficia" width="112" height="142"></a>
        <p class="status-code"><span class="visually-hidden">Fejl </span>423</p>
        <h1>Det er låst lige nu</h1>
        <p class="status-message">Det, du prøver at ændre, er låst lige nu. Prøv igen senere.</p>
        <div class="status-actions">
            <a class="button" href="/">Til forsiden</a>
            <a class="button button--outlined" href="/products">Se produkterne</a>
        </div>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
