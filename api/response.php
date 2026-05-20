<?php

function json_response($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

function json_error($message, $errors = null, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => $message];
    if ($errors !== null) {
        $response['errors'] = $errors;
    }
    echo json_encode($response);
    exit;
}

function get_json_input() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        return [];
    }
    return $input;
}

function sanitize($value) {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

