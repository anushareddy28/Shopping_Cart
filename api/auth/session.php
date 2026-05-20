<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        json_error('Authentication required', null, 401);
    }
    return $_SESSION['user_id'];
}

function require_admin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        json_error('Admin access required', null, 403);
    }
    return $_SESSION['user_id'];
}

function get_current_user_id() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

if (basename($_SERVER['SCRIPT_FILENAME']) === 'session.php') {
    header('Content-Type: application/json');
    if (isset($_SESSION['user_id'])) {
        json_response(true, 'Session active', [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role']
        ]);
    } else {
        json_error('Not logged in', null, 401);
    }
}

