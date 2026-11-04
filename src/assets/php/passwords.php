<?php
// Shared by the customer signup, the admin's "add employee" form and an employee changing their own password

// What's wrong with a new password and its repeat, as messages for the form (empty when it's fine)
function newPasswordErrors(string $password, string $passwordRepeat): array
{
    $errors = [];
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
    return $errors;
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
