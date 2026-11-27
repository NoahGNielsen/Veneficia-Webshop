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

// Products for the moon: tagged with the phase's tag ("fuldmåne" etc.) in /admin/item. Without any, the section shows
// the shop's first products in the list's recommended order (in stock and with a picture first) as "Fra butikken".
$db = getDb();
const MOON_TAGS = [0 => ['nymåne', 'Til nymånen'], 4 => ['fuldmåne', 'Til fuldmånen']];
[$moonTag, $moonHeading] = MOON_TAGS[$moonPhase] ?? ($moonPhase < 4 ? ['tiltagende måne', 'Til den tiltagende måne'] : ['aftagende måne', 'Til den aftagende måne']);
$taggedWith = fn(string $tag): array => loadProductCards($db,
    ['EXISTS (SELECT 1 FROM VeneficiaItemTags it JOIN VeneficiaTags t ON t.tagId = it.tagId WHERE it.itemId = i.itemId AND t.tagName = ?)'],
    [$tag], PRODUCT_SORTS[PRODUCT_SORT_DEFAULT][1], 4);
$moonProducts = $taggedWith($moonTag);
$homeProducts = $moonProducts ?: loadProductCards($db, [], [], PRODUCT_SORTS[PRODUCT_SORT_DEFAULT][1], 8);

