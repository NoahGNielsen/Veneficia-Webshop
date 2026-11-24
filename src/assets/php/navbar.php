<?php
// The top app bar on every shop page. Shows the login state if the page called startSession() before its output
// (navbar is included mid-page, too late to start a session itself).
//
// Compact screens get the wordmark, the basket and a menu button; the button opens the <nav> as a navigation drawer
// with the Popover API, so it works without JavaScript and closes with Esc or a click outside. From 840px the same <nav>
// sits in the bar (mainStyle.css). The skip link jumps to <main id="indhold">, which every page with this bar has.
//
// The current page gets aria-current="page". A page can set $navSection first; products.php and product.php set
// 'products', since category and product URLs don't start with /products.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/basket.php';
$navCustomer = currentCustomer();
$navBasketCount = basketCount();
$navPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$navSection ??= match (is_string($navPath) ? rtrim($navPath, '/') : '') {
    '/products' => 'products',
    '/basket' => 'basket',
    default => '',
};
$navCurrent = fn(string $section): string => $navSection === $section ? ' aria-current="page"' : '';
$navBasketLabel = 'Kurv' . match (true) {
    $navBasketCount === 1 => ', 1 vare',
    $navBasketCount > 1 => ', ' . $navBasketCount . ' varer',
    default => '',
};
?>
<header class="navbar on-secondary">
    <a class="skip-link" href="#indhold">Spring til indhold</a>
    <div class="navbar__inner container">
        <a class="brand" href="/"><?= uiIcon('crescent') ?>Veneficia</a>
        <nav id="menu" class="nav-drawer" popover aria-label="Hovedmenu">
            <div class="nav-drawer__header">
                <span class="brand"><?= uiIcon('crescent') ?>Veneficia</span>
                <button type="button" class="icon-button" popovertarget="menu" popovertargetaction="hide" aria-label="Luk menu"><?= uiIcon('close') ?></button>
            </div>
            <ul class="nav-list">
                <li><a class="nav-link" href="/products"<?= $navCurrent('products') ?>>Produkter</a></li>
                <?php if ($navCustomer !== null): ?>
                    <li class="nav-greeting">Hej, <?= htmlspecialchars($navCustomer['firstName']) ?></li>
                    <li>
                        <form method="post" action="/userMgmt/logout">
                            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <button type="submit" class="nav-link">Log ud</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li><a class="nav-link" href="/userMgmt/login">Log ind</a></li>
                    <li><a class="nav-link" href="/userMgmt/signup">Opret konto</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <a class="cart-link" href="/basket" aria-label="<?= $navBasketLabel ?>"<?= $navCurrent('basket') ?>>
            <?= uiIcon('shopping-bag') ?>
            <?php if ($navBasketCount > 0): ?>
                <span class="badge" aria-hidden="true"><?= $navBasketCount > 99 ? '99+' : $navBasketCount ?></span>
            <?php endif; ?>
        </a>
        <button type="button" class="icon-button menu-button" popovertarget="menu" aria-label="Menu"><?= uiIcon('menu', 'icon--menu') ?><?= uiIcon('close', 'icon--close') ?></button>
    </div>
</header>
