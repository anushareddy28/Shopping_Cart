<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth/session.php';

header('Content-Type: application/json');

require_admin();

try {
    $db = getDB();

    $totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $totalProducts = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $totalOrders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $totalRevenue = $db->query("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE order_status != 'cancelled'")->fetchColumn();
    $pendingOrders = $db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();
    $lowStock = $db->query("SELECT COUNT(*) FROM products WHERE stock <= 5 AND status = 'active'")->fetchColumn();

    json_response(true, 'Stats fetched', [
        'total_users' => intval($totalUsers),
        'total_products' => intval($totalProducts),
        'total_orders' => intval($totalOrders),
        'total_revenue' => round(floatval($totalRevenue), 2),
        'pending_orders' => intval($pendingOrders),
        'low_stock' => intval($lowStock)
    ]);

} catch (PDOException $e) {
    json_error('Failed to fetch stats', null, 500);
}

