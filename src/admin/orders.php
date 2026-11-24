<?php
// Order overview: every order, newest first, filterable by status and searchable by order number or customer email.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/products.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/orders.php';
$employee = requireEmployee();

const ORDERS_PER_PAGE = 50;

// URL of this page with the given filters. Empty filters and page 1 are left out.
function ordersUrl(string $status, string $search, int $page = 1): string
{
    $query = array_filter(['status' => $status, 'q' => $search, 'page' => $page > 1 ? $page : null], fn($v) => $v !== null && $v !== '');
    return '/admin/orders' . ($query ? '?' . http_build_query($query) : '');
}

$status = is_string($_GET['status'] ?? null) && isset(ORDER_STATUSES[$_GET['status']]) ? $_GET['status'] : '';
$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 254) : '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]);

$db = getDb();

// Number of orders per status, for the filter (one query, uses the ordreStatus index)
$statusCounts = $db->query('SELECT ordreStatus, COUNT(*) FROM VeneficiaOrdre GROUP BY ordreStatus')->fetchAll(PDO::FETCH_KEY_PAIR);

$where = [];
$params = [];
if ($status !== '') {
    $where[] = 'o.ordreStatus = ?';
    $params[] = $status;
}
if ($search !== '') {
    // A number is an order number, anything else is the start of the customer's email (uses the unique email index)
    if (ctype_digit($search)) {
        $where[] = 'o.ordreID = ?';
        $params[] = $search;
    } else {
        $where[] = 'c.email LIKE ?'; // \ is LIKE's default escape character
        $params[] = addcslashes($search, '%_\\') . '%';
    }
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

if ($search === '') {
    $total = $status === '' ? array_sum($statusCounts) : (int)($statusCounts[$status] ?? 0);
} else {
    $stmt = $db->prepare("SELECT COUNT(*) FROM VeneficiaOrdre o JOIN VeneficiaCustomers c ON c.VeneficiaCustomerId = o.customerId $whereSql");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
}
$pages = max(1, (int)ceil($total / ORDERS_PER_PAGE));
$page = min($page, $pages);

$stmt = $db->prepare(
    "SELECT o.ordreID, o.ordreStatus, o.ordredAt, o.shippingPrice, o.discountAmount, c.firstName, c.lastName, c.email
     FROM VeneficiaOrdre o JOIN VeneficiaCustomers c ON c.VeneficiaCustomerId = o.customerId
     $whereSql
     ORDER BY o.ordreID DESC
     LIMIT " . ORDERS_PER_PAGE . ' OFFSET ' . (($page - 1) * ORDERS_PER_PAGE)
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Item totals and item counts for just the orders on this page, in one query
$lineTotals = [];
if ($orders) {
    $ids = array_map(fn($o) => (int)$o['ordreID'], $orders);
    $stmt = $db->prepare(
        'SELECT ordreId, SUM(quantity * itemPrice) AS itemsTotal, SUM(quantity) AS itemCount FROM VeneficiaOrdreItems
         WHERE ordreId IN (' . implode(',', array_fill(0, count($ids), '?')) . ') GROUP BY ordreId'
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $lineTotals[(int)$row['ordreId']] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin.css') ?>">
    <title>Ordrer - Veneficia admin</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/adminBar.php'; ?>
    <main class="admin-page admin-list">
        <h1>Ordrer</h1>
        <form class="admin-list-head" method="get" action="/admin/orders">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Alle (<?= (int)array_sum($statusCounts) ?>)</option>
                <?php foreach (ORDER_STATUSES as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?> (<?= (int)($statusCounts[$value] ?? 0) ?>)</option>
                <?php endforeach; ?>
            </select>
            <label for="q">Søg</label>
            <input type="search" id="q" name="q" maxlength="254" placeholder="Ordrenummer eller email" value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Vis</button>
            <?php if ($status !== '' || $search !== ''): ?>
                <a href="/admin/orders">Nulstil</a>
            <?php endif; ?>
        </form>

        <?php if (!$orders): ?>
            <p>Der er ingen ordrer<?= $status !== '' || $search !== '' ? ' der passer til filteret' : ' endnu' ?>.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>Ordre</th><th>Bestilt</th><th>Kunde</th><th>Varer</th><th>Total</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $id = (int)$order['ordreID'];
                        $line = $lineTotals[$id] ?? ['itemsTotal' => 0, 'itemCount' => 0];
                        $orderTotal = (float)$line['itemsTotal'] - (float)$order['discountAmount'] + (float)$order['shippingPrice'];
                        ?>
                        <tr class="admin-row">
                            <td><a href="/admin/order?id=<?= $id ?>">#<?= $id ?></a></td>
                            <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($order['ordredAt']))) ?></td>
                            <td><?= htmlspecialchars($order['firstName'] . ' ' . $order['lastName']) ?><br><?= htmlspecialchars($order['email']) ?></td>
                            <td><?= (int)$line['itemCount'] ?></td>
                            <td><?= formatPrice($orderTotal) ?></td>
                            <td><?= htmlspecialchars(orderStatusLabel($order['ordreStatus'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($pages > 1): ?>
                <nav>
                    <?php if ($page > 1): ?>
                        <a href="<?= htmlspecialchars(ordersUrl($status, $search, $page - 1)) ?>">Forrige</a>
                    <?php endif; ?>
                    Side <?= $page ?> af <?= $pages ?>
                    <?php if ($page < $pages): ?>
                        <a href="<?= htmlspecialchars(ordersUrl($status, $search, $page + 1)) ?>">Næste</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
