<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';

header('Content-Type: application/json');

session_unset();
session_destroy();

json_response(true, 'Logged out successfully');

