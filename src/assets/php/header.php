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
// The colours in theme-color here and in manifest.json are mainStyle.css's --background, so they must be updated
// together with it.
//
// The ?v= on each file is its last change, so browsers fetch it again after an update. A file that doesn't exist yet
// (the icons, until they're made) gets no ?v= instead of a warning.
//
// The canonical URL is the page's clean path without the query string, so /userMgmt/login?return=%2F counts as
// /userMgmt/login, and /index as /. Error pages (anything that isn't a 200) are noindex, so they get none.
//
// The keywords tag is only there when a page sets $headerKeywords (a list of words) before including this. Google
// ignores the tag, but Bing and the smaller search engines still read it; the
// descriptions do most of the work.
//
// The font (Raleway, assets/fonts/, @font-face in mainStyle.css) is our own copy instead of Google Fonts', so the
// first paint doesn't wait on two other servers. Its Latin file is preloaded, since the browser would otherwise only
// find it after mainStyle.css has loaded.
$headerVersion = fn(string $path): string => is_file(__DIR__ . '/../..' . $path)
    ? $path . '?v=' . filemtime(__DIR__ . '/../..' . $path)
    : $path;

$headerCanonical = null;
if (http_response_code() === 200) {
    $headerPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $headerPath = is_string($headerPath) && str_starts_with($headerPath, '/') ? $headerPath : '/';
    $headerCanonical = 'https://veneficia.noahgajnielsen.dk' . preg_replace('~(^|/)index$~', '$1', $headerPath);
}
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!-- Follows the system theme before mainStyle.css has loaded, so a slow page load doesn't flash the wrong colour -->
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
<link rel="icon" type="image/svg+xml" href="<?= $headerVersion('/assets/images/favicon.svg') ?>">
<link rel="apple-touch-icon" href="<?= $headerVersion('/assets/images/appleTouchIcon.png') ?>">
<link rel="manifest" href="<?= $headerVersion('/manifest.json') ?>">
<meta name="apple-mobile-web-app-title" content="Veneficia">
<link rel="preload" href="/assets/fonts/raleway-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= $headerVersion('/assets/css/mainStyle.css') ?>">
<link rel="terms-of-service" href="https://veneficia.noahgajnielsen.dk/legal/terms">
<link rel="privacy-policy" href="https://veneficia.noahgajnielsen.dk/legal/privacy">

<!-- Search engines and link previews. og:title/og:description fall back to the page's <title> and description. -->
<?php if ($headerCanonical !== null): ?>
<link rel="canonical" href="<?= htmlspecialchars($headerCanonical) ?>">
<meta property="og:url" content="<?= htmlspecialchars($headerCanonical) ?>">
<?php endif; ?>
<?php if (!empty($headerKeywords)): ?>
<meta name="keywords" content="<?= htmlspecialchars(implode(', ', $headerKeywords)) ?>">
<?php endif; ?>
<meta property="og:site_name" content="Veneficia">
<meta property="og:type" content="website">
<meta property="og:locale" content="da_DK">
<meta name="twitter:card" content="summary">
