<?php
// One item: /admin/item adds a new one, /admin/item?id=5 edits item 5.
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

// No name typed: named after its type values ("M / Rød"), or "Standard" when the item has no types
function variantName(string $name, array $values): string
{
    return $name !== '' ? $name : ($values ? implode(' / ', $values) : 'Standard');
}

$db = getDb();
$categories = $db->query('SELECT categoryId, categoryName FROM VeneficiaCategories ORDER BY categoryName')->fetchAll();
$categoryIds = array_map('intval', array_column($categories, 'categoryId'));

$errors = [];
$itemId = null;
$storedVariants = [];
$storedImages = [];
$form = [
    'name' => '',
    'description' => '',
    'categories' => [],
    'tags' => '',
    'types' => [],
    'variants' => [['id' => null, 'name' => '', 'price' => '', 'weight' => '', 'stock' => '0', 'values' => []]],
    'defaultVariant' => 0,
    'imageOrder' => [],
    'removeImages' => [],
];

if (isset($_GET['id'])) {
    $itemId = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $stmt = $db->prepare('SELECT itemName, itemDescription, itemOptions, itemDefaultVariant FROM VeneficiaItems WHERE itemId = ?');
    $stmt->execute([$itemId ?: 0]);
    $item = $stmt->fetch();
    if (!$item) {
        include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/statusCodesPages/404.php';
        exit;
    }

    $options = json_decode($item['itemOptions'] ?? '', true) ?: [];
    $types = $options['variantTypes'] ?? [];

    $stmt = $db->prepare('SELECT itemVariantId, varriantType, varriantName, varriantPrice, varriantWeight, inventoryCount FROM VeneficiaItemVariants WHERE itemId = ? ORDER BY itemVariantId');
    $stmt->execute([$itemId]);
    foreach ($stmt->fetchAll() as $row) {
        $variantTypes = json_decode($row['varriantType'], true) ?: [];
        $values = array_map(fn($type) => (string)($variantTypes[$type] ?? ''), $types);
        $storedVariants[(int)$row['itemVariantId']] = [
            'id' => (int)$row['itemVariantId'],
            // An automatic name stays automatic, so it follows the type values when they are changed
            'name' => $row['varriantName'] === variantName('', $values) ? '' : $row['varriantName'],
            'price' => $row['varriantPrice'],
            'weight' => (string)($row['varriantWeight'] ?? ''),
            'stock' => (string)$row['inventoryCount'],
            'values' => $values,
        ];
    }

    $stmt = $db->prepare(
        'SELECT a.attachmentId, a.fileName, a.fileUrl
         FROM VeneficiaItemAttachments ia JOIN VeneficiaAttachments a ON a.attachmentId = ia.attachmentId
         WHERE ia.itemId = ? ORDER BY ia.sortOrder, a.attachmentId'
    );
    $stmt->execute([$itemId]);
    foreach ($stmt->fetchAll() as $row) {
        $storedImages[(int)$row['attachmentId']] = $row;
        $form['imageOrder'][(int)$row['attachmentId']] = count($form['imageOrder']) + 1;
    }

    $stmt = $db->prepare('SELECT t.tagName FROM VeneficiaItemTags it JOIN VeneficiaTags t ON t.tagId = it.tagId WHERE it.itemId = ? ORDER BY t.tagName');
    $stmt->execute([$itemId]);

    $form['name'] = $item['itemName'];
    $form['description'] = (string)$item['itemDescription'];
    $form['categories'] = array_map('intval', $options['categories'] ?? []);
    $form['tags'] = implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $form['types'] = $types;
    $form['variants'] = array_values($storedVariants) ?: $form['variants'];
    $form['defaultVariant'] = (int)array_search((int)$item['itemDefaultVariant'], array_keys($storedVariants), true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A request bigger than post_max_size arrives with $_POST and $_FILES empty
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'Billederne fylder for meget tilsammen. Upload færre eller mindre billeder.';
    } elseif (!checkCsrf($_POST['csrfToken'] ?? null)) {
        $errors[] = 'Siden var udløbet. Prøv igen.';
    }

    if ($_POST) {
        $form['name'] = postedString($_POST['name'] ?? '');
        $form['description'] = str_replace("\r\n", "\n", postedString($_POST['description'] ?? ''));
        $form['tags'] = postedString($_POST['tags'] ?? '');

        $form['categories'] = [];
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
            // Only this item's own variants can be changed, and only once. Any other id counts as a new variant.
            $id = postedString($variant['id'] ?? '');
            $isStored = ctype_digit($id) && isset($storedVariants[(int)$id]) && !in_array((int)$id, array_column($form['variants'], 'id'), true);
            $form['variants'][] = [
                'id' => $isStored ? (int)$id : null,
                'name' => postedString($variant['name'] ?? ''),
                'price' => str_replace(',', '.', postedString($variant['price'] ?? '')),
                'weight' => postedString($variant['weight'] ?? ''),
                'stock' => $itemId === null ? '0' : postedString($variant['stock'] ?? ''),
                'values' => array_map(fn($i) => postedString($values[$i] ?? ''), array_keys($form['types'])),
            ];
        }
        $defaultVariant = postedString($_POST['defaultVariant'] ?? '');
        $form['defaultVariant'] = ctype_digit($defaultVariant) && (int)$defaultVariant < count($form['variants']) ? (int)$defaultVariant : 0;

        // Only this item's own images can be moved or removed
        $postedOrder = is_array($_POST['imageOrder'] ?? null) ? $_POST['imageOrder'] : [];
        foreach (array_keys($storedImages) as $id) {
            $position = postedString($postedOrder[$id] ?? '');
            if (ctype_digit($position)) {
                $form['imageOrder'][$id] = (int)$position;
            }
        }
        $postedRemovals = is_array($_POST['removeImages'] ?? null) ? $_POST['removeImages'] : [];
        foreach ($postedRemovals as $id) {
            if (is_string($id) && ctype_digit($id) && isset($storedImages[(int)$id])) {
                $form['removeImages'][] = (int)$id;
            }
        }
        $form['removeImages'] = array_values(array_unique($form['removeImages']));
    }

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
            if ($variant['weight'] !== '' && !preg_match('/^\d{1,7}$/', $variant['weight'])) {
                $errors[] = $label . 'skriv vægten som et helt antal gram, fx 250.';
            } elseif ($variant['weight'] !== '' && (int)$variant['weight'] < 10) {
                $errors[] = $label . 'vægten skal være mindst 10 gram (0,01 kg).';
            }
            if (!preg_match('/^\d{1,9}$/', $variant['stock'])) {
                $errors[] = $label . 'skriv et gyldigt lagerantal.';
            }
            if (array_filter($variant['values'], fn($v) => $v === '' || mb_strlen($v) > 50)) {
                $errors[] = $label . 'udfyld alle typer (højst 50 tegn hver).';
            }
            if (mb_strlen($variant['name']) > 150) {
                $errors[] = $label . 'navnet må højst være 150 tegn.';
            }
            $variantNames[] = variantName($variant['name'], $variant['values']);
        }
        if (count(array_unique(array_map('mb_strtolower', $variantNames))) !== count($variantNames)) {
            $errors[] = 'To varianter har samme navn eller samme typer. Giv dem forskellige navne eller typer.';
        }

        // Orders, returns and the warehouse log must keep their variant (ON DELETE RESTRICT). Those variants go back in
        // the form, so it can be saved without them being removed.
        $removedVariants = array_values(array_diff(array_keys($storedVariants), array_column($form['variants'], 'id')));
        if ($removedVariants) {
            $marks = implode(',', array_fill(0, count($removedVariants), '?'));
            $stmt = $db->prepare(
                "SELECT itemVariantId FROM VeneficiaOrdreItems WHERE itemVariantId IN ($marks)
                 UNION SELECT itemVariantId FROM VeneficiaOrdreReturns WHERE itemVariantId IN ($marks)
                 UNION SELECT itemVariantId FROM VeneficiaWarehouseLog WHERE itemVariantId IN ($marks)"
            );
            $stmt->execute([...$removedVariants, ...$removedVariants, ...$removedVariants]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $variant = $storedVariants[(int)$id];
                $errors[] = 'Varianten "' . variantName($variant['name'], $variant['values']) . '" kan ikke fjernes, fordi den findes på en ordre, en returnering eller i lagerloggen. Den er sat ind igen.';
                $form['variants'][] = $variant;
            }
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
        if (count($storedImages) - count($form['removeImages']) + count($images) > ITEM_MAX_IMAGES) {
            $errors[] = 'Højst ' . ITEM_MAX_IMAGES . ' billeder pr. vare.';
        }
    }

    if (!$errors) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/items';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $itemOptions = json_encode(['categories' => $form['categories'], 'variantTypes' => $form['types']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $description = $form['description'] === '' ? null : $form['description'];
        $keptImages = array_diff_key($form['imageOrder'], array_flip($form['removeImages']));
        asort($keptImages);
        $isNew = $itemId === null;
        $movedFiles = [];
        $removedFiles = [];

        $db->beginTransaction();
        try {
            $stockNow = [];
            if ($isNew) {
                $db->prepare('INSERT INTO VeneficiaItems (itemName, itemDescription, itemOptions) VALUES (?, ?, ?)')
                    ->execute([$form['name'], $description, $itemOptions]);
                $itemId = (int)$db->lastInsertId();
            } else {
                $db->prepare('UPDATE VeneficiaItems SET itemName = ?, itemDescription = ?, itemOptions = ? WHERE itemId = ?')
                    ->execute([$form['name'], $description, $itemOptions, $itemId]);

                // Images that still carry the old name as alt text follow the new name
                if ($form['name'] !== $item['itemName']) {
                    $db->prepare(
                        'UPDATE VeneficiaAttachments a JOIN VeneficiaItemAttachments ia ON ia.attachmentId = a.attachmentId
                         SET a.altText = ? WHERE ia.itemId = ? AND a.altText = ?'
                    )->execute([mb_substr($form['name'], 0, 500), $itemId, $item['itemName']]);
                }

                // Locked, so the stock change written to the log matches what the count really was
                $stmt = $db->prepare('SELECT itemVariantId, inventoryCount FROM VeneficiaItemVariants WHERE itemId = ? FOR UPDATE');
                $stmt->execute([$itemId]);
                $stockNow = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            }

            $deleteVariant = $db->prepare('DELETE FROM VeneficiaItemVariants WHERE itemVariantId = ? AND itemId = ?');
            foreach ($removedVariants as $id) {
                $deleteVariant->execute([$id, $itemId]);
            }

            // New variants start on the default inventoryCount of 0
            $insertVariant = $db->prepare('INSERT INTO VeneficiaItemVariants (itemId, varriantType, varriantName, varriantPrice, varriantWeight) VALUES (?, ?, ?, ?, ?)');
            $updateVariant = $db->prepare('UPDATE VeneficiaItemVariants SET varriantType = ?, varriantName = ?, varriantPrice = ?, varriantWeight = ? WHERE itemVariantId = ? AND itemId = ?');
            $updateStock = $db->prepare('UPDATE VeneficiaItemVariants SET inventoryCount = ? WHERE itemVariantId = ? AND itemId = ?');
            $logStock = $db->prepare("INSERT INTO VeneficiaWarehouseLog (itemVariantId, employeeId, changeType, quantityChange, note) VALUES (?, ?, 'adjustment', ?, 'Rettet i admin')");
            $defaultVariantId = null;
            foreach ($form['variants'] as $i => $variant) {
                // (object) so it's always a JSON object, even if a type is named "0"
                $types = json_encode((object)array_combine($form['types'], $variant['values']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $weight = $variant['weight'] === '' ? null : (int)$variant['weight'];
                if ($variant['id'] === null) {
                    $insertVariant->execute([$itemId, $types, $variantNames[$i], $variant['price'], $weight]);
                    $variantId = (int)$db->lastInsertId();
                } else {
                    $variantId = $variant['id'];
                    $updateVariant->execute([$types, $variantNames[$i], $variant['price'], $weight, $variantId, $itemId]);
                }

                $stockChange = (int)$variant['stock'] - (int)($stockNow[$variantId] ?? 0);
                if ($stockChange !== 0) {
                    $updateStock->execute([(int)$variant['stock'], $variantId, $itemId]);
                    $logStock->execute([$variantId, $employee['employeeID'], $stockChange]);
                }

                if ($i === $form['defaultVariant']) {
                    $defaultVariantId = $variantId;
                }
            }
            $db->prepare('UPDATE VeneficiaItems SET itemDefaultVariant = ? WHERE itemId = ?')->execute([$defaultVariantId, $itemId]);

            // New tags are created, and a tag that already exists keeps its spelling (the unique key ignores case)
            $db->prepare('DELETE FROM VeneficiaItemTags WHERE itemId = ?')->execute([$itemId]);
            if ($tags) {
                $db->prepare('INSERT IGNORE INTO VeneficiaTags (tagName) VALUES ' . implode(',', array_fill(0, count($tags), '(?)')))->execute($tags);
                $marks = implode(',', array_fill(0, count($tags), '?'));
                $db->prepare("INSERT INTO VeneficiaItemTags (tagId, itemId) SELECT tagId, ? FROM VeneficiaTags WHERE tagName IN ($marks)")->execute([$itemId, ...$tags]);
            }

            // The link in VeneficiaItemAttachments goes with the attachment (ON DELETE CASCADE)
            $deleteAttachment = $db->prepare('DELETE FROM VeneficiaAttachments WHERE attachmentId = ?');
            foreach ($form['removeImages'] as $id) {
                $deleteAttachment->execute([$id]);
                $removedFiles[] = $uploadDir . '/' . basename($storedImages[$id]['fileUrl']);
            }

            $position = 0;
            $sortImage = $db->prepare('UPDATE VeneficiaItemAttachments SET sortOrder = ? WHERE itemId = ? AND attachmentId = ?');
            foreach (array_keys($keptImages) as $id) {
                $sortImage->execute([$position++, $itemId, $id]);
            }

            $insertAttachment = $db->prepare('INSERT INTO VeneficiaAttachments (fileName, fileUrl, mimeType, fileSize, altText) VALUES (?, ?, ?, ?, ?)');
            $linkAttachment = $db->prepare('INSERT INTO VeneficiaItemAttachments (itemId, attachmentId, sortOrder) VALUES (?, ?, ?)');
            foreach ($images as $image) {
                $storedName = bin2hex(random_bytes(16)) . '.' . ITEM_IMAGE_TYPES[$image['mimeType']];
                if (!move_uploaded_file($image['tmpName'], $uploadDir . '/' . $storedName)) {
                    throw new RuntimeException('Could not move uploaded image');
                }
                $movedFiles[] = $uploadDir . '/' . $storedName;
                $insertAttachment->execute([$image['fileName'], '/uploads/items/' . $storedName, $image['mimeType'], $image['fileSize'], mb_substr($form['name'], 0, 500)]);
                $linkAttachment->execute([$itemId, (int)$db->lastInsertId(), $position++]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            array_map('unlink', $movedFiles);
            throw $e;
        }

        // Only now, so a failed save can't lose images that are still in use
        array_map('unlink', array_filter($removedFiles, 'is_file'));

        // Redirect, so reloading the page doesn't save a second time
        header('Location: ' . ($isNew ? '/admin/items?added=' . $itemId : '/admin/item?id=' . $itemId . '&saved=1'), true, 303);
        exit;
    }
}

// Tags already used on any item, as suggestions for the tags field. Fetched here so a successful save skips it.
$knownTags = $db->query('SELECT t.tagName FROM VeneficiaTags t WHERE EXISTS (SELECT 1 FROM VeneficiaItemTags it WHERE it.tagId = t.tagId) ORDER BY t.tagName')->fetchAll(PDO::FETCH_COLUMN);

$editorState = ['types' => $form['types'], 'variants' => $form['variants'], 'defaultVariant' => $form['defaultVariant'], 'maxVariants' => ITEM_MAX_VARIANTS, 'maxTypes' => ITEM_MAX_TYPES, 'showStock' => $itemId !== null];
$jsPath = '/assets/js/itemForm.js';
$heading = $itemId === null ? 'Ny vare' : 'Ret vare';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin.css') ?>">
    <script src="<?= $jsPath ?>?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . $jsPath) ?>" defer></script>
    <title><?= $heading ?> - Veneficia admin</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/adminBar.php'; ?>
    <main class="admin-page">
        <form class="item-form" method="post" action="/admin/item<?= $itemId === null ? '' : '?id=' . $itemId ?>" enctype="multipart/form-data">
            <a href="/admin/items">Alle varer</a>
            <h1><?= $heading ?></h1>
            <?php if (isset($_GET['saved']) && !$errors): ?>
                <p class="admin-success" role="status">Ændringerne er gemt.</p>
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
            <div class="item-tags">
                <input type="text" id="tags" name="tags" maxlength="1100" placeholder="fx sommer, bomuld" value="<?= htmlspecialchars($form['tags']) ?>" data-known-tags="<?= htmlspecialchars(json_encode($knownTags, JSON_UNESCAPED_UNICODE)) ?>">
            </div>

            <?php if ($storedImages): ?>
                <fieldset>
                    <legend>Nuværende billeder (laveste placering vises først)</legend>
                    <div class="item-images">
                        <?php foreach ($storedImages as $id => $image): ?>
                            <div class="item-image">
                                <img src="<?= htmlspecialchars($image['fileUrl']) ?>" alt="<?= htmlspecialchars($image['fileName']) ?>" width="120" height="120" loading="lazy" decoding="async">
                                <label>Placering
                                    <input type="number" name="imageOrder[<?= $id ?>]" min="1" value="<?= (int)$form['imageOrder'][$id] ?>">
                                </label>
                                <label class="item-checkbox">
                                    <input type="checkbox" name="removeImages[]" value="<?= $id ?>" <?= in_array($id, $form['removeImages'], true) ? 'checked' : '' ?>>
                                    Fjern
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endif; ?>

            <label for="images"><?= $storedImages ? 'Tilføj billeder' : 'Billeder' ?> (JPG, PNG, WebP eller GIF, højst <?= ITEM_MAX_IMAGES ?> á <?= ITEM_MAX_IMAGE_BYTES >> 20 ?> MB, i den rækkefølge de skal vises)</label>
            <?php if ($errors): ?>
                <p>Vælg billederne igen.</p>
            <?php endif; ?>
            <input type="file" id="images" name="images[]" multiple accept="<?= implode(',', array_keys(ITEM_IMAGE_TYPES)) ?>">

            <h2>Varianter</h2>
            <p>Hver vare har mindst 1 og højst <?= ITEM_MAX_VARIANTS ?> varianter. Variant-typer er fx Størrelse og Farve,
                og hver variant får en værdi for hver type (fx M og Rød). Standard-varianten er den der vises først.
                <?php if ($itemId === null): ?>
                    Lagerantal starter på 0.
                <?php else: ?>
                    Ændringer i lagerantal skrives i lagerloggen. En variant der findes på en ordre, en returnering eller
                    i lagerloggen kan ikke fjernes.
                <?php endif; ?>
            </p>
            <div id="variantEditor" data-state="<?= htmlspecialchars(json_encode($editorState, JSON_UNESCAPED_UNICODE)) ?>"></div>
            <noscript><p class="auth-error">Varianter kræver JavaScript.</p></noscript>

            <button type="submit"><?= $itemId === null ? 'Tilføj vare' : 'Gem ændringer' ?></button>
        </form>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
