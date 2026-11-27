<?php
// Suggestions while typing in a search field: /searchSuggestions?q=ame gives up to six products as JSON
// ([{name, url, price, category}]), in the product list's recommended order. Public, read-only and the same search as
// /products?q= (productSearchWhere()), so it shows nothing the list doesn't. assets/js/site.js asks for it.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/products.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: public, max-age=60');

$search = is_string($_GET['q'] ?? null) ? mb_substr(trim(preg_replace('/\s+/u', ' ', $_GET['q']) ?? ''), 0, 100) : '';
if (mb_strlen($search) < 2) {
    echo '[]';
    exit;
}

$params = [];
$where = productSearchWhere($search, $params);
$suggestions = [];
foreach (loadProductCards(getDb(), $where, $params, PRODUCT_SORTS[PRODUCT_SORT_DEFAULT][1], 6) as $product) {
    $suggestions[] = [
        'name' => $product['itemName'],
        'url' => productUrl((int)$product['itemId'], $product['itemName'], $product['categoryName']),
        'price' => ((float)$product['minPrice'] === (float)$product['maxPrice'] ? '' : 'Fra ') . formatPriceShort($product['minPrice']),
        'category' => (string)$product['categoryName'],
    ];
}
// JSON_HEX_* so the values are safe however the page uses them
echo json_encode($suggestions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
