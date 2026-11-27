<?php
// Every product in the shop, PRODUCTS_PER_PAGE at a time. /products?tag=sommer shows only the products with that tag.
// /category-name (see categoryPagePath() and .htaccess) shows only the products in that category or one of its
// subcategories, and takes the same parameters. ?q= searches names, descriptions and categories, ?sort= picks one of
// PRODUCT_SORTS (default: in stock first, then with a picture, then newest) and ?lager=1 hides sold-out products.
// Products without any variants are left out, since they can't be bought.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/products.php';
startSession(false);

const PRODUCTS_PER_PAGE = 48;
const PRODUCTS_MAX_PAGE = 1000;

$tag = is_string($_GET['tag'] ?? null) ? trim($_GET['tag']) : '';
if (mb_strlen($tag) > 50) {
    $tag = '';
}
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => PRODUCTS_MAX_PAGE]]) ?: 1;
$search = is_string($_GET['q'] ?? null) ? mb_substr(trim(preg_replace('/\s+/u', ' ', $_GET['q']) ?? ''), 0, 100) : '';
$sort = is_string($_GET['sort'] ?? null) && isset(PRODUCT_SORTS[$_GET['sort']]) ? $_GET['sort'] : PRODUCT_SORT_DEFAULT;
$inStockOnly = ($_GET['lager'] ?? '') === '1';
// The filters every link on the page keeps (category chips, sort, pages)
$listFilters = ['q' => $search, 'sort' => $sort, 'lager' => $inStockOnly ? '1' : null];

$db = getDb();
$categories = loadShopCategories($db);

// Anything but /products came through the category rewrite, so the path names the category
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '';
$categoryId = null;
if ($requestPath !== '/products' && $requestPath !== '/products/') {
    $wanted = '/' . strtolower(trim(rawurldecode($requestPath), '/'));
    foreach ($categories as $id => $category) {
        if ($category['path'] === $wanted) {
            $categoryId = $id;
            break;
        }
    }
    if ($categoryId === null) {
        include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/statusCodesPages/404.php';
        exit;
    }
    // /Toej or /toej/ moves to /toej, so each category has one URL
    if ($requestPath !== $wanted) {
        header('Location: ' . productsUrl($tag, $page, $wanted, $listFilters), true, 301);
        exit;
    }
}
$category = $categoryId !== null ? $categories[$categoryId] : null;
$listPath = $category['path'] ?? '/products';

$where = [];
$params = [];
if ($tag !== '') {
    $where[] = 'EXISTS (SELECT 1 FROM VeneficiaItemTags it JOIN VeneficiaTags t ON t.tagId = it.tagId WHERE it.itemId = i.itemId AND t.tagName = ?)';
    $params[] = $tag;
}
if ($categoryId !== null) {
    // The category or any of its subcategories. Each id is bound as a JSON value ("5"), like /admin/categories does.
    $ids = categoryWithDescendants($categories, $categoryId);
    $where[] = '(' . implode(' OR ', array_fill(0, count($ids), "JSON_CONTAINS(i.itemOptions->'$.categories', ?)")) . ')';
    array_push($params, ...array_map('strval', $ids));
}
array_push($where, ...productSearchWhere($search, $params));

// One row more than a page is fetched, so we know whether there is a next page without a separate COUNT query
$products = loadProductCards($db, $where, $params, PRODUCT_SORTS[$sort][1], PRODUCTS_PER_PAGE + 1, ($page - 1) * PRODUCTS_PER_PAGE,
    $inStockOnly ? 'SUM(v.inventoryCount) > 0' : '');
$hasNext = count($products) > PRODUCTS_PER_PAGE;
$products = array_slice($products, 0, PRODUCTS_PER_PAGE);

// The categories to browse into: the subcategories here, or the top level on /products
$subcategories = array_filter($categories, fn($c) => $c['parent'] === $categoryId && $c['path'] !== null);

// The category chips. Usually "Alle" (this page, marked as current) plus the categories above. A category without
// subcategories shows its own level instead, with itself marked, so a neighbouring category is one tap away.
$chipCategories = $subcategories;
$chipAll = ['path' => $listPath, 'name' => $category === null ? 'Alle' : 'Alle i ' . $category['name'], 'current' => true];
if (!$subcategories && $category !== null) {
    $chipCategories = array_filter($categories, fn($c) => $c['parent'] === $category['parent'] && $c['path'] !== null);
    $parent = $category['parent'] !== null ? ($categories[$category['parent']] ?? null) : null;
    $chipAll = $parent !== null && $parent['path'] !== null
        ? ['path' => $parent['path'], 'name' => 'Alle i ' . $parent['name'], 'current' => false]
        : ['path' => '/products', 'name' => 'Alle', 'current' => false];
}
$navSection = 'products';

