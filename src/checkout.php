<?php
// Checkout: the customer checks their basket and enters a delivery address. Payment isn't built yet, so placing the
// order skips straight to /ordreConfimation and the order is stored as paid.
// Only logged-in customers can check out (an order needs a customerId); guests are sent to log in and come back here.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/basket.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/products.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/discounts.php';
$customer = requireCustomer('/checkout');
$customerId = (int)$customer['VeneficiaCustomerId'];

const SHIPPING_PRICE = 39.00; // standard shipping price in kr. (0 with a free-shipping discount code)

// Fingerprint of what the customer saw (variants, amounts, prices and discount code). The order is only placed if it
// still matches, so a price change or a basket/code changed in another tab never orders something they didn't see.
function basketFingerprint(array $items, ?array $code): string
{
    $lines = [];
    foreach ($items as $item) {
        $lines[(int)$item['itemVariantId']] = (int)$item['amount'] . ':' . number_format((float)$item['varriantPrice'], 2, '.', '');
    }
    ksort($lines);
    return hash('sha256', json_encode([$lines, $code === null ? null : (int)$code['discountCodeID']]));
}

// Turns the basket into an order in one transaction: order, order lines, stock, warehouse log, empty basket.
// Returns the new order's id, or null with the reasons added to $errors.
function placeOrder(int $customerId, array $address, bool $saveAddress, string $fingerprint, array &$errors): ?int
{
    $db = getDb();
    $db->beginTransaction();
    try {
        // Locks the basket and variant rows, so a double click on "Bestil" (or two tabs) can't order the same basket
        // twice, and two customers can't both buy the last one
        $stmt = $db->prepare(
            'SELECT b.itemVariantId, b.amount, v.itemId, v.varriantPrice, v.inventoryCount, v.varriantName, i.itemName
             FROM VeneficiaBasketItems b
             JOIN VeneficiaItemVariants v ON v.itemVariantId = b.itemVariantId
             JOIN VeneficiaItems i ON i.itemId = v.itemId
             WHERE b.customerId = ?
             FOR UPDATE'
        );
        $stmt->execute([$customerId]);
        $items = $stmt->fetchAll();
        $code = sessionDiscountCode(true);

        if (!$items) {
            $errors[] = 'Din kurv er tom.';
        } elseif (!hash_equals(basketFingerprint($items, $code), $fingerprint)) {
            $errors[] = 'Kurven eller priserne har ændret sig. Tjek ordren og bestil igen.';
        } else {
            foreach ($items as $item) {
                if ((int)$item['amount'] > (int)$item['inventoryCount']) {
                    $name = $item['itemName'] . ($item['varriantName'] !== '' ? ' (' . $item['varriantName'] . ')' : '');
                    $errors[] = 'Der er kun ' . max(0, (int)$item['inventoryCount']) . ' af ' . $name . ' på lager. Ret antallet i kurven.';
                }
            }
        }
        if ($errors) {
            $db->rollBack();
            return null;
        }

        $db->prepare(
            "INSERT INTO VeneficiaOrdre (customerId, ordreStatus, addressLine, addressPostalCode, addressCity,
                                         discountCodeId, discountAmount, shippingPrice)
             VALUES (?, 'paid', ?, ?, ?, ?, ?, ?)"
        )->execute([
            $customerId, $address['addressLine'], $address['addressPostalCode'], $address['addressCity'],
            $code === null ? null : (int)$code['discountCodeID'],
            number_format(discountFor($code, $items), 2, '.', ''),
            number_format(shippingFor($code), 2, '.', ''),
        ]);
        $orderId = (int)$db->lastInsertId();

        // Order lines and log rows go in as one insert each
        $lineValues = [];
        $logValues = [];
        $takeStock = $db->prepare('UPDATE VeneficiaItemVariants SET inventoryCount = inventoryCount - ? WHERE itemVariantId = ?');
        foreach ($items as $item) {
            $amount = (int)$item['amount'];
            array_push($lineValues, $orderId, (int)$item['itemVariantId'], $amount, $item['varriantPrice']);
            array_push($logValues, (int)$item['itemVariantId'], $orderId, -$amount);
            $takeStock->execute([$amount, (int)$item['itemVariantId']]);
        }
        $count = count($items);
        $db->prepare('INSERT INTO VeneficiaOrdreItems (ordreId, itemVariantId, quantity, itemPrice) VALUES '
            . implode(',', array_fill(0, $count, '(?, ?, ?, ?)')))->execute($lineValues);
        $db->prepare("INSERT INTO VeneficiaWarehouseLog (itemVariantId, ordreId, changeType, quantityChange) VALUES "
            . implode(',', array_fill(0, $count, "(?, ?, 'sale', ?)")))->execute($logValues);

        $db->prepare('DELETE FROM VeneficiaBasketItems WHERE customerId = ?')->execute([$customerId]);
        if ($saveAddress) {
            $db->prepare('UPDATE VeneficiaCustomers SET addressLine = ?, addressPostalCode = ?, addressCity = ? WHERE VeneficiaCustomerId = ?')
                ->execute([$address['addressLine'], $address['addressPostalCode'], $address['addressCity'], $customerId]);
        }
        $db->commit();
        unset($_SESSION['discountCodeId']);
        return $orderId;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function shippingFor(?array $code): float
{
    return $code !== null && $code['discountType'] === 'freeShipping' ? 0.0 : SHIPPING_PRICE;
}

// The discount code form: choose or remove a code, then back to the checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['applyCode', 'removeCode'], true)) {
    if (!checkCsrf($_POST['csrfToken'] ?? null)) {
        $_SESSION['checkoutMessage'] = 'Siden var udløbet. Prøv igen.';
    } elseif ($_POST['action'] === 'removeCode') {
        unset($_SESSION['discountCodeId']);
    } else {
        $typed = $_POST['discountCode'] ?? '';
        $error = applyDiscountCode(is_string($typed) ? $typed : '', basketItems());
        $_SESSION['checkoutMessage'] = $error ?? 'Rabatkoden er tilføjet.';
    }
    header('Location: /checkout', true, 303);
    exit;
}

