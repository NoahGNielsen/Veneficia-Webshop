<?php
// Shared helpers for the public product pages (/products and the product pages)

// 1234.5 -> "1.234,50 kr."
function formatPrice(float|string $price): string
{
    return number_format((float)$price, 2, ',', '.') . ' kr.';
}

// URL of the product list (or of a category's list, given its path from loadShopCategories()), optionally filtered by
// tag and paged. Page 1 and no tag give the plain path.
function productsUrl(?string $tag = null, int $page = 1, string $path = '/products'): string
{
    $query = array_filter(['tag' => $tag, 'page' => $page > 1 ? $page : null], fn($v) => $v !== null && $v !== '');
    return $path . ($query ? '?' . http_build_query($query) : '');
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
    uasort($categories, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return $categories;
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
