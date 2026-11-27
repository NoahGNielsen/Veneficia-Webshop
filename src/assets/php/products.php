<?php
// Shared helpers for the public product pages (/products and the product pages)

// 1234.5 -> "1.234,50 kr."
function formatPrice(float|string $price): string
{
    return number_format((float)$price, 2, ',', '.') . ' kr.';
}

// For the shop's cards and product page: whole kroner without ",00" ("20 kr.", "89,95 kr."). Tables, the basket and
// the checkout keep formatPrice(), so their figures line up.
function formatPriceShort(float|string $price): string
{
    $price = round((float)$price, 2);
    return number_format($price, fmod($price, 1.0) === 0.0 ? 0 : 2, ',', '.') . ' kr.';
}

// GLS Pakkeshop prices in kr., by the order's total weight: max grams => price. Heavier orders can't be shipped.
// The checkout charges these and the homepage lists them.
const SHIPPING_RATES = [2000 => 45.00, 5000 => 62.00, 10000 => 83.00, 15000 => 127.00, 20000 => 154.00];
const SHIPPING_MAX_GRAMS = 20000;

// What one unit of a variant costs to ship on its own (the product page's "Fragt"); null when it's too heavy or has no
// weight. The checkout charges by the whole order's weight.
function shippingPriceFor(?int $grams): ?float
{
    if ($grams === null) {
        return null;
    }
    foreach (SHIPPING_RATES as $maxGrams => $price) {
        if ($grams <= $maxGrams) {
            return $price;
        }
    }
    return null;
}

// The icon (in assets/icons/icons.svg) for a category, by its name's slug: the homepage's category list and the
// placeholder of a product without images. Add new categories here; anything unknown gets "category".
const CATEGORY_ICONS = [
    'krystaller' => 'diamond',
    'smykker' => 'diamond',
    'roegelse' => 'local-fire-department',
    'lys' => 'candle',
    'urter' => 'psychiatry',
    'eliksirer' => 'experiment',
    'eliksirer-ingredienser' => 'psychiatry',
    'boeger' => 'auto-stories',
    'trylleformularer' => 'menu-book',
    'stave' => 'wand-stars',
    'rituelle-genstande' => 'wine-bar',
    'religioese-symboler' => 'star',
    'andre-symboler' => 'star',
    'stjernetegn' => 'crescent',
];

function categoryIcon(?string $categoryName): string
{
    return CATEGORY_ICONS[urlSlug((string)$categoryName)] ?? 'category';
}

// URL of the product list (or of a category's list, given its path from loadShopCategories()), optionally filtered by
// tag and paged. $extra carries the search, sort and stock filter (['q' => ..., 'sort' => ..., 'lager' => '1']).
// Page 1, the default sort and empty values are left out, so the plain list has the plain path.
function productsUrl(?string $tag = null, int $page = 1, string $path = '/products', array $extra = []): string
{
    $query = array_filter(
        ['q' => $extra['q'] ?? null, 'tag' => $tag, 'sort' => ($extra['sort'] ?? '') === PRODUCT_SORT_DEFAULT ? null : ($extra['sort'] ?? null),
         'lager' => $extra['lager'] ?? null, 'page' => $page > 1 ? $page : null],
        fn($v) => $v !== null && $v !== ''
    );
    return $path . ($query ? '?' . http_build_query($query) : '');
}

// How the product list can be sorted: key => [label, ORDER BY]. "anbefalet" puts what can be bought first: in stock,
// then with a picture, then the newest.
const PRODUCT_SORT_DEFAULT = 'anbefalet';
const PRODUCT_SORTS = [
    'anbefalet' => ['Anbefalet', 'SUM(v.inventoryCount) > 0 DESC, MAX(img.itemId) IS NOT NULL DESC, i.itemId DESC'],
    'nyeste' => ['Nyeste', 'i.itemId DESC'],
    'pris-lav' => ['Billigst først', 'MIN(v.varriantPrice) ASC, i.itemId DESC'],
    'pris-hoej' => ['Dyrest først', 'MIN(v.varriantPrice) DESC, i.itemId DESC'],
];

// The search's words as WHERE conditions on "i" and "pc": every word must be in the name, the description or the
// category name. LIKE's own characters are escaped (\ is its default escape character). At most five words.
function productSearchWhere(string $search, array &$params): array
{
    $where = [];
    foreach (array_slice(preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY), 0, 5) as $word) {
        $like = '%' . addcslashes($word, '%_\\') . '%';
        $where[] = '(i.itemName LIKE ? OR i.itemDescription LIKE ? OR pc.categoryName LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    return $where;
}

