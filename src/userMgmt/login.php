<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
startSession();
// Where to go after logging in, e.g. back to /checkout
$next = safeReturnPath($_GET['next'] ?? null);
$nextQuery = $next !== null ? '?next=' . rawurlencode($next) : '';

if (currentCustomer() !== null) {
    header('Location: ' . ($next ?? '/'));
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!checkCsrf($_POST['csrfToken'] ?? null)) {
        $error = 'Siden var udløbet. Prøv igen.';
    } else {
        $stmt = getDb()->prepare('SELECT VeneficiaCustomerId, passwordHash FROM VeneficiaCustomers WHERE email = ?');
        $stmt->execute([$email]);
        $customer = $stmt->fetch();

        if ($customer && password_verify($password, $customer['passwordHash'])) {
            if (password_needs_rehash($customer['passwordHash'], PASSWORD_DEFAULT)) {
                getDb()->prepare('UPDATE VeneficiaCustomers SET passwordHash = ? WHERE VeneficiaCustomerId = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $customer['VeneficiaCustomerId']]);
            }
            loginCustomer((int)$customer['VeneficiaCustomerId'], !empty($_POST['rememberMe']));
            header('Location: ' . ($next ?? '/'));
            exit;
        }

        // Hash anyway when the email is unknown, so the response time doesn't reveal which emails have an account
        if (!$customer) {
            password_hash($password, PASSWORD_DEFAULT);
        }
        $error = 'Forkert e-mail eller adgangskode.';
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/usermgnt.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/usermgnt.css') ?>">
    <title>Log ind - Veneficia</title>
    <meta name="description" content="Log ind på din Veneficia-konto.">
    <meta property="og:title" content="Log ind - Veneficia">
    <meta property="og:description" content="Log ind på din Veneficia-konto.">
</head>
<body>
    <main class="auth-page">
        <form class="auth-form" method="post" action="/userMgmt/login<?= htmlspecialchars($nextQuery) ?>">
            <h1>Log ind</h1>
            <?php if ($error !== ''): ?>
                <p class="auth-error" role="alert"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">

            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" autocomplete="email" required value="<?= htmlspecialchars($email) ?>">

            <label for="password">Adgangskode</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>

            <label class="auth-checkbox">
                <input type="checkbox" name="rememberMe" value="1"> Husk mig
            </label>

            <button type="submit">Log ind</button>
            <p class="auth-switch">Har du ikke en konto? <a href="/userMgmt/signup<?= htmlspecialchars($nextQuery) ?>">Opret en her</a></p>
        </form>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
