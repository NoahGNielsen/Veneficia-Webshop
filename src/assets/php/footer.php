<?php
// The footer on every page (error pages too, so it mustn't touch the database)
?>
<footer class="footer on-secondary">
    <div class="container">
        <div class="footer__grid">
            <div>
                <a class="brand" href="/"><?= uiIcon('logo') ?>Veneficia</a>
                <p class="footer__tagline">Krystaller, røgelse, urter og bøger til din praksis.</p>
            </div>
            <nav aria-label="Sidefod">
                <ul class="footer__links">
                    <li><a href="/products">Produkter</a></li>
                    <li><a href="/basket">Kurv</a></li>
                    <li><a href="/legal/terms">Handelsbetingelser</a></li>
                    <li><a href="/legal/privacy">Privatlivspolitik</a></li>
                </ul>
            </nav>
            <p class="footer__copy">&copy; <?= date('Y') ?> Veneficia</p>
        </div>
    </div>
</footer>
