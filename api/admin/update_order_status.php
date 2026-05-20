<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', null, 405);
}

require_admin();

$input = get_json_input();
$order_id = isset($input['order_id']) ? intval($input['order_id']) : 0;
$status = isset($input['status']) ? sanitize($input['status']) : '';

$validStatuses = ['pending', 'confirmed', 'packed', 'shipped', 'delivered', 'cancelled'];

if ($order_id <= 0) {
    json_error('Order ID is required', null, 400);
}

if (!in_array($status, $validStatuses)) {
    json_error('Invalid order status', null, 400);
}

try {
    $db = getDB();
    $db->beginTransaction();

    $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        $db->rollBack();
        json_error('Order not found', null, 404);
    }

    if ($order['order_status'] === 'cancelled' || $order['order_status'] === 'delivered') {
        $db->rollBack();
        json_error('Cannot update status of ' . $order['order_status'] . ' order', null, 400);
    }

    if ($status === 'cancelled' && $order['order_status'] !== 'cancelled') {
        $itemStmt = $db->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
        $itemStmt->execute([$order_id]);
        $items = $itemStmt->fetchAll();

        $stockStmt = $db->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        foreach ($items as $item) {
            $stockStmt->execute([$item['quantity'], $item['product_id']]);
        }
    }

    $payment_status = $order['payment_status'];
    if ($status === 'delivered' && $order['payment_method'] === 'cod') {
        $payment_status = 'paid';
    }
    if ($status === 'cancelled') {
        $payment_status = 'failed';
    }

    $updateStmt = $db->prepare('UPDATE orders SET order_status = ?, payment_status = ? WHERE id = ?');
    $updateStmt->execute([$status, $payment_status, $order_id]);

    $db->commit();

    json_response(true, 'Order status updated to ' . $status);

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_error('Failed to update order status', null, 500);
}

