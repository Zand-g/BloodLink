<?php
// PUT (or POST) /api/drive_update.php
// Body: { "drive_id": 2, "title": "Updated title", "start_time": "08:30" }
// Omitted fields keep their current values. Coordinates must be updated together.
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validate.php';

require_method('PUT', 'POST');
$in = read_json_body();

$errors = [];
$id = f_int($in, 'drive_id', $errors, true, 1, 4294967295);
$editable = [
    'organizer_id', 'bank_id', 'city_id', 'title', 'venue',
    'drive_date', 'start_time', 'end_time', 'latitude', 'longitude',
];
$hasChanges = false;
foreach ($editable as $key) {
    if (array_key_exists($key, $in)) $hasChanges = true;
}
if (!$hasChanges) $errors[] = 'send at least one field to update';

$newOrganizer = array_key_exists('organizer_id', $in)
    ? f_int($in, 'organizer_id', $errors, true, 1, 4294967295) : null;
$newBank = array_key_exists('bank_id', $in)
    ? f_int($in, 'bank_id', $errors, true, 1, 4294967295) : null;
$newCity = array_key_exists('city_id', $in)
    ? f_int($in, 'city_id', $errors, true, 1, 65535) : null;
$newTitle = array_key_exists('title', $in) ? f_str($in, 'title', $errors, true, 150) : null;
$newVenue = array_key_exists('venue', $in) ? f_str($in, 'venue', $errors, true, 200) : null;
$newDate = array_key_exists('drive_date', $in) ? f_date($in, 'drive_date', $errors, true) : null;
$newStart = array_key_exists('start_time', $in) ? f_time($in, 'start_time', $errors, true) : null;
$newEnd = array_key_exists('end_time', $in) ? f_time($in, 'end_time', $errors, true) : null;
$hasLat = array_key_exists('latitude', $in);
$hasLng = array_key_exists('longitude', $in);
$newLat = $hasLat
    ? f_float($in, 'latitude', $errors, $in['latitude'] !== null, -90, 90)
    : null;
$newLng = $hasLng
    ? f_float($in, 'longitude', $errors, $in['longitude'] !== null, -180, 180)
    : null;
if ($hasLat !== $hasLng) $errors[] = 'send both latitude and longitude to change drive coordinates';
if ($errors) {
    json_out(['ok' => false, 'message' => implode('; ', $errors)], 422);
}

$db = db();
$stmt = $db->prepare(
    'SELECT organizer_id, bank_id, city_id, title, venue, drive_date, start_time, end_time,
            latitude, longitude, status
       FROM drives WHERE drive_id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$old = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$old) {
    json_out(['ok' => false, 'message' => 'Drive not found'], 404);
}
if (!in_array($old['status'], ['scheduled', 'ongoing'], true)) {
    json_out(['ok' => false, 'message' => 'Only scheduled or ongoing drives can be updated'], 422);
}

$organizerId = $newOrganizer ?? (int) $old['organizer_id'];
$bankId = $newBank ?? (int) $old['bank_id'];
$cityId = $newCity ?? (int) $old['city_id'];
if (!row_exists($db, 'SELECT 1 FROM organizers WHERE organizer_id = ?', 'i', $organizerId)) {
    json_out(['ok' => false, 'message' => 'Organizer not found'], 422);
}
if (!row_exists($db, 'SELECT 1 FROM blood_banks WHERE bank_id = ?', 'i', $bankId)) {
    json_out(['ok' => false, 'message' => 'Blood bank not found'], 422);
}
if (!row_exists($db, 'SELECT 1 FROM cities WHERE city_id = ?', 'i', $cityId)) {
    json_out(['ok' => false, 'message' => 'City not found'], 422);
}
$title = $newTitle ?? $old['title'];
$venue = $newVenue ?? $old['venue'];
$driveDate = $newDate ?? $old['drive_date'];
$startTime = $newStart ?? $old['start_time'];
$endTime = $newEnd ?? $old['end_time'];
$lat = $hasLat ? $newLat : ($old['latitude'] !== null ? (float) $old['latitude'] : null);
$lng = $hasLng ? $newLng : ($old['longitude'] !== null ? (float) $old['longitude'] : null);
if ($startTime >= $endTime) {
    json_out(['ok' => false, 'message' => 'end_time must be after start_time'], 422);
}
if (($lat === null) !== ($lng === null)) {
    json_out(['ok' => false, 'message' => 'latitude and longitude must both be numbers or both be null'], 422);
}

try {
    $stmt = $db->prepare(
        'UPDATE drives
            SET organizer_id = ?, bank_id = ?, city_id = ?, title = ?, venue = ?, drive_date = ?,
                start_time = ?, end_time = ?, latitude = ?, longitude = ?
          WHERE drive_id = ?'
    );
    $stmt->bind_param(
        'iiisssssddi',
        $organizerId, $bankId, $cityId, $title, $venue, $driveDate,
        $startTime, $endTime, $lat, $lng, $id
    );
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1452) {
        json_out(['ok' => false, 'message' => 'Unknown organizer_id, bank_id or city_id'], 422);
    }
    if ($e->getCode() === 3819) {
        json_out(['ok' => false, 'message' => 'Drive coordinates or times are outside the allowed range'], 422);
    }
    throw $e;
}

json_out(['ok' => true, 'drive_id' => $id]);
