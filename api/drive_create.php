<?php
// POST /api/drive_create.php
// Required: organizer_id, bank_id, city_id, title, venue, drive_date, start_time, end_time.
// Optional coordinates must be sent together.
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validate.php';

require_method('POST');
$in = read_json_body();

$errors = [];
$organizerId = f_int($in, 'organizer_id', $errors, true, 1, 4294967295);
$bankId = f_int($in, 'bank_id', $errors, true, 1, 4294967295);
$cityId = f_int($in, 'city_id', $errors, true, 1, 65535);
$title = f_str($in, 'title', $errors, true, 150);
$venue = f_str($in, 'venue', $errors, true, 200);
$driveDate = f_date($in, 'drive_date', $errors, true);
$startTime = f_time($in, 'start_time', $errors, true);
$endTime = f_time($in, 'end_time', $errors, true);
$hasLat = array_key_exists('latitude', $in);
$hasLng = array_key_exists('longitude', $in);
$lat = f_float($in, 'latitude', $errors, $hasLat && $in['latitude'] !== null, -90, 90);
$lng = f_float($in, 'longitude', $errors, $hasLng && $in['longitude'] !== null, -180, 180);
if ($hasLat !== $hasLng) $errors[] = 'send both latitude and longitude, or neither';
if (!$errors && $startTime >= $endTime) $errors[] = 'end_time must be after start_time';
if ($errors) {
    json_out(['ok' => false, 'message' => implode('; ', $errors)], 422);
}
if (($lat === null) !== ($lng === null)) {
    json_out(['ok' => false, 'message' => 'latitude and longitude must both be numbers or both be null'], 422);
}

$db = db();
if (!row_exists($db, 'SELECT 1 FROM organizers WHERE organizer_id = ?', 'i', $organizerId)) {
    json_out(['ok' => false, 'message' => 'Organizer not found'], 422);
}
if (!row_exists($db, 'SELECT 1 FROM blood_banks WHERE bank_id = ?', 'i', $bankId)) {
    json_out(['ok' => false, 'message' => 'Blood bank not found'], 422);
}
if (!row_exists($db, 'SELECT 1 FROM cities WHERE city_id = ?', 'i', $cityId)) {
    json_out(['ok' => false, 'message' => 'City not found'], 422);
}
try {
    $stmt = $db->prepare(
        'INSERT INTO drives (organizer_id, bank_id, city_id, title, venue, drive_date,
                             start_time, end_time, latitude, longitude)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'iiisssssdd',
        $organizerId, $bankId, $cityId, $title, $venue, $driveDate,
        $startTime, $endTime, $lat, $lng
    );
    $stmt->execute();
    $driveId = $db->insert_id;
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

json_out(['ok' => true, 'drive_id' => $driveId, 'status' => 'scheduled'], 201);
