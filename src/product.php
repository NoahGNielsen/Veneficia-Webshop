<?php
// One product: /category/product-name-5 (see productUrl()). Shows its images, description, tags and every variant with price and stock.
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/products.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/basket.php';
startSession(false);
$basketMessage = takeBasketMessage();
$basketToken = session_status() === PHP_SESSION_ACTIVE ? csrfToken() : '';

$db = getDb();
$itemId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$item = false;
if ($itemId) {
    $stmt = $db->prepare(
        'SELECT i.itemName, i.itemDescription, i.itemOptions, i.itemDefaultVariant, pc.categoryName
         FROM VeneficiaItems i ' . PRODUCT_CATEGORY_JOIN . ' WHERE i.itemId = ?'
    );
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

// Any other spelling of the URL (an old /product?id=5 link, a renamed product or category, wrong case) moves to the
// real one, so each product has exactly one URL
$headerCanonicalPath = productUrl($itemId, $item['itemName'], $item['categoryName']);
if (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) !== $headerCanonicalPath) {
    header('Location: ' . $headerCanonicalPath, true, 301);
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

// For the page: the variant chosen from the start (the first one in stock), whether the basket message is good news,
// and the category page that the breadcrumb and the sold-out box link to
$defaultVariantId = null;
foreach ($variants as $variant) {
    if ((int)$variant['inventoryCount'] > 0) {
        $defaultVariantId = (int)$variant['itemVariantId'];
        break;
    }
}
$basketMessageOk = $basketMessage !== null && str_ends_with($basketMessage, 'lagt i kurven.');
$categoryPath = categoryPagePath((string)$item['categoryName']);
$navSection = 'products';
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
    <main id="indhold" class="product-page">
        <nav class="breadcrumbs" aria-label="Brødkrumme">
            <ol>
                <li><a href="/products">Alle produkter</a></li>
                <?php if ($categoryPath !== null): ?>
                    <li><a href="<?= htmlspecialchars($categoryPath) ?>"><?= htmlspecialchars($item['categoryName']) ?></a></li>
                <?php endif; ?>
                <li><span aria-current="page"><?= htmlspecialchars($item['itemName']) ?></span></li>
            </ol>
        </nav>

        <div class="product-layout">
            <?php if ($images): ?>
                <ul class="product-gallery" aria-label="Billeder af <?= htmlspecialchars($item['itemName']) ?>"<?= count($images) > 1 ? ' tabindex="0"' : '' ?>>
                    <?php foreach ($images as $i => $image): ?>
                        <li class="media-frame">
                            <img src="<?= htmlspecialchars($image['fileUrl']) ?>" alt="<?= htmlspecialchars($image['altText'] ?: $item['itemName']) ?>"
                                 width="600" height="600" decoding="async" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="product-gallery">
                    <div class="media-frame media-frame--empty" aria-hidden="true"><?= uiIcon('diamond') ?><span class="media-frame__label">Intet billede</span></div>
                </div>
            <?php endif; ?>

            <div class="product-info">
                <h1><?= htmlspecialchars($item['itemName']) ?></h1>
                <p class="product-price"><?= min($prices) === max($prices) ? formatPrice(min($prices)) : 'Fra ' . formatPrice(min($prices)) ?></p>
                <p class="stock<?= $inStock ? ' stock--in' : '' ?>"><?= $inStock ? 'På lager' : 'Udsolgt' ?></p>

                <?php if ($basketMessage !== null): ?>
                    <p class="banner <?= $basketMessageOk ? 'banner--success' : 'banner--error' ?>" role="status"><?= htmlspecialchars($basketMessage) ?> <a href="/basket">Gå til kurven</a></p>
                <?php endif; ?>
                <?php if ($inStock): ?>
                    <form class="product-add" method="post" action="/basket">
                        <?php // Guests without a session have no token yet; /basket checks the Origin header for them instead ?>
                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($basketToken) ?>">
                        <input type="hidden" name="action" value="add">
                        <?php if (count($variants) > 1): ?>
                            <fieldset class="chip-fieldset">
                                <legend>Vælg variant</legend>
                                <div class="chip-set">
                                    <?php foreach ($variants as $variant):
                                        $variantInStock = (int)$variant['inventoryCount'] > 0;
                                    ?>
                                        <label class="chip">
                                            <input type="radio" name="variantId" value="<?= (int)$variant['itemVariantId'] ?>" required<?= $variantInStock ? '' : ' disabled' ?><?= (int)$variant['itemVariantId'] === $defaultVariantId ? ' checked' : '' ?>>
                                            <?= htmlspecialchars($types ? implode(' / ', $variant['values']) : $variant['varriantName']) ?>
                                            · <?= formatPrice($variant['varriantPrice']) ?><?= $variantInStock ? '' : ' · udsolgt' ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        <?php else: ?>
                            <input type="hidden" name="variantId" value="<?= (int)$variants[0]['itemVariantId'] ?>">
                        <?php endif; ?>
                        <div class="field">
                            <label for="amount">Antal</label>
                            <input type="number" id="amount" name="amount" value="1" min="1" max="<?= BASKET_MAX_AMOUNT ?>" required inputmode="numeric">
                        </div>
                        <button type="submit" class="button--large button--block">Læg i kurv</button>
                    </form>
                <?php else: ?>
                    <div class="product-soldout">
                        <p class="type-title-large">Udsolgt lige nu</p>
                        <p>Denne vare er ikke på lager. Kig efter noget lignende i <a href="<?= htmlspecialchars($categoryPath ?? '/products') ?>"><?= htmlspecialchars($categoryPath !== null ? $item['categoryName'] : 'alle produkter') ?></a>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="product-details">
            <?php if ($descriptionText !== ''): ?>
                <section aria-labelledby="beskrivelse">
                    <h2 id="beskrivelse">Beskrivelse</h2>
                    <p class="product-description type-body-large"><?= nl2br(htmlspecialchars($descriptionText)) ?></p>
                </section>
            <?php endif; ?>

            <section aria-labelledby="varianter">
                <h2 id="varianter"><?= count($variants) > 1 ? 'Varianter' : 'Pris og lager' ?></h2>
                <div class="table-scroll" role="region" aria-labelledby="varianter" tabindex="0">
                    <table class="product-variants">
                        <thead>
                            <tr>
                                <?php if ($types): ?>
                                    <?php foreach ($types as $type): ?>
                                        <th scope="col"><?= htmlspecialchars($type) ?></th>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <th scope="col">Variant</th>
                                <?php endif; ?>
                                <th scope="col" class="num">Pris</th>
                                <th scope="col">Lager</th>
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
                                    <td class="num"><?= formatPrice($variant['varriantPrice']) ?></td>
                                    <td>
                                        <?php if ((int)$variant['inventoryCount'] > 0): ?>
                                            <span class="stock stock--in">På lager (<?= (int)$variant['inventoryCount'] ?>)</span>
                                        <?php else: ?>
                                            <span class="stock">Udsolgt</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php if ($tags): ?>
                <section aria-labelledby="tags">
                    <h2 id="tags">Tags</h2>
                    <ul class="chip-set product-tags">
                        <?php foreach ($tags as $tag): ?>
                            <li><a class="chip" href="<?= htmlspecialchars(productsUrl($tag)) ?>"><?= htmlspecialchars($tag) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
