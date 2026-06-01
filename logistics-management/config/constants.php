<?php
// ─── App Constants ───────────────────────────────────────────────────────────
define('APP_NAME',    'LogiTrack Pro');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    '/logistics-management'); // subfolder on XAMPP

// ─── Roles ────────────────────────────────────────────────────────────────────
define('ROLE_ADMIN',     'admin');
define('ROLE_MANAGER',   'manager');
define('ROLE_ACCOUNTANT','accountant');
define('ROLE_OPS',       'operations');

// ─── Role home pages ──────────────────────────────────────────────────────────
$ROLE_HOME = [
    ROLE_ADMIN      => BASE_URL . '/admin/dashboard.php',
    ROLE_MANAGER    => BASE_URL . '/manager/dashboard.php',
    ROLE_ACCOUNTANT => BASE_URL . '/accountant/dashboard.php',
    ROLE_OPS        => BASE_URL . '/operations/dashboard.php',
];

// ─── Order statuses ───────────────────────────────────────────────────────────
define('ORDER_STATUS', [
    'PENDING'    => ['label'=>'Pending',    'color'=>'yellow'],
    'CONFIRMED'  => ['label'=>'Confirmed',  'color'=>'blue'],
    'IN_TRANSIT' => ['label'=>'In Transit', 'color'=>'olive'],
    'DELIVERED'  => ['label'=>'Delivered',  'color'=>'green'],
    'CANCELLED'  => ['label'=>'Cancelled',  'color'=>'red'],
    'RETURNED'   => ['label'=>'Returned',   'color'=>'gray'],
]);

// ─── Invoice statuses ─────────────────────────────────────────────────────────
define('INVOICE_STATUS', [
    'DRAFT'    => ['label'=>'Draft',    'color'=>'gray'],
    'ISSUED'   => ['label'=>'Issued',   'color'=>'blue'],
    'PAID'     => ['label'=>'Paid',     'color'=>'green'],
    'OVERDUE'  => ['label'=>'Overdue',  'color'=>'red'],
    'CANCELED' => ['label'=>'Canceled', 'color'=>'red'],
]);

// ─── Shipment statuses ────────────────────────────────────────────────────────
define('SHIPMENT_STATUS', [
    'SCHEDULED'  => ['label'=>'Scheduled',  'color'=>'blue'],
    'LOADING'    => ['label'=>'Loading',    'color'=>'yellow'],
    'IN_TRANSIT' => ['label'=>'In Transit', 'color'=>'olive'],
    'DELIVERED'  => ['label'=>'Delivered',  'color'=>'green'],
    'DELAYED'    => ['label'=>'Delayed',    'color'=>'red'],
    'CANCELLED'  => ['label'=>'Cancelled',  'color'=>'gray'],
]);

// ─── Currency ────────────────────────────────────────────────────────────────
define('CURRENCIES', ['VND', 'USD']);
define('DEFAULT_EXCHANGE_RATE', 25400); // 1 USD = 25,400 VND

// ─── Pagination ──────────────────────────────────────────────────────────────
define('PAGE_SIZE', 10);
