<?php
// POST /api/registration_create.php
// Body: { "drive_id": 4, "donor_id": 1, "slot_time": "07:30" }     (slot_time optional)
//
// Registers a donor for a drive.
//  - The drive must be scheduled/ongoing and not already over.
//  - slot_time, if sent, must fall inside the drive's hours.
//  - Already registered -> 409 "already registered".
//  - A donor who CANCELLED earlier is re-activated (UNIQUE (drive, donor) allows only one row).
// The drives table has no capacity column, so "drive is full" cannot be checked yet.
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validate.php';

require_method('POST');
$in = read_json_body();

$errors = [];
$driveId = f_int($in, 'drive_id', $errors, true, 1);
$donorId = f_int($in, 'donor_id', $errors, true, 1);
$slot    = f_time($in, 'slot_time', $errors);
if ($errors) {
    json_out(['ok' => false, 'message' => implode('; ', $errors)], 422);
}

$db = db();

// ---------- The drive ----------
$stmt = $db->prepare('SELECT drive_date, start_time, end_time, status FROM drives WHERE drive_id = ?');
$stmt->bind_param('i', $driveId);
$stmt->execute();
$drive = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$drive) {
    json_out(['ok' => false, 'message' => 'Drive not found'], 404);
}
if (!in_array($drive['status'], ['scheduled', 'ongoing'], true)) {
    json_out(['ok' => false, 'message' => 'This drive is not open for registration'], 422);
}
$today = date('Y-m-d');          // Manila time (config.php)
if ($drive['drive_date'] < $today
    || ($drive['drive_date'] === $today && $drive['end_time'] <= date('H:i:s'))) {
    json_out(['ok' => false, 'message' => 'This drive has already taken place'], 422);
}
if ($slot !== null && ($slot < $drive['start_time'] || $slot >= $drive['end_time'])) {
    json_out([
        'ok' => false,
        'message' => 'slot_time must be between ' . substr($drive['start_time'], 0, 5)
                   . ' and ' . substr($drive['end_time'], 0, 5),
    ], 422);
}

// ---------- The donor ----------
if (!row_exists($db, 'SELECT 1 FROM donors WHERE donor_id = ?', 'i', $donorId)) {
    json_out(['ok' => false, 'message' => 'Donor not found'], 404);
}

// ---------- Existing registration? ----------
$stmt = $db->prepare('SELECT registration_id, status FROM drive_registrations WHERE drive_id = ? AND donor_id = ?');
$stmt->bind_param('ii', $driveId, $donorId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing && $existing['status'] !== 'cancelled') {
    json_out(['ok' => false, 'message' => 'Donor is already registered for this drive',
              'registration_id' => (int) $existing['registration_id']], 409);
}

if ($existing) {   // cancelled before: bring it back
    $regId = (int) $existing['registration_id'];
    $stmt = $db->prepare(
        "UPDATE drive_registrations SET status = 'registered', slot_time = ?, registered_at = NOW()
          WHERE registration_id = ? AND status = 'cancelled'"
    );
    $stmt->bind_param('si', $slot, $regId);
    $stmt->execute();
    $stmt->close();
    json_out(['ok' => true, 'registration_id' => $regId, 'status' => 'registered',
              'message' => 'Registration re-activated']);
}

try {
    $stmt = $db->prepare('INSERT INTO drive_registrations (drive_id, donor_id, slot_time) VALUES (?, ?, ?)');
    $stmt->bind_param('iis', $driveId, $donorId, $slot);
    $stmt->execute();
    $regId = $db->insert_id;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {   // two requests raced: the other one won
        json_out(['ok' => false, 'message' => 'Donor is already registered for this drive'], 409);
    }
    throw $e;
}

json_out(['ok' => true, 'registration_id' => $regId, 'status' => 'registered',
          'message' => 'Registered'], 201);