// Products for the cards (list, homepage, product page, basket): id, name, lowest and highest price, stock, category
// and the first image ("image", null for none). Only products with variants, since the rest can't be bought.
// $where and $params are extra conditions on "i" (the item) and "pc" (its URL category); $having filters on the
// aggregates ("SUM(v.inventoryCount) > 0" for in stock only). Two queries: the products, then their first images.
function loadProductCards(PDO $db, array $where, array $params, string $orderBy, int $limit, int $offset = 0, string $having = ''): array
{
    $stmt = $db->prepare(
        'SELECT i.itemId, i.itemName, MIN(v.varriantPrice) AS minPrice, MAX(v.varriantPrice) AS maxPrice,
                SUM(v.inventoryCount) AS stock, MIN(pc.categoryName) AS categoryName
         FROM VeneficiaItems i ' . PRODUCT_CATEGORY_JOIN . '
         JOIN VeneficiaItemVariants v ON v.itemId = i.itemId
         LEFT JOIN (SELECT DISTINCT itemId FROM VeneficiaItemAttachments) img ON img.itemId = i.itemId'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' GROUP BY i.itemId' . ($having !== '' ? ' HAVING ' . $having : '')
        . ' ORDER BY ' . $orderBy . ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset
    );
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    if (!$products) {
        return [];
    }

    $ids = array_map('intval', array_column($products, 'itemId'));
    $stmt = $db->prepare(
        'SELECT ia.itemId, a.fileUrl, a.altText
         FROM VeneficiaItemAttachments ia JOIN VeneficiaAttachments a ON a.attachmentId = ia.attachmentId
         WHERE ia.itemId IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
         ORDER BY ia.itemId, ia.sortOrder, a.attachmentId'
    );
    $stmt->execute($ids);
    $images = [];
    foreach ($stmt->fetchAll() as $row) {
        $images[(int)$row['itemId']] ??= $row;
    }
    foreach ($products as &$product) {
        $product['image'] = $images[(int)$product['itemId']] ?? null;
    }
    return $products;
}

// One product card (an <li> for a .product-grid). Products without a picture get the compact card: the category's
// icon in a small arch instead of a square placeholder, so a list of them doesn't become a wall of identical boxes.
// The first $eager cards on a page load their image at once, the rest lazily. Every value is escaped here.
function productCard(array $product, int $index, string $heading = 'h2', int $eager = 4): string
{
    $id = (int)$product['itemId'];
    $image = $product['image'] ?? null;
    $inStock = (int)$product['stock'] > 0;
    $samePrice = (float)$product['minPrice'] === (float)$product['maxPrice'];
    $url = productUrl($id, $product['itemName'], $product['categoryName']);
    $classes = 'product-card' . ($image === null ? ' product-card--compact' : '') . ($inStock ? '' : ' product-card--soldout');

    $html = '<li class="' . $classes . '">';
    if ($image !== null) {
        $html .= '<div class="media-frame"><img src="' . htmlspecialchars($image['fileUrl']) . '" alt="'
            . htmlspecialchars($image['altText'] ?: $product['itemName']) . '" width="300" height="300" decoding="async" '
            . ($index < $eager ? 'fetchpriority="high"' : 'loading="lazy"') . '></div>';
    } else {
        $html .= '<span class="arch-icon product-card__icon" aria-hidden="true">' . uiIcon(categoryIcon($product['categoryName'])) . '</span>';
    }
    $html .= '<div class="product-card__body">';
    if ((string)$product['categoryName'] !== '') {
        $html .= '<p class="product-card__category">' . htmlspecialchars($product['categoryName']) . '</p>';
    }
    $html .= "<$heading class=\"product-card__name\"><a class=\"product-card__link\" href=\"" . htmlspecialchars($url) . '">'
        . htmlspecialchars($product['itemName']) . "</a></$heading>";
    $html .= '<p class="product-card__price">' . ($samePrice ? '' : 'Fra ') . formatPriceShort($product['minPrice']) . '</p>';
    $html .= '<p class="stock' . ($inStock ? ' stock--in' : '') . '">' . ($inStock ? 'På lager' : 'Udsolgt') . '</p>';
    return $html . '</div></li>';
}

