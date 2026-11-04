<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
startSession(false);

// POST with a CSRF token, so other sites can't log people out with a link or an image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrfToken'] ?? null)) {
    logout();
}
header('Location: /');
exit;
