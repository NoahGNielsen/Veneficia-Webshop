<?php
// The bar at the top of every admin page. Needs $employee from requireEmployee().
?>
<header class="admin-bar">
    <a href="/admin/">Admin forside</a>
    <span>Logget ind som <strong><?= htmlspecialchars($employee['employeeUsername']) ?></strong></span>
    <form method="post" action="/admin/logout">
        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">
        <button type="submit">Log ud</button>
    </form>
</header>
