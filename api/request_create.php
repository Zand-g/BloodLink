<?php
// POST /api/request_create.php
// Body: { "bank_id": 1, "blood_type_id": 8, "units_needed": 4,
//         "urgency": "emergency", "needed_by": "2026-10-20" }
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validate.php';

require_method('POST');
$in = read_json_body();

$errors = [];
$bankId = f_int($in, 'bank_id', $errors, true, 1, 4294967295);
$typeId = f_int($in, 'blood_type_id', $errors, true, 1, 255);
$units = f_int($in, 'units_needed', $errors, true, 1, 65535);
$urgency = f_enum($in, 'urgency', ['routine', 'urgent', 'emergency'], $errors, false, 'routine');
$neededBy = f_date($in, 'needed_by', $errors, true);
if ($errors) {
    json_out(['ok' => false, 'message' => implode('; ', $errors)], 422);
}

$db = db();
if (!row_exists($db, 'SELECT 1 FROM blood_banks WHERE bank_id = ?', 'i', $bankId)) {
    json_out(['ok' => false, 'message' => 'Blood bank not found'], 422);
}
if (!row_exists($db, 'SELECT 1 FROM blood_types WHERE blood_type_id = ?', 'i', $typeId)) {
    json_out(['ok' => false, 'message' => 'Blood type not found'], 422);
}
try {
    $stmt = $db->prepare(
        "INSERT INTO blood_requests (bank_id, blood_type_id, units_needed, urgency, needed_by)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('iiiss', $bankId, $typeId, $units, $urgency, $neededBy);
    $stmt->execute();
    $requestId = $db->insert_id;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1452) {
        json_out(['ok' => false, 'message' => 'Unknown bank_id or blood_type_id'], 422);
    }
    throw $e;
}

json_out(['ok' => true, 'request_id' => $requestId, 'status' => 'open'], 201);
