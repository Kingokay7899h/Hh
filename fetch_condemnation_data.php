<?php
session_start();
$conn = new mysqli("localhost", "root", "", "asset_management");
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

if (!isset($_SESSION['lab_id'])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["error" => "Invalid request method"]);
    exit;
}

$lab_id = $_SESSION['lab_id'];
$selected_items = json_decode(file_get_contents('php://input'), true);

if (!is_array($selected_items) || empty($selected_items)) {
    echo json_encode(["error" => "No items provided"]);
    exit;
}

// Prepare SQL to fetch data for selected items
$sr_nos = array_column($selected_items, 'sr_no');
$placeholders = implode(',', array_fill(0, count($sr_nos), '?'));
$sql = "SELECT sr_no, name_of_the_item, date, price FROM register WHERE lab_id = ? AND sr_no IN ($placeholders)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["error" => "Query preparation failed"]);
    exit;
}

// Bind parameters (lab_id first, then sr_nos)
$types = str_repeat('s', count($sr_nos) + 1); // 's' for lab_id and each sr_no
$params = array_merge([$lab_id], $sr_nos);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$stmt->close();
$conn->close();
?>
