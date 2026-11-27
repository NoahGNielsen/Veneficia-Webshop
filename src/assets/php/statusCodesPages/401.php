<?php
// 401 Unauthorized
if (!headers_sent()) {
    http_response_code(401);
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/statusCodePage.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/statusCodePage.css') ?>">
    <title>Log ind for at fortsætte - Veneficia</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <main class="status-page">
        <a class="status-brand" href="/"><img src="<?= htmlspecialchars($headerVersion('/assets/images/logo.svg')) ?>" alt="Veneficia" width="112" height="142"></a>
        <p class="status-code"><span class="visually-hidden">Fejl </span>401</p>
        <h1>Du skal logge ind først</h1>
        <p class="status-message">Du skal være logget ind for at se denne side.</p>
        <div class="status-actions">
            <a class="button" href="/">Til forsiden</a>
            <a class="button button--outlined" href="/products">Se produkterne</a>
        </div>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