// The saved address fills in the form
$stmt = getDb()->prepare('SELECT addressLine, addressPostalCode, addressCity FROM VeneficiaCustomers WHERE VeneficiaCustomerId = ?');
$stmt->execute([$customerId]);
$address = array_map('strval', $stmt->fetch() ?: []) + ['addressLine' => '', 'addressPostalCode' => '', 'addressCity' => ''];
$saveAddress = true;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($address) as $field) {
        $value = $_POST[$field] ?? '';
        $address[$field] = is_string($value) ? trim($value) : '';
    }
    $saveAddress = !empty($_POST['saveAddress']);
    $fingerprint = $_POST['basketFingerprint'] ?? '';

    if (!checkCsrf($_POST['csrfToken'] ?? null)) {
        $errors[] = 'Siden var udløbet. Prøv igen.';
    }
    if ($address['addressLine'] === '' || mb_strlen($address['addressLine']) > 255) {
        $errors[] = 'Skriv din adresse (højst 255 tegn).';
    }
    if ($address['addressPostalCode'] === '' || mb_strlen($address['addressPostalCode']) > 20) {
        $errors[] = 'Skriv dit postnummer (højst 20 tegn).';
    }
    if ($address['addressCity'] === '' || mb_strlen($address['addressCity']) > 100) {
        $errors[] = 'Skriv din by (højst 100 tegn).';
    }
    if (empty($_POST['acceptTerms'])) {
        $errors[] = 'Du skal acceptere handelsbetingelserne.';
    }

    if (!$errors) {
        $orderId = placeOrder($customerId, $address, $saveAddress, is_string($fingerprint) ? $fingerprint : '', $errors);
        if ($orderId !== null) {
            header('Location: /ordreConfimation?id=' . $orderId, true, 303);
            exit;
        }
    }
}

