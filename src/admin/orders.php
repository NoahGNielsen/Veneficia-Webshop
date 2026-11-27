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
    <main id="indhold" class="admin-page admin-list">
        <div class="admin-list-head">
            <h1>Ordrer</h1>
            <?php // The search keeps the chosen status; the status chips keep the search ?>
            <form class="admin-search" method="get" action="/admin/orders" role="search">
                <?php if ($status !== ''): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                <?php endif; ?>
                <label for="q" class="visually-hidden">Søg i ordrer</label>
                <input type="search" id="q" name="q" maxlength="254" placeholder="Ordrenummer eller email" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="button--tonal"><?= uiIcon('search', 'icon--20') ?>Søg</button>
            </form>
        </div>
        <?php // One click per status instead of a select and a button (Hick); the count shows where the work is ?>
        <nav aria-label="Status">
            <ul class="chip-set">
                <li><a class="chip" href="<?= htmlspecialchars(ordersUrl('', $search)) ?>"<?= $status === '' ? ' aria-current="page"' : '' ?>>Alle <span class="chip__count"><?= (int)array_sum($statusCounts) ?></span></a></li>
                <?php foreach (ORDER_STATUSES as $value => $label): ?>
                    <li><a class="chip" href="<?= htmlspecialchars(ordersUrl($value, $search)) ?>"<?= $status === $value ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($label) ?> <span class="chip__count"><?= (int)($statusCounts[$value] ?? 0) ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php if ($search !== ''): ?>
            <p class="admin-filter-note">Søgning: <strong><?= htmlspecialchars($search) ?></strong> <a href="<?= htmlspecialchars(ordersUrl($status, '')) ?>">Ryd søgningen</a></p>
        <?php endif; ?>

        <?php if (!$orders): ?>
            <p>Der er ingen ordrer<?= $status !== '' || $search !== '' ? ' der passer til filteret' : ' endnu' ?>.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>Ordre</th><th>Bestilt</th><th>Kunde</th><th class="num">Varer</th><th class="num">Total</th><th>Status</th></tr>
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
                            <td><?= htmlspecialchars($order['firstName'] . ' ' . $order['lastName']) ?><br><span class="text-muted"><?= htmlspecialchars($order['email']) ?></span></td>
                            <td class="num"><?= (int)$line['itemCount'] ?></td>
                            <td class="num"><?= formatPrice($orderTotal) ?></td>
                            <td><span class="status status--<?= htmlspecialchars($order['ordreStatus']) ?>"><?= htmlspecialchars(orderStatusLabel($order['ordreStatus'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($pages > 1): ?>
                <nav class="admin-pagination" aria-label="Sider">
                    <?php if ($page > 1): ?>
                        <a class="button button--outlined" href="<?= htmlspecialchars(ordersUrl($status, $search, $page - 1)) ?>"><?= uiIcon('chevron-left', 'icon--20') ?>Forrige</a>
                    <?php endif; ?>
                    <span>Side <?= $page ?> af <?= $pages ?></span>
                    <?php if ($page < $pages): ?>
                        <a class="button button--outlined" href="<?= htmlspecialchars(ordersUrl($status, $search, $page + 1)) ?>">Næste<?= uiIcon('chevron-right', 'icon--20') ?></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
