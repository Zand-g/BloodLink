<?php
// POST (or DELETE) /api/request_cancel.php
// Body: { "request_id": 1 }
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validate.php';

require_method('POST', 'DELETE');
$in = read_json_body();
$errors = [];
$id = f_int($in, 'request_id', $errors, true, 1, 4294967295);
if ($errors) {
    json_out(['ok' => false, 'message' => implode('; ', $errors)], 422);
}

$db = db();
$stmt = $db->prepare('SELECT status FROM blood_requests WHERE request_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    json_out(['ok' => false, 'message' => 'Blood request not found'], 404);
}
if ($row['status'] === 'cancelled') {
    json_out(['ok' => true, 'request_id' => $id, 'status' => 'cancelled', 'message' => 'Already cancelled']);
}

$stmt = $db->prepare("UPDATE blood_requests SET status = 'cancelled' WHERE request_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

json_out(['ok' => true, 'request_id' => $id, 'status' => 'cancelled', 'message' => 'Cancelled']);
