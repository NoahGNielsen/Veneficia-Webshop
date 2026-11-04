<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
startSession();

if (currentCustomer() !== null) {
    header('Location: /');
    exit;
}

function isCommonPassword(string $password): bool
{
    $needle = mb_strtolower($password);
    $file = fopen($_SERVER['DOCUMENT_ROOT'] . '/assets/commonPasswords.txt', 'r');
    while (($line = fgets($file)) !== false) {
        if (mb_strtolower(trim($line)) === $needle) {
            fclose($file);
            return true;
        }
    }
    fclose($file);
    return false;
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
    if (mb_strlen($password) < 8) {
        $errors[] = 'Adgangskoden skal være mindst 8 tegn.';
    } elseif (strlen($password) > 72) {
        // bcrypt ignores everything after 72 bytes
        $errors[] = 'Adgangskoden må højst være 72 tegn.';
    } elseif (isCommonPassword($password)) {
        $errors[] = 'Den adgangskode er for almindelig. Vælg en anden.';
    }
    if ($password !== $passwordRepeat) {
        $errors[] = 'De to adgangskoder er ikke ens.';
    }
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
            header('Location: /');
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
    <link rel="stylesheet" href="/assets/css/mainStyle.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/mainStyle.css') ?>">
    <link rel="stylesheet" href="/assets/css/usermgnt.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/usermgnt.css') ?>">
    <title>Opret konto - Veneficia</title>
    <meta name="description" content="Opret en konto hos Veneficia.">
    <meta property="og:title" content="Opret konto - Veneficia">
    <meta property="og:description" content="Opret en konto hos Veneficia.">
</head>
<body>
    <main class="auth-page">
        <form class="auth-form" method="post" action="/userMgmt/signup">
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
            <p class="auth-switch">Har du allerede en konto? <a href="/userMgmt/login">Log ind</a></p>
        </form>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/php/footer.php'; ?>
</body>
</html>