// True when /slug already is something else: a folder (admin, assets, ...), a file or a page (basket, products, ...).
// Those win over the category rewrite in .htaccess, so a category can't use that address.
function slugIsTaken(string $slug): bool
{
    $path = $_SERVER['DOCUMENT_ROOT'] . '/' . $slug;
    return file_exists($path) || is_file("$path.php") || is_file("$path.html") || is_file("$path.htm");
}

// Category pages live at /category-name (.htaccess sends every other /a-b-c to products.php). null when the name has
// no letters or digits, or its address is taken by a real page.
function categoryPagePath(string $categoryName): ?string
{
    $slug = urlSlug($categoryName);
    return $slug === '' || slugIsTaken($slug) ? null : '/' . $slug;
}

// Every category keyed by id, sorted by name, with its page path ("path", null for none). When two categories give the
// same path (data from before /admin/categories refused that) the oldest one gets it.
function loadShopCategories(PDO $db): array
{
    // The top app bar and the page both ask for them: one query per request
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }
    $categories = [];
    $usedPaths = [];
    foreach ($db->query('SELECT categoryId, categoryName, categoryDescription, parentCategoryId FROM VeneficiaCategories ORDER BY categoryId') as $row) {
        $path = categoryPagePath($row['categoryName']);
        if ($path !== null && isset($usedPaths[$path])) {
            $path = null;
        } elseif ($path !== null) {
            $usedPaths[$path] = true;
        }
        $categories[(int)$row['categoryId']] = [
            'name' => $row['categoryName'],
            'description' => trim((string)$row['categoryDescription']),
            'parent' => $row['parentCategoryId'] === null ? null : (int)$row['parentCategoryId'],
            'path' => $path,
        ];
    }
    // By name, but "Andet" (other) last: the least telling category shouldn't take the first place in every list
    uasort($categories, fn($a, $b) => (urlSlug($a['name']) === 'andet') <=> (urlSlug($b['name']) === 'andet') ?: strcasecmp($a['name'], $b['name']));
    return $loaded = $categories;
}

// The category's id and the ids of every category below it
function categoryWithDescendants(array $categories, int $id): array
{
    $found = [$id => true];
    do {
        $added = false;
        foreach ($categories as $catId => $category) {
            if (!isset($found[$catId]) && $category['parent'] !== null && isset($found[$category['parent']])) {
                $found[$catId] = true;
                $added = true;
            }
        }
    } while ($added);
    return array_keys($found);
}

// Product pages live at /category/product-name-5: the item's first category (see PRODUCT_CATEGORY_JOIN) and its name
// as URL slugs, then its id. .htaccess sends every /a/b-5 to product.php?id=5, which redirects any other spelling (an
// old /product?id=5 link, a renamed product or category) to this one, so only the id has to be right.
const PRODUCT_URL_FALLBACK = 'produkt'; // the category part for products without a (still existing) category

// LEFT JOIN of the category a product's URL uses, as "pc", for a query on "VeneficiaItems i"
const PRODUCT_CATEGORY_JOIN = 'LEFT JOIN VeneficiaCategories pc'
    . ' ON pc.categoryId = CAST(JSON_UNQUOTE(JSON_EXTRACT(i.itemOptions, \'$.categories[0]\')) AS UNSIGNED)';

// "Rød Æble-kurv!" -> "roed-aeble-kurv". Only a-z, 0-9 and single dashes, at most 80 characters, may be "".
function urlSlug(string $text): string
{
    $text = strtr(mb_strtolower($text), [
        'æ' => 'ae', 'ø' => 'oe', 'å' => 'aa', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'á' => 'a', 'à' => 'a', 'â' => 'a',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'ú' => 'u', 'ù' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ]);
    $text = trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-');
    return rtrim(substr($text, 0, 80), '-');
}

function productUrl(int $itemId, string $itemName, ?string $categoryName): string
{
    $category = urlSlug((string)$categoryName);
    // A category named like a real folder (admin, assets, uploads, ...) would land in that folder instead
    if ($category === '' || is_dir($_SERVER['DOCUMENT_ROOT'] . '/' . $category)) {
        $category = PRODUCT_URL_FALLBACK;
    }
    $name = urlSlug($itemName);
    return '/' . $category . '/' . ($name !== '' ? $name : 'produkt') . '-' . $itemId;
}
