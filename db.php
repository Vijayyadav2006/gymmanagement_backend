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

// ================= GOOGLE SHEET SYNC FUNCTION =================

function syncToGoogleSheet($sheetName, $rowData) {

    $url = "https://script.google.com/macros/s/AKfycbyPhaJU7HYJrAAWmVqxaFpYe3I7ipHVXd-vVUGKIb6f-57PcLMz4d20zJ6zfEl3aOgl/exec";

    $data = [
        "sheet" => $sheetName,
        "data" => $rowData
    ];

    $options = [
        "http" => [
            "header"  => "Content-Type: application/json\r\n",
            "method"  => "POST",
            "content" => json_encode($data)
        ]
    ];

    $context = stream_context_create($options);

    return file_get_contents($url, false, $context);
}

?>