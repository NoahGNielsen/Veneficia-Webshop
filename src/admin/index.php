<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
$employee = requireEmployee();

// One button per admin page. Add new pages here.
$adminPages = [
    ['href' => '/admin/employees', 'title' => 'Medarbejdere', 'description' => 'Se og tilføj medarbejdere.'],
    ['href' => '/admin/resetPassword', 'title' => 'Skift adgangskode', 'description' => 'Skift din egen adgangskode.'],
];
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin.css') ?>">
    <title>Forside - Veneficia admin</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/adminBar.php'; ?>
    <main class="admin-page admin-home">
        <h1>Admin</h1>
        <nav class="admin-buttons">
            <?php foreach ($adminPages as $page): ?>
                <a class="admin-button" href="<?= htmlspecialchars($page['href']) ?>">
                    <strong><?= htmlspecialchars($page['title']) ?></strong>
                    <span><?= htmlspecialchars($page['description']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
