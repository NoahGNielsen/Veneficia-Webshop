<?php
// Shared by the customer signup and the admin's "add employee" form
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
