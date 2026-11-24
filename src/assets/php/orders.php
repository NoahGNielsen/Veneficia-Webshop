<?php
// Shared helpers for orders (admin order list and order page)

// Every order status, in the order an order goes through them. Stored in VeneficiaOrdre.ordreStatus.
const ORDER_STATUSES = [
    'paid' => 'Betalt',
    'packed' => 'Pakket',
    'shipped' => 'Afsendt',
    'delivered' => 'Leveret',
];

function orderStatusLabel(string $status): string
{
    return ORDER_STATUSES[$status] ?? $status;
}
