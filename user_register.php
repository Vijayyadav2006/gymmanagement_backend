<?php
// ================= CORS =================
header("Access-Control-Allow-Origin: https://management-gym.onrender.com");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ================= DB =================

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

// ================= CHECK ACTION TYPE =================
$action = '';

// Check POST/GET first
if (isset($_POST['action'])) {
    $action = $_POST['action'];
} elseif (isset($_GET['action'])) {
    $action = $_GET['action'];
}

// If not found, check JSON input
if (empty($action)) {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $rawInput = file_get_contents("php://input");
        $jsonData = json_decode($rawInput, true);
        if ($jsonData && isset($jsonData['action'])) {
            $action = $jsonData['action'];
        }
    }
}

// Route based on action
if ($action === 'login') {
    handleLogin($conn);
} elseif ($action === 'register') {
    handleRegistration($conn);
} elseif ($action === 'admin_add_user') {
    handleAdminAddUser($conn);
} elseif ($action === 'get_all_users') {
    handleGetAllUsers($conn);
} elseif ($action === 'update_user') {
    handleUpdateUser($conn);
} elseif ($action === 'get_expiring_members') {
    handleGetExpiringMembers($conn);
} elseif ($action === 'renew_membership') {
    handleRenewMembership($conn);
} elseif ($action === 'get_payment_history') {
    handleGetPaymentHistory($conn);
} elseif ($action === 'get_all_payments') {
    handleGetAllPayments($conn);
} elseif ($action === 'delete_user') {
    handleDeleteUser($conn);
} else {
    // If no action specified, try to infer from data
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $rawInput = file_get_contents("php://input");
        $data = json_decode($rawInput, true);
        
        if ($data) {
            if (isset($data['action']) && $data['action'] === 'admin_add_user') {
                handleAdminAddUser($conn);
            } elseif (isset($data['first_name']) && isset($data['email']) && isset($data['password'])) {
                handleRegistration($conn);
            } elseif (isset($data['email']) && isset($data['password'])) {
                handleLogin($conn);
            } elseif (isset($data['action']) && $data['action'] === 'update_user') {
                handleUpdateUser($conn);
            } elseif (isset($data['action']) && $data['action'] === 'delete_user') {
                handleDeleteUser($conn);
            } else {
                echo json_encode([
                    "status" => "error",
                    "message" => "Invalid request. Specify action='register', action='login', action='admin_add_user', action='get_all_users', action='update_user', or action='delete_user'"
                ]);
            }
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Invalid JSON data received"
            ]);
        }
    } 
    // Check for FormData
    elseif (isset($_POST['first_name']) && !empty($_POST['first_name'])) {
        handleRegistration($conn);
    } 
    elseif (isset($_POST['email']) && isset($_POST['password'])) {
        handleLogin($conn);
    } 
    else {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid request. Specify action='register', action='login', action='admin_add_user', action='get_all_users', action='update_user', or action='delete_user'",
            "debug_info" => [
                "post_keys" => array_keys($_POST),
                "get_keys" => array_keys($_GET),
                "content_type" => $_SERVER['CONTENT_TYPE'] ?? 'not set',
                "request_method" => $_SERVER['REQUEST_METHOD']
            ]
        ]);
    }
}

mysqli_close($conn);
exit;

