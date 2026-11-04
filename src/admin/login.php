<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
startSession();

if (currentEmployee() !== null) {
    header('Location: /admin/employees');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!checkCsrf($_POST['csrfToken'] ?? null)) {
        $error = 'Siden var udløbet. Prøv igen.';
    } else {
        $stmt = getDb()->prepare('SELECT employeeID, passwordHash FROM VeneficiaEmployees WHERE employeeUsername = ?');
        $stmt->execute([$username]);
        $employee = $stmt->fetch();

        if ($employee && password_verify($password, $employee['passwordHash'])) {
            if (password_needs_rehash($employee['passwordHash'], PASSWORD_DEFAULT)) {
                getDb()->prepare('UPDATE VeneficiaEmployees SET passwordHash = ? WHERE employeeID = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $employee['employeeID']]);
            }
            loginEmployee((int)$employee['employeeID']);
            header('Location: /admin/employees');
            exit;
        }

        // Hash anyway when the username is unknown, so the response time doesn't reveal which usernames exist
        if (!$employee) {
            password_hash($password, PASSWORD_DEFAULT);
        }
        $error = 'Forkert brugernavn eller adgangskode.';
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/usermgnt.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/usermgnt.css') ?>">
    <title>Medarbejderlogin - Veneficia</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <main class="auth-page">
        <form class="auth-form" method="post" action="/admin/login">
            <h1>Medarbejderlogin</h1>
            <?php if ($error !== ''): ?>
                <p class="auth-error" role="alert"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">

            <label for="username">Brugernavn</label>
            <input type="text" id="username" name="username" autocomplete="username" maxlength="100" required value="<?= htmlspecialchars($username) ?>">

            <label for="password">Adgangskode</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>

            <button type="submit">Log ind</button>
        </form>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
