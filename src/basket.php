<?php
// The basket: /basket shows it, and POSTs here add to it (from the product pages), change amounts or remove lines.
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
            $stmt = getDb()->prepare(
                'SELECT i.itemId, i.itemName, pc.categoryName
                 FROM VeneficiaItemVariants v JOIN VeneficiaItems i ON i.itemId = v.itemId ' . PRODUCT_CATEGORY_JOIN . '
                 WHERE v.itemVariantId = ?'
            );
            $stmt->execute([$variantId]);
            $product = $stmt->fetch();
            if ($product) {
                $redirect = productUrl((int)$product['itemId'], $product['itemName'], $product['categoryName']);
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
$messageOk = $message !== null && str_ends_with($message, 'lagt i kurven.');
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
    <main id="indhold" class="basket-page">
        <?php if ($items): ?>
            <ol class="steps">
                <li aria-current="step">Kurv</li>
                <li>Levering</li>
                <li>Bekræftelse</li>
            </ol>
        <?php endif; ?>
        <h1>Kurv</h1>
        <?php if ($message !== null): ?>
            <p class="banner <?= $messageOk ? 'banner--success' : 'banner--error' ?>" role="status"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <?php if (!$items): ?>
            <div class="empty-state">
                <span class="arch-icon"><?= uiIcon('shopping-bag') ?></span>
                <p class="type-title-large">Din kurv er tom</p>
                <p>Find noget, der passer til din praksis.</p>
                <a class="button" href="/products">Se produkterne</a>
            </div>
        <?php else: ?>
            <div class="checkout-layout">
                <div class="basket-lines">
                <table class="data-table basket-table">
                    <thead>
                        <tr>
                            <th scope="col">Produkt</th>
                            <th scope="col" class="num">Pris</th>
                            <th scope="col">Antal</th>
                            <th scope="col" class="num">I alt</th>
                            <th scope="col"><span class="visually-hidden">Fjern</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <a class="line-name" href="<?= htmlspecialchars(productUrl((int)$item['itemId'], $item['itemName'], $item['categoryName'])) ?>"><?= htmlspecialchars($item['itemName']) ?></a>
                                    <?php if ($item['varriantName'] !== ''): ?>
                                        <br><span class="line-variant"><?= htmlspecialchars($item['varriantName']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['amount'] > (int)$item['inventoryCount']): ?>
                                        <br><strong class="line-warning">Kun <?= (int)$item['inventoryCount'] ?> på lager</strong>
                                    <?php endif; ?>
                                </td>
                                <td class="num" data-label="Pris"><?= formatPrice($item['varriantPrice']) ?></td>
                                <td data-label="Antal">
                                    <form class="qty-form" method="post" action="/basket">
                                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($token) ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="variantId" value="<?= (int)$item['itemVariantId'] ?>">
                                        <input type="number" name="amount" value="<?= (int)$item['amount'] ?>" min="0"
                                               max="<?= min(BASKET_MAX_AMOUNT, max(0, (int)$item['inventoryCount'])) ?>" inputmode="numeric" aria-label="Antal, <?= htmlspecialchars($item['itemName']) ?>">
                                        <button type="submit" class="button--tonal">Opdater<span class="visually-hidden"> antal af <?= htmlspecialchars($item['itemName']) ?></span></button>
                                    </form>
                                </td>
                                <td class="num" data-label="I alt"><?= formatPrice((float)$item['varriantPrice'] * $item['amount']) ?></td>
                                <td class="line-remove">
                                    <form method="post" action="/basket">
                                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($token) ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="variantId" value="<?= (int)$item['itemVariantId'] ?>">
                                        <button type="submit" class="button--text"><?= uiIcon('delete', 'icon--20') ?>Fjern<span class="visually-hidden"> <?= htmlspecialchars($item['itemName']) ?></span></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <aside class="summary" aria-labelledby="oversigt">
                    <h2 id="oversigt" class="type-title-large">Oversigt</h2>
                    <p class="summary__row summary__total"><span>Total</span> <span><?= formatPrice($total) ?></span></p>
                    <p class="summary__note">Fragt og en eventuel rabatkode lægges til i kassen.</p>
                    <a class="button button--large button--block" href="/checkout">Gå til kassen</a>
                    <a class="summary__continue" href="/products">Fortsæt med at handle</a>
                </aside>
            </div>
        <?php endif; ?>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