// ================= ADMIN ADD USER FUNCTION =================
function handleAdminAddUser($conn) {
    // Get data from either JSON or POST
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $photoName = "";
    
    if (strpos($contentType, 'application/json') !== false) {
        $rawInput = file_get_contents("php://input");
        $data = json_decode($rawInput, true);
        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';
        $mobile = $data['mobile'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? ''; // If empty, will use default
        $joinDate = $data['join_date'] ?? '';
        $plan = $data['plan'] ?? '';
        $expiryDate = $data['expiry_date'] ?? '';
        $occupation = $data['occupation'] ?? '';
    } else {
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $mobile = $_POST['mobile'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $joinDate = $_POST['join_date'] ?? '';
        $plan = $_POST['plan'] ?? '';
        $expiryDate = $_POST['expiry_date'] ?? '';
        $occupation = $_POST['occupation'] ?? '';
        
        // Handle photo upload for FormData
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            if (!file_exists("uploads")) {
                mkdir("uploads", 0777, true);
            }
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            if (in_array($_FILES['photo']['type'], $allowed_types)) {
                $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $photoName = time() . "_" . uniqid() . "." . $file_extension;
                $upload_path = "uploads/" . $photoName;
                move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path);
            }
        }
    }

    // Validation (password is optional for admin add)
    $missing_fields = [];
    if (empty($firstName)) $missing_fields[] = "first_name";
    if (empty($lastName)) $missing_fields[] = "last_name";
    if (empty($mobile)) $missing_fields[] = "mobile";
    if (empty($email)) $missing_fields[] = "email";
    if (empty($joinDate)) $missing_fields[] = "join_date";
    if (empty($plan)) $missing_fields[] = "plan";

    if (!empty($missing_fields)) {
        echo json_encode([
            "status" => "error",
            "type" => "validation",
            "message" => "Required fields missing: " . implode(", ", $missing_fields)
        ]);
        return;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            "status" => "error",
            "type" => "validation",
            "message" => "Invalid email format"
        ]);
        return;
    }

    // Check if user exists
    $checkSql = "SELECT id FROM users WHERE email = ? OR mobile = ? LIMIT 1";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "ss", $email, $mobile);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);

    if (mysqli_num_rows($checkRes) > 0) {
        echo json_encode([
            "status" => "error",
            "type" => "exists",
            "message" => "User already exists with this email or mobile"
        ]);
        mysqli_stmt_close($checkStmt);
        return;
    }
    mysqli_stmt_close($checkStmt);

    // Set default password if not provided
    if (empty($password)) {
        $password = "admin@123";
    }

    // Insert user
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $insertSql = "INSERT INTO users 
        (first_name, last_name, mobile, email, password, photo, join_date, plan, expiry_date, occupation, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";

    $insertStmt = mysqli_prepare($conn, $insertSql);
    mysqli_stmt_bind_param(
        $insertStmt, 
        "ssssssssss", 
        $firstName, 
        $lastName, 
        $mobile, 
        $email, 
        $hashedPassword, 
        $photoName, 
        $joinDate, 
        $plan, 
        $expiryDate, 
        $occupation
    );

    if (mysqli_stmt_execute($insertStmt)) {
          syncToGoogleSheet("Users", [
        date("Y-m-d H:i:s"),
        $firstName,
        $lastName,
        $mobile,
        $email,
        $plan,
        $joinDate,
        $expiryDate,
        $occupation
    ]);
    
        $userId = mysqli_insert_id($conn);
        
        $fetchSql = "SELECT id, first_name, last_name, email, mobile, occupation, plan, join_date, expiry_date, photo FROM users WHERE id = ?";
        $fetchStmt = mysqli_prepare($conn, $fetchSql);
        mysqli_stmt_bind_param($fetchStmt, "i", $userId);
        mysqli_stmt_execute($fetchStmt);
        $fetchResult = mysqli_stmt_get_result($fetchStmt);
        $userData = mysqli_fetch_assoc($fetchResult);
        mysqli_stmt_close($fetchStmt);
        
        if (!empty($userData['photo'])) {
            $userData['photo'] = "https://gymmanagement-backend-tvxb.onrender.com/uploads/" . $userData['photo'];
        } else {
            $userData['photo'] = null;
        }
        
        echo json_encode([
            "status" => "success",
            "type" => "admin_add_user",
            "message" => "User added successfully. " . (empty($data['password'] ?? $_POST['password'] ?? '') ? "Default password: admin@123" : "Password set by admin"),
            "user" => $userData,
            "default_password_used" => empty($password) || $password === "admin@123"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "type" => "insert",
            "message" => "Failed to add user: " . mysqli_error($conn)
        ]);
    }
    mysqli_stmt_close($insertStmt);
}

