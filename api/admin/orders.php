<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth/session.php';

header('Content-Type: application/json');

require_admin();

try {
    $db = getDB();

    $where = '1=1';
    $params = [];

    if (!empty($_GET['status'])) {
        $where .= ' AND o.order_status = ?';
        $params[] = sanitize($_GET['status']);
    }

    $stmt = $db->prepare("SELECT o.*, u.name as user_name, u.email as user_email FROM orders o JOIN users u ON o.user_id = u.id WHERE {$where} ORDER BY o.created_at DESC");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    json_response(true, 'Orders fetched', $orders);

} catch (PDOException $e) {
    json_error('Failed to fetch orders', null, 500);
}

