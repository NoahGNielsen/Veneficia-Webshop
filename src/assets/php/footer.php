<?php
// The footer on every page (error pages too, so it mustn't touch the database)
//
// The shop's contact details. Danish law (e-handelsloven) wants the business's name, address, email and CVR number
// easy to find on a webshop, and they build trust. Fill them in here; each line only shows once it has a value.
$footerContact = [
    'name' => '',    // the business's registered name
    'address' => '', // street, postcode and town
    'email' => '',
    'cvr' => '',
];
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
            <?php if (array_filter($footerContact)): ?>
                <address class="footer__contact">
                    <?php if ($footerContact['name'] !== ''): ?>
                        <span><?= htmlspecialchars($footerContact['name']) ?></span>
                    <?php endif; ?>
                    <?php if ($footerContact['address'] !== ''): ?>
                        <span><?= htmlspecialchars($footerContact['address']) ?></span>
                    <?php endif; ?>
                    <?php if ($footerContact['email'] !== ''): ?>
                        <a href="mailto:<?= htmlspecialchars($footerContact['email']) ?>"><?= htmlspecialchars($footerContact['email']) ?></a>
                    <?php endif; ?>
                    <?php if ($footerContact['cvr'] !== ''): ?>
                        <span>CVR <?= htmlspecialchars($footerContact['cvr']) ?></span>
                    <?php endif; ?>
                </address>
            <?php endif; ?>
            <p class="footer__copy">&copy; <?= date('Y') ?> Veneficia</p>
        </div>
    </div>
</footer>