// ================= RENEW MEMBERSHIP FUNCTION =================
function handleRenewMembership($conn) {
    // Get data from JSON input
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid data received"
        ]);
        return;
    }
    
    $userId = $data['user_id'] ?? 0;
    $email = $data['email'] ?? '';
    $newPlan = $data['plan'] ?? '';
    $newExpiryDate = $data['expiry_date'] ?? '';
    $amount = $data['amount'] ?? 0;
    $paymentMethod = $data['payment_method'] ?? 'cash';
    $adminNotes = $data['admin_notes'] ?? 'Membership renewal';
    $renewalStartingDay = $data['renewal_starting_day'] ?? date('Y-m-d');
    
    // Validate required fields
    if ((!$userId && empty($email)) || empty($newPlan) || empty($newExpiryDate) || empty($amount)) {
        echo json_encode([
            "status" => "error",
            "message" => "User ID/email, plan, expiry date, and amount are required for renewal"
        ]);
        return;
    }
    
    // If email provided but no user_id, get user_id from email
    if (!$userId && !empty($email)) {
        $emailQuery = "SELECT id, first_name, last_name, plan as old_plan, expiry_date as old_expiry, join_date FROM users WHERE email = ?";
        $emailStmt = mysqli_prepare($conn, $emailQuery);
        mysqli_stmt_bind_param($emailStmt, "s", $email);
        mysqli_stmt_execute($emailStmt);
        $emailResult = mysqli_stmt_get_result($emailStmt);
        $userData = mysqli_fetch_assoc($emailResult);
        
        if (!$userData) {
            echo json_encode([
                "status" => "error",
                "message" => "User not found with email: " . $email
            ]);
            mysqli_stmt_close($emailStmt);
            return;
        }
        $userId = $userData['id'];
        $oldPlan = $userData['old_plan'];
        $oldExpiry = $userData['old_expiry'];
        $joinDate = $userData['join_date'];
        mysqli_stmt_close($emailStmt);
    } else {
        // Get existing user data
        $userQuery = "SELECT id, first_name, last_name, plan as old_plan, expiry_date as old_expiry, join_date FROM users WHERE id = ?";
        $userStmt = mysqli_prepare($conn, $userQuery);
        mysqli_stmt_bind_param($userStmt, "i", $userId);
        mysqli_stmt_execute($userStmt);
        $userResult = mysqli_stmt_get_result($userStmt);
        $userData = mysqli_fetch_assoc($userResult);
        
        if (!$userData) {
            echo json_encode([
                "status" => "error",
                "message" => "User not found"
            ]);
            mysqli_stmt_close($userStmt);
            return;
        }
        $oldPlan = $userData['old_plan'];
        $oldExpiry = $userData['old_expiry'];
        $joinDate = $userData['join_date'];
        mysqli_stmt_close($userStmt);
    }
    
    // Generate receipt number
    $receiptNo = "RCP-" . date('Ymd') . "-" . str_pad($userId, 4, '0', STR_PAD_LEFT) . "-" . rand(100, 999);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // 1. Update user's plan and expiry date (KEEP join_date unchanged)
        $updateSql = "UPDATE users SET 
                        plan = ?, 
                        expiry_date = ?
                      WHERE id = ?";
        
        $updateStmt = mysqli_prepare($conn, $updateSql);
        mysqli_stmt_bind_param($updateStmt, "ssi", $newPlan, $newExpiryDate, $userId);
        
        if (!mysqli_stmt_execute($updateStmt)) {
            throw new Exception("Failed to update user membership: " . mysqli_error($conn));
        }
        mysqli_stmt_close($updateStmt);
        
        // 2. Insert payment record with renewal_starting_day
        $paymentSql = "INSERT INTO payments (
                            user_id, 
                            receipt_no, 
                            payment_date, 
                            plan, 
                            amount, 
                            payment_method, 
                            status, 
                            admin_notes,
                            renewal_starting_day
                        ) VALUES (?, ?, CURDATE(), ?, ?, ?, 'Paid', ?, ?)";
        
        $paymentStmt = mysqli_prepare($conn, $paymentSql);
        mysqli_stmt_bind_param($paymentStmt, "issdsss", 
            $userId, 
            $receiptNo, 
            $newPlan, 
            $amount, 
            $paymentMethod, 
            $adminNotes,
            $renewalStartingDay
        );
        
        if (!mysqli_stmt_execute($paymentStmt)) {
            throw new Exception("Failed to record payment: " . mysqli_error($conn));
        }
        $paymentId = mysqli_insert_id($conn);
        mysqli_stmt_close($paymentStmt);
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Return success response
        echo json_encode([
            "status" => "success",
            "message" => "Membership renewed successfully",
            "renewal_details" => [
                "user_id" => $userId,
                "user_name" => $userData['first_name'] . " " . $userData['last_name'],
                "join_date" => $joinDate,
                "old_plan" => $oldPlan,
                "new_plan" => $newPlan,
                "old_expiry_date" => $oldExpiry,
                "new_expiry_date" => $newExpiryDate,
                "renewal_starting_day" => $renewalStartingDay,
                "receipt_no" => $receiptNo,
                "payment_id" => $paymentId,
                "amount" => $amount,
                "payment_method" => $paymentMethod,
                "payment_date" => date('Y-m-d')
            ]
        ]);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    }
}

