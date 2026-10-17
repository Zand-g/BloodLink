<?php
// PUT (or POST) /api/request_update.php
// Body: { "request_id": 1, "units_needed": 5, "urgency": "urgent" }
// Omitted editable fields keep their current values. Status is managed by create,
// donation fulfillment, and request_cancel.php.
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validate.php';
require_once __DIR__ . '/lib/bundle.php';

require_method('PUT', 'POST');
$in = read_json_body();

$errors = [];
$id = f_int($in, 'request_id', $errors, true, 1, 4294967295);
$editable = ['bank_id', 'blood_type_id', 'units_needed', 'urgency', 'needed_by'];
$hasChanges = false;
foreach ($editable as $key) {
    if (array_key_exists($key, $in)) $hasChanges = true;
}
if (!$hasChanges) $errors[] = 'send at least one field to update';

$newBank = array_key_exists('bank_id', $in) ? f_int($in, 'bank_id', $errors, true, 1, 4294967295) : null;
$newType = array_key_exists('blood_type_id', $in) ? f_int($in, 'blood_type_id', $errors, true, 1, 255) : null;
$newUnits = array_key_exists('units_needed', $in) ? f_int($in, 'units_needed', $errors, true, 1, 65535) : null;
$newUrgency = array_key_exists('urgency', $in)
    ? f_enum($in, 'urgency', ['routine', 'urgent', 'emergency'], $errors, true)
    : null;
$newNeededBy = array_key_exists('needed_by', $in) ? f_date($in, 'needed_by', $errors, true) : null;
if ($errors) {
    json_out(['ok' => false, 'message' => implode('; ', $errors)], 422);
}

$db = db();
$stmt = $db->prepare(
    'SELECT bank_id, blood_type_id, units_needed, urgency, needed_by, status
       FROM blood_requests WHERE request_id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$old = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$old) {
    json_out(['ok' => false, 'message' => 'Blood request not found'], 404);
}
if ($old['status'] === 'cancelled') {
    json_out(['ok' => false, 'message' => 'A cancelled blood request cannot be updated'], 422);
}

$bankId = $newBank ?? (int) $old['bank_id'];
$typeId = $newType ?? (int) $old['blood_type_id'];
if (!row_exists($db, 'SELECT 1 FROM blood_banks WHERE bank_id = ?', 'i', $bankId)) {
    json_out(['ok' => false, 'message' => 'Blood bank not found'], 422);
}
if (!row_exists($db, 'SELECT 1 FROM blood_types WHERE blood_type_id = ?', 'i', $typeId)) {
    json_out(['ok' => false, 'message' => 'Blood type not found'], 422);
}
$units = $newUnits ?? (int) $old['units_needed'];
$urgency = $newUrgency ?? $old['urgency'];
$neededBy = $newNeededBy ?? $old['needed_by'];

try {
    $db->begin_transaction();
    $stmt = $db->prepare(
        'UPDATE blood_requests
            SET bank_id = ?, blood_type_id = ?, units_needed = ?, urgency = ?, needed_by = ?
          WHERE request_id = ?'
    );
    $stmt->bind_param('iiissi', $bankId, $typeId, $units, $urgency, $neededBy, $id);
    $stmt->execute();
    $stmt->close();
    refresh_request_status($db, $id);
    $db->commit();
} catch (mysqli_sql_exception $e) {
    $db->rollback();
    if ($e->getCode() === 1452) {
        json_out(['ok' => false, 'message' => 'Unknown bank_id or blood_type_id'], 422);
    }
    throw $e;
}

json_out(['ok' => true, 'request_id' => $id]);
