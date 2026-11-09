<?php
// One product: /product?id=5. Shows its images, description, tags and every variant with price and stock.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/products.php';
startSession(false);

$db = getDb();
$itemId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$item = false;
if ($itemId) {
    $stmt = $db->prepare('SELECT itemName, itemDescription, itemOptions, itemDefaultVariant FROM VeneficiaItems WHERE itemId = ?');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
}
if ($item) {
    // Default variant first, the rest in the order they were added
    $stmt = $db->prepare(
        'SELECT itemVariantId, varriantType, varriantName, varriantPrice, inventoryCount FROM VeneficiaItemVariants
         WHERE itemId = ? ORDER BY itemVariantId = ? DESC, itemVariantId'
    );
    $stmt->execute([$itemId, (int)$item['itemDefaultVariant']]);
    $variants = $stmt->fetchAll();
}
// Unknown product, or one without variants (it can't be bought, and the shop list hides it too)
if (!$item || !$variants) {
    include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/statusCodesPages/404.php';
    exit;
}

$options = json_decode($item['itemOptions'] ?? '', true) ?: [];
$types = array_values(array_filter($options['variantTypes'] ?? [], 'is_string'));
foreach ($variants as &$variant) {
    $values = json_decode($variant['varriantType'], true) ?: [];
    $variant['values'] = array_map(fn($type) => (string)($values[$type] ?? ''), $types);
}
unset($variant);

$stmt = $db->prepare(
    'SELECT a.fileUrl, a.altText
     FROM VeneficiaItemAttachments ia JOIN VeneficiaAttachments a ON a.attachmentId = ia.attachmentId
     WHERE ia.itemId = ? ORDER BY ia.sortOrder, a.attachmentId'
);
$stmt->execute([$itemId]);
$images = $stmt->fetchAll();

$stmt = $db->prepare('SELECT t.tagName FROM VeneficiaItemTags it JOIN VeneficiaTags t ON t.tagId = it.tagId WHERE it.itemId = ? ORDER BY t.tagName');
$stmt->execute([$itemId]);
$tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

$prices = array_map('floatval', array_column($variants, 'varriantPrice'));
$inStock = array_sum(array_column($variants, 'inventoryCount')) > 0;
$descriptionText = trim((string)$item['itemDescription']);
$metaDescription = $descriptionText !== ''
    ? mb_strimwidth(preg_replace('/\s+/u', ' ', $descriptionText), 0, 160, '…')
    : $item['itemName'] . ' hos Veneficia.';
$siteUrl = 'https://veneficia.noahgajnielsen.dk';
$headerCanonicalPath = '/product?id=' . $itemId;
$title = $item['itemName'] . ' - Veneficia';

// Structured data, so search engines can show price and stock. The JSON_HEX_* flags keep "</script>" in a name or
// description from closing the script tag.
$structuredData = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $item['itemName'],
    'description' => $descriptionText !== '' ? $descriptionText : null,
    'image' => array_map(fn($image) => $siteUrl . $image['fileUrl'], $images) ?: null,
    'url' => $siteUrl . $headerCanonicalPath,
    'offers' => [
        '@type' => 'AggregateOffer',
        'priceCurrency' => 'DKK',
        'lowPrice' => number_format(min($prices), 2, '.', ''),
        'highPrice' => number_format(max($prices), 2, '.', ''),
        'offerCount' => count($variants),
        'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
    ],
];
$structuredData = array_filter($structuredData, fn($v) => $v !== null);
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/products.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/products.css') ?>">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
    <?php if ($images): ?>
        <meta property="og:image" content="<?= htmlspecialchars($siteUrl . $images[0]['fileUrl']) ?>">
    <?php endif; ?>
    <script type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/navbar.php'; ?>
    <main class="product-page">
        <p><a href="/products">Alle produkter</a></p>
        <h1><?= htmlspecialchars($item['itemName']) ?></h1>
        <p class="product-price">
            <?= min($prices) === max($prices) ? formatPrice(min($prices)) : 'Fra ' . formatPrice(min($prices)) ?>
            · <?= $inStock ? 'På lager' : 'Udsolgt' ?>
        </p>

        <?php if ($images): ?>
            <div class="product-images">
                <?php foreach ($images as $i => $image): ?>
                    <img src="<?= htmlspecialchars($image['fileUrl']) ?>" alt="<?= htmlspecialchars($image['altText'] ?: $item['itemName']) ?>"
                         width="400" height="400" decoding="async" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($descriptionText !== ''): ?>
            <h2>Beskrivelse</h2>
            <p class="product-description"><?= nl2br(htmlspecialchars($descriptionText)) ?></p>
        <?php endif; ?>

        <h2><?= count($variants) > 1 ? 'Varianter' : 'Pris og lager' ?></h2>
        <table class="product-variants">
            <thead>
                <tr>
                    <?php if ($types): ?>
                        <?php foreach ($types as $type): ?>
                            <th><?= htmlspecialchars($type) ?></th>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <th>Variant</th>
                    <?php endif; ?>
                    <th>Pris</th>
                    <th>Lager</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($variants as $variant): ?>
                    <tr>
                        <?php if ($types): ?>
                            <?php foreach ($variant['values'] as $value): ?>
                                <td><?= htmlspecialchars($value) ?></td>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <td><?= htmlspecialchars($variant['varriantName']) ?></td>
                        <?php endif; ?>
                        <td><?= formatPrice($variant['varriantPrice']) ?></td>
                        <td><?= (int)$variant['inventoryCount'] > 0 ? 'På lager (' . (int)$variant['inventoryCount'] . ')' : 'Udsolgt' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($tags): ?>
            <h2>Tags</h2>
            <ul class="product-tags">
                <?php foreach ($tags as $tag): ?>
                    <li><a href="<?= htmlspecialchars(productsUrl($tag)) ?>"><?= htmlspecialchars($tag) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
