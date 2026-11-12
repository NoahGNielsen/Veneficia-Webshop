<?php
// Shown after /checkout has placed an order. Customers can only see their own orders.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/products.php';
$customer = requireCustomer();

$orderId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$order = false;
if ($orderId !== false && $orderId !== null) {
    // customerId in the WHERE, so guessing another order number shows nothing
    $stmt = getDb()->prepare(
        'SELECT ordreID, ordredAt, addressLine, addressPostalCode, addressCity, shippingPrice
         FROM VeneficiaOrdre WHERE ordreID = ? AND customerId = ?'
    );
    $stmt->execute([$orderId, $customer['VeneficiaCustomerId']]);
    $order = $stmt->fetch();
}
if (!$order) {
    http_response_code(404);
    include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/statusCodesPages/404.php';
    exit;
}

$stmt = getDb()->prepare(
    'SELECT o.quantity, o.itemPrice, v.varriantName, i.itemName
     FROM VeneficiaOrdreItems o
     JOIN VeneficiaItemVariants v ON v.itemVariantId = o.itemVariantId
     JOIN VeneficiaItems i ON i.itemId = v.itemId
     WHERE o.ordreId = ?'
);
$stmt->execute([$order['ordreID']]);
$lines = $stmt->fetchAll();
$total = (float)$order['shippingPrice'];
foreach ($lines as $line) {
    $total += (float)$line['itemPrice'] * $line['quantity'];
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/products.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/products.css') ?>">
    <title>Ordrebekræftelse - Veneficia</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/navbar.php'; ?>
    <main class="basket-page">
        <h1>Tak for din ordre, <?= htmlspecialchars($customer['firstName']) ?>!</h1>
        <p>Ordrenummer: <strong><?= (int)$order['ordreID'] ?></strong><br>
            Bestilt: <?= htmlspecialchars(date('d-m-Y H:i', strtotime($order['ordredAt']))) ?></p>

        <h2>Leveres til</h2>
        <p><?= htmlspecialchars($customer['firstName'] . ' ' . $customer['lastName']) ?><br>
            <?= htmlspecialchars($order['addressLine']) ?><br>
            <?= htmlspecialchars($order['addressPostalCode'] . ' ' . $order['addressCity']) ?></p>

        <h2>Varer</h2>
        <table class="basket-table">
            <thead>
                <tr><th>Produkt</th><th>Pris</th><th>Antal</th><th>I alt</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($line['itemName']) ?>
                            <?php if ($line['varriantName'] !== ''): ?>
                                <br><?= htmlspecialchars($line['varriantName']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= formatPrice($line['itemPrice']) ?></td>
                        <td><?= (int)$line['quantity'] ?></td>
                        <td><?= formatPrice((float)$line['itemPrice'] * $line['quantity']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><th colspan="3">Fragt</th><th><?= formatPrice($order['shippingPrice']) ?></th></tr>
                <tr><th colspan="3">Total</th><th><?= formatPrice($total) ?></th></tr>
            </tfoot>
        </table>
        <p><a href="/products">Shop videre</a></p>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
