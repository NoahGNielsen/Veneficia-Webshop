<?php
// Writes /sitemap-products.xml (listed in robots.txt): the page of every buyable product (one with variants). It is a
// real file, so search engines get it straight from Apache. The whole file is rebuilt from the database each time, so
// a renamed product or category (which changes the URL) can't leave an old address behind. It lives only on the
// server (.gitignore), so a deploy never overwrites it. /admin/sitemap builds it the first time.
require_once __DIR__ . '/products.php';

const PRODUCT_SITEMAP_FILE = '/sitemap-products.xml';
const PRODUCT_SITEMAP_SITE_URL = 'https://veneficia.noahgajnielsen.dk';

// Returns the number of products written, or null when the file couldn't be written (the reason goes to the error log)
function writeProductSitemap(PDO $db): ?int
{
    $stmt = $db->query(
        'SELECT i.itemId, i.itemName, pc.categoryName FROM VeneficiaItems i ' . PRODUCT_CATEGORY_JOIN . '
         WHERE EXISTS (SELECT 1 FROM VeneficiaItemVariants v WHERE v.itemId = i.itemId)
         ORDER BY i.itemId'
    );
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $count = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $url = PRODUCT_SITEMAP_SITE_URL . productUrl((int)$row['itemId'], $row['itemName'], $row['categoryName']);
        $xml .= '    <url>' . "\n" . '        <loc>' . htmlspecialchars($url, ENT_XML1) . '</loc>' . "\n" . '    </url>' . "\n";
        $count++;
    }
    $xml .= '</urlset>' . "\n";

    // Written next to the real file and then renamed over it, so a crawler never reads a half-written sitemap
    $file = $_SERVER['DOCUMENT_ROOT'] . PRODUCT_SITEMAP_FILE;
    $temp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (@file_put_contents($temp, $xml, LOCK_EX) === false || !@rename($temp, $file)) {
        @unlink($temp);
        error_log('Could not write ' . $file);
        return null;
    }
    return $count;
}

// For the admin pages: a failed sitemap must never stop a product from being saved
function updateProductSitemap(PDO $db): void
{
    try {
        writeProductSitemap($db);
    } catch (Throwable $e) {
        error_log('Product sitemap: ' . $e->getMessage());
    }
}
