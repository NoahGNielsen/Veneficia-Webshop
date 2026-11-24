<?php
// Every product in the shop, newest first, PRODUCTS_PER_PAGE at a time. /products?tag=sommer shows only the products
// with that tag. /category-name (see categoryPagePath() and .htaccess) shows only the products in that category or one of
// its subcategories, and takes ?tag= too. Products without any variants are left out, since they can't be bought.
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
        header('Location: ' . productsUrl($tag, $page, $wanted), true, 301);
        exit;
    }
}
$category = $categoryId !== null ? $categories[$categoryId] : null;
$listPath = $category['path'] ?? '/products';

// One row more than a page is fetched, so we know whether there is a next page without a separate COUNT query
$sql = 'SELECT i.itemId, i.itemName, MIN(v.varriantPrice) AS minPrice, MAX(v.varriantPrice) AS maxPrice, SUM(v.inventoryCount) AS stock,
               MIN(pc.categoryName) AS categoryName
        FROM VeneficiaItems i ' . PRODUCT_CATEGORY_JOIN . ' JOIN VeneficiaItemVariants v ON v.itemId = i.itemId';
$params = [];
if ($tag !== '') {
    $sql .= ' JOIN VeneficiaItemTags it ON it.itemId = i.itemId JOIN VeneficiaTags t ON t.tagId = it.tagId AND t.tagName = ?';
    $params[] = $tag;
}
if ($categoryId !== null) {
    // The category or any of its subcategories. Each id is bound as a JSON value ("5"), like /admin/categories does.
    $ids = categoryWithDescendants($categories, $categoryId);
    $sql .= ' WHERE (' . implode(' OR ', array_fill(0, count($ids), "JSON_CONTAINS(i.itemOptions->'$.categories', ?)")) . ')';
    array_push($params, ...array_map('strval', $ids));
}
$sql .= ' GROUP BY i.itemId ORDER BY i.itemId DESC LIMIT ' . (PRODUCTS_PER_PAGE + 1) . ' OFFSET ' . (($page - 1) * PRODUCTS_PER_PAGE);
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$hasNext = count($products) > PRODUCTS_PER_PAGE;
$products = array_slice($products, 0, PRODUCTS_PER_PAGE);

// The first image of each product on this page, in one query
$images = [];
if ($products) {
    $ids = array_map('intval', array_column($products, 'itemId'));
    $stmt = $db->prepare(
        'SELECT ia.itemId, a.fileUrl, a.altText
         FROM VeneficiaItemAttachments ia JOIN VeneficiaAttachments a ON a.attachmentId = ia.attachmentId
         WHERE ia.itemId IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
         ORDER BY ia.itemId, ia.sortOrder, a.attachmentId'
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $images[(int)$row['itemId']] ??= $row;
    }
}

// The categories to browse into: the subcategories here, or the top level on /products
$subcategories = array_filter($categories, fn($c) => $c['parent'] === $categoryId && $c['path'] !== null);

// A page past the end (or an unknown tag) has nothing to show. An empty category is still a real page, just noindex.
if (!$products && ($page > 1 || $tag !== '')) {
    http_response_code(404);
}
$headerCanonicalPath = productsUrl($tag, $page, $listPath);
$heading = $category['name'] ?? 'Produkter';
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
    <?php if (!$products): ?>
        <meta name="robots" content="noindex">
    <?php endif; ?>
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/navbar.php'; ?>
    <main id="indhold" class="products-page">
        <?php if ($category !== null): ?>
            <p>
                <a href="/products">Alle produkter</a>
                <?php if ($category['parent'] !== null && ($categories[$category['parent']]['path'] ?? null) !== null): ?>
                    · <a href="<?= htmlspecialchars($categories[$category['parent']]['path']) ?>"><?= htmlspecialchars($categories[$category['parent']]['name']) ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <h1><?= htmlspecialchars($heading) ?><?= $tag !== '' ? ' med tagget „' . htmlspecialchars($tag) . '“' : '' ?></h1>
        <?php if ($tag !== ''): ?>
            <p><a href="<?= htmlspecialchars($listPath) ?>">Vis alle <?= $category !== null ? 'i ' . htmlspecialchars($category['name']) : 'produkter' ?></a></p>
        <?php endif; ?>
        <?php if ($category !== null && $category['description'] !== '' && $page === 1): ?>
            <p class="category-description"><?= nl2br(htmlspecialchars($category['description'])) ?></p>
        <?php endif; ?>

        <?php if ($subcategories): ?>
            <nav class="category-list" aria-label="Kategorier">
                <ul>
                    <?php foreach ($subcategories as $sub): ?>
                        <li><a href="<?= htmlspecialchars(productsUrl($tag, 1, $sub['path'])) ?>"><?= htmlspecialchars($sub['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>

        <?php if (!$products): ?>
            <p>Der er ingen produkter at vise.</p>
        <?php else: ?>
            <ul class="product-grid">
                <?php foreach ($products as $i => $product):
                    $id = (int)$product['itemId'];
                    $image = $images[$id] ?? null;
                    $samePrice = (float)$product['minPrice'] === (float)$product['maxPrice'];
                ?>
                    <li class="product-card">
                        <a href="<?= htmlspecialchars(productUrl($id, $product['itemName'], $product['categoryName'])) ?>">
                            <?php if ($image !== null): ?>
                                <img src="<?= htmlspecialchars($image['fileUrl']) ?>" alt="<?= htmlspecialchars($image['altText'] ?: $product['itemName']) ?>"
                                     width="300" height="300" decoding="async" <?= $i < 4 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                            <?php else: ?>
                                <span class="product-noimage">Intet billede</span>
                            <?php endif; ?>
                            <span class="product-name"><?= htmlspecialchars($product['itemName']) ?></span>
                        </a>
                        <span class="product-price"><?= $samePrice ? '' : 'Fra ' ?><?= formatPrice($product['minPrice']) ?></span>
                        <span class="product-stock"><?= (int)$product['stock'] > 0 ? 'På lager' : 'Udsolgt' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($page > 1 || $hasNext): ?>
            <nav class="product-pages" aria-label="Sider">
                <?php if ($page > 1): ?>
                    <a href="<?= htmlspecialchars(productsUrl($tag, $page - 1, $listPath)) ?>" rel="prev">Forrige side</a>
                <?php endif; ?>
                <span>Side <?= $page ?></span>
                <?php if ($hasNext): ?>
                    <a href="<?= htmlspecialchars(productsUrl($tag, $page + 1, $listPath)) ?>" rel="next">Næste side</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