// ================= GET ALL PAYMENTS FUNCTION =================
function handleGetAllPayments($conn) {
    $query = "SELECT p.*, u.first_name, u.last_name, u.email, u.mobile 
              FROM payments p 
              JOIN users u ON p.user_id = u.id 
              ORDER BY p.payment_date DESC, p.created_at DESC";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to fetch payments: " . mysqli_error($conn)
        ]);
        return;
    }
    
    $payments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $payments[] = $row;
    }
    
    echo json_encode([
        "status" => "success",
        "total_payments" => count($payments),
        "payments" => $payments
    ]);
}

// ================= GET EXPIRING MEMBERS FUNCTION =================
function handleGetExpiringMembers($conn) {
    $days = $_GET['days'] ?? 7; // Default 7 days
    
    $query = "SELECT id, first_name, last_name, email, mobile, plan, expiry_date 
              FROM users 
              WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
              AND status = 'active'
              ORDER BY expiry_date ASC";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $days);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    echo json_encode([
        "status" => "success",
        "expiring_count" => count($users),
        "users" => $users,
        "days_range" => $days
    ]);
}

// ================= GET PAYMENT HISTORY FUNCTION =================
function handleGetPaymentHistory($conn) {
    $userId = $_GET['user_id'] ?? $_POST['user_id'] ?? 0;
    $email = $_GET['email'] ?? $_POST['email'] ?? '';
    
    if (!$userId && empty($email)) {
        echo json_encode([
            "status" => "error",
            "message" => "User ID or email is required"
        ]);
        return;
    }
    
    // Get user ID if email provided
    if (!$userId && !empty($email)) {
        $emailQuery = "SELECT id FROM users WHERE email = ?";
        $emailStmt = mysqli_prepare($conn, $emailQuery);
        mysqli_stmt_bind_param($emailStmt, "s", $email);
        mysqli_stmt_execute($emailStmt);
        $emailResult = mysqli_stmt_get_result($emailStmt);
        $user = mysqli_fetch_assoc($emailResult);
        
        if (!$user) {
            echo json_encode([
                "status" => "error",
                "message" => "User not found"
            ]);
            mysqli_stmt_close($emailStmt);
            return;
        }
        $userId = $user['id'];
        mysqli_stmt_close($emailStmt);
    }
    
    // Get user details
    $userQuery = "SELECT id, first_name, last_name, email, mobile, join_date, plan, expiry_date FROM users WHERE id = ?";
    $userStmt = mysqli_prepare($conn, $userQuery);
    mysqli_stmt_bind_param($userStmt, "i", $userId);
    mysqli_stmt_execute($userStmt);
    $userResult = mysqli_stmt_get_result($userStmt);
    $userDetails = mysqli_fetch_assoc($userResult);
    mysqli_stmt_close($userStmt);
    
    if (!$userDetails) {
        echo json_encode([
            "status" => "error",
            "message" => "User not found"
        ]);
        return;
    }
    
    // Get payment history with renewal_starting_day
    $paymentQuery = "SELECT * FROM payments WHERE user_id = ? ORDER BY payment_date DESC";
    $paymentStmt = mysqli_prepare($conn, $paymentQuery);
    mysqli_stmt_bind_param($paymentStmt, "i", $userId);
    mysqli_stmt_execute($paymentStmt);
    $paymentResult = mysqli_stmt_get_result($paymentStmt);
    $payments = [];
    while ($row = mysqli_fetch_assoc($paymentResult)) {
        $payments[] = $row;
    }
    mysqli_stmt_close($paymentStmt);
    
    echo json_encode([
        "status" => "success",
        "user" => $userDetails,
        "payment_history" => $payments,
        "total_payments" => count($payments),
        "total_amount_paid" => array_sum(array_column($payments, 'amount'))
    ]);
}

