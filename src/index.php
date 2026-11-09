<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
startSession(false);
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/homepage.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/homepage.css') ?>">
    <title>Homepage - Veneficia</title>
    <meta name="description" content="">
    <meta property="og:title" content="Homepage - Veneficia">
    <meta property="og:description" content="">
</head>
<body>
    <main class="homepage">
        <h1>TEMP PAGE</h1>
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/navbar.php'; ?>
        <h1>Velkommen til Veneficia</h1>
        <p>Dette er hjemmesiden for Veneficia. Udforsk vores website for at lære mere om vores tjenester og tilbud.</p>
        <a href="/products">Se vores produkter</a>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
