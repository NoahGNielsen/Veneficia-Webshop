<?php
// The admin's frame on every admin page: a sidebar with the pages in three groups (Butik, Site, Konto) and who is
// logged in, with the log-out button. From 840px it sits left of the page (admin.css); below that a top bar's menu
// button opens the same <nav> as a drawer with the Popover API, like the shop's menu, so no JavaScript is needed.
// Needs $employee from requireEmployee(). The current page gets aria-current="page".
//
// Ordrer has a badge with the number of paid orders, the ones waiting to be packed (one COUNT on the ordreStatus index).
$adminPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$adminSection = match (is_string($adminPath) ? rtrim($adminPath, '/') : '') {
    '/admin', '/admin/index' => 'home',
    '/admin/orders', '/admin/order' => 'orders',
    '/admin/items', '/admin/item' => 'items',
    '/admin/categories' => 'categories',
    '/admin/discountCodes' => 'discountCodes',
    '/admin/sitemap' => 'sitemap',
    '/admin/employees' => 'employees',
    '/admin/resetPassword' => 'resetPassword',
    default => '',
};
$adminPaidOrders = (int)getDb()->query("SELECT COUNT(*) FROM VeneficiaOrdre WHERE ordreStatus = 'paid'")->fetchColumn();

// [section, href, icon, label]. Add new admin pages here (and to the match above).
$adminNav = [
    'Butik' => [
        ['home', '/admin/', 'space-dashboard', 'Overblik'],
        ['orders', '/admin/orders', 'receipt-long', 'Ordrer'],
        ['items', '/admin/items', 'inventory-2', 'Varer'],
        ['categories', '/admin/categories', 'category', 'Kategorier'],
        ['discountCodes', '/admin/discountCodes', 'sell', 'Rabatkoder'],
    ],
    'Site' => [
        ['sitemap', '/admin/sitemap', 'account-tree', 'Sitemap'],
        ['', '/', 'storefront', 'Se butikken'],
    ],
    'Konto' => [
        ['employees', '/admin/employees', 'group', 'Medarbejdere'],
        ['resetPassword', '/admin/resetPassword', 'key', 'Skift adgangskode'],
    ],
];
$adminName = $employee['employeeUsername'];
?>
<a class="skip-link" href="#indhold">Spring til indhold</a>
<header class="admin-topbar on-secondary">
    <a class="brand" href="/admin/"><?= uiIcon('logo') ?>Veneficia</a>
    <button type="button" class="icon-button menu-button" popovertarget="admin-menu" aria-label="Menu"><?= uiIcon('menu', 'icon--menu') ?><?= uiIcon('close', 'icon--close') ?></button>
</header>
<nav id="admin-menu" class="admin-nav on-secondary" popover aria-label="Admin">
    <div class="admin-nav__head">
        <a class="brand" href="/admin/"><?= uiIcon('logo') ?>Veneficia</a>
        <span class="admin-nav__tag">Admin</span>
        <button type="button" class="icon-button admin-nav__close" popovertarget="admin-menu" popovertargetaction="hide" aria-label="Luk menu"><?= uiIcon('close') ?></button>
    </div>
    <div class="admin-menu">
        <?php foreach ($adminNav as $group => $links): ?>
            <p class="admin-menu__label" id="admin-menu-<?= htmlspecialchars(strtolower($group)) ?>"><?= htmlspecialchars($group) ?></p>
            <ul class="nav-list" aria-labelledby="admin-menu-<?= htmlspecialchars(strtolower($group)) ?>">
                <?php foreach ($links as [$section, $href, $icon, $label]): ?>
                    <li>
                        <a class="nav-link" href="<?= htmlspecialchars($href) ?>"<?= $section !== '' && $section === $adminSection ? ' aria-current="page"' : '' ?>>
                            <?= uiIcon($icon) ?><?= htmlspecialchars($label) ?>
                            <?php if ($section === 'orders' && $adminPaidOrders > 0): ?>
                                <span class="badge"><?= $adminPaidOrders ?><span class="visually-hidden"> venter på at blive pakket</span></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </div>
    <div class="admin-account">
        <span class="admin-account__avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($adminName, 0, 1))) ?></span>
        <span class="admin-account__who"><span class="admin-account__muted">Logget ind som</span> <strong><?= htmlspecialchars($adminName) ?></strong></span>
        <form method="post" action="/admin/logout">
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">
            <button type="submit" class="icon-button" aria-label="Log ud" title="Log ud"><?= uiIcon('logout') ?></button>
        </form>
    </div>
</nav>
