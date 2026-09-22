<?php
session_start();

require_once "../db_connect.php";

if(($_SESSION['role'] ?? '') !== 'admin'){
    http_response_code(403);
    exit;
}

$sql = "
SELECT
u.username,
t.shop_name,
t.total_amount,
t.created_at
FROM transactions t
JOIN users u ON t.inspector_id=u.user_id
WHERE t.status='paid'
ORDER BY t.created_at DESC
LIMIT 5
";

$result = $conn->query($sql);

$data = [];

while($row = $result->fetch_assoc()){
    $data[] = $row;
}

header("Content-Type: application/json");
echo json_encode($data);