$items = basketItems();
$code = sessionDiscountCode();
$subtotal = 0.0;
$overStock = false;
foreach ($items as $item) {
    $subtotal += (float)$item['varriantPrice'] * $item['amount'];
    $overStock = $overStock || $item['amount'] > (int)$item['inventoryCount'];
}
$discount = discountFor($code, $items);
$shipping = shippingFor($code);
$total = $subtotal - $discount + $shipping;
$message = $_SESSION['checkoutMessage'] ?? null;
unset($_SESSION['checkoutMessage']);
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/products.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/products.css') ?>">
    <title>Kassen - Veneficia</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/navbar.php'; ?>
    <main class="basket-page">
        <h1>Kassen</h1>
        <?php if (is_string($message)): ?>
            <p role="status"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>
        <?php if ($errors): ?>
            <ul role="alert">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!$items): ?>
            <p>Din kurv er tom. <a href="/products">Se produkterne</a></p>
        <?php else: ?>
            <h2>Din ordre</h2>
            <table class="basket-table">
                <thead>
                    <tr><th>Produkt</th><th>Pris</th><th>Antal</th><th>I alt</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($item['itemName']) ?>
                                <?php if ($item['varriantName'] !== ''): ?>
                                    <br><?= htmlspecialchars($item['varriantName']) ?>
                                <?php endif; ?>
                                <?php if ($item['amount'] > (int)$item['inventoryCount']): ?>
                                    <br><strong>Kun <?= max(0, (int)$item['inventoryCount']) ?> på lager</strong>
                                <?php endif; ?>
                            </td>
                            <td><?= formatPrice($item['varriantPrice']) ?></td>
                            <td><?= (int)$item['amount'] ?></td>
                            <td><?= formatPrice((float)$item['varriantPrice'] * $item['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><th colspan="3">Varer</th><th><?= formatPrice($subtotal) ?></th></tr>
                    <?php if ($code !== null): ?>
                        <tr><th colspan="3">Rabat: <?= htmlspecialchars(describeDiscountCode($code)) ?></th><th>-<?= formatPrice($discount) ?></th></tr>
                    <?php endif; ?>
                    <tr><th colspan="3">Fragt</th><th><?= formatPrice($shipping) ?></th></tr>
                    <tr><th colspan="3">Total</th><th><?= formatPrice($total) ?></th></tr>
                </tfoot>
            </table>
            <p><a href="/basket">Ret kurven</a></p>

            <form class="checkout-form" method="post" action="/checkout">
                <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">
                <?php if ($code === null): ?>
                    <input type="hidden" name="action" value="applyCode">
                    <label for="discountCode">Rabatkode</label>
                    <input type="text" id="discountCode" name="discountCode" autocomplete="off" maxlength="100" required>
                    <button type="submit">Brug rabatkode</button>
                <?php else: ?>
                    <input type="hidden" name="action" value="removeCode">
                    <?php if ($code['discountType'] === 'product' && $discount <= 0): ?>
                        <p>Rabatkoden gælder ikke for varerne i din kurv.</p>
                    <?php endif; ?>
                    <button type="submit">Fjern rabatkode</button>
                <?php endif; ?>
            </form>

            <?php if ($overStock): ?>
                <p><strong>Der er ikke nok på lager af alle varer. <a href="/basket">Ret antallet i kurven</a> før du bestiller.</strong></p>
            <?php else: ?>
                <h2>Levering</h2>
                <form class="checkout-form" method="post" action="/checkout">
                    <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <input type="hidden" name="basketFingerprint" value="<?= htmlspecialchars(basketFingerprint($items, $code)) ?>">

                    <p><?= htmlspecialchars($customer['firstName'] . ' ' . $customer['lastName']) ?><br><?= htmlspecialchars($customer['email']) ?></p>

                    <label for="addressLine">Adresse</label>
                    <input type="text" id="addressLine" name="addressLine" autocomplete="street-address" maxlength="255" required value="<?= htmlspecialchars($address['addressLine']) ?>">

                    <label for="addressPostalCode">Postnummer</label>
                    <input type="text" id="addressPostalCode" name="addressPostalCode" autocomplete="postal-code" inputmode="numeric" maxlength="20" required value="<?= htmlspecialchars($address['addressPostalCode']) ?>">

                    <label for="addressCity">By</label>
                    <input type="text" id="addressCity" name="addressCity" autocomplete="address-level2" maxlength="100" required value="<?= htmlspecialchars($address['addressCity']) ?>">

                    <label><input type="checkbox" name="saveAddress" value="1" <?= $saveAddress ? 'checked' : '' ?>> Gem adressen til næste gang</label>
                    <label><input type="checkbox" name="acceptTerms" value="1" required> Jeg accepterer <a href="/legal/terms">handelsbetingelserne</a></label>

                    <button type="submit">Gå til betaling</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
