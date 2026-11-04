<?php
// The shared bits of every page's <head>: charset, the font preload, and what search engines and link previews
// (Discord, Snapchat, iMessage, ...) read. Each page adds its own stylesheets, <title> and description after it.
//
// The canonical URL is the page's clean path without the query string, so /userMgmt/login?return=%2F counts as
// /userMgmt/login, and /index as /. Error pages (anything that isn't a 200) are noindex, so they get none.
//
// The keywords tag holds the shop's name on every page, plus the page's own: a page sets $headerKeywords (a list of
// words) before including this. Google ignores the tag, but Bing and the smaller search engines still read it; the
// descriptions do most of the work.
//
// The font (Raleway, assets/fonts/, @font-face in mainStyle.css) is our own copy instead of Google Fonts', so the
// first paint doesn't wait on two other servers. Its Latin file is preloaded, since the browser would otherwise only
// find it after mainStyle.css has loaded.
$headerCanonical = null;
if (http_response_code() === 200) {
    $headerPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $headerPath = is_string($headerPath) && str_starts_with($headerPath, '/') ? $headerPath : '/';
    $headerCanonical = 'https://veneficia.noahgajnielsen.dk' . preg_replace('~(^|/)index$~', '$1', $headerPath);
}

$headerKeywords = array_unique([
    ...($headerKeywords ?? []),
    'Veneficia', 'webshop',
]);
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!-- Follows the system theme before mainStyle.css has loaded, so a slow page load doesn't flash the wrong colour -->
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
<link rel="preload" href="/assets/fonts/raleway-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="terms-of-service" href="https://veneficia.noahgajnielsen.dk/legal/terms">
<link rel="privacy-policy" href="https://veneficia.noahgajnielsen.dk/legal/privacy">

<!-- Search engines and link previews. og:title/og:description fall back to the page's <title> and description. -->
<?php if ($headerCanonical !== null): ?>
<link rel="canonical" href="<?= htmlspecialchars($headerCanonical) ?>">
<meta property="og:url" content="<?= htmlspecialchars($headerCanonical) ?>">
<?php endif; ?>
<meta name="keywords" content="<?= htmlspecialchars(implode(', ', $headerKeywords)) ?>">
<meta property="og:site_name" content="Veneficia">
<meta property="og:type" content="website">
<meta property="og:locale" content="da_DK">
<meta name="twitter:card" content="summary">
