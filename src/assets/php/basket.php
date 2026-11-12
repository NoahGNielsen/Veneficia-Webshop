<?php
// The shopping basket. A logged-in customer's basket lives in VeneficiaBasketItems, so it follows them across devices.
// A guest's basket lives in their session ($_SESSION['basket'] = [itemVariantId => amount]) and is moved into the
// database when they log in or sign up (see mergeGuestBasket(), called from loginCustomer()).
require_once __DIR__ . '/session.php';

const BASKET_MAX_AMOUNT = 99; // per variant
const BASKET_MAX_LINES = 50;  // different variants in one basket, so a session can't be stuffed endlessly

// [itemVariantId => amount] for the current visitor. Only works once startSession() has run.
function basketLines(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }
    if (isset($_SESSION['customerId'])) {
        $stmt = getDb()->prepare('SELECT itemVariantId, amount FROM VeneficiaBasketItems WHERE customerId = ? ORDER BY timeAdded');
        $stmt->execute([$_SESSION['customerId']]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }
    return is_array($_SESSION['basket'] ?? null) ? $_SESSION['basket'] : [];
}

// Total number of things in the basket, for the navbar
function basketCount(): int
{
    static $count = null;
    if ($count === null) {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['customerId'])) {
            $stmt = getDb()->prepare('SELECT COALESCE(SUM(amount), 0) FROM VeneficiaBasketItems WHERE customerId = ?');
            $stmt->execute([$_SESSION['customerId']]);
            $count = (int)$stmt->fetchColumn();
        } else {
            $count = array_sum(basketLines());
        }
    }
    return $count;
}

// Every basket line with its product, variant name, current price and stock, in the order they were added
function basketItems(): array
{
    $lines = basketLines();
    if (!$lines) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($lines), '?'));
    $stmt = getDb()->prepare(
        "SELECT v.itemVariantId, v.itemId, v.varriantName, v.varriantPrice, v.varriantWeight, v.inventoryCount, i.itemName
         FROM VeneficiaItemVariants v JOIN VeneficiaItems i ON i.itemId = v.itemId
         WHERE v.itemVariantId IN ($placeholders)"
    );
    $stmt->execute(array_keys($lines));
    $variants = array_column($stmt->fetchAll(), null, 'itemVariantId');

    $items = [];
    foreach ($lines as $variantId => $amount) {
        // A variant deleted since it was added (the database cascades it away, a guest's session doesn't)
        if (!isset($variants[$variantId])) {
            continue;
        }
        $items[] = $variants[$variantId] + ['amount' => $amount];
    }
    return $items;
}

// Sets how many of a variant are in the basket, capped by stock and BASKET_MAX_AMOUNT. 0 removes it.
// Returns the amount actually stored, or null if the variant doesn't exist.
function setBasketAmount(int $variantId, int $amount): ?int
{
    $stmt = getDb()->prepare('SELECT inventoryCount FROM VeneficiaItemVariants WHERE itemVariantId = ?');
    $stmt->execute([$variantId]);
    $stock = $stmt->fetchColumn();
    if ($stock === false) {
        return null;
    }
    $amount = max(0, min($amount, (int)$stock, BASKET_MAX_AMOUNT));

    if (isset($_SESSION['customerId'])) {
        if ($amount === 0) {
            getDb()->prepare('DELETE FROM VeneficiaBasketItems WHERE customerId = ? AND itemVariantId = ?')
                ->execute([$_SESSION['customerId'], $variantId]);
        } else {
            $lines = basketLines();
            if (!isset($lines[$variantId]) && count($lines) >= BASKET_MAX_LINES) {
                return 0;
            }
            getDb()->prepare(
                'INSERT INTO VeneficiaBasketItems (customerId, itemVariantId, amount) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE amount = VALUES(amount)'
            )->execute([$_SESSION['customerId'], $variantId, $amount]);
        }
    } else {
        $basket = basketLines();
        if ($amount === 0) {
            unset($basket[$variantId]);
        } elseif (isset($basket[$variantId]) || count($basket) < BASKET_MAX_LINES) {
            $basket[$variantId] = $amount;
        } else {
            return 0;
        }
        $_SESSION['basket'] = $basket;
    }
    return $amount;
}

// Adds to what's already in the basket. Returns how many were actually added (less when stock runs out), or null if
// the variant doesn't exist.
function addToBasket(int $variantId, int $amount): ?int
{
    $current = basketLines()[$variantId] ?? 0;
    $stored = setBasketAmount($variantId, $current + max(0, $amount));
    return $stored === null ? null : max(0, $stored - $current);
}

// Moves a guest basket into the customer's database basket on login, adding to what they already had there
function mergeGuestBasket(array $guestBasket): void
{
    foreach (array_slice($guestBasket, 0, BASKET_MAX_LINES, true) as $variantId => $amount) {
        if (is_int($variantId) && is_int($amount) && $amount > 0) {
            addToBasket($variantId, $amount);
        }
    }
}

// Basket forms must work for guests who have no session (and so no CSRF token) yet. They're accepted when they carry
// the session's CSRF token, or, only while the session has no token at all, when the browser says the form was sent
// from our own site. Browsers send Origin on every POST, so a form on another site is always refused.
function checkBasketRequest(?string $token): bool
{
    if (checkCsrf($token)) {
        return true;
    }
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['csrfToken'])) {
        return false;
    }
    // Only the host is compared, so it also works behind a proxy that handles HTTPS for us
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    if ($origin !== null) {
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);
        return is_string($host) && strcasecmp($host . ($port ? ':' . $port : ''), $_SERVER['HTTP_HOST'] ?? '') === 0;
    }
    return ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'same-origin';
}

// A one-time message shown on the next page ("Lagt i kurven" etc.)
function setBasketMessage(string $message): void
{
    $_SESSION['basketMessage'] = $message;
}

function takeBasketMessage(): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['basketMessage'])) {
        return null;
    }
    $message = (string)$_SESSION['basketMessage'];
    unset($_SESSION['basketMessage']);
    return $message;
}
