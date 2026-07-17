<?php
session_start();
require_once "../db_connect.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}



$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$results = [];

/* -----------------------------
   Search Shops
----------------------------- */

$stmt = $conn->prepare("
SELECT
id,
shop_name,
phone,
address,
stall_type

FROM shops

WHERE
shop_name LIKE CONCAT('%', ?, '%')

LIMIT 5
");

$stmt->bind_param("s",$q);

$stmt->execute();

$res=$stmt->get_result();

while($row=$res->fetch_assoc()){

$results[] = [
    "type" => "shop",
    "icon" => "🏪",
    "title" => $row["shop_name"],
    "subtitle" => $row["address"],
    "shop_id" => $row["id"]
];

}

/* -----------------------------
   Search Inspectors (Admin Only)
----------------------------- */

if ($_SESSION['role'] == 'admin') {

    $stmt = $conn->prepare("
    SELECT username
    FROM users
    WHERE username LIKE CONCAT('%', ?, '%')
    LIMIT 5
    ");

    $stmt->bind_param("s", $q);
    $stmt->execute();

    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {

        $results[] = [
            "icon" => "👤",
            "title" => $row['username'],
            "subtitle" => "Inspector",
            "url" => "manage_inspectors.php"
        ];
    }
}

/* -----------------------------
   Static Pages
----------------------------- */

$pages = [

["Dashboard","dashboard.php","🏠"],

["Spot Tax","spot_tax.php","🧾"],

["History","history.php","📜"],

["Settings","settings.php","⚙️"]

];

foreach ($pages as $page) {

    if (stripos($page[0], $q) !== false) {

        $results[] = [

            "icon" => $page[2],

            "title" => $page[0],

            "subtitle" => "Page",

            "url" => $page[1]

        ];

    }

}

header('Content-Type: application/json');
echo json_encode($results);
exit;