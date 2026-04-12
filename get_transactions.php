<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Added POST, OPTIONS
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Origin: http://localhost:5173"); // Changed from * to match your main API


// Database connection
$host = "localhost";
$dbname = "gym_db";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get email from request
    $email = isset($_GET['email']) ? $_GET['email'] : '';
    
    if (empty($email)) {
        echo json_encode([
            "status" => "error",
            "message" => "Email is required"
        ]);
        exit;
    }
    
    // Fetch user ID from email
    $userQuery = "SELECT id FROM users WHERE email = :email";
    $userStmt = $pdo->prepare($userQuery);
    $userStmt->execute([':email' => $email]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            "status" => "error",
            "message" => "User not found"
        ]);
        exit;
    }
    
    // Fetch transactions for this user
    $query = "SELECT * FROM payments WHERE user_id = :user_id ORDER BY payment_date DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $user['id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($transactions) > 0) {
        echo json_encode([
            "status" => "success",
            "transactions" => $transactions
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No payment records found"
        ]);
    }
    
} catch(PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>