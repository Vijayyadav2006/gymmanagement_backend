<?php
header("Access-Control-Allow-Origin: https://management-gym.onrender.com/");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection

$host = "sql12.freesqldatabase.com";
$user = "sql12825070";
$pass = "sMJdt7qxqM";
$db   = "sql12825070";
$port = 3306;

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    echo json_encode([
        "status" => "error",
        "type" => "db",
        "message" => "Database connection failed: " . mysqli_connect_error()
    ]);
    exit;
}

// Get email from request
$email = isset($_GET['email']) ? mysqli_real_escape_string($conn, $_GET['email']) : '';

if (empty($email)) {
    echo json_encode([
        "status" => "error",
        "message" => "Email is required"
    ]);
    mysqli_close($conn);
    exit;
}

// Fetch user profile (real data only)
$query = "SELECT id, first_name, last_name, email, mobile, occupation, plan, join_date, expiry_date, photo 
          FROM users 
          WHERE email = '$email' 
          LIMIT 1";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
    echo json_encode([
        "status" => "success",
        "user" => $user
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "User not found"
    ]);
}

mysqli_close($conn);
?>