<?php
$host = "sql12.freesqldatabase.com";
$user = "sql12825070";
$pass = "sMJdt7qxqM";
$db   = "sql12825070";
$port = 3306;

$conn = mysqli_connect($host, $user, $pass, $db);

header("Content-Type: application/json");

if (!$conn) {
    echo json_encode([
        "status" => "error",
        "message" => mysqli_connect_error()
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "message" => "Database connected successfully"
]);