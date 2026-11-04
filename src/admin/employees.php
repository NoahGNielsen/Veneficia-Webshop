<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/passwords.php';
$employee = requireEmployee();

$errors = [];
$added = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $passwordRepeat = (string)($_POST['passwordRepeat'] ?? '');

    if (!checkCsrf($_POST['csrfToken'] ?? null)) {
        $errors[] = 'Siden var udløbet. Prøv igen.';
    }
    if ($username === '' || mb_strlen($username) > 100) {
        $errors[] = 'Skriv et brugernavn (højst 100 tegn).';
    }
    array_push($errors, ...newPasswordErrors($password, $passwordRepeat));

    if (!$errors) {
        try {
            getDb()->prepare('INSERT INTO VeneficiaEmployees (employeeUsername, passwordHash) VALUES (?, ?)')
                ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            $added = $username;
            $username = '';
        } catch (PDOException $e) {
            // 23000 = the unique key on employeeUsername
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $errors[] = 'Der findes allerede en medarbejder med det brugernavn.';
        }
    }
}

$employees = getDb()->query('SELECT employeeID, employeeUsername FROM VeneficiaEmployees ORDER BY employeeUsername')->fetchAll();
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/usermgnt.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/usermgnt.css') ?>">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin.css') ?>">
    <title>Medarbejdere - Veneficia admin</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/adminBar.php'; ?>
    <main class="admin-page">
        <section>
            <h1>Medarbejdere</h1>
            <table class="admin-table">
                <thead>
                    <tr><th>ID</th><th>Brugernavn</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $row): ?>
                        <tr>
                            <td><?= (int)$row['employeeID'] ?></td>
                            <td><?= htmlspecialchars($row['employeeUsername']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <form class="auth-form" method="post" action="/admin/employees">
            <h2>Tilføj medarbejder</h2>
            <?php if ($added !== ''): ?>
                <p class="admin-success" role="status"><?= htmlspecialchars($added) ?> er tilføjet.</p>
            <?php endif; ?>
            <?php if ($errors): ?>
                <ul class="auth-error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">

            <label for="username">Brugernavn</label>
            <input type="text" id="username" name="username" autocomplete="off" maxlength="100" required value="<?= htmlspecialchars($username) ?>">

            <label for="password">Adgangskode</label>
            <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required>

            <label for="passwordRepeat">Gentag adgangskode</label>
            <input type="password" id="passwordRepeat" name="passwordRepeat" autocomplete="new-password" minlength="8" maxlength="72" required>

            <button type="submit">Tilføj medarbejder</button>
        </form>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
