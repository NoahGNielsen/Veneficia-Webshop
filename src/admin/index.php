<?php
// The admin's overview: what needs doing now (paid orders to pack, sold-out variants), how the last 30 days went
// against the 30 before, orders per day for two weeks and the newest orders. Every number links to the page where
// it's handled. Read-only: a handful of small queries, no user input in any of them.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/products.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/orders.php';
$employee = requireEmployee();
$db = getDb();

// The database's clock, so "today" and the 30 days match the order times it stored
$now = new DateTimeImmutable((string)$db->query('SELECT NOW()')->fetchColumn());

// Orders per status (the same query as the order list's filter)
$statusCounts = $db->query('SELECT ordreStatus, COUNT(*) FROM VeneficiaOrdre GROUP BY ordreStatus')->fetchAll(PDO::FETCH_KEY_PAIR);
$paidOrders = (int)($statusCounts['paid'] ?? 0);

// The last 30 days against the 30 before: orders, and what they sold for (items less discount, without shipping)
$period = $db->query(
    'SELECT COALESCE(SUM(o.ordredAt >= NOW() - INTERVAL 30 DAY), 0) AS ordersNow,
            COALESCE(SUM(o.ordredAt < NOW() - INTERVAL 30 DAY), 0) AS ordersBefore,
            COALESCE(SUM(CASE WHEN o.ordredAt >= NOW() - INTERVAL 30 DAY THEN COALESCE(t.itemsTotal, 0) - o.discountAmount END), 0) AS salesNow,
            COALESCE(SUM(CASE WHEN o.ordredAt < NOW() - INTERVAL 30 DAY THEN COALESCE(t.itemsTotal, 0) - o.discountAmount END), 0) AS salesBefore
     FROM VeneficiaOrdre o
     LEFT JOIN (SELECT ordreId, SUM(quantity * itemPrice) AS itemsTotal FROM VeneficiaOrdreItems GROUP BY ordreId) t ON t.ordreId = o.ordreID
     WHERE o.ordredAt >= NOW() - INTERVAL 60 DAY'
)->fetch();

// Items, and variants that are sold out (the first five by name, with a link to fix them)
$itemCount = (int)$db->query('SELECT COUNT(*) FROM VeneficiaItems')->fetchColumn();
$soldOutCount = (int)$db->query('SELECT COUNT(*) FROM VeneficiaItemVariants WHERE inventoryCount = 0')->fetchColumn();
$soldOut = $db->query(
    'SELECT i.itemId, i.itemName, v.varriantName FROM VeneficiaItemVariants v JOIN VeneficiaItems i ON i.itemId = v.itemId
     WHERE v.inventoryCount = 0 ORDER BY i.itemName, v.varriantName LIMIT 5'
)->fetchAll();

// Orders per day, today and the 13 days before (days without orders are 0)
$perDay = $db->query(
    'SELECT DATE(ordredAt) AS orderDay, COUNT(*) FROM VeneficiaOrdre WHERE ordredAt >= CURDATE() - INTERVAL 13 DAY GROUP BY orderDay'
)->fetchAll(PDO::FETCH_KEY_PAIR);
const DASHBOARD_WEEKDAYS = ['søndag', 'mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lørdag'];
const DASHBOARD_MONTHS = ['januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december'];
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $day = $now->modify("-$i day");
    $days[] = [
        'count' => (int)($perDay[$day->format('Y-m-d')] ?? 0),
        'short' => $day->format('j'),
        'long' => DASHBOARD_WEEKDAYS[(int)$day->format('w')] . ' ' . $day->format('j') . '. ' . DASHBOARD_MONTHS[(int)$day->format('n') - 1],
        'today' => $i === 0,
    ];
}
$dayMax = max(1, ...array_column($days, 'count'));
$dayTotal = array_sum(array_column($days, 'count'));

