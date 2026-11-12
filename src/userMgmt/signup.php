<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/passwords.php';
startSession();
// Where to go after signing up, e.g. back to /checkout
$next = safeReturnPath($_GET['next'] ?? null);
$nextQuery = $next !== null ? '?next=' . rawurlencode($next) : '';

if (currentCustomer() !== null) {
    header('Location: ' . ($next ?? '/'));
    exit;
}

$errors = [];
$firstName = '';
$lastName = '';
$email = '';
$newsLetterConsent = false;
$rememberMe = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $passwordRepeat = (string)($_POST['passwordRepeat'] ?? '');
    $newsLetterConsent = !empty($_POST['newsLetterConsent']);
    $rememberMe = !empty($_POST['rememberMe']);

    if (!checkCsrf($_POST['csrfToken'] ?? null)) {
        $errors[] = 'Siden var udløbet. Prøv igen.';
    }
    if ($firstName === '' || mb_strlen($firstName) > 100) {
        $errors[] = 'Skriv dit fornavn (højst 100 tegn).';
    }
    if ($lastName === '' || mb_strlen($lastName) > 100) {
        $errors[] = 'Skriv dit efternavn (højst 100 tegn).';
    }
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Skriv en gyldig e-mailadresse.';
    }
    array_push($errors, ...newPasswordErrors($password, $passwordRepeat));
    if (empty($_POST['acceptTerms'])) {
        $errors[] = 'Du skal acceptere handelsbetingelserne og privatlivspolitikken.';
    }

    if (!$errors) {
        try {
            getDb()->prepare(
                'INSERT INTO VeneficiaCustomers (email, firstName, lastName, passwordHash, newsLetterConsent, newsLetterConsentAt)
                 VALUES (?, ?, ?, ?, ?, ' . ($newsLetterConsent ? 'NOW()' : 'NULL') . ')'
            )->execute([$email, $firstName, $lastName, password_hash($password, PASSWORD_DEFAULT), (int)$newsLetterConsent]);
            loginCustomer((int)getDb()->lastInsertId(), $rememberMe);
            header('Location: ' . ($next ?? '/'));
            exit;
        } catch (PDOException $e) {
            // 23000 = the unique key on email
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $errors[] = 'Der findes allerede en konto med den e-mail. <a href="/userMgmt/login">Log ind her</a>.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/header.php'; ?>
    <link rel="stylesheet" href="/assets/css/usermgnt.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/usermgnt.css') ?>">
    <title>Opret konto - Veneficia</title>
    <meta name="description" content="Opret en konto hos Veneficia.">
    <meta property="og:title" content="Opret konto - Veneficia">
    <meta property="og:description" content="Opret en konto hos Veneficia.">
</head>
<body>
    <main class="auth-page">
        <form class="auth-form" method="post" action="/userMgmt/signup<?= htmlspecialchars($nextQuery) ?>">
            <h1>Opret konto</h1>
            <?php if ($errors): ?>
                <ul class="auth-error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error /* fixed strings only, some contain a link */ ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">

            <label for="firstName">Fornavn</label>
            <input type="text" id="firstName" name="firstName" autocomplete="given-name" maxlength="100" required value="<?= htmlspecialchars($firstName) ?>">

            <label for="lastName">Efternavn</label>
            <input type="text" id="lastName" name="lastName" autocomplete="family-name" maxlength="100" required value="<?= htmlspecialchars($lastName) ?>">

            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" autocomplete="email" maxlength="254" required value="<?= htmlspecialchars($email) ?>">

            <label for="password">Adgangskode</label>
            <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required>

            <label for="passwordRepeat">Gentag adgangskode</label>
            <input type="password" id="passwordRepeat" name="passwordRepeat" autocomplete="new-password" minlength="8" maxlength="72" required>

            <label class="auth-checkbox">
                <input type="checkbox" name="newsLetterConsent" value="1" <?= $newsLetterConsent ? 'checked' : '' ?>> Ja tak, send mig nyhedsbrevet
            </label>
            <label class="auth-checkbox">
                <input type="checkbox" name="rememberMe" value="1" <?= $rememberMe ? 'checked' : '' ?>> Husk mig
            </label>
            <label class="auth-checkbox">
                <input type="checkbox" name="acceptTerms" value="1" required> Jeg accepterer <a href="/legal/terms">handelsbetingelserne</a> og <a href="/legal/privacy">privatlivspolitikken</a>
            </label>

            <button type="submit">Opret konto</button>
            <p class="auth-switch">Har du allerede en konto? <a href="/userMgmt/login<?= htmlspecialchars($nextQuery) ?>">Log ind</a></p>
        </form>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
