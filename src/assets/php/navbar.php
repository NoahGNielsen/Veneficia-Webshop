<?php
// Shows the login state if the page called startSession() before its output (navbar is included mid-page, too late
// to start a session itself)
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/php/session.php';
$navCustomer = currentCustomer();
?>
<nav class="navbar">
    <a href="/products">Produkter</a>
    <?php if ($navCustomer !== null): ?>
        <span>Hej, <?= htmlspecialchars($navCustomer['firstName']) ?></span>
        <form method="post" action="/userMgmt/logout">
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">
            <button type="submit">Log ud</button>
        </form>
    <?php else: ?>
        <a href="/userMgmt/login">Log ind</a>
        <a href="/userMgmt/signup">Opret konto</a>
    <?php endif; ?>
</nav>
