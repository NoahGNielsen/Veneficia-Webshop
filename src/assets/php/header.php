<?php
// The shared bits of every page's <head>: charset, favicon, mainStyle.css and the font, and what search engines and
// link previews (Discord, Snapchat, iMessage, ...) read. Each page adds its own stylesheets, <title> and description
// after it.
//
// /manifest.json is the web app manifest, so the shop can be added to a phone's home screen and opens without the
// browser bars (JSON can't hold comments, so it's explained here). Its "any" icons are appIcon192/512.png; the
// "maskable" ones (appIconMaskable192/512.png) are full-bleed with the logo inside the middle 80%, since Android crops
// them to a circle or squircle. iOS uses appleTouchIcon.png instead.
//
// The site only has a light theme. theme-color here and theme_color in manifest.json are the top app bar's moss green
// (mainStyle.css's --palette-moss), so the phone's browser bar blends into the navbar; background_color in
// manifest.json is --color-background (parchment). Update them together with mainStyle.css.
//
// The ?v= on each file is its last change, so browsers fetch it again after an update. A file that doesn't exist yet
// (the icons, until they're made) gets no ?v= instead of a warning.
//
// The canonical URL is the page's clean path without the query string, so /userMgmt/login?return=%2F counts as
// /userMgmt/login, and /index as /. Error pages (anything that isn't a 200) are noindex, so they get none. A page whose
// URL picks the content (the product pages, /products?tag=...) sets $headerCanonicalPath to its own path and query
// before including this.
//
// The fonts (Raleway for text, Lora for headings, a tiny Cinzel subset for the wordmark; assets/fonts/, @font-face in
// mainStyle.css) are our own copies instead of Google Fonts', so the first paint doesn't wait on two other servers.
// The Latin files of Raleway and Lora are preloaded, since the browser would otherwise only find them after
// mainStyle.css has loaded. Cinzel is only a few kB and isn't preloaded.
$headerVersion = fn(string $path): string => is_file(__DIR__ . '/../..' . $path)
    ? $path . '?v=' . filemtime(__DIR__ . '/../..' . $path)
    : $path;

// uiIcon('shopping-bag') is an icon from assets/icons/icons.svg (Material Symbols Rounded + our moon artwork) for the
// page's markup. Icons are decorative (aria-hidden): the text next to them, or the control's aria-label, says what they
// mean. Defined here because every page includes this file before its <body>.
if (!defined('UI_ICON_SPRITE')) {
    define('UI_ICON_SPRITE', $headerVersion('/assets/icons/icons.svg'));
}
if (!function_exists('uiIcon')) {
    function uiIcon(string $name, string $class = ''): string
    {
        return '<svg class="icon' . ($class !== '' ? ' ' . htmlspecialchars($class) : '') . '" aria-hidden="true" focusable="false">'
            . '<use href="' . htmlspecialchars(UI_ICON_SPRITE . '#' . $name) . '"></use></svg>';
    }
}

$headerCanonical = null;
if (http_response_code() === 200) {
    if (isset($headerCanonicalPath)) {
        $headerCanonical = 'https://veneficia.noahgajnielsen.dk' . $headerCanonicalPath;
    } else {
        $headerPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $headerPath = is_string($headerPath) && str_starts_with($headerPath, '/') ? $headerPath : '/';
        $headerCanonical = 'https://veneficia.noahgajnielsen.dk' . preg_replace('~(^|/)index$~', '$1', $headerPath);
    }
}
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!-- Light theme only, so the browser's own controls and scrollbars stay light even in dark mode -->
<meta name="color-scheme" content="light">
<meta name="theme-color" content="#3B4D2E">
<link rel="icon" type="image/svg+xml" href="<?= $headerVersion('/assets/images/favicon.svg') ?>">
<link rel="apple-touch-icon" href="<?= $headerVersion('/assets/images/appleTouchIcon.png') ?>">
<link rel="manifest" href="<?= $headerVersion('/manifest.json') ?>">
<meta name="apple-mobile-web-app-title" content="Veneficia">
<link rel="preload" href="/assets/fonts/raleway-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/lora-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= $headerVersion('/assets/css/mainStyle.css') ?>">
<link rel="terms-of-service" href="https://veneficia.noahgajnielsen.dk/legal/terms">
<link rel="privacy-policy" href="https://veneficia.noahgajnielsen.dk/legal/privacy">

<!-- Search engines and link previews. og:title/og:description fall back to the page's <title> and description. -->
<?php if ($headerCanonical !== null): ?>
<link rel="canonical" href="<?= htmlspecialchars($headerCanonical) ?>">
<meta property="og:url" content="<?= htmlspecialchars($headerCanonical) ?>">
<?php endif; ?>
<meta property="og:site_name" content="Veneficia">
<meta property="og:type" content="website">
<meta property="og:locale" content="da_DK">
<meta name="twitter:card" content="summary">
