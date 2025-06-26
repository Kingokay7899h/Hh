<?php
session_start();
$conn = new mysqli("localhost", "root", "", "asset_management");
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

// Validate session
if (!isset($_SESSION['lab_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$lab_id = $_SESSION['lab_id'];
$sr_no = $_POST['sr_no'] ?? '';
$last_maintenance = $_POST['last_maintenance'] ?? '';
$maintenance_due = $_POST['maintenance_due'] ?? '';
$service_provider = $_POST['service_provider'] ?? '';

// Validate inputs
if (empty($sr_no) || empty($last_maintenance) || empty($maintenance_due)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// Validate date formats
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $last_maintenance) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $maintenance_due)) {
    echo json_encode(["status" => "error", "message" => "Invalid date format"]);
    exit;
}

// Verify due date is 10 years from last maintenance
$last_date = new DateTime($last_maintenance);
$expected_due_date = $last_date->modify('+10 years')->format('Y-m-d');
if ($maintenance_due !== $expected_due_date) {
    echo json_encode(["status" => "error", "message" => "Maintenance due date must be 10 years from last maintenance"]);
    exit;
}

// Prepare and execute update query
$sql = "UPDATE register SET last_maintenance = ?, maintenance_due = ?, service_provider = ? WHERE sr_no = ? AND lab_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(["status" => "error", "message" => "Query preparation failed"]);
    exit;
}

$stmt->bind_param("sssss", $last_maintenance, $maintenance_due, $service_provider, $sr_no, $lab_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    echo json_encode(["status" => "error", "message" => "Update failed: " . $stmt->error]);
    exit;
}

if ($stmt->affected_rows === 0) {
    echo json_encode(["status" => "error", "message" => "No record updated. Check sr_no and lab_id."]);
    exit;
}

echo json_encode(["status" => "success", "message" => "Maintenance updated successfully"]);
$stmt->close();
$conn->close();
?>


