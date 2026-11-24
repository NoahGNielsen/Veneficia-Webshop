<?php
// The shop's terms and conditions. Only a page shell for now: the group writes the legal text.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
startSession(false);
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <title>Handelsbetingelser - Veneficia</title>
    <meta name="description" content="Veneficias handelsbetingelser.">
    <meta property="og:title" content="Handelsbetingelser - Veneficia">
    <meta property="og:description" content="Veneficias handelsbetingelser.">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/navbar.php'; ?>
    <main id="indhold" class="legal-page">
        <h1>Handelsbetingelser</h1>
        <p class="type-body-large">Teksten er på vej.</p>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
