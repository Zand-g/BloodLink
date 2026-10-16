<?php
// PUT (or POST) /api/donation_update.php
// Body: { "donation_id": 7, "volume_ml": 450, "status": "cleared", "remarks": "...", "request_id": null }
// Only these fields can be edited. Who donated, where and when is history and stays fixed.
//
// volume_ml and status are required. "remarks" and "request_id" are KEPT AS THEY ARE when
// the key is left out of the body (so a form that never sends remarks cannot wipe the
// [REVIEW] marker). Send the key with null or "" to clear it on purpose.
//
// After saving, the status of the old and the new blood request is recalculated.
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/bundle.php';   // also loads db.php, validate.php, refresh_request_status()

require_method('PUT', 'POST');
$in = read_json_body();

$errors = [];
$id     = f_int($in, 'donation_id', $errors, true, 1);
$volume = f_int($in, 'volume_ml', $errors, true, 100, 600);
$status = f_enum($in, 'status', ['pending_test', 'cleared', 'discarded'], $errors, true);
$newRemarks   = f_str($in, 'remarks', $errors, false, 255);
$newRequestId = f_int($in, 'request_id', $errors, false, 1);
if ($errors) {
    json_out(['ok' => false, 'message' => implode('; ', $errors)], 422);
}

$db = db();
$stmt = $db->prepare('SELECT request_id, remarks FROM donations WHERE donation_id = ? AND deleted_at IS NULL');
$stmt->bind_param('i', $id);
$stmt->execute();
$old = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$old) {
    json_out(['ok' => false, 'message' => 'Donation not found'], 404);
}
$oldRequestId = $old['request_id'] !== null ? (int) $old['request_id'] : null;

// Key left out = keep the stored value.
$remarks   = array_key_exists('remarks', $in) ? $newRemarks : $old['remarks'];
$requestId = array_key_exists('request_id', $in) ? $newRequestId : $oldRequestId;

try {
    $db->begin_transaction();

    $stmt = $db->prepare(
        'UPDATE donations SET volume_ml = ?, status = ?, remarks = ?, request_id = ?
          WHERE donation_id = ? AND deleted_at IS NULL'
    );
    $stmt->bind_param('issii', $volume, $status, $remarks, $requestId, $id);
    $stmt->execute();
    $stmt->close();

    if ($requestId !== null) {
        refresh_request_status($db, $requestId);
    }
    if ($oldRequestId !== null && $oldRequestId !== $requestId) {
        refresh_request_status($db, $oldRequestId);
    }

    $db->commit();
} catch (mysqli_sql_exception $e) {
    $db->rollback();
    if ($e->getCode() === 1452) {
        json_out(['ok' => false, 'message' => 'Unknown request_id'], 422);
    }
    throw $e;
}

json_out(['ok' => true, 'donation_id' => $id]);