// ================= GET ALL USERS FUNCTION =================
function handleGetAllUsers($conn) {
    $sql = "SELECT 
              id,
              first_name,
              last_name,
              email,
              mobile,
              photo,
              join_date,
              plan,
              expiry_date,
              occupation,
              created_at
            FROM users
            ORDER BY id DESC";

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        echo json_encode([
            "status" => "error",
            "mysql_error" => mysqli_error($conn)
        ]);
        return;
    }

    $users = [];

    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['photo'])) {
            $row['photo'] = "https://gymmanagement-backend-tvxb.onrender.com/uploads" . $row['photo'];
        } else {
            $row['photo'] = null;
        }
        $users[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "total" => count($users),
        "users" => $users
    ]);
}

// ================= UPDATE USER FUNCTION =================
function handleUpdateUser($conn) {
    // Get data from JSON input
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid data received"
        ]);
        return;
    }
    
    $userId = $data['id'] ?? 0;
    $firstName = $data['first_name'] ?? '';
    $lastName = $data['last_name'] ?? '';
    $email = $data['email'] ?? '';
    $mobile = $data['mobile'] ?? '';
    $plan = $data['plan'] ?? '';
    $occupation = $data['occupation'] ?? '';
    $joinDate = $data['join_date'] ?? null;
    $expiryDate = $data['expiry_date'] ?? null;
    $password = $data['password'] ?? null; // Optional password update
    
    // Validate required fields
    if (!$userId || empty($firstName) || empty($lastName) || empty($email) || empty($mobile)) {
        echo json_encode([
            "status" => "error",
            "message" => "Required fields missing"
        ]);
        return;
    }
    
    // Check if user exists
    $checkSql = "SELECT id FROM users WHERE id = ?";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "i", $userId);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);
    
    if (mysqli_num_rows($checkRes) === 0) {
        echo json_encode([
            "status" => "error",
            "message" => "User not found"
        ]);
        mysqli_stmt_close($checkStmt);
        return;
    }
    mysqli_stmt_close($checkStmt);
    
    // Build update query dynamically based on whether password is provided
    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updateSql = "UPDATE users SET 
                        first_name = ?, 
                        last_name = ?, 
                        email = ?, 
                        mobile = ?, 
                        plan = ?, 
                        occupation = ?, 
                        join_date = ?, 
                        expiry_date = ?,
                        password = ?
                      WHERE id = ?";
        
        $updateStmt = mysqli_prepare($conn, $updateSql);
        mysqli_stmt_bind_param(
            $updateStmt,
            "sssssssssi",
            $firstName,
            $lastName,
            $email,
            $mobile,
            $plan,
            $occupation,
            $joinDate,
            $expiryDate,
            $hashedPassword,
            $userId
        );
    } else {
        $updateSql = "UPDATE users SET 
                        first_name = ?, 
                        last_name = ?, 
                        email = ?, 
                        mobile = ?, 
                        plan = ?, 
                        occupation = ?, 
                        join_date = ?, 
                        expiry_date = ?
                      WHERE id = ?";
        
        $updateStmt = mysqli_prepare($conn, $updateSql);
        mysqli_stmt_bind_param(
            $updateStmt,
            "ssssssssi",
            $firstName,
            $lastName,
            $email,
            $mobile,
            $plan,
            $occupation,
            $joinDate,
            $expiryDate,
            $userId
        );
    }
    
    if (mysqli_stmt_execute($updateStmt)) {
        $response = [
            "status" => "success",
            "message" => "User updated successfully"
        ];
        
        if (!empty($password)) {
            $response["password_updated"] = true;
        }
        
        echo json_encode($response);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to update user: " . mysqli_error($conn)
        ]);
    }
    mysqli_stmt_close($updateStmt);
}

