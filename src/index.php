<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/products.php';
startSession(false);

// The top-level categories that have a page, for the category tiles
$homeCategories = array_filter(loadShopCategories(getDb()), fn($c) => $c['parent'] === null && $c['path'] !== null);

// Icon for each category tile, by the category's slug (its path without the "/"). Unknown slugs get "category".
const HOME_CATEGORY_ICONS = [
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
                    <p class="type-overline hero__overline">Krystaller · Røgelse · Urter · Bøger</p>
                    <h1 id="hero-titel" class="type-display">Redskaber til din spirituelle praksis</h1>
                    <p class="type-body-large hero__lead">Krystaller, røgelse, urter og bøger – beskrevet, så du ved, hvad du køber.</p>
                    <div class="hero__actions">
                        <a class="button button--large" href="/products">Se alle produkter</a>
                        <a class="button button--outlined button--large" href="#kategorier">Find en kategori</a>
                    </div>
                    <div class="moon-phases" aria-hidden="true">
                        <svg viewBox="0 0 248 24" width="186" height="18" focusable="false"><use href="<?= htmlspecialchars(UI_ICON_SPRITE) ?>#moon-phases"></use></svg>
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
                    <ul class="category-tiles">
                        <?php foreach ($homeCategories as $homeCategory): ?>
                            <li>
                                <a class="category-tile" href="<?= htmlspecialchars($homeCategory['path']) ?>">
                                    <span class="arch-icon"><?= uiIcon(HOME_CATEGORY_ICONS[ltrim($homeCategory['path'], '/')] ?? 'category') ?></span>
                                    <span class="category-tile__name"><?= htmlspecialchars($homeCategory['name']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endif; ?>

        <section class="home-section home-section--how" aria-labelledby="handel-titel">
            <div class="container">
                <h2 id="handel-titel">Sådan handler du</h2>
                <ul class="how-list">
                    <li>
                        <span class="how-list__icon"><?= uiIcon('menu-book') ?></span>
                        <h3>Grundige beskrivelser</h3>
                        <p>Hvert produkt har en beskrivelse af dets betydning, så du ved, hvad du køber.</p>
                    </li>
                    <li>
                        <span class="how-list__icon"><?= uiIcon('local-shipping') ?></span>
                        <h3>Levering med GLS</h3>
                        <p>Vi sender din ordre til en GLS Pakkeshop, hvor du henter den.</p>
                    </li>
                    <li>
                        <span class="how-list__icon"><?= uiIcon('home') ?></span>
                        <h3>I dit eget tempo</h3>
                        <p>Handl i ro hjemmefra, i dit eget tempo.</p>
                    </li>
                </ul>
            </div>
        </section>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
