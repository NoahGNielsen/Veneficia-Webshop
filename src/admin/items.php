<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
$employee = requireEmployee();

$items = getDb()->query(
    'SELECT i.itemId, i.itemName, COUNT(v.itemVariantId) AS variantCount, MIN(v.varriantPrice) AS minPrice, SUM(v.inventoryCount) AS stock
     FROM VeneficiaItems i LEFT JOIN VeneficiaItemVariants v ON v.itemId = i.itemId
     GROUP BY i.itemId ORDER BY i.itemId DESC'
)->fetchAll();
$addedId = filter_var($_GET['added'] ?? null, FILTER_VALIDATE_INT);
$added = $addedId ? array_column($items, 'itemName', 'itemId')[$addedId] ?? null : null;
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin.css') ?>">
    <title>Varer - Veneficia admin</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/adminBar.php'; ?>
    <main class="admin-page admin-list">
        <div class="admin-list-head">
            <h1>Varer</h1>
            <a class="admin-button" href="/admin/item">Ny vare</a>
        </div>
        <?php if ($added !== null): ?>
            <p class="admin-success" role="status"><?= htmlspecialchars($added) ?> er tilføjet.</p>
        <?php endif; ?>
        <?php if (!$items): ?>
            <p>Der er ingen varer endnu.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>ID</th><th>Navn</th><th>Varianter</th><th>Fra pris</th><th>På lager</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr class="admin-row">
                            <td><?= (int)$item['itemId'] ?></td>
                            <td><a href="/admin/item?id=<?= (int)$item['itemId'] ?>"><?= htmlspecialchars($item['itemName']) ?></a></td>
                            <td><?= (int)$item['variantCount'] ?></td>
                            <td><?= $item['minPrice'] === null ? '-' : number_format((float)$item['minPrice'], 2, ',', '.') . ' kr.' ?></td>
                            <td><?= (int)$item['stock'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
