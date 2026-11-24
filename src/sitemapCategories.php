<?php
// /sitemap-categories.xml (rewritten here by .htaccess, listed in robots.txt): every category page with products to
// show. The fixed pages stay in the static sitemap.xml, which the IndexNow step in ftp-upload.yml reads.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/products.php';

$siteUrl = 'https://veneficia.noahgajnielsen.dk';
$db = getDb();
$categories = loadShopCategories($db);

// A category has products when a buyable product (one with variants) is in it or in one of its subcategories
$hasProducts = [];
$stmt = $db->query(
    "SELECT i.itemOptions->'$.categories' FROM VeneficiaItems i
     WHERE EXISTS (SELECT 1 FROM VeneficiaItemVariants v WHERE v.itemId = i.itemId)"
);
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $json) {
    foreach (json_decode((string)$json, true) ?: [] as $id) {
        // Up through the parents; $seen stops a (broken) parent loop
        for ($id = (int)$id, $seen = []; isset($categories[$id]) && !isset($seen[$id]); $id = (int)$categories[$id]['parent']) {
            $seen[$id] = $hasProducts[$id] = true;
        }
    }
}

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($categories as $id => $category): ?>
<?php if ($category['path'] !== null && isset($hasProducts[$id])): ?>
    <url>
        <loc><?= htmlspecialchars($siteUrl . $category['path'], ENT_XML1) ?></loc>
    </url>
<?php endif; ?>
<?php endforeach; ?>
</urlset>
