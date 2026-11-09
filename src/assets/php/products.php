<?php
// Shared helpers for the public product pages (/products and /product)

// 1234.5 -> "1.234,50 kr."
function formatPrice(float|string $price): string
{
    return number_format((float)$price, 2, ',', '.') . ' kr.';
}

// URL of the product list, optionally filtered by tag and paged. Page 1 and no tag give plain /products.
function productsUrl(?string $tag = null, int $page = 1): string
{
    $query = array_filter(['tag' => $tag, 'page' => $page > 1 ? $page : null], fn($v) => $v !== null && $v !== '');
    return '/products' . ($query ? '?' . http_build_query($query) : '');
}
