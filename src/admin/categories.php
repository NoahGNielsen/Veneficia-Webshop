<?php
// Categories: list, add, edit and delete. Items pick their categories in /admin/item (stored in itemOptions.categories).
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
$employee = requireEmployee();

// A posted value as a trimmed string. An array where a single value was expected counts as empty.
function postedString(mixed $value): string
{
    return is_string($value) ? trim($value) : '';
}

function postedId(mixed $value): ?int
{
    $id = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
    return $id === false ? null : $id;
}

// All categories keyed by id
function loadCategories(PDO $db): array
{
    $categories = [];
    foreach ($db->query('SELECT categoryId, categoryName, categoryDescription, parentCategoryId FROM VeneficiaCategories ORDER BY categoryName')
        ->fetchAll() as $row) {
        $row['parentCategoryId'] = $row['parentCategoryId'] === null ? null : (int)$row['parentCategoryId'];
        $categories[(int)$row['categoryId']] = $row;
    }
    return $categories;
}

// The category itself and every category below it, so it can't be moved under one of them (a loop)
function selfAndDescendants(array $categories, int $id): array
{
    $found = [$id => true];
    do {
        $added = false;
        foreach ($categories as $catId => $category) {
            if (!isset($found[$catId]) && $category['parentCategoryId'] !== null && isset($found[$category['parentCategoryId']])) {
                $found[$catId] = true;
                $added = true;
            }
        }
    } while ($added);
    return $found;
}

// "Overkategori > Underkategori"
function categoryPath(array $categories, ?int $id): string
{
    $names = [];
    $seen = [];
    while ($id !== null && isset($categories[$id]) && !isset($seen[$id])) {
        $seen[$id] = true;
        array_unshift($names, $categories[$id]['categoryName']);
        $id = $categories[$id]['parentCategoryId'];
    }
    return implode(' > ', $names);
}

$db = getDb();
$categories = loadCategories($db);

$errors = [];
$message = '';
$emptyForm = ['categoryId' => null, 'categoryName' => '', 'categoryDescription' => '', 'parentCategoryId' => null];
$form = $emptyForm;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf($_POST['csrfToken'] ?? null)) {
        $errors[] = 'Siden var udløbet. Prøv igen.';
    } elseif (isset($_POST['delete'])) {
        $deleteId = postedId($_POST['delete']);
        if ($deleteId !== null && isset($categories[$deleteId])) {
            $db->beginTransaction();
            // Remove the id from the items that use it. Subcategories become top level (ON DELETE SET NULL).
            $stmt = $db->prepare("SELECT itemId, itemOptions FROM VeneficiaItems WHERE JSON_CONTAINS(itemOptions->'$.categories', ?) FOR UPDATE");
            $stmt->execute([(string)$deleteId]);
            $update = $db->prepare('UPDATE VeneficiaItems SET itemOptions = ? WHERE itemId = ?');
            foreach ($stmt->fetchAll() as $item) {
                $options = json_decode($item['itemOptions'], true, 512, JSON_THROW_ON_ERROR);
                $options['categories'] = array_values(array_filter(
                    array_map('intval', $options['categories'] ?? []),
                    fn(int $id) => $id !== $deleteId
                ));
                $update->execute([json_encode($options, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $item['itemId']]);
            }
            $db->prepare('DELETE FROM VeneficiaCategories WHERE categoryId = ?')->execute([$deleteId]);
            $db->commit();
            $message = 'Kategorien ' . $categories[$deleteId]['categoryName'] . ' er slettet.';
            $categories = loadCategories($db);
        }
    } else {
        $form['categoryId'] = postedId($_POST['categoryId'] ?? '');
        $form['categoryName'] = postedString($_POST['categoryName'] ?? '');
        $form['categoryDescription'] = postedString($_POST['categoryDescription'] ?? '');
        $form['parentCategoryId'] = postedId($_POST['parentCategoryId'] ?? '');

        if ($form['categoryId'] !== null && !isset($categories[$form['categoryId']])) {
            $errors[] = 'Kategorien findes ikke længere.';
            $form['categoryId'] = null;
        }
        if ($form['categoryName'] === '' || mb_strlen($form['categoryName']) > 150) {
            $errors[] = 'Skriv et navn til kategorien (højst 150 tegn).';
        }
        if (mb_strlen($form['categoryDescription']) > 5000) {
            $errors[] = 'Beskrivelsen må højst være 5000 tegn.';
        }
        if ($form['parentCategoryId'] !== null) {
            if (!isset($categories[$form['parentCategoryId']])) {
                $errors[] = 'Vælg en overkategori fra listen.';
            } elseif ($form['categoryId'] !== null && isset(selfAndDescendants($categories, $form['categoryId'])[$form['parentCategoryId']])) {
                $errors[] = 'En kategori kan ikke ligge under sig selv eller en af sine underkategorier.';
            }
        }

        if (!$errors) {
            $values = [$form['categoryName'], $form['categoryDescription'] === '' ? null : $form['categoryDescription'], $form['parentCategoryId']];
            if ($form['categoryId'] === null) {
                $db->prepare('INSERT INTO VeneficiaCategories (categoryName, categoryDescription, parentCategoryId) VALUES (?, ?, ?)')
                    ->execute($values);
                $message = 'Kategorien ' . $form['categoryName'] . ' er oprettet.';
            } else {
                $db->prepare('UPDATE VeneficiaCategories SET categoryName = ?, categoryDescription = ?, parentCategoryId = ? WHERE categoryId = ?')
                    ->execute([...$values, $form['categoryId']]);
                $message = 'Kategorien ' . $form['categoryName'] . ' er gemt.';
            }
            $form = $emptyForm;
            $categories = loadCategories($db);
        }
    }
} elseif (($editId = postedId($_GET['edit'] ?? '')) !== null && isset($categories[$editId])) {
    $form = $categories[$editId];
    $form['categoryId'] = $editId;
    $form['categoryDescription'] = (string)$form['categoryDescription'];
}

