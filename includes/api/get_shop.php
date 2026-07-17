<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

require_once "../db_connect.php";

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("
SELECT
    id,
    shop_name,
    address,
    phone,
    stall_type
FROM shops
WHERE id = ?
LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

header("Content-Type: application/json");

if ($result->num_rows == 0) {
    echo json_encode([
        "success" => false,
        "message" => "Shop not found"
    ]);
    exit;
}

echo json_encode($result->fetch_assoc());