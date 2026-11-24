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
        'SELECT o.ordreID, o.ordredAt, o.addressLine, o.addressPostalCode, o.addressCity, o.shippingPrice, o.discountAmount, d.code
         FROM VeneficiaOrdre o LEFT JOIN VeneficiaDiscountCodes d ON d.discountCodeID = o.discountCodeId
         WHERE o.ordreID = ? AND o.customerId = ?'
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
$total = (float)$order['shippingPrice'] - (float)$order['discountAmount'];
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
    <main id="indhold" class="basket-page confirmation-page">
        <ol class="steps">
            <li>Kurv</li>
            <li>Levering</li>
            <li aria-current="step">Bekræftelse</li>
        </ol>

        <header class="confirmation-header">
            <span class="arch-icon arch-icon--large"><?= uiIcon('crescent', 'icon--40') ?></span>
            <h1>Tak for din ordre, <?= htmlspecialchars($customer['firstName']) ?>!</h1>
            <p>Vi har modtaget din bestilling.</p>
            <p class="order-number"><span class="type-label-medium">Ordrenummer</span> <strong><?= (int)$order['ordreID'] ?></strong></p>
            <p class="text-muted">Bestilt <?= htmlspecialchars(date('d-m-Y H:i', strtotime($order['ordredAt']))) ?></p>
        </header>

        <div class="card confirmation-card">
            <section aria-labelledby="leveres-til">
                <h2 id="leveres-til" class="type-title-large">Leveres til</h2>
                <p><?= htmlspecialchars($customer['firstName'] . ' ' . $customer['lastName']) ?><br>
                    <?= htmlspecialchars($order['addressLine']) ?><br>
                    <?= htmlspecialchars($order['addressPostalCode'] . ' ' . $order['addressCity']) ?></p>
            </section>

            <section aria-labelledby="varer">
                <h2 id="varer" class="type-title-large">Varer</h2>
                <table class="data-table order-table">
                    <thead>
                        <tr><th scope="col">Produkt</th><th scope="col" class="num">Pris</th><th scope="col" class="num">Antal</th><th scope="col" class="num">I alt</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td>
                                    <span class="line-name"><?= htmlspecialchars($line['itemName']) ?></span>
                                    <?php if ($line['varriantName'] !== ''): ?>
                                        <br><span class="line-variant"><?= htmlspecialchars($line['varriantName']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="num" data-label="Pris"><?= formatPrice($line['itemPrice']) ?></td>
                                <td class="num" data-label="Antal"><?= (int)$line['quantity'] ?></td>
                                <td class="num" data-label="I alt"><?= formatPrice((float)$line['itemPrice'] * $line['quantity']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php if ($order['code'] !== null || (float)$order['discountAmount'] > 0): ?>
                            <tr><th scope="row" colspan="3">Rabat<?= $order['code'] !== null ? ' (' . htmlspecialchars($order['code']) . ')' : '' ?></th><td class="num">-<?= formatPrice($order['discountAmount']) ?></td></tr>
                        <?php endif; ?>
                        <tr><th scope="row" colspan="3">Fragt</th><td class="num"><?= formatPrice($order['shippingPrice']) ?></td></tr>
                        <tr class="total-row"><th scope="row" colspan="3">Total</th><td class="num"><?= formatPrice($total) ?></td></tr>
                    </tfoot>
                </table>
            </section>
        </div>

        <p class="confirmation-actions"><a class="button button--large" href="/products">Shop videre</a></p>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
