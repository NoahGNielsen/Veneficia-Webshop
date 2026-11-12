<?php
// Discount codes in the checkout. The types are described in database/schema.sql (VeneficiaDiscountCodes) and codes
// are made in /admin/discountCodes. One code per order; the chosen one is kept in $_SESSION['discountCodeId'].
require_once __DIR__ . '/session.php';

const DISCOUNT_MAX_FAILED_TRIES = 10;  // wrong codes per session...
const DISCOUNT_TRY_WINDOW = 900;       // ...within 15 minutes, so codes can't be guessed by trying thousands

const DISCOUNT_ACTIVE_SQL = 'SELECT discountCodeID, code, discountType, discountAmount, itemId FROM VeneficiaDiscountCodes
    WHERE validAt <= NOW() AND (validUntil IS NULL OR validUntil > NOW()) AND ';

// The code with this id if it's valid right now, or null. $lock = true locks it until the transaction ends, so it
// can't be deleted while an order using it is being placed.
function activeDiscountCodeById(int $id, bool $lock = false): ?array
{
    $stmt = getDb()->prepare(DISCOUNT_ACTIVE_SQL . 'discountCodeID = ?' . ($lock ? ' LOCK IN SHARE MODE' : ''));
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// The code currently chosen in the checkout, or null. A code that has run out since it was chosen is dropped.
function sessionDiscountCode(bool $lock = false): ?array
{
    $id = $_SESSION['discountCodeId'] ?? null;
    if (!is_int($id)) {
        return null;
    }
    $code = activeDiscountCodeById($id, $lock);
    if ($code === null) {
        unset($_SESSION['discountCodeId']);
    }
    return $code;
}

// Chooses the code the customer typed. Returns an error message, or null when it worked.
function applyDiscountCode(string $typed, array $items): ?string
{
    $now = time();
    $tries = array_filter(
        is_array($_SESSION['discountTries'] ?? null) ? $_SESSION['discountTries'] : [],
        fn($t) => is_int($t) && $t > $now - DISCOUNT_TRY_WINDOW
    );
    if (count($tries) >= DISCOUNT_MAX_FAILED_TRIES) {
        $_SESSION['discountTries'] = array_values($tries);
        return 'For mange forkerte rabatkoder. Prøv igen om lidt.';
    }

    $code = null;
    $typed = mb_strtoupper(trim($typed)); // codes are stored in capitals
    if (preg_match('/^[A-Z0-9_-]{3,100}$/', $typed)) {
        $stmt = getDb()->prepare(DISCOUNT_ACTIVE_SQL . 'code = ?');
        $stmt->execute([$typed]);
        $code = $stmt->fetch() ?: null;
    }
    if ($code === null) {
        $tries[] = $now;
        $_SESSION['discountTries'] = array_values($tries);
        return 'Rabatkoden findes ikke eller er udløbet.';
    }
    if ($code['discountType'] === 'product' && !in_array((int)$code['itemId'], array_map(fn($i) => (int)$i['itemId'], $items), true)) {
        return 'Rabatkoden gælder ikke for varerne i din kurv.';
    }
    $_SESSION['discountCodeId'] = (int)$code['discountCodeID'];
    return null;
}

// How many kr. the code takes off these basket lines (needs itemId, varriantPrice and amount). Never more than the
// goods cost. Free shipping gives 0 here, it's handled by setting the shipping price to 0.
function discountFor(?array $code, array $items): float
{
    if ($code === null) {
        return 0.0;
    }
    $subtotal = 0.0;
    $itemSubtotal = 0.0;
    foreach ($items as $item) {
        $line = (float)$item['varriantPrice'] * (int)$item['amount'];
        $subtotal += $line;
        if ((int)$item['itemId'] === (int)$code['itemId']) {
            $itemSubtotal += $line;
        }
    }
    $value = (float)$code['discountAmount'];
    $discount = match ($code['discountType']) {
        'percent' => $subtotal * $value / 100,
        'product' => $itemSubtotal * $value / 100,
        'amount' => $value,
        default => 0.0,
    };
    return round(min(max($discount, 0.0), $subtotal), 2);
}

// Shown to the customer, e.g. "SOMMER (10 % på hele ordren)"
function describeDiscountCode(array $code): string
{
    $value = rtrim(rtrim(number_format((float)$code['discountAmount'], 2, ',', '.'), '0'), ',');
    return $code['code'] . ' (' . match ($code['discountType']) {
        'product' => $value . ' % på en vare',
        'freeShipping' => 'gratis fragt',
        'percent' => $value . ' % på hele ordren',
        'amount' => $value . ' kr. på ordren',
        default => 'rabat',
    } . ')';
}
