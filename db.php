<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "gym_db";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    header("Content-Type: application/json");
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}