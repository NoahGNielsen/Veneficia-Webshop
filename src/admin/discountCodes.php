<?php
// Discount codes: list, add and delete. The types are described in database/schema.sql (VeneficiaDiscountCodes).
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
$employee = requireEmployee();

const DISCOUNT_TYPES = [
    'product' => 'Rabat på bestemt produkt (%)',
    'freeShipping' => 'Gratis fragt',
    'percent' => 'Rabat på hele ordren (%)',
    'amount' => 'Rabat på hele ordren (kr.)',
];
const DISCOUNT_DATE_FORMAT = 'Y-m-d\TH:i'; // what <input type="datetime-local"> sends

// A posted value as a trimmed string. An array where a single value was expected counts as empty.
function postedString(mixed $value): string
{
    return is_string($value) ? trim($value) : '';
}

// "2026-11-12T14:30" as a DateTime, or null if empty or not a real date
function postedDate(string $value): ?DateTime
{
    $date = DateTime::createFromFormat('!' . DISCOUNT_DATE_FORMAT, $value);
    return $date !== false && $date->format(DISCOUNT_DATE_FORMAT) === $value ? $date : null;
}

function formatDiscount(array $code): string
{
    $amount = number_format((float)$code['discountAmount'], 2, ',', '.');
    return match ($code['discountType']) {
        'product' => $amount . ' % på ' . ($code['itemName'] ?? '?'),
        'freeShipping' => 'Gratis fragt',
        'percent' => $amount . ' % på ordren',
        'amount' => $amount . ' kr. på ordren',
        default => $code['discountType'],
    };
}

$db = getDb();
$items = $db->query('SELECT itemId, itemName FROM VeneficiaItems ORDER BY itemName')->fetchAll(PDO::FETCH_KEY_PAIR);

$errors = [];
$message = '';
$form = [
    'codeName' => '',
    'code' => '',
    'validAt' => date(DISCOUNT_DATE_FORMAT),
    'validUntil' => '',
    'discountType' => 'percent',
    'discountAmount' => '',
    'itemId' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf($_POST['csrfToken'] ?? null)) {
        $errors[] = 'Siden var udløbet. Prøv igen.';
    } elseif (isset($_POST['delete'])) {
        $deleteId = filter_var($_POST['delete'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($deleteId) {
            // Orders that used the code keep existing, their discountCodeId becomes NULL (ON DELETE SET NULL)
            $db->prepare('DELETE FROM VeneficiaDiscountCodes WHERE discountCodeID = ?')->execute([$deleteId]);
            $message = 'Rabatkoden er slettet.';
        }
    } else {
        foreach (array_keys($form) as $field) {
            $form[$field] = postedString($_POST[$field] ?? '');
        }
        // Codes are stored in capitals, so customers can type them in any case
        $form['code'] = mb_strtoupper($form['code']);

        if ($form['codeName'] === '' || mb_strlen($form['codeName']) > 150) {
            $errors[] = 'Skriv et navn til rabatkoden (højst 150 tegn).';
        }
        if (!preg_match('/^[A-Z0-9_-]{3,100}$/', $form['code'])) {
            $errors[] = 'Koden skal være 3-100 tegn og må kun indeholde bogstaverne A-Z, tal, - og _.';
        }
        $validAt = postedDate($form['validAt']);
        if ($validAt === null) {
            $errors[] = 'Vælg hvornår koden gælder fra.';
        }
        $validUntil = null;
        if ($form['validUntil'] !== '') {
            $validUntil = postedDate($form['validUntil']);
            if ($validUntil === null) {
                $errors[] = 'Slutdatoen er ikke en gyldig dato.';
            } elseif ($validAt !== null && $validUntil <= $validAt) {
                $errors[] = 'Slutdatoen skal være efter startdatoen.';
            }
        }

        $type = $form['discountType'];
        $amount = 0.0;
        $itemId = null;
        if (!isset(DISCOUNT_TYPES[$type])) {
            $errors[] = 'Vælg en rabattype.';
        } elseif ($type !== 'freeShipping') {
            $amountString = str_replace(',', '.', $form['discountAmount']);
            $max = $type === 'amount' ? 99999999.99 : 100;
            if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amountString) || (float)$amountString <= 0 || (float)$amountString > $max) {
                $errors[] = $type === 'amount'
                    ? 'Skriv rabatten i kr. (over 0, højst 2 decimaler).'
                    : 'Skriv rabatten i procent (over 0 og højst 100, højst 2 decimaler).';
            } else {
                $amount = (float)$amountString;
            }
            if ($type === 'product') {
                $itemId = filter_var($form['itemId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (!$itemId || !isset($items[$itemId])) {
                    $errors[] = 'Vælg hvilket produkt rabatten gælder.';
                }
            }
        }

        if (!$errors) {
            try {
                $db->prepare(
                    'INSERT INTO VeneficiaDiscountCodes (codeName, code, validAt, validUntil, discountType, discountAmount, itemId)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $form['codeName'],
                    $form['code'],
                    $validAt->format('Y-m-d H:i:s'),
                    $validUntil?->format('Y-m-d H:i:s'),
                    $type,
                    number_format($amount, 2, '.', ''),
                    $itemId ?: null,
                ]);
                $message = 'Rabatkoden ' . $form['code'] . ' er oprettet.';
                $form = ['codeName' => '', 'code' => '', 'validAt' => date(DISCOUNT_DATE_FORMAT), 'validUntil' => '',
                    'discountType' => 'percent', 'discountAmount' => '', 'itemId' => ''];
            } catch (PDOException $e) {
                // 23000 = the unique key on code
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $errors[] = 'Der findes allerede en rabatkode med den kode.';
            }
        }
    }
}

$codes = $db->query(
    'SELECT d.discountCodeID, d.codeName, d.code, d.validAt, d.validUntil, d.discountType, d.discountAmount, i.itemName,
            (d.validAt <= NOW() AND (d.validUntil IS NULL OR d.validUntil > NOW())) AS isActive
     FROM VeneficiaDiscountCodes d LEFT JOIN VeneficiaItems i ON i.itemId = d.itemId
     ORDER BY d.discountCodeID DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/usermgnt.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/usermgnt.css') ?>">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin.css') ?>">
    <title>Rabatkoder - Veneficia admin</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/adminBar.php'; ?>
    <main class="admin-page">
        <section>
            <h1>Rabatkoder</h1>
            <?php if (!$codes): ?>
                <p>Der er ingen rabatkoder endnu.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr><th>Navn</th><th>Kode</th><th>Rabat</th><th>Gælder fra</th><th>Gælder til</th><th>Aktiv</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($codes as $code): ?>
                            <tr>
                                <td><?= htmlspecialchars($code['codeName']) ?></td>
                                <td><?= htmlspecialchars($code['code']) ?></td>
                                <td><?= htmlspecialchars(formatDiscount($code)) ?></td>
                                <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($code['validAt']))) ?></td>
                                <td><?= $code['validUntil'] === null ? '-' : htmlspecialchars(date('d-m-Y H:i', strtotime($code['validUntil']))) ?></td>
                                <td><?= $code['isActive'] ? 'Ja' : 'Nej' ?></td>
                                <td>
                                    <form method="post" action="/admin/discountCodes" onsubmit="return confirm('Slet rabatkoden?')">
                                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">
                                        <button type="submit" name="delete" value="<?= (int)$code['discountCodeID'] ?>">Slet</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <form class="auth-form" method="post" action="/admin/discountCodes">
            <h2>Opret rabatkode</h2>
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

            <label for="codeName">Navn (kun til internt brug)</label>
            <input type="text" id="codeName" name="codeName" maxlength="150" required value="<?= htmlspecialchars($form['codeName']) ?>">

            <label for="code">Kode (det kunden skriver)</label>
            <input type="text" id="code" name="code" autocomplete="off" minlength="3" maxlength="100" pattern="[A-Za-z0-9_\-]+" required value="<?= htmlspecialchars($form['code']) ?>">

            <label for="discountType">Type</label>
            <select id="discountType" name="discountType">
                <?php foreach (DISCOUNT_TYPES as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $form['discountType'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="itemId">Produkt (kun ved rabat på bestemt produkt)</label>
            <select id="itemId" name="itemId">
                <option value="">Vælg produkt</option>
                <?php foreach ($items as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= $form['itemId'] === (string)$id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="discountAmount">Rabat (% eller kr. efter typen, tom ved gratis fragt)</label>
            <input type="text" id="discountAmount" name="discountAmount" inputmode="decimal" maxlength="12" value="<?= htmlspecialchars($form['discountAmount']) ?>">

            <label for="validAt">Gælder fra</label>
            <input type="datetime-local" id="validAt" name="validAt" required value="<?= htmlspecialchars($form['validAt']) ?>">

            <label for="validUntil">Gælder til (tom = ingen slutdato)</label>
            <input type="datetime-local" id="validUntil" name="validUntil" value="<?= htmlspecialchars($form['validUntil']) ?>">

            <button type="submit">Opret rabatkode</button>
        </form>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
