<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
$employee = requireEmployee();

const ITEM_MAX_VARIANTS = 25;
const ITEM_MAX_TYPES = 5;
const ITEM_MAX_TAGS = 20;
const ITEM_MAX_IMAGES = 10;
const ITEM_MAX_IMAGE_BYTES = 8 * 1024 * 1024;
// Only raster formats (SVG can carry scripts). The saved file's extension comes from here, never from the upload.
const ITEM_IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

// A posted value as a trimmed string. An array where a single value was expected (name[]=...) counts as empty.
function postedString(mixed $value): string
{
    return is_string($value) ? trim($value) : '';
}

$db = getDb();
$categories = $db->query('SELECT categoryId, categoryName FROM VeneficiaCategories ORDER BY categoryName')->fetchAll();
$categoryIds = array_map('intval', array_column($categories, 'categoryId'));

$errors = [];
$form = [
    'name' => '',
    'description' => '',
    'categories' => [],
    'tags' => '',
    'types' => [],
    'variants' => [['name' => '', 'price' => '', 'values' => []]],
    'defaultVariant' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A request bigger than post_max_size arrives with $_POST and $_FILES empty
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'Billederne fylder for meget tilsammen. Upload færre eller mindre billeder.';
    } elseif (!checkCsrf($_POST['csrfToken'] ?? null)) {
        $errors[] = 'Siden var udløbet. Prøv igen.';
    }

    $form['name'] = postedString($_POST['name'] ?? '');
    $form['description'] = str_replace("\r\n", "\n", postedString($_POST['description'] ?? ''));
    $form['tags'] = postedString($_POST['tags'] ?? '');

    $postedCategories = is_array($_POST['categories'] ?? null) ? $_POST['categories'] : [];
    foreach ($postedCategories as $id) {
        if (is_string($id) && ctype_digit($id) && in_array((int)$id, $categoryIds, true)) {
            $form['categories'][] = (int)$id;
        }
    }
    $form['categories'] = array_values(array_unique($form['categories']));

    $postedTypes = is_array($_POST['typeNames'] ?? null) ? array_values($_POST['typeNames']) : [];
    $form['types'] = array_map('postedString', array_slice($postedTypes, 0, ITEM_MAX_TYPES + 1));

    $postedVariants = is_array($_POST['variants'] ?? null) ? array_values($_POST['variants']) : [];
    $form['variants'] = [];
    foreach (array_slice($postedVariants, 0, ITEM_MAX_VARIANTS + 1) as $variant) {
        $variant = is_array($variant) ? $variant : [];
        $values = is_array($variant['values'] ?? null) ? $variant['values'] : [];
        $form['variants'][] = [
            'name' => postedString($variant['name'] ?? ''),
            'price' => str_replace(',', '.', postedString($variant['price'] ?? '')),
            'values' => array_map(fn($i) => postedString($values[$i] ?? ''), array_keys($form['types'])),
        ];
    }
    $defaultVariant = postedString($_POST['defaultVariant'] ?? '');
    $form['defaultVariant'] = ctype_digit($defaultVariant) && (int)$defaultVariant < count($form['variants']) ? (int)$defaultVariant : 0;

    if (!$errors) {
        if ($form['name'] === '' || mb_strlen($form['name']) > 200) {
            $errors[] = 'Skriv et navn (højst 200 tegn).';
        }
        if (mb_strlen($form['description']) > 10000) {
            $errors[] = 'Beskrivelsen må højst være 10.000 tegn.';
        }

        $tags = [];
        foreach (explode(',', $form['tags']) as $tag) {
            $tag = trim($tag);
            if ($tag !== '' && !in_array(mb_strtolower($tag), array_map('mb_strtolower', $tags), true)) {
                $tags[] = $tag;
            }
        }
        if (count($tags) > ITEM_MAX_TAGS || array_filter($tags, fn($t) => mb_strlen($t) > 50)) {
            $errors[] = 'Højst ' . ITEM_MAX_TAGS . ' tags, hver på højst 50 tegn.';
        }

        if (count($form['types']) > ITEM_MAX_TYPES) {
            $errors[] = 'Højst ' . ITEM_MAX_TYPES . ' variant-typer.';
        }
        if (array_filter($form['types'], fn($t) => $t === '' || mb_strlen($t) > 50)) {
            $errors[] = 'Alle variant-typer skal have et navn (højst 50 tegn).';
        }
        if (count(array_unique(array_map('mb_strtolower', $form['types']))) !== count($form['types'])) {
            $errors[] = 'To variant-typer har samme navn.';
        }

        if (!$form['variants'] || count($form['variants']) > ITEM_MAX_VARIANTS) {
            $errors[] = 'En vare skal have mellem 1 og ' . ITEM_MAX_VARIANTS . ' varianter.';
        }
        $variantNames = [];
        foreach ($form['variants'] as $i => $variant) {
            $label = 'Variant ' . ($i + 1) . ': ';
            if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $variant['price'])) {
                $errors[] = $label . 'skriv en gyldig pris, fx 149,95.';
            }
            if (array_filter($variant['values'], fn($v) => $v === '' || mb_strlen($v) > 50)) {
                $errors[] = $label . 'udfyld alle typer (højst 50 tegn hver).';
            }
            if (mb_strlen($variant['name']) > 150) {
                $errors[] = $label . 'navnet må højst være 150 tegn.';
            }
            // No name typed: named after its type values ("M / Rød"), or "Standard" when the item has no types
            $variantNames[] = $variant['name'] !== '' ? $variant['name'] : (implode(' / ', $variant['values']) ?: 'Standard');
        }
        if (count(array_unique(array_map('mb_strtolower', $variantNames))) !== count($variantNames)) {
            $errors[] = 'To varianter har samme navn eller samme typer. Giv dem forskellige navne eller typer.';
        }

        // The images are checked by their content, not by the name or type the browser sent
        $images = [];
        $files = $_FILES['images'] ?? null;
        if (is_array($files) && is_array($files['error'] ?? null)) {
            foreach ($files['error'] as $key => $error) {
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $fileName = is_string($files['name'][$key] ?? null) ? $files['name'][$key] : '';
                $tmpName = is_string($files['tmp_name'][$key] ?? null) ? $files['tmp_name'][$key] : '';
                if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmpName)) {
                    $errors[] = 'Billedet "' . $fileName . '" kunne ikke uploades (højst ' . (ITEM_MAX_IMAGE_BYTES >> 20) . ' MB).';
                    continue;
                }
                $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
                $size = getimagesize($tmpName);
                if (!is_string($mimeType) || !array_key_exists($mimeType, ITEM_IMAGE_TYPES) || $size === false || $size['mime'] !== $mimeType) {
                    $errors[] = 'Billedet "' . $fileName . '" skal være JPG, PNG, WebP eller GIF.';
                } elseif (filesize($tmpName) > ITEM_MAX_IMAGE_BYTES) {
                    $errors[] = 'Billedet "' . $fileName . '" er større end ' . (ITEM_MAX_IMAGE_BYTES >> 20) . ' MB.';
                } elseif ($size[0] > 10000 || $size[1] > 10000) {
                    $errors[] = 'Billedet "' . $fileName . '" er større end 10000x10000 pixels.';
                } else {
                    $images[] = [
                        'tmpName' => $tmpName,
                        'fileName' => mb_substr(basename(str_replace('\\', '/', $fileName)), 0, 255) ?: 'billede',
                        'mimeType' => $mimeType,
                        'fileSize' => filesize($tmpName),
                    ];
                }
            }
        }
        if (count($images) > ITEM_MAX_IMAGES) {
            $errors[] = 'Højst ' . ITEM_MAX_IMAGES . ' billeder pr. vare.';
        }
    }

    if (!$errors) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/items';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $itemOptions = ['categories' => $form['categories'], 'tags' => $tags, 'variantTypes' => $form['types']];
        $movedFiles = [];

        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO VeneficiaItems (itemName, itemDescription, itemOptions) VALUES (?, ?, ?)')
                ->execute([$form['name'], $form['description'] === '' ? null : $form['description'], json_encode($itemOptions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            $itemId = (int)$db->lastInsertId();

            // inventoryCount is left to its default of 0
            $insertVariant = $db->prepare('INSERT INTO VeneficiaItemVariants (itemId, varriantType, varriantName, varriantPrice) VALUES (?, ?, ?, ?)');
            $defaultVariantId = null;
            foreach ($form['variants'] as $i => $variant) {
                // (object) so it's always a JSON object, even if a type is named "0"
                $types = (object)array_combine($form['types'], $variant['values']);
                $insertVariant->execute([$itemId, json_encode($types, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $variantNames[$i], $variant['price']]);
                if ($i === $form['defaultVariant']) {
                    $defaultVariantId = (int)$db->lastInsertId();
                }
            }
            $db->prepare('UPDATE VeneficiaItems SET itemDefaultVariant = ? WHERE itemId = ?')->execute([$defaultVariantId, $itemId]);

            $insertAttachment = $db->prepare('INSERT INTO VeneficiaAttachments (fileName, fileUrl, mimeType, fileSize, altText) VALUES (?, ?, ?, ?, ?)');
            $linkAttachment = $db->prepare('INSERT INTO VeneficiaItemAttachments (itemId, attachmentId, sortOrder) VALUES (?, ?, ?)');
            foreach ($images as $i => $image) {
                $storedName = bin2hex(random_bytes(16)) . '.' . ITEM_IMAGE_TYPES[$image['mimeType']];
                if (!move_uploaded_file($image['tmpName'], $uploadDir . '/' . $storedName)) {
                    throw new RuntimeException('Could not move uploaded image');
                }
                $movedFiles[] = $uploadDir . '/' . $storedName;
                $insertAttachment->execute([$image['fileName'], '/uploads/items/' . $storedName, $image['mimeType'], $image['fileSize'], mb_substr($form['name'], 0, 500)]);
                $linkAttachment->execute([$itemId, (int)$db->lastInsertId(), $i]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            array_map('unlink', $movedFiles);
            throw $e;
        }

        // Redirect, so reloading the page doesn't add the item a second time
        header('Location: /admin/items?added=' . $itemId, true, 303);
        exit;
    }
}

$items = $db->query(
    'SELECT i.itemId, i.itemName, COUNT(v.itemVariantId) AS variantCount, MIN(v.varriantPrice) AS minPrice
     FROM VeneficiaItems i LEFT JOIN VeneficiaItemVariants v ON v.itemId = i.itemId
     GROUP BY i.itemId ORDER BY i.itemId DESC LIMIT 50'
)->fetchAll();
$addedId = filter_var($_GET['added'] ?? null, FILTER_VALIDATE_INT);
$added = $addedId ? array_values(array_filter($items, fn($item) => (int)$item['itemId'] === $addedId))[0] ?? null : null;

$editorState = ['types' => $form['types'], 'variants' => $form['variants'], 'defaultVariant' => $form['defaultVariant'], 'maxVariants' => ITEM_MAX_VARIANTS, 'maxTypes' => ITEM_MAX_TYPES];
$jsPath = '/assets/js/itemForm.js';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin.css') ?>">
    <script src="<?= $jsPath ?>?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . $jsPath) ?>" defer></script>
    <title>Varer - Veneficia admin</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/adminBar.php'; ?>
    <main class="admin-page">
        <form class="item-form" method="post" action="/admin/items" enctype="multipart/form-data">
            <h1>Tilføj vare</h1>
            <?php if ($added): ?>
                <p class="admin-success" role="status"><?= htmlspecialchars($added['itemName']) ?> er tilføjet.</p>
            <?php endif; ?>
            <?php if ($errors): ?>
                <ul class="auth-error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">

            <label for="name">Navn</label>
            <input type="text" id="name" name="name" maxlength="200" required value="<?= htmlspecialchars($form['name']) ?>">

            <label for="description">Beskrivelse</label>
            <textarea id="description" name="description" rows="6" maxlength="10000"><?= htmlspecialchars($form['description']) ?></textarea>

            <fieldset>
                <legend>Kategorier</legend>
                <?php if (!$categories): ?>
                    <p>Der er ingen kategorier endnu.</p>
                <?php endif; ?>
                <?php foreach ($categories as $category): ?>
                    <label class="item-checkbox">
                        <input type="checkbox" name="categories[]" value="<?= (int)$category['categoryId'] ?>" <?= in_array((int)$category['categoryId'], $form['categories'], true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($category['categoryName']) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <label for="tags">Tags (adskilt med komma)</label>
            <input type="text" id="tags" name="tags" maxlength="1100" placeholder="fx sommer, bomuld" value="<?= htmlspecialchars($form['tags']) ?>">

            <label for="images">Billeder (JPG, PNG, WebP eller GIF, højst <?= ITEM_MAX_IMAGES ?> á <?= ITEM_MAX_IMAGE_BYTES >> 20 ?> MB, i den rækkefølge de skal vises)</label>
            <?php if ($errors && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <p>Vælg billederne igen.</p>
            <?php endif; ?>
            <input type="file" id="images" name="images[]" multiple accept="<?= implode(',', array_keys(ITEM_IMAGE_TYPES)) ?>">

            <h2>Varianter</h2>
            <p>Hver vare har mindst 1 og højst <?= ITEM_MAX_VARIANTS ?> varianter. Variant-typer er fx Størrelse og Farve,
                og hver variant får en værdi for hver type (fx M og Rød). Standard-varianten er den der vises først.
                Lagerantal starter på 0.</p>
            <div id="variantEditor" data-state="<?= htmlspecialchars(json_encode($editorState, JSON_UNESCAPED_UNICODE)) ?>"></div>
            <noscript><p class="auth-error">Varianter kræver JavaScript.</p></noscript>

            <button type="submit">Tilføj vare</button>
        </form>

        <section>
            <h2>Seneste varer</h2>
            <table class="admin-table">
                <thead>
                    <tr><th>ID</th><th>Navn</th><th>Varianter</th><th>Fra pris</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= (int)$item['itemId'] ?></td>
                            <td><?= htmlspecialchars($item['itemName']) ?></td>
                            <td><?= (int)$item['variantCount'] ?></td>
                            <td><?= $item['minPrice'] === null ? '-' : number_format((float)$item['minPrice'], 2, ',', '.') . ' kr.' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