// The five newest orders
$recent = $db->query(
    'SELECT o.ordreID, o.ordreStatus, o.ordredAt, o.shippingPrice, o.discountAmount, c.firstName, c.lastName,
            (SELECT COALESCE(SUM(oi.quantity * oi.itemPrice), 0) FROM VeneficiaOrdreItems oi WHERE oi.ordreId = o.ordreID) AS itemsTotal
     FROM VeneficiaOrdre o JOIN VeneficiaCustomers c ON c.VeneficiaCustomerId = o.customerId
     ORDER BY o.ordreID DESC LIMIT 5'
)->fetchAll();

// "12 % flere end de 30 dage før", or nothing when there's nothing to compare with
function dashboardChange(float $now, float $before, string $more, string $less): string
{
    if ($before <= 0) {
        return '';
    }
    $percent = (int)round(($now - $before) / $before * 100);
    return $percent === 0 ? 'Som de 30 dage før' : abs($percent) . ' % ' . ($percent > 0 ? $more : $less) . ' end de 30 dage før';
}

$hour = (int)$now->format('G');
$greeting = match (true) {
    $hour < 5 => 'God nat',
    $hour < 10 => 'Godmorgen',
    $hour < 12 => 'God formiddag',
    $hour < 18 => 'God eftermiddag',
    default => 'God aften',
};
$today = ucfirst(DASHBOARD_WEEKDAYS[(int)$now->format('w')]) . ' ' . $now->format('j') . '. ' . DASHBOARD_MONTHS[(int)$now->format('n') - 1];
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin.css') ?>">
    <title>Overblik - Veneficia admin</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/adminBar.php'; ?>
    <main id="indhold" class="admin-page admin-home">
        <header class="admin-head">
            <div>
                <p class="admin-head__date"><?= htmlspecialchars($today) ?></p>
                <h1><?= htmlspecialchars($greeting . ', ' . $employee['employeeUsername']) ?></h1>
            </div>
            <?php // Quick actions: the one that needs doing now is the filled button ?>
            <div class="admin-actions">
                <?php if ($paidOrders > 0): ?>
                    <a class="button" href="/admin/orders?status=paid"><?= uiIcon('receipt-long', 'icon--20') ?>Pak ordrer (<?= $paidOrders ?>)</a>
                <?php endif; ?>
                <a class="button <?= $paidOrders > 0 ? 'button--tonal' : '' ?>" href="/admin/item"><?= uiIcon('add', 'icon--20') ?>Ny vare</a>
                <a class="button button--tonal" href="/admin/discountCodes"><?= uiIcon('sell', 'icon--20') ?>Ny rabatkode</a>
            </div>
        </header>

        <div class="dashboard">
            <a class="stat stat--pack<?= $paidOrders > 0 ? ' stat--attention' : '' ?>" href="/admin/orders?status=paid">
                <span class="stat__label">Venter på pakning</span>
                <span class="stat__value"><?= $paidOrders ?></span>
                <span class="stat__note"><?= $paidOrders === 0 ? 'Alle betalte ordrer er pakket' : ($paidOrders === 1 ? 'Betalt ordre' : 'Betalte ordrer') ?></span>
            </a>
            <a class="stat stat--orders" href="/admin/orders">
                <span class="stat__label">Ordrer, 30 dage</span>
                <span class="stat__value"><?= (int)$period['ordersNow'] ?></span>
                <span class="stat__note"><?= htmlspecialchars(dashboardChange((float)$period['ordersNow'], (float)$period['ordersBefore'], 'flere', 'færre')) ?></span>
            </a>
            <div class="stat stat--sales">
                <span class="stat__label">Solgt for, 30 dage</span>
                <span class="stat__value"><?= formatPrice((float)$period['salesNow']) ?></span>
                <span class="stat__note"><?= htmlspecialchars(dashboardChange((float)$period['salesNow'], (float)$period['salesBefore'], 'mere', 'mindre')) ?></span>
            </div>
            <a class="stat stat--items" href="/admin/items">
                <span class="stat__label">Varer</span>
                <span class="stat__value"><?= $itemCount ?></span>
                <span class="stat__note"><?= $soldOutCount === 0 ? 'Alt er på lager' : ($soldOutCount === 1 ? '1 variant udsolgt' : $soldOutCount . ' varianter udsolgt') ?></span>
            </a>

            <section class="panel panel--chart" aria-labelledby="dag-titel">
                <h2 id="dag-titel">Ordrer pr. dag</h2>
                <p class="panel__note"><?= $dayTotal === 1 ? '1 ordre' : $dayTotal . ' ordrer' ?> de sidste 14 dage</p>
                <ol class="bar-chart" style="--max: <?= $dayMax ?>">
                    <?php foreach ($days as $day): ?>
                        <?php // Today and the busiest day show their number; the rest on hover ?>
                        <li class="bar-chart__day<?= $day['today'] ? ' bar-chart__day--today' : '' ?><?= $day['count'] > 0 && $day['count'] === $dayMax ? ' bar-chart__day--max' : '' ?>" style="--value: <?= $day['count'] ?>">
                            <span class="bar-chart__bar" aria-hidden="true"><span class="bar-chart__tip"><?= $day['count'] ?></span></span>
                            <span class="bar-chart__date" aria-hidden="true"><?= $day['today'] ? 'I dag' : $day['short'] ?></span>
                            <span class="visually-hidden"><?= htmlspecialchars(ucfirst($day['long'])) ?>: <?= $day['count'] === 1 ? '1 ordre' : $day['count'] . ' ordrer' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>

            <section class="panel panel--recent" aria-labelledby="nye-titel">
                <div class="panel__head">
                    <h2 id="nye-titel">Nyeste ordrer</h2>
                    <a href="/admin/orders">Alle ordrer</a>
                </div>
                <?php if (!$recent): ?>
                    <p class="panel__note">Der er ingen ordrer endnu.</p>
                <?php else: ?>
                    <ul class="recent-orders">
                        <?php foreach ($recent as $order): ?>
                            <?php $orderTotal = (float)$order['itemsTotal'] - (float)$order['discountAmount'] + (float)$order['shippingPrice']; ?>
                            <li class="recent-order">
                                <a class="recent-order__link" href="/admin/order?id=<?= (int)$order['ordreID'] ?>">#<?= (int)$order['ordreID'] ?></a>
                                <span class="recent-order__who"><?= htmlspecialchars($order['firstName'] . ' ' . $order['lastName']) ?></span>
                                <span class="recent-order__when"><?= htmlspecialchars(date('d-m-Y H:i', strtotime($order['ordredAt']))) ?></span>
                                <span class="recent-order__total"><?= formatPrice($orderTotal) ?></span>
                                <span class="status status--<?= htmlspecialchars($order['ordreStatus']) ?>"><?= htmlspecialchars(orderStatusLabel($order['ordreStatus'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="panel panel--flow" aria-labelledby="flow-titel">
                <h2 id="flow-titel">Ordrernes vej</h2>
                <ol class="order-flow">
                    <?php foreach (ORDER_STATUSES as $value => $label): ?>
                        <li>
                            <a class="order-flow__step" href="/admin/orders?status=<?= htmlspecialchars(urlencode($value)) ?>">
                                <span class="order-flow__count"><?= (int)($statusCounts[$value] ?? 0) ?></span>
                                <span class="status status--<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>

            <section class="panel panel--stock" aria-labelledby="lager-titel">
                <div class="panel__head">
                    <h2 id="lager-titel">Udsolgt</h2>
                    <a href="/admin/items">Alle varer</a>
                </div>
                <?php if (!$soldOut): ?>
                    <p class="panel__note">Alle varianter er på lager.</p>
                <?php else: ?>
                    <p class="panel__note"><?= $soldOutCount === 1 ? '1 variant er udsolgt' : $soldOutCount . ' varianter er udsolgt' ?></p>
                    <ul class="stock-list">
                        <?php foreach ($soldOut as $variant): ?>
                            <li><a href="/admin/item?id=<?= (int)$variant['itemId'] ?>"><?= htmlspecialchars($variant['itemName']) ?></a> <span class="text-muted"><?= htmlspecialchars($variant['varriantName']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
