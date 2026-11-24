<?php
// One order: customer, address, lines and totals, and a form to change its status. Opened from /admin/orders.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/products.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/orders.php';
$employee = requireEmployee();

$db = getDb();
$orderId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$errors = [];

if ($orderId !== false && $orderId !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['ordreStatus'] ?? null;
    if (!checkCsrf($_POST['csrfToken'] ?? null)) {
        $errors[] = 'Siden var udløbet. Prøv igen.';
    } elseif (!is_string($newStatus) || !isset(ORDER_STATUSES[$newStatus])) {
        $errors[] = 'Vælg en status fra listen.';
    } else {
        $db->prepare('UPDATE VeneficiaOrdre SET ordreStatus = ? WHERE ordreID = ?')->execute([$newStatus, $orderId]);
        // Back to a GET, so a refresh doesn't post the form again
        header('Location: /admin/order?id=' . $orderId . '&saved=1', true, 303);
        exit;
    }
}

$order = false;
if ($orderId !== false && $orderId !== null) {
    $stmt = $db->prepare(
        'SELECT o.ordreID, o.ordreStatus, o.ordredAt, o.addressLine, o.addressPostalCode, o.addressCity, o.shippingPrice,
                o.discountAmount, d.code, c.firstName, c.lastName, c.email, c.phoneNumber
         FROM VeneficiaOrdre o
         JOIN VeneficiaCustomers c ON c.VeneficiaCustomerId = o.customerId
         LEFT JOIN VeneficiaDiscountCodes d ON d.discountCodeID = o.discountCodeId
         WHERE o.ordreID = ?'
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
}
if (!$order) {
    http_response_code(404);
    include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/statusCodesPages/404.php';
    exit;
}

$stmt = $db->prepare(
    'SELECT o.quantity, o.itemPrice, v.itemId, v.varriantName, i.itemName
     FROM VeneficiaOrdreItems o
     JOIN VeneficiaItemVariants v ON v.itemVariantId = o.itemVariantId
     JOIN VeneficiaItems i ON i.itemId = v.itemId
     WHERE o.ordreId = ?'
);
$stmt->execute([$order['ordreID']]);
$lines = $stmt->fetchAll();
$itemsTotal = 0.0;
foreach ($lines as $line) {
    $itemsTotal += (float)$line['itemPrice'] * $line['quantity'];
}
$total = $itemsTotal - (float)$order['discountAmount'] + (float)$order['shippingPrice'];
$saved = isset($_GET['saved']) && !$errors;
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin.css') ?>">
    <title>Ordre #<?= (int)$order['ordreID'] ?> - Veneficia admin</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/adminBar.php'; ?>
    <main class="admin-page admin-list">
        <div class="admin-list-head">
            <h1>Ordre #<?= (int)$order['ordreID'] ?></h1>
            <a href="/admin/orders">Alle ordrer</a>
        </div>
        <?php if ($saved): ?>
            <p class="admin-success" role="status">Status er gemt.</p>
        <?php endif; ?>
        <?php if ($errors): ?>
            <ul role="alert">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p>Bestilt: <?= htmlspecialchars(date('d-m-Y H:i', strtotime($order['ordredAt']))) ?></p>

        <form method="post" action="/admin/order?id=<?= (int)$order['ordreID'] ?>">
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">
            <label for="ordreStatus">Status</label>
            <select id="ordreStatus" name="ordreStatus">
                <?php if (!isset(ORDER_STATUSES[$order['ordreStatus']])): ?>
                    <option value="" selected><?= htmlspecialchars($order['ordreStatus']) ?></option>
                <?php endif; ?>
                <?php foreach (ORDER_STATUSES as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $order['ordreStatus'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Gem status</button>
        </form>

        <section>
            <h2>Kunde</h2>
            <p><?= htmlspecialchars($order['firstName'] . ' ' . $order['lastName']) ?><br>
                <a href="mailto:<?= htmlspecialchars($order['email']) ?>"><?= htmlspecialchars($order['email']) ?></a>
                <?php if ($order['phoneNumber'] !== null && $order['phoneNumber'] !== ''): ?>
                    <br><?= htmlspecialchars($order['phoneNumber']) ?>
                <?php endif; ?>
            </p>
            <h2>Leveres til</h2>
            <p><?= htmlspecialchars($order['addressLine']) ?><br>
                <?= htmlspecialchars($order['addressPostalCode'] . ' ' . $order['addressCity']) ?></p>
        </section>

        <section>
            <h2>Varer</h2>
            <table class="admin-table">
                <thead>
                    <tr><th>Produkt</th><th>Pris</th><th>Antal</th><th>I alt</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line): ?>
                        <tr>
                            <td>
                                <a href="/admin/item?id=<?= (int)$line['itemId'] ?>"><?= htmlspecialchars($line['itemName']) ?></a>
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
                    <tr><th colspan="3">Varer</th><th><?= formatPrice($itemsTotal) ?></th></tr>
                    <?php if ($order['code'] !== null || (float)$order['discountAmount'] > 0): ?>
                        <tr><th colspan="3">Rabat<?= $order['code'] !== null ? ' (' . htmlspecialchars($order['code']) . ')' : '' ?></th><th>-<?= formatPrice($order['discountAmount']) ?></th></tr>
                    <?php endif; ?>
                    <tr><th colspan="3">Fragt</th><th><?= formatPrice($order['shippingPrice']) ?></th></tr>
                    <tr><th colspan="3">Total</th><th><?= formatPrice($total) ?></th></tr>
                </tfoot>
            </table>
        </section>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
