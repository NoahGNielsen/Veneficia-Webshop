<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/products.php';
startSession(false);

// The top-level categories that have a page, for the category list
$homeCategories = array_filter(loadShopCategories(getDb()), fn($c) => $c['parent'] === null && $c['path'] !== null);

// Today's moon phase for the hero: 0 = new moon, 4 = full moon, 7 = waning crescent (assets/icons/icons.svg has
// moon-0 to moon-7). Worked out from a known new moon (6 January 2000, 18:14 UTC) and the average lunar month, which is
// right to within about a day, so no API or database is needed.
const MOON_MONTH_DAYS = 29.530588853;
const MOON_PHASE_NAMES = ['Nymåne', 'Tiltagende måne', 'Første kvarter', 'Tiltagende måne', 'Fuldmåne', 'Aftagende måne', 'Sidste kvarter', 'Aftagende måne'];
$moonAge = fmod((time() - 947182440) / 86400, MOON_MONTH_DAYS);
$moonPhase = (int)floor($moonAge / MOON_MONTH_DAYS * 8 + 0.5) % 8;
// What comes next: the full moon while the moon grows (new moon included), the new moon while it wanes
$daysToFull = (int)round(fmod(MOON_MONTH_DAYS / 2 - $moonAge + MOON_MONTH_DAYS, MOON_MONTH_DAYS));
$daysToNew = (int)round(MOON_MONTH_DAYS - $moonAge);
$moonNext = $moonPhase < 4
    ? 'Fuldmåne om ' . $daysToFull . ($daysToFull === 1 ? ' dag.' : ' dage.')
    : 'Nymåne om ' . $daysToNew . ($daysToNew === 1 ? ' dag.' : ' dage.');

// The shipping prices, straight from the checkout's rates
$homeShipping = [];
foreach (SHIPPING_RATES as $maxGrams => $price) {
    $homeShipping[] = ['kg' => number_format($maxGrams / 1000, 0, ',', '.'), 'price' => formatPrice($price)];
}
$homeDescription = 'Krystaller, røgelse, urter og bøger til din spirituelle praksis, med en grundig beskrivelse af hvert produkt og levering til en GLS Pakkeshop.';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/homepage.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/homepage.css') ?>">
    <title>Veneficia - krystaller, røgelse, urter og bøger</title>
    <meta name="description" content="<?= htmlspecialchars($homeDescription) ?>">
    <meta property="og:title" content="Veneficia - krystaller, røgelse, urter og bøger">
    <meta property="og:description" content="<?= htmlspecialchars($homeDescription) ?>">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/navbar.php'; ?>
    <main id="indhold" class="homepage">
        <section class="hero on-secondary" aria-labelledby="hero-titel">
            <div class="container hero__inner">
                <div class="hero__text">
                    <h1 id="hero-titel" class="type-display">Redskaber til din spirituelle praksis</h1>
                    <p class="type-body-large hero__lead">Krystaller, røgelse, urter og bøger – beskrevet, så du ved, hvad du køber.</p>
                    <div class="hero__actions">
                        <a class="button button--large" href="/products">Se alle produkter</a>
                        <a class="button button--outlined button--large" href="#kategorier">Find en kategori</a>
                    </div>
                    <div class="moon-today">
                        <div class="moon-phases" aria-hidden="true">
                            <?php for ($i = 0; $i < 8; $i++): ?>
                                <svg class="moon-phases__phase<?= $i === $moonPhase ? ' moon-phases__phase--today' : '' ?>" viewBox="0 0 24 24" width="24" height="24" focusable="false"><use href="<?= htmlspecialchars(UI_ICON_SPRITE) ?>#moon-<?= $i ?>"></use></svg>
                            <?php endfor; ?>
                        </div>
                        <p class="moon-today__text"><strong><?= MOON_PHASE_NAMES[$moonPhase] ?> i dag.</strong> <?= $moonNext ?></p>
                    </div>
                </div>
                <div class="hero__visual arch arch--glow">
                    <?php // The hero visual. A Blender render replaces it by changing src only (keep it 4:5, alt="" since it's decoration). ?>
                    <img src="/assets/images/obelisk.svg" alt="" width="400" height="500" fetchpriority="high">
                </div>
            </div>
        </section>

        <?php if ($homeCategories): ?>
            <section class="home-section" id="kategorier" aria-labelledby="kategorier-titel">
                <div class="container">
                    <h2 id="kategorier-titel">Find en kategori</h2>
                    <ul class="category-links">
                        <?php foreach ($homeCategories as $homeCategory): ?>
                            <li>
                                <a class="category-link" href="<?= htmlspecialchars($homeCategory['path']) ?>">
                                    <span class="category-link__icon"><?= uiIcon(categoryIcon($homeCategory['name']), 'icon--20') ?></span>
                                    <?= htmlspecialchars($homeCategory['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endif; ?>

        <section class="home-section home-facts" aria-labelledby="fakta-titel">
            <div class="container">
                <h2 id="fakta-titel">Godt at vide, før du handler</h2>
                <dl class="facts">
                    <div class="facts__row">
                        <dt>Fragt</dt>
                        <dd>
                            <p>Vi sender din ordre med GLS til en Pakkeshop, hvor du henter den. Prisen afhænger af vægten:</p>
                            <ul class="shipping-list">
                                <?php foreach ($homeShipping as $rate): ?>
                                    <li><span>Op til <?= $rate['kg'] ?> kg</span> <span><?= $rate['price'] ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        </dd>
                    </div>
                    <div class="facts__row">
                        <dt>Beskrivelser</dt>
                        <dd><p>Hvert produkt har en beskrivelse af dets betydning, så du ved, hvad du køber.</p></dd>
                    </div>
                    <div class="facts__row">
                        <dt>Konto</dt>
                        <dd><p>Du skal have en konto for at bestille. Du kan oprette den, når du går til kassen.</p></dd>
                    </div>
                    <div class="facts__row">
                        <dt>Rabatkode</dt>
                        <dd><p>Har du en rabatkode, skriver du den i kassen, før du bestiller.</p></dd>
                    </div>
                </dl>
            </div>
        </section>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
