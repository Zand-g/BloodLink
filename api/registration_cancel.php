<?php
// POST (or DELETE) /api/registration_cancel.php
// Body: { "registration_id": 9 }   or   { "drive_id": 4, "donor_id": 1 }
//
// Sets the registration's status to 'cancelled' (the row stays, so history is kept and
// the donor can register again later). Only a 'registered' row can be cancelled; one that
// is already 'attended' or 'no_show' is history. Cancelling twice is harmless (still ok).
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validate.php';

require_method('POST', 'DELETE');
$in = read_json_body();

$errors = [];
$regId   = f_int($in, 'registration_id', $errors, false, 1);
$driveId = f_int($in, 'drive_id', $errors, false, 1);
$donorId = f_int($in, 'donor_id', $errors, false, 1);
if (!$errors && $regId === null && ($driveId === null || $donorId === null)) {
    $errors[] = 'send registration_id, or both drive_id and donor_id';
}
if ($errors) {
    json_out(['ok' => false, 'message' => implode('; ', $errors)], 422);
}

$db = db();
if ($regId !== null) {
    $stmt = $db->prepare('SELECT registration_id, status FROM drive_registrations WHERE registration_id = ?');
    $stmt->bind_param('i', $regId);
} else {
    $stmt = $db->prepare('SELECT registration_id, status FROM drive_registrations WHERE drive_id = ? AND donor_id = ?');
    $stmt->bind_param('ii', $driveId, $donorId);
}
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    json_out(['ok' => false, 'message' => 'Registration not found'], 404);
}
$regId = (int) $row['registration_id'];

if ($row['status'] === 'cancelled') {
    json_out(['ok' => true, 'registration_id' => $regId, 'status' => 'cancelled', 'message' => 'Already cancelled']);
}
if ($row['status'] !== 'registered') {
    json_out(['ok' => false, 'message' => 'Only a registration that is still "registered" can be cancelled'], 422);
}

$stmt = $db->prepare("UPDATE drive_registrations SET status = 'cancelled' WHERE registration_id = ? AND status = 'registered'");
$stmt->bind_param('i', $regId);
$stmt->execute();
$stmt->close();

json_out(['ok' => true, 'registration_id' => $regId, 'status' => 'cancelled', 'message' => 'Cancelled']);
