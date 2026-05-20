<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth/session.php';

header('Content-Type: application/json');

require_admin();

try {
    $db = getDB();
    $stmt = $db->query('SELECT * FROM coupons ORDER BY id DESC');
    $coupons = $stmt->fetchAll();

    json_response(true, 'Coupons fetched', $coupons);

} catch (PDOException $e) {
    json_error('Failed to fetch coupons', null, 500);
}