// ================= DELETE USER FUNCTION =================
function handleDeleteUser($conn) {
    // Get data from JSON input
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid data received"
        ]);
        return;
    }
    
    $userId = $data['id'] ?? 0;
    
    if (!$userId) {
        echo json_encode([
            "status" => "error",
            "message" => "User ID is required"
        ]);
        return;
    }
    
    // Check if user exists
    $checkSql = "SELECT id, photo FROM users WHERE id = ?";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "i", $userId);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);
    $user = mysqli_fetch_assoc($checkRes);
    
    if (!$user) {
        echo json_encode([
            "status" => "error",
            "message" => "User not found"
        ]);
        mysqli_stmt_close($checkStmt);
        return;
    }
    mysqli_stmt_close($checkStmt);
    
    // Delete user photo if exists
    if (!empty($user['photo']) && file_exists("uploads/" . $user['photo'])) {
        unlink("uploads/" . $user['photo']);
    }
    
    // Delete user
    $deleteSql = "DELETE FROM users WHERE id = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteSql);
    mysqli_stmt_bind_param($deleteStmt, "i", $userId);
    
    if (mysqli_stmt_execute($deleteStmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "User deleted successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to delete user: " . mysqli_error($conn)
        ]);
    }
    mysqli_stmt_close($deleteStmt);
}

// ================= REGISTRATION FUNCTION =================
function handleRegistration($conn) {
    // Get data from either JSON or POST
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $photoName = "";
    
    if (strpos($contentType, 'application/json') !== false) {
        $rawInput = file_get_contents("php://input");
        $data = json_decode($rawInput, true);
        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';
        $mobile = $data['mobile'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $joinDate = $data['join_date'] ?? '';
        $plan = $data['plan'] ?? '';
        $expiryDate = $data['expiry_date'] ?? '';
        $occupation = $data['occupation'] ?? '';
    } else {
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $mobile = $_POST['mobile'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $joinDate = $_POST['join_date'] ?? '';
        $plan = $_POST['plan'] ?? '';
        $expiryDate = $_POST['expiry_date'] ?? '';
        $occupation = $_POST['occupation'] ?? '';
        
        // Handle photo upload for FormData
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            if (!file_exists("uploads")) {
                mkdir("uploads", 0777, true);
            }
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            if (in_array($_FILES['photo']['type'], $allowed_types)) {
                $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $photoName = time() . "_" . uniqid() . "." . $file_extension;
                $upload_path = "uploads/" . $photoName;
                move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path);
            }
        }
    }

    // Validation
    $missing_fields = [];
    if (empty($firstName)) $missing_fields[] = "first_name";
    if (empty($lastName)) $missing_fields[] = "last_name";
    if (empty($mobile)) $missing_fields[] = "mobile";
    if (empty($email)) $missing_fields[] = "email";
    if (empty($password)) $missing_fields[] = "password";
    if (empty($joinDate)) $missing_fields[] = "join_date";
    if (empty($plan)) $missing_fields[] = "plan";

    if (!empty($missing_fields)) {
        echo json_encode([
            "status" => "error",
            "type" => "validation",
            "message" => "Required fields missing: " . implode(", ", $missing_fields)
        ]);
        return;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            "status" => "error",
            "type" => "validation",
            "message" => "Invalid email format"
        ]);
        return;
    }

    // Check if user exists
    $checkSql = "SELECT id FROM users WHERE email = ? OR mobile = ? LIMIT 1";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "ss", $email, $mobile);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);

    if (mysqli_num_rows($checkRes) > 0) {
        echo json_encode([
            "status" => "error",
            "type" => "exists",
            "message" => "User already exists with this email or mobile"
        ]);
        mysqli_stmt_close($checkStmt);
        return;
    }
    mysqli_stmt_close($checkStmt);

    // Insert user
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $insertSql = "INSERT INTO users 
        (first_name, last_name, mobile, email, password, photo, join_date, plan, expiry_date, occupation, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";

    $insertStmt = mysqli_prepare($conn, $insertSql);
    mysqli_stmt_bind_param(
        $insertStmt, 
        "ssssssssss", 
        $firstName, 
        $lastName, 
        $mobile, 
        $email, 
        $hashedPassword, 
        $photoName, 
        $joinDate, 
        $plan, 
        $expiryDate, 
        $occupation
    );

    if (mysqli_stmt_execute($insertStmt)) {
        $userId = mysqli_insert_id($conn);
        
        $fetchSql = "SELECT id, first_name, last_name, email, mobile, occupation, plan, join_date, expiry_date, photo FROM users WHERE id = ?";
        $fetchStmt = mysqli_prepare($conn, $fetchSql);
        mysqli_stmt_bind_param($fetchStmt, "i", $userId);
        mysqli_stmt_execute($fetchStmt);
        $fetchResult = mysqli_stmt_get_result($fetchStmt);
        $userData = mysqli_fetch_assoc($fetchResult);
        mysqli_stmt_close($fetchStmt);
        
        if (!empty($userData['photo'])) {
            $userData['photo'] = "https://gymmanagement-backend-tvxb.onrender.com/uploads/" . $userData['photo'];
        } else {
            $userData['photo'] = null;
        }
        
        echo json_encode([
            "status" => "success",
            "type" => "registration",
            "message" => "Registration successful! Please login.",
            "user" => $userData
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "type" => "insert",
            "message" => "Failed to register user: " . mysqli_error($conn)
        ]);
    }
    mysqli_stmt_close($insertStmt);
}

