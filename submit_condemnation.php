<?php
session_start();

// Prevent HTML error output from breaking JSON
ob_start(); // Start output buffering to capture stray output

$conn = new mysqli("localhost", "root", "", "asset_management");
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

if (!isset($_SESSION['lab_id'])) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$raw_input = file_get_contents('php://input');
error_log("Raw input data: " . $raw_input);
$data = json_decode($raw_input, true);
$lab_id = $data['lab_id'] ?? null;
$items = $data['items'] ?? [];

if (!is_array($items) || empty($items) || !$lab_id) {
    error_log("Validation failed: Invalid items or lab_id. Items: " . print_r($items, true) . ", Lab ID: " . $lab_id);
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "No items provided or invalid lab ID"]);
    exit;
}

$conn->begin_transaction();

try {
    foreach ($items as $index => $item) {
        if (!empty($item['sr_no'])) {
            // Log item data for debugging
            error_log("Processing item $index: " . print_r($item, true));
            error_log("Raw condemnation_date for item $index: " . ($item['condemnation_date'] ?? 'NULL'));

            // Update register table
            $sql = "UPDATE register SET reason_for_disposal = ?, disposal_status = ? WHERE sr_no = ? AND lab_id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Query preparation failed for register update: " . $conn->error);
            }
            $disposal_status = 'Pending Stores'; // Updated to Pending Stores
            $reason = $item['reason_for_condemnation'] ?? null;
            $stmt->bind_param("ssss", $reason, $disposal_status, $item['sr_no'], $lab_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update register: " . $stmt->error);
            }
            $stmt->close();

            // Insert into condemnation_records table
            $sql = "INSERT INTO condemnation_records (
                sr_no, lab_id, name_of_the_item, quantity, price, purchase_date, condemnation_date,
                period_of_use, `condition`, effort_for_repair_and_cost, location_of_items, reason_for_condemnation, remarks
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Query preparation failed for condemnation_records insert: " . $conn->error);
            }

            // Validate and format data
            $quantity = !empty($item['quantity']) ? intval($item['quantity']) : 1;
$price = !empty($item['price']) ? floatval($item['price']) : null;
$effort_for_repair_and_cost = $item['effort_for_repair_and_cost'] ?? null; // Keep as string
$location_of_items = $item['location_of_items'] ?? null; // Keep as string
$purchase_date = !empty($item['purchase_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $item['purchase_date'])
    ? $item['purchase_date'] : null;
            // Handle condemnation_date as DATE (YYYY-MM-DD)
            $condemnation_date = null;
            if (!empty($item['condemnation_date'])) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $item['condemnation_date'])) {
                    $d = DateTime::createFromFormat('Y-m-d', $item['condemnation_date']);
                    if ($d && $d->format('Y-m-d') === $item['condemnation_date']) {
                        $condemnation_date = $item['condemnation_date'];
                    } else {
                        error_log("Invalid condemnation_date format for item $index: '{$item['condemnation_date']}'. Defaulting to today.");
                        $condemnation_date = date('Y-m-d');
                    }
                } else {
                    error_log("Invalid condemnation_date format for item $index: '{$item['condemnation_date']}'. Defaulting to today.");
                    $condemnation_date = date('Y-m-d');
                }
            } else {
                error_log("Condemnation_date is empty for item $index. Defaulting to today.");
                $condemnation_date = date('Y-m-d');
            }

            // Log validated data
            error_log("Item $index - Validated Data: " . print_r([
                'sr_no' => $item['sr_no'],
                'name_of_the_item' => $item['name_of_the_item'],
                'quantity' => $quantity,
                'price' => $price,
                'purchase_date' => $purchase_date,
                'condemnation_date' => $condemnation_date,
                'period_of_use' => $item['period_of_use'],
                'condition' => $item['condition'],
                'effort_for_repair_and_cost' => $effort_for_repair_and_cost,
                'location_of_items' => $location_of_items,
                'reason_for_condemnation' => $item['reason_for_condemnation'],
                'remarks' => $item['remarks']
            ], true));

            // Update bind_param to treat condemnation_date as string
            $stmt->bind_param(
                "sssidssdsssss",
                $item['sr_no'],
                $lab_id,
                $item['name_of_the_item'],
                $quantity,
                $price,
                $purchase_date,
                $condemnation_date, // Now bound as string
                $item['period_of_use'],
                $item['condition'],
                $effort_for_repair_and_cost,
                $location_of_items,
                $item['reason_for_condemnation'],
                $item['remarks']
            );
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert into condemnation_records: " . $stmt->error);
            }
            $stmt->close();
        }
    }

    $conn->commit();
    ob_end_clean();
    echo json_encode(["status" => "success", "message" => "Condemnation form submitted successfully"]);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error in submit_condemnation.php: " . $e->getMessage());
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();

