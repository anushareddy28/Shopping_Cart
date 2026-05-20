<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth/session.php';

header('Content-Type: application/json');

require_admin();

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id <= 0) {
    json_error('Order ID is required', null, 400);
}

try {
    $db = getDB();

    $stmt = $db->prepare('SELECT o.*, u.name as user_name, u.email as user_email, u.phone as user_phone FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?');
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        json_error('Order not found', null, 404);
    }

    $itemStmt = $db->prepare('SELECT oi.*, p.image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?');
    $itemStmt->execute([$order_id]);
    $order['items'] = $itemStmt->fetchAll();

    json_response(true, 'Order details fetched', $order);

} catch (PDOException $e) {
    json_error('Failed to fetch order details', null, 500);
}

