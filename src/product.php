<?php
// One product: /category/product-name-5 (see productUrl()). Shows its images, the buy box (variants as chips; price,
// stock and shipping follow the chosen one), description, tags and more products from the same category. On phones a
// bar with the price and "Læg i kurv" stays at the bottom while the real button is out of sight.
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
        'SELECT itemVariantId, varriantType, varriantName, varriantPrice, inventoryCount, varriantWeight FROM VeneficiaItemVariants
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

// What the buy box says about a variant: its price, its stock (honest scarcity: the real count when 5 or fewer are
// left) and what one unit costs to ship. The chips carry the same as data-* for the script at the bottom.
function variantStockText(int $count): string
{
    return match (true) {
        $count <= 0 => 'Udsolgt',
        $count <= 5 => 'Kun ' . $count . ' på lager',
        default => 'På lager',
    };
}
function variantShippingText(?int $grams): string
{
    $price = shippingPriceFor($grams);
    return $price === null ? 'fra ' . formatPriceShort(min(SHIPPING_RATES)) : formatPriceShort($price);
}
$shownVariant = $variants[0];
foreach ($variants as $variant) {
    if ((int)$variant['itemVariantId'] === $defaultVariantId) {
        $shownVariant = $variant;
    }
}
$variantData = fn(array $variant): string => ' data-price="' . htmlspecialchars(formatPriceShort($variant['varriantPrice'])) . '"'
    . ' data-stock="' . htmlspecialchars(variantStockText((int)$variant['inventoryCount'])) . '"'
    . ' data-in-stock="' . ((int)$variant['inventoryCount'] > 0 ? '1' : '0') . '"'
    . ' data-shipping="' . htmlspecialchars(variantShippingText($variant['varriantWeight'] === null ? null : (int)$variant['varriantWeight'])) . '"';
// The price at the top: the chosen variant's when one is in stock, otherwise the lowest
$shownPrice = $defaultVariantId !== null || min($prices) === max($prices)
    ? formatPriceShort($shownVariant['varriantPrice'])
    : 'Fra ' . formatPriceShort(min($prices));

// More from the same category (in stock and with a picture first), or from the whole shop when it has no category
$relatedCategoryId = (int)($options['categories'][0] ?? 0);
$relatedParams = [$itemId];
$relatedWhere = ['i.itemId <> ?'];
if ($relatedCategoryId > 0 && $categoryPath !== null) {
    $relatedWhere[] = 'pc.categoryId = ?';
    $relatedParams[] = $relatedCategoryId;
}
$related = loadProductCards($db, $relatedWhere, $relatedParams, PRODUCT_SORTS[PRODUCT_SORT_DEFAULT][1], 4);
$relatedHeading = count($relatedWhere) > 1 ? 'Mere fra ' . $item['categoryName'] : 'Andre varer';
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
                <li><a href="/">Forside</a></li>
                <li><a href="/products">Produkter</a></li>
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
                    <div class="media-frame media-frame--empty" aria-hidden="true"><?= uiIcon(categoryIcon($item['categoryName'])) ?></div>
                </div>
            <?php endif; ?>

            <div class="product-info">
                <h1><?= htmlspecialchars($item['itemName']) ?></h1>
                <p class="product-price" data-variant-price><?= htmlspecialchars($shownPrice) ?></p>
                <p class="stock<?= $inStock ? ' stock--in' : '' ?>" data-variant-stock><?= htmlspecialchars($inStock ? variantStockText((int)$shownVariant['inventoryCount']) : 'Udsolgt') ?></p>

                <?php if ($basketMessage !== null): ?>
                    <p class="banner <?= $basketMessageOk ? 'banner--success' : 'banner--error' ?>" role="status"><?= htmlspecialchars($basketMessage) ?> <a href="/basket">Gå til kurven</a></p>
                <?php endif; ?>
                <?php if ($inStock): ?>
                    <form class="product-add" id="product-add" method="post" action="/basket">
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
                                            <input type="radio" name="variantId" value="<?= (int)$variant['itemVariantId'] ?>" required<?= $variantInStock ? '' : ' disabled' ?><?= (int)$variant['itemVariantId'] === $defaultVariantId ? ' checked' : '' ?><?= $variantData($variant) ?>>
                                            <span class="chip__name"><?= htmlspecialchars($types ? implode(' / ', $variant['values']) : $variant['varriantName']) ?></span>
                                            <span class="chip__price"><?= $variantInStock ? formatPriceShort($variant['varriantPrice']) : 'Udsolgt' ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        <?php else: ?>
                            <input type="hidden" name="variantId" value="<?= (int)$variants[0]['itemVariantId'] ?>"<?= $variantData($variants[0]) ?>>
                        <?php endif; ?>
                        <div class="field">
                            <label for="amount">Antal</label>
                            <input type="number" id="amount" name="amount" value="1" min="1" max="<?= BASKET_MAX_AMOUNT ?>" required inputmode="numeric">
                        </div>
                        <button type="submit" class="button--large button--block" id="add-button">Læg i kurv</button>
                    </form>
                    <?php // Delivery, right where the decision is made: GLS to a parcel shop, and what one unit costs to ship ?>
                    <ul class="buy-facts">
                        <li><?= uiIcon('local-shipping', 'icon--20') ?><span>Sendes med GLS til en Pakkeshop. Fragt <span data-variant-shipping><?= htmlspecialchars(variantShippingText($shownVariant['varriantWeight'] === null ? null : (int)$shownVariant['varriantWeight'])) ?></span>, efter ordrens samlede vægt.</span></li>
                    </ul>
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

        <?php if ($related): ?>
            <section class="related" aria-labelledby="mere">
                <div class="related__head">
                    <h2 id="mere"><?= htmlspecialchars($relatedHeading) ?></h2>
                    <a href="<?= htmlspecialchars($categoryPath !== null && $relatedCategoryId > 0 ? $categoryPath : '/products') ?>">Se alle</a>
                </div>
                <ul class="product-grid product-grid--row">
                    <?php foreach ($related as $i => $product): ?>
                        <?= productCard($product, $i, 'h3', 0) ?>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($inStock): ?>
            <?php // Phones: the price and the button stay in reach while the real button is out of sight (assets/js/site.js) ?>
            <div class="buy-bar" data-sticky-for="add-button" hidden>
                <p class="buy-bar__info"><span class="buy-bar__name"><?= htmlspecialchars($item['itemName']) ?></span> <span class="buy-bar__price" data-variant-price><?= htmlspecialchars($shownPrice) ?></span></p>
                <button type="submit" form="product-add">Læg i kurv</button>
            </div>
            <script>
                // The chosen variant's price, stock and shipping in the buy box and the bar. Without JavaScript the page
                // shows the first variant's.
                (() => {
                    const form = document.getElementById('product-add');
                    const show = (input) => {
                        if (!input || !input.dataset.price) return;
                        document.querySelectorAll('[data-variant-price]').forEach((el) => { el.textContent = input.dataset.price; });
                        const stock = document.querySelector('[data-variant-stock]');
                        stock.textContent = input.dataset.stock;
                        stock.classList.toggle('stock--in', input.dataset.inStock === '1');
                        const shipping = document.querySelector('[data-variant-shipping]');
                        if (shipping) shipping.textContent = input.dataset.shipping;
                    };
                    form.addEventListener('change', (event) => { if (event.target.name === 'variantId') show(event.target); });
                    show(form.querySelector('input[name="variantId"]:checked'));
                })();
            </script>
        <?php endif; ?>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