$editing = $form['categoryId'] !== null;
$blockedParents = $editing ? selfAndDescendants($categories, $form['categoryId']) : [];
$paths = [];
foreach ($categories as $id => $category) {
    $paths[$id] = categoryPath($categories, $id);
}
uasort($paths, 'strcasecmp');
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/usermgnt.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/usermgnt.css') ?>">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin.css') ?>">
    <title>Kategorier - Veneficia admin</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/adminBar.php'; ?>
    <main class="admin-page">
        <section>
            <h1>Kategorier</h1>
            <?php if (!$categories): ?>
                <p>Der er ingen kategorier endnu.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr><th>Kategori</th><th>Beskrivelse</th><th></th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paths as $id => $path): ?>
                            <tr>
                                <td><?= htmlspecialchars($path) ?></td>
                                <td><?= htmlspecialchars((string)$categories[$id]['categoryDescription']) ?></td>
                                <td><a href="/admin/categories?edit=<?= (int)$id ?>">Ret</a></td>
                                <td>
                                    <form method="post" action="/admin/categories" onsubmit="return confirm('Slet kategorien? Den fjernes fra alle varer, og underkategorier bliver til hovedkategorier.')">
                                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">
                                        <button type="submit" name="delete" value="<?= (int)$id ?>">Slet</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <form class="auth-form" method="post" action="/admin/categories">
            <h2><?= $editing ? 'Ret kategori' : 'Opret kategori' ?></h2>
            <?php if ($message !== ''): ?>
                <p class="admin-success" role="status"><?= htmlspecialchars($message) ?></p>
            <?php endif; ?>
            <?php if ($errors): ?>
                <ul class="auth-error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="categoryId" value="<?= (int)$form['categoryId'] ?>">
            <?php endif; ?>

            <label for="categoryName">Navn</label>
            <input type="text" id="categoryName" name="categoryName" maxlength="150" required value="<?= htmlspecialchars($form['categoryName']) ?>">

            <label for="categoryDescription">Beskrivelse (valgfri)</label>
            <textarea id="categoryDescription" name="categoryDescription" rows="4" maxlength="5000"><?= htmlspecialchars($form['categoryDescription']) ?></textarea>

            <label for="parentCategoryId">Overkategori</label>
            <select id="parentCategoryId" name="parentCategoryId">
                <option value="">Ingen (hovedkategori)</option>
                <?php foreach ($paths as $id => $path): ?>
                    <?php if (!isset($blockedParents[$id])): ?>
                        <option value="<?= (int)$id ?>" <?= $form['parentCategoryId'] === $id ? 'selected' : '' ?>><?= htmlspecialchars($path) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>

            <button type="submit"><?= $editing ? 'Gem kategori' : 'Opret kategori' ?></button>
            <?php if ($editing): ?>
                <a href="/admin/categories">Annuller</a>
            <?php endif; ?>
        </form>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