// The Wheel of the Year (assets/php/seasons.php): the next sabbat, products tagged with it, and the wheel itself:
// the eight sabbats placed by date around a circle that starts at 1 January at the top, the season so far as an arc
// from the last sabbat to today
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/seasons.php';
$todayDk = new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen'));
$sabbat = nextSabbat($todayDk);
$sabbatProducts = $taggedWith($sabbat['key']);
$sabbatWhen = $sabbat['days'] === 0 ? 'i dag' : ($sabbat['days'] === 1 ? 'i morgen' : 'om ' . $sabbat['days'] . ' dage');
// The eight coming sabbats, the next one first
$wheelList = [];
foreach ([(int)$todayDk->format('Y'), (int)$todayDk->format('Y') + 1] as $year) {
    foreach (sabbatDates($year) as $key => $date) {
        if ($date >= $sabbat['date'] && count($wheelList) < 8) {
            $wheelList[] = ['key' => $key, 'date' => $date];
        }
    }
}
// Angle on the wheel (degrees from the top, clockwise) and a point on it
$wheelAngle = fn(DateTimeImmutable $d): float => ((int)$d->format('z') + 0.5) / (365 + (int)$d->format('L')) * 360;
$wheelPoint = fn(float $angle, float $r): array => [round(180 + $r * sin(deg2rad($angle)), 1), round(180 - $r * cos(deg2rad($angle)), 1)];
$wheelFrom = $wheelAngle($sabbat['previous']['date']);
$wheelToday = $wheelAngle($todayDk);
$wheelSweep = fmod($wheelToday - $wheelFrom + 360, 360);
[$arcX1, $arcY1] = $wheelPoint($wheelFrom, 120);
[$arcX2, $arcY2] = $wheelPoint($wheelToday, 120);

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
            <div class="hero__media">
                <?php // The Blender render (16:9, the crystal about two thirds across). A new render replaces it by
                      // exporting the same two file names; ?v= makes browsers fetch it again. ?>
                <img src="<?= htmlspecialchars($headerVersion('/assets/images/forside-1920.webp')) ?>"
                     srcset="<?= htmlspecialchars($headerVersion('/assets/images/forside-1280.webp')) ?> 1280w, <?= htmlspecialchars($headerVersion('/assets/images/forside-1920.webp')) ?> 1920w"
                     sizes="(max-width: 599px) 222vw, 100vw" width="1920" height="1080" fetchpriority="high"
                     alt="Ametyst-krystaltårn på et alter med pentagram i en tåget skov ved levende lys">
            </div>
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
                        <p class="moon-today__text"><a href="#aarets-hjul"><?= htmlspecialchars($sabbat['name']) ?> <?= htmlspecialchars($sabbatWhen) ?></a>, <?= htmlspecialchars(mb_strtolower(mb_substr($sabbat['meaning'], 0, 1)) . mb_substr($sabbat['meaning'], 1)) ?>.</p>
                    </div>
                </div>
            </div>
        </section>

        <?php // Products right under the hero: the moon's if any are tagged with today's phase, else the shop's first ?>
        <?php if ($homeProducts): ?>
            <section class="home-section home-products" aria-labelledby="udvalg-titel">
                <div class="container">
                    <div class="section-head">
                        <h2 id="udvalg-titel"><?= htmlspecialchars($moonProducts ? $moonHeading : 'Fra butikken') ?></h2>
                        <a href="<?= htmlspecialchars($moonProducts ? productsUrl($moonTag) : '/products') ?>">Se alle</a>
                    </div>
                    <ul class="product-grid">
                        <?php foreach ($homeProducts as $i => $product): ?>
                            <?= productCard($product, $i, 'h3', 0) ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endif; ?>

        <?php // The Wheel of the Year: where the year is, the next sabbat and the eight coming ones (the wheel is a
              // picture of the list next to it) ?>
        <section class="home-section wheel-section on-secondary" id="aarets-hjul" aria-labelledby="hjul-titel">
            <div class="container wheel-section__inner">
                <svg class="wheel" viewBox="-70 0 500 360" aria-hidden="true" focusable="false">
                    <circle class="wheel__ring" cx="180" cy="180" r="120"/>
                    <?php if ($wheelSweep > 0): ?>
                        <path class="wheel__season" d="M<?= $arcX1 ?> <?= $arcY1 ?>A120 120 0 <?= $wheelSweep > 180 ? 1 : 0 ?> 1 <?= $arcX2 ?> <?= $arcY2 ?>"/>
                    <?php endif; ?>
                    <?php foreach (sabbatDates((int)$todayDk->format('Y')) as $key => $date):
                        $angle = $wheelAngle($date);
                        [$x, $y] = $wheelPoint($angle, 120);
                        [$lx, $ly] = $wheelPoint($angle, 148);
                        $anchor = $lx > 188 ? 'start' : ($lx < 172 ? 'end' : 'middle');
                    ?>
                        <circle class="wheel__sabbat<?= $key === $sabbat['key'] ? ' wheel__sabbat--next' : '' ?>" cx="<?= $x ?>" cy="<?= $y ?>" r="<?= $key === $sabbat['key'] ? 9 : 5 ?>"/>
                        <text class="wheel__label<?= $key === $sabbat['key'] ? ' wheel__label--next' : '' ?>" x="<?= $lx ?>" y="<?= $ly + 4 ?>" text-anchor="<?= $anchor ?>"><?= htmlspecialchars(SABBATS[$key][0]) ?></text>
                    <?php endforeach; ?>
                    <?php [$tx, $ty] = $wheelPoint($wheelToday, 120); ?>
                    <circle class="wheel__today" cx="<?= $tx ?>" cy="<?= $ty ?>" r="5"/>
                    <text class="wheel__center" x="180" y="174" text-anchor="middle">Årets hjul</text>
                    <text class="wheel__center wheel__center--small" x="180" y="196" text-anchor="middle"><?= htmlspecialchars(seasonDate($todayDk)) ?></text>
                </svg>
                <div class="wheel-section__text">
                    <h2 id="hjul-titel">Årets hjul</h2>
                    <p class="type-body-large"><strong><?= htmlspecialchars($sabbat['name']) ?> <?= htmlspecialchars($sabbatWhen) ?></strong>, <?= htmlspecialchars(seasonDate($sabbat['date'])) ?>. <?= htmlspecialchars($sabbat['meaning']) ?>.</p>
                    <ol class="wheel-list">
                        <?php foreach ($wheelList as $entry): ?>
                            <li<?= $entry['key'] === $sabbat['key'] ? ' class="wheel-list__next"' : '' ?>>
                                <span class="wheel-list__name"><?= htmlspecialchars(SABBATS[$entry['key']][0]) ?></span>
                                <span class="wheel-list__date"><?= htmlspecialchars(seasonDate($entry['date'])) ?></span>
                                <span class="wheel-list__meaning"><?= htmlspecialchars(SABBATS[$entry['key']][1]) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
            <?php if ($sabbatProducts): ?>
                <div class="container wheel-products">
                    <div class="section-head">
                        <h3>Til <?= htmlspecialchars($sabbat['name']) ?></h3>
                        <a href="<?= htmlspecialchars(productsUrl($sabbat['key'])) ?>">Se alle</a>
                    </div>
                    <ul class="product-grid">
                        <?php foreach ($sabbatProducts as $i => $product): ?>
                            <?= productCard($product, $i, 'h4', 0) ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
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
