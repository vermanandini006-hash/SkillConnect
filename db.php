
<?php

$host = getenv("DB_HOST") ?: "localhost";
$username = getenv("DB_USER") ?: "root";
$password = getenv("DB_PASSWORD") ?: "";
$database = getenv("DB_NAME") ?: "student_skill_exchange";
$port = (int)(getenv("DB_PORT") ?: 3306);

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database,
    $port
);

if ($conn->connect_error) {
    error_log("Database connection failed.");
    http_response_code(500);
    exit("Database connection unavailable.");
}

$conn->set_charset("utf8mb4");

?>
