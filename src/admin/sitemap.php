<?php
// Builds /sitemap-products.xml with every product. Saving a product or category keeps it up to date by itself; this
// page is for the first time (the file isn't deployed) and for fixing it by hand.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/sitemapProducts.php';
$employee = requireEmployee();

$error = '';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf($_POST['csrfToken'] ?? null)) {
        $error = 'Siden var udløbet. Prøv igen.';
    } elseif (($count = writeProductSitemap(getDb())) === null) {
        $error = 'Sitemappet kunne ikke skrives. Se fejlloggen.';
    } else {
        $message = "Sitemappet er bygget med $count produkter.";
    }
}
$file = $_SERVER['DOCUMENT_ROOT'] . PRODUCT_SITEMAP_FILE;
$builtAt = is_file($file) ? date('d-m-Y H:i', filemtime($file)) : null;
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin.css') ?>">
    <title>Sitemap - Veneficia admin</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/adminBar.php'; ?>
    <main id="indhold" class="admin-page">
        <h1>Produkt-sitemap</h1>
        <?php if ($error !== ''): ?>
            <p class="auth-error" role="alert"><?= htmlspecialchars($error) ?></p>
        <?php elseif ($message !== ''): ?>
            <p class="admin-success" role="status"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>
        <p>
            <a href="<?= htmlspecialchars(PRODUCT_SITEMAP_FILE) ?>"><?= htmlspecialchars(PRODUCT_SITEMAP_FILE) ?></a>
            <?= $builtAt === null ? 'findes ikke endnu.' : 'blev sidst bygget ' . htmlspecialchars($builtAt) . '.' ?>
            Den opdateres selv, når en vare eller kategori gemmes.
        </p>
        <form method="post">
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">
            <button type="submit" class="admin-button">Byg sitemap med alle produkter</button>
        </form>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
