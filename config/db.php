<?php
// CampusSync — DB Configuration
// Modify these values to match your local/server setup

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // change to your MySQL username
define('DB_PASS', '');            // change to your MySQL password
define('DB_NAME', 'campussync');

define('BASE_URL', 'http://localhost/campussync'); // change on deployment

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');
