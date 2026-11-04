<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/passwords.php';
$employee = requireEmployee();

$errors = [];
$changed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string)($_POST['currentPassword'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $passwordRepeat = (string)($_POST['passwordRepeat'] ?? '');

    if (!checkCsrf($_POST['csrfToken'] ?? null)) {
        $errors[] = 'Siden var udløbet. Prøv igen.';
    } else {
        $stmt = getDb()->prepare('SELECT passwordHash FROM VeneficiaEmployees WHERE employeeID = ?');
        $stmt->execute([$employee['employeeID']]);
        if (!password_verify($currentPassword, (string)$stmt->fetchColumn())) {
            $errors[] = 'Din nuværende adgangskode er forkert.';
        }
        array_push($errors, ...newPasswordErrors($password, $passwordRepeat));
    }

    if (!$errors) {
        getDb()->prepare('UPDATE VeneficiaEmployees SET passwordHash = ? WHERE employeeID = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $employee['employeeID']]);
        // Anyone else logged in with the old password is thrown out
        logoutOtherEmployeeSessions((int)$employee['employeeID']);
        $changed = true;
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/usermgnt.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/usermgnt.css') ?>">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin.css') ?>">
    <title>Skift adgangskode - Veneficia admin</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/adminBar.php'; ?>
    <main class="admin-page">
        <form class="auth-form" method="post" action="/admin/resetPassword">
            <h1>Skift adgangskode</h1>
            <?php if ($changed): ?>
                <p class="admin-success" role="status">Din adgangskode er skiftet. Du er logget ud på alle andre enheder.</p>
            <?php endif; ?>
            <?php if ($errors): ?>
                <ul class="auth-error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">
            <!-- Lets password managers know which account the new password belongs to -->
            <input type="hidden" name="username" autocomplete="username" value="<?= htmlspecialchars($employee['employeeUsername']) ?>">

            <label for="currentPassword">Nuværende adgangskode</label>
            <input type="password" id="currentPassword" name="currentPassword" autocomplete="current-password" required>

            <label for="password">Ny adgangskode</label>
            <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required>

            <label for="passwordRepeat">Gentag ny adgangskode</label>
            <input type="password" id="passwordRepeat" name="passwordRepeat" autocomplete="new-password" minlength="8" maxlength="72" required>

            <button type="submit">Skift adgangskode</button>
        </form>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
