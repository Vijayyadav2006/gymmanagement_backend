<?php
// CORS (adjust origin in production)
header("Access-Control-Allow-Origin: https://management-gym.onrender.com");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1️⃣ Read JSON input from React
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !isset($data['password'])) {
    echo json_encode([
        "success" => false,
        "message" => "Email and password required"
    ]);
    exit;
}

$email = trim($data['email']);
$password = $data['password'];

// 2️⃣ Database connection

$host = "sql312.infinityfree.com";
$user = "if0_41634430";
$pass = "QBzB1XSGUt";
$db   = "if0_41634430_gym_db";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    echo json_encode([
        "status" => "error",
        "type" => "db",
        "message" => "Database connection failed: " . mysqli_connect_error()
    ]);
    exit;
}

// 3️⃣ Fetch admin by email
$sql = "SELECT id, email, password FROM admins WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// 4️⃣ Check email exists
if ($result->num_rows !== 1) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password"
    ]);
    exit;
}

$admin = $result->fetch_assoc();

// 5️⃣ Verify password
if (password_verify($password, $admin['password'])) {

    // OPTIONAL: start session
    // session_start();
    // $_SESSION['admin_id'] = $admin['id'];

    echo json_encode([
        "success" => true,
        "message" => "Admin login successful"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password"
    ]);
}

$stmt->close();
$conn->close();
?>