// ================= LOGIN FUNCTION =================
function handleLogin($conn) {
    // Get credentials from JSON or POST
    $email = '';
    $password = '';
    
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $rawInput = file_get_contents("php://input");
        $data = json_decode($rawInput, true);
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
    }
    
    if ($email === '' || $password === '') {
        echo json_encode([
            "status" => "error",
            "type" => "validation",
            "message" => "Email and password are required"
        ]);
        return;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            "status" => "error",
            "type" => "validation",
            "message" => "Invalid email format"
        ]);
        return;
    }
    
    // Get user from database
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$user) {
        echo json_encode([
            "status" => "error",
            "type" => "not_found",
            "message" => "No registration data found. Please register first."
        ]);
        return;
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode([
            "status" => "error",
            "type" => "invalid_password",
            "message" => "Invalid password. Please try again."
        ]);
        return;
    }
    
    // Check account status
    if (isset($user['status']) && $user['status'] !== 'active') {
        echo json_encode([
            "status" => "error",
            "type" => "inactive",
            "message" => "Your account is inactive. Please contact admin."
        ]);
        return;
    }
    
    // Check expiry
    if (!empty($user['expiry_date']) && $user['expiry_date'] < date('Y-m-d')) {
        echo json_encode([
            "status" => "error",
            "type" => "expired",
            "message" => "Your membership has expired on " . $user['expiry_date'] . ". Please renew."
        ]);
        return;
    }
    
    // Remove password from response
    unset($user['password']);
    
    // Set proper photo URL
    if (!empty($user['photo'])) {
        if (strpos($user['photo'], 'http') !== false) {
            $user['photo'] = $user['photo'];
        } else {
            $user['photo'] = "https://gymmanagement-backend-tvxb.onrender.com/uploads" . $user['photo'];
        }
    } else {
        $user['photo'] = null;
    }
    
    echo json_encode([
        "status" => "success",
        "type" => "login",
        "message" => "Login successful",
        "user" => $user
    ]);
}
?>