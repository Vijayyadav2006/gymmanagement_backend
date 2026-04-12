<?php
$host = "sql312.infinityfree.com"; // <-- from control panel
$user = "if0_41634430";
$pass = "QBzB1XSGUt";      // change password NOW
$db   = "if0_41634430_gym_db";

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