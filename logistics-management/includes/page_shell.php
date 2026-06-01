<?php
/**
 * page_shell.php — Helper to open a page HTML shell
 * Call open_page() at top, close_page() at bottom.
 */
function open_page(string $title, string $activeKey, array $breadcrumbs = [], array $extraScripts = []): void {
    global $pageTitle, $activePage, $extraScripts;
    $pageTitle  = $title;
    $activePage = $activeKey;
    $GLOBALS['extraScripts'] = $extraScripts;

    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="en">' . "\n";
    echo '<head>' . "\n";
    echo '  <meta charset="UTF-8">' . "\n";
    echo '  <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    echo '  <title>' . htmlspecialchars($title) . ' — LogiTrack Pro</title>' . "\n";
    echo '  <link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">' . "\n";
    $b = defined('BASE_URL') ? BASE_URL : '';
    echo '  <link rel="stylesheet" href="' . $b . '/assets/css/main.css">' . "\n";
    echo '  <link rel="stylesheet" href="' . $b . '/assets/css/components.css">' . "\n";
    echo '</head>' . "\n";
    echo '<body>' . "\n";
    echo '<div class="app-shell">' . "\n";

    // Render header (sidebar + topbar)
    $GLOBALS['breadcrumbs'] = $breadcrumbs;
    require __DIR__ . '/header.php';
}

function close_page(): void {
    require __DIR__ . '/footer.php';
    echo '</div><!-- /app-shell -->' . "\n";
}

// ─── Badge helper ─────────────────────────────────────────────────────────────
function status_badge(string $status): string {
    $map = [
        // Order/Shipment
        'PENDING'    => 'yellow',
        'CONFIRMED'  => 'blue',
        'IN_TRANSIT' => 'olive',
        'DELIVERED'  => 'green',
        'CANCELLED'  => 'gray',
        'RETURNED'   => 'gray',
        'SCHEDULED'  => 'blue',
        'LOADING'    => 'yellow',
        'DELAYED'    => 'red',
        // Invoice
        'DRAFT'      => 'gray',
        'ISSUED'     => 'blue',
        'PAID'       => 'green',
        'OVERDUE'    => 'red',
        'CANCELED'   => 'red',
        // Exception
        'OPEN'       => 'red',
        'IN_REVIEW'  => 'yellow',
        'RESOLVED'   => 'green',
        // Request
        'APPROVED'   => 'green',
        'REJECTED'   => 'red',
        // Asset
        'Available'  => 'green',
        'In Use'     => 'olive',
        'Maintenance'=> 'yellow',
        'Inactive'   => 'gray',
        // Stock
        'Adequate'   => 'green',
        'Low'        => 'red',
        // Account
        'active'     => 'green',
        'inactive'   => 'gray',
        // Reconciled
        'Yes'        => 'green',
        'No'         => 'yellow',
        // Schedule
        'COMPLETED'  => 'green',
        'IN_PROGRESS'=> 'olive',
    ];
    $label = str_replace('_', ' ', ucwords(strtolower($status)));
    $color = $map[$status] ?? 'gray';
    return '<span class="badge badge-' . $color . '">' . htmlspecialchars($label) . '</span>';
}

function fmt_num(float $n, int $dec = 0): string {
    return number_format($n, $dec);
}

function fmt_currency(float $amount, string $currency = 'VND'): string {
    if ($currency === 'USD') {
        return '$' . number_format($amount, 2);
    }
    return number_format($amount, 0) . ' ₫';
}