// A page past the end (or an unknown tag) has nothing to show. An empty category or search is still a real page.
if (!$products && ($page > 1 || $tag !== '') && $search === '' && !$inStockOnly) {
    http_response_code(404);
}
// A search, another sort or the stock filter is a view of the same list: search engines get the list itself
$headerCanonicalPath = productsUrl($tag, $page, $listPath);
$listIsFiltered = $search !== '' || $sort !== PRODUCT_SORT_DEFAULT || $inStockOnly;
$heading = $search !== ''
    ? 'Søgning: „' . $search . '“' . ($category !== null ? ' i ' . $category['name'] : '')
    : ($category['name'] ?? 'Produkter');
$title = $heading . ($tag !== '' ? ' med tagget ' . $tag : '') . ($page > 1 ? " - side $page" : '') . ' - Veneficia';
if ($category !== null && $category['description'] !== '' && $tag === '') {
    $description = mb_strimwidth(preg_replace('/\s+/u', ' ', $category['description']), 0, 160, '…');
} else {
    $description = 'Se alle Veneficias produkter' . ($category !== null ? ' i kategorien ' . $category['name'] : '')
        . ($tag !== '' ? " med tagget $tag" : '') . '.';
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/products.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/products.css') ?>">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($description) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
    <?php if (!$products || $listIsFiltered): ?>
        <meta name="robots" content="noindex">
    <?php endif; ?>
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/navbar.php'; ?>
    <main id="indhold" class="products-page">
        <header class="page-header">
            <nav class="breadcrumbs" aria-label="Brødkrumme">
                <ol>
                    <li><a href="/">Forside</a></li>
                    <?php if ($category === null && $tag === '' && $search === ''): ?>
                        <li><span aria-current="page">Produkter</span></li>
                    <?php else: ?>
                        <li><a href="/products">Produkter</a></li>
                        <?php if ($category !== null && $category['parent'] !== null && ($categories[$category['parent']]['path'] ?? null) !== null): ?>
                            <li><a href="<?= htmlspecialchars($categories[$category['parent']]['path']) ?>"><?= htmlspecialchars($categories[$category['parent']]['name']) ?></a></li>
                        <?php endif; ?>
                        <?php if ($category !== null && $tag === ''): ?>
                            <li><span aria-current="page"><?= htmlspecialchars($category['name']) ?></span></li>
                        <?php elseif ($category !== null): ?>
                            <li><a href="<?= htmlspecialchars($listPath) ?>"><?= htmlspecialchars($category['name']) ?></a></li>
                        <?php endif; ?>
                        <?php if ($tag !== ''): ?>
                            <li><span aria-current="page">Tagget „<?= htmlspecialchars($tag) ?>“</span></li>
                        <?php elseif ($search !== ''): ?>
                            <li><span aria-current="page">Søgning</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ol>
            </nav>
            <h1><?= htmlspecialchars($heading) ?><?= $tag !== '' ? ' med tagget „' . htmlspecialchars($tag) . '“' : '' ?></h1>
            <?php if ($category !== null && $category['description'] !== '' && $page === 1 && $search === ''): ?>
                <p class="category-description type-body-large"><?= nl2br(htmlspecialchars($category['description'])) ?></p>
            <?php endif; ?>
            <?php if ($tag !== ''): ?>
                <p class="tag-filter">
                    <span class="chip" aria-current="true"><span class="visually-hidden">Viser tagget </span><?= htmlspecialchars($tag) ?></span>
                    <a href="<?= htmlspecialchars(productsUrl(null, 1, $listPath, $listFilters)) ?>">Vis alle <?= $category !== null ? 'i ' . htmlspecialchars($category['name']) : 'produkter' ?></a>
                </p>
            <?php endif; ?>

            <?php if ($chipCategories): ?>
                <nav class="category-list" id="kategorier" aria-label="Kategorier">
                    <ul class="chip-set">
                        <li><a class="chip" href="<?= htmlspecialchars(productsUrl($tag, 1, $chipAll['path'], $listFilters)) ?>"<?= $chipAll['current'] ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($chipAll['name']) ?></a></li>
                        <?php foreach ($chipCategories as $chipId => $chip): ?>
                            <li><a class="chip" href="<?= htmlspecialchars(productsUrl($tag, 1, $chip['path'], $listFilters)) ?>"<?= $chipId === $categoryId ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($chip['name']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
                <script>
                    // On a phone the chips scroll sideways; bring the current one into view (without moving the page)
                    (() => {
                        const list = document.querySelector('#kategorier .chip-set');
                        const current = list && list.querySelector('[aria-current]');
                        if (current && list.scrollWidth > list.clientWidth) {
                            list.scrollLeft += current.getBoundingClientRect().left - list.getBoundingClientRect().left - 16;
                        }
                    })();
                </script>
            <?php endif; ?>

            <?php // Search in this list (on phones, and in a category; the top app bar searches the whole shop) ?>
            <form class="list-search<?= $category !== null ? ' list-search--category' : '' ?>" method="get" action="<?= htmlspecialchars($listPath) ?>" role="search">
                <?php if ($tag !== ''): ?>
                    <input type="hidden" name="tag" value="<?= htmlspecialchars($tag) ?>">
                <?php endif; ?>
                <?php if ($sort !== PRODUCT_SORT_DEFAULT): ?>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <?php endif; ?>
                <?php if ($inStockOnly): ?>
                    <input type="hidden" name="lager" value="1">
                <?php endif; ?>
                <label for="list-q" class="visually-hidden">Søg i <?= $category !== null ? htmlspecialchars($category['name']) : 'produkterne' ?></label>
                <input type="search" id="list-q" name="q" maxlength="100" placeholder="Søg i <?= $category !== null ? htmlspecialchars($category['name']) : 'produkterne' ?>" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="icon-button" aria-label="Søg"><?= uiIcon('search') ?></button>
            </form>

            <?php // The stock filter and the sort are links: one tap, no form, and the page's address says what you see ?>
            <div class="list-tools">
                <a class="chip" href="<?= htmlspecialchars(productsUrl($tag, 1, $listPath, array_merge($listFilters, ['lager' => $inStockOnly ? null : '1']))) ?>"<?= $inStockOnly ? ' aria-current="true"' : '' ?>>Kun på lager</a>
                <nav class="list-sort" aria-label="Sortering">
                    <ul class="chip-set">
                        <?php foreach (PRODUCT_SORTS as $sortKey => [$sortLabel]): ?>
                            <li><a class="chip" href="<?= htmlspecialchars(productsUrl($tag, 1, $listPath, array_merge($listFilters, ['sort' => $sortKey]))) ?>"<?= $sortKey === $sort ? ' aria-current="true"' : '' ?>><?= htmlspecialchars($sortLabel) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </header>

        <?php if (!$products): ?>
            <div class="empty-state">
                <span class="arch-icon"><?= uiIcon($search !== '' ? 'search' : 'category') ?></span>
                <?php if ($search !== ''): ?>
                    <p class="type-title-large">Ingen varer passer til „<?= htmlspecialchars($search) ?>“</p>
                    <p>Prøv et andet eller kortere ord<?= $inStockOnly ? ', eller vis også de udsolgte' : '' ?>.</p>
                    <a class="button" href="<?= htmlspecialchars($inStockOnly ? productsUrl($tag, 1, $listPath, array_merge($listFilters, ['lager' => null])) : '/products') ?>"><?= $inStockOnly ? 'Vis også udsolgte' : 'Se alle produkter' ?></a>
                <?php elseif ($inStockOnly): ?>
                    <p class="type-title-large">Intet er på lager her lige nu</p>
                    <p>Se de udsolgte varer, eller kig i en anden kategori.</p>
                    <a class="button" href="<?= htmlspecialchars(productsUrl($tag, 1, $listPath, array_merge($listFilters, ['lager' => null]))) ?>">Vis også udsolgte</a>
                <?php elseif ($category === null && $tag === '' && $page === 1): ?>
                    <p class="type-title-large">Der er ingen produkter at vise</p>
                    <p>Kig forbi igen snart.</p>
                    <a class="button" href="/">Til forsiden</a>
                <?php else: ?>
                    <p class="type-title-large">Der er ingen produkter at vise</p>
                    <p>Prøv en anden kategori, eller se alle produkterne.</p>
                    <a class="button" href="/products">Se alle produkter</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <ul class="product-grid">
                <?php foreach ($products as $i => $product): ?>
                    <?= productCard($product, $i) ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($page > 1 || $hasNext): ?>
            <nav class="pagination product-pages" aria-label="Sider">
                <?php if ($page > 1): ?>
                    <a class="button button--outlined" href="<?= htmlspecialchars(productsUrl($tag, $page - 1, $listPath, $listFilters)) ?>" rel="prev"><?= uiIcon('chevron-left', 'icon--20') ?>Forrige side</a>
                <?php endif; ?>
                <span class="pagination__current">Side <?= $page ?></span>
                <?php if ($hasNext): ?>
                    <a class="button button--outlined" href="<?= htmlspecialchars(productsUrl($tag, $page + 1, $listPath, $listFilters)) ?>" rel="next">Næste side<?= uiIcon('chevron-right', 'icon--20') ?></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
