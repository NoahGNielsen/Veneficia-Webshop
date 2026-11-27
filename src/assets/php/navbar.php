<?php
// The top app bar on every shop page. Shows the login state if the page called startSession() before its output
// (navbar is included mid-page, too late to start a session itself).
//
// Compact screens get the wordmark, the basket and a menu button; the button opens the <nav> as a navigation drawer
// with the Popover API, so it works without JavaScript and closes with Esc or a click outside. The drawer has the
// search, the links and the categories. From 840px the same <nav> sits in the bar (mainStyle.css) with the search
// and the links; the categories are on the product list there. The skip link jumps to <main id="indhold">, which every
// page with this bar has.
//
// The current page gets aria-current="page". A page can set $navSection first; products.php and product.php set
// 'products', since category and product URLs don't start with /products.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/basket.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/products.php';
$navCustomer = currentCustomer();
// The search keeps what was searched for; the drawer lists the top-level categories
$navSearch = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$navCategories = array_filter(loadShopCategories(getDb()), fn($c) => $c['parent'] === null && $c['path'] !== null);
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
        <a class="brand" href="/"><?= uiIcon('logo') ?>Veneficia</a>
        <nav id="menu" class="nav-drawer" popover aria-label="Hovedmenu">
            <div class="nav-drawer__header">
                <span class="brand"><?= uiIcon('logo') ?>Veneficia</span>
                <button type="button" class="icon-button" popovertarget="menu" popovertargetaction="hide" aria-label="Luk menu"><?= uiIcon('close') ?></button>
            </div>
            <form class="nav-search" method="get" action="/products" role="search">
                <label for="nav-q" class="visually-hidden">Søg i butikken</label>
                <input type="search" id="nav-q" name="q" maxlength="100" placeholder="Søg i butikken" value="<?= htmlspecialchars($navSearch) ?>">
                <button type="submit" class="icon-button" aria-label="Søg"><?= uiIcon('search') ?></button>
            </form>
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
                    <?php // One way in: the login page links to "Opret konto" ?>
                    <li><a class="nav-link" href="/userMgmt/login">Log ind</a></li>
                <?php endif; ?>
            </ul>
            <?php // Phones: the categories in the drawer, with their icons (from 840px they are on the product list) ?>
            <?php if ($navCategories): ?>
                <p class="nav-drawer__label" id="nav-kategorier">Kategorier</p>
                <ul class="nav-list nav-categories" aria-labelledby="nav-kategorier">
                    <?php foreach ($navCategories as $navCategory): ?>
                        <li><a class="nav-link" href="<?= htmlspecialchars($navCategory['path']) ?>"><?= uiIcon(categoryIcon($navCategory['name']), 'icon--20') ?><?= htmlspecialchars($navCategory['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
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
