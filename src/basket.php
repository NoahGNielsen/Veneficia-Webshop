<?php
// The basket: /basket shows it, and POSTs here add to it (from /product), change amounts or remove lines.
// Every POST redirects afterwards, so reloading the page never adds the same thing twice.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/basket.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/products.php';
startSession(false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $variantId = filter_var($_POST['variantId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => BASKET_MAX_AMOUNT]]);
    $redirect = '/basket';

    // Checked before a session is created, so a refused (maybe cross-site) request never sets a cookie that could
    // replace the visitor's real session
    if (!checkBasketRequest($_POST['csrfToken'] ?? null)) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            setBasketMessage('Siden var udløbet. Prøv igen.');
        }
    } elseif ($variantId === false || $variantId === null) {
        startSession();
        setBasketMessage('Vælg en variant.');
    } else {
        startSession();
        csrfToken(); // from now on this visitor's basket forms carry a token
        if ($action === 'add') {
            $stmt = getDb()->prepare('SELECT itemId FROM VeneficiaItemVariants WHERE itemVariantId = ?');
            $stmt->execute([$variantId]);
            $itemId = $stmt->fetchColumn();
            if ($itemId !== false) {
                $redirect = '/product?id=' . (int)$itemId;
            }
            $added = addToBasket($variantId, $amount === false || $amount === null || $amount < 1 ? 1 : $amount);
            setBasketMessage(match (true) {
                $added === null => 'Varen findes ikke længere.',
                $added === 0 => 'Der er ikke flere på lager, eller kurven er fuld.',
                default => $added . ' lagt i kurven.',
            });
        } elseif ($action === 'update' && $amount !== false && $amount !== null) {
            setBasketAmount($variantId, $amount);
        } elseif ($action === 'remove') {
            setBasketAmount($variantId, 0);
        }
    }
    header('Location: ' . $redirect, true, 303);
    exit;
}

$items = basketItems();
$message = takeBasketMessage();
$total = 0.0;
foreach ($items as $item) {
    $total += (float)$item['varriantPrice'] * $item['amount'];
}
$token = session_status() === PHP_SESSION_ACTIVE ? csrfToken() : '';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/products.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/products.css') ?>">
    <title>Kurv - Veneficia</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/navbar.php'; ?>
    <main class="basket-page">
        <h1>Kurv</h1>
        <?php if ($message !== null): ?>
            <p role="status"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <?php if (!$items): ?>
            <p>Din kurv er tom. <a href="/products">Se produkterne</a></p>
        <?php else: ?>
            <table class="basket-table">
                <thead>
                    <tr><th>Produkt</th><th>Pris</th><th>Antal</th><th>I alt</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <a href="/product?id=<?= (int)$item['itemId'] ?>"><?= htmlspecialchars($item['itemName']) ?></a>
                                <?php if ($item['varriantName'] !== ''): ?>
                                    <br><?= htmlspecialchars($item['varriantName']) ?>
                                <?php endif; ?>
                                <?php if ($item['amount'] > (int)$item['inventoryCount']): ?>
                                    <br><strong>Kun <?= (int)$item['inventoryCount'] ?> på lager</strong>
                                <?php endif; ?>
                            </td>
                            <td><?= formatPrice($item['varriantPrice']) ?></td>
                            <td>
                                <form method="post" action="/basket">
                                    <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($token) ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="variantId" value="<?= (int)$item['itemVariantId'] ?>">
                                    <input type="number" name="amount" value="<?= (int)$item['amount'] ?>" min="0"
                                           max="<?= min(BASKET_MAX_AMOUNT, max(0, (int)$item['inventoryCount'])) ?>" aria-label="Antal">
                                    <button type="submit">Opdater</button>
                                </form>
                            </td>
                            <td><?= formatPrice((float)$item['varriantPrice'] * $item['amount']) ?></td>
                            <td>
                                <form method="post" action="/basket">
                                    <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($token) ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="variantId" value="<?= (int)$item['itemVariantId'] ?>">
                                    <button type="submit">Fjern</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><th colspan="3">Total</th><th><?= formatPrice($total) ?></th><th></th></tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
