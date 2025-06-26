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

$lab_id = $_SESSION['lab_id'];
$sql = "SELECT sr_no, lab_id, name_of_the_item, date, last_maintenance, maintenance_due, service_provider FROM register WHERE lab_id = ? AND maintenance_due < CURDATE()";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["error" => "Query preparation failed"]);
    exit;
}

$stmt->bind_param("s", $lab_id);
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

