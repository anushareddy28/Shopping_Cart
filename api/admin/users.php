<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth/session.php';

header('Content-Type: application/json');

require_admin();

try {
    $db = getDB();

    $stmt = $db->query("SELECT u.id, u.name, u.email, u.phone, u.created_at, COUNT(o.id) as order_count
                         FROM users u
                         LEFT JOIN orders o ON u.id = o.user_id
                         WHERE u.role = 'user'
                         GROUP BY u.id
                         ORDER BY u.created_at DESC");
    $users = $stmt->fetchAll();

    json_response(true, 'Users fetched', $users);

} catch (PDOException $e) {
    json_error('Failed to fetch users', null, 500);
}

