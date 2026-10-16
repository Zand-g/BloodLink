<?php
// ---------------------------------------------------------------------
// alert_send.php   POST
// Creates ONE alert (for a drive OR a blood request) and queues it for every
// matching donor in alert_recipients (notified_at stays NULL = "not delivered
// yet"; the read API / service worker marks it when the donor is notified).
//
// Body: { "drive_id": 2 }  or  { "request_id": 1 }   (exactly one)
//       "title": "...", "message": "...",
//       optional: "radius_km" (default 10), "blood_type_id"
//       (a request alert defaults to the request's own blood type)
//
// Matching:
//   - donor has coordinates  -> Haversine distance <= radius_km from the origin
//   - donor has NO coordinates -> same city as the origin
//   - blood_type_id given    -> donor must have that blood type
// Origin = the drive's coordinates, or the blood bank's coordinates for a
// request (only if blood_banks has latitude/longitude). With no origin
// coordinates, every donor in the same province matches (blood type still applies).
// ---------------------------------------------------------------------
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validate.php';

// Great-circle distance in km between (?, ?) and the donor's saved position.
// Placeholder order: lat, lat, lng.  Written by us, not taken from user input.
const HAVERSINE_KM = '(6371 * 2 * ASIN(LEAST(1, SQRT(
        POW(SIN(RADIANS(d.latitude - ?) / 2), 2)
      + COS(RADIANS(?)) * COS(RADIANS(d.latitude))
        * POW(SIN(RADIANS(d.longitude - ?) / 2), 2)))))';

function fetch_one(mysqli $db, string $sql, string $types, ...$params): ?array
{
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// The suggested ALTER may or may not have been run yet, so check first.
function bank_has_coords(mysqli $db): bool
{
    $r = $db->query("SHOW COLUMNS FROM blood_banks LIKE 'latitude'");   // fixed text, no user input
    $has = $r->num_rows > 0;
    $r->free();
    return $has;
}

require_method('POST');
$body = read_json_body();
$db = db();

// ---------- 1. Validate ----------
$errors = [];
$driveId   = f_int($body, 'drive_id', $errors, false, 1);
$requestId = f_int($body, 'request_id', $errors, false, 1);
$typeId    = f_int($body, 'blood_type_id', $errors, false, 1);
$radius    = f_float($body, 'radius_km', $errors, false, 0.1, 500);
$title     = f_str($body, 'title', $errors, true, 120);
$message   = f_str($body, 'message', $errors, true, 500);
if (($driveId === null) === ($requestId === null)) {
    $errors[] = 'send exactly one of drive_id or request_id';
}
if ($errors) {
    json_out(['ok' => false, 'message' => implode('; ', $errors)], 422);
}
$radius = round($radius ?? 10.0, 1);   // the column is DECIMAL(5,1); use the same number we store

// ---------- 2. Find the origin (where the alert is centred) ----------
$lat = $lng = null;
if ($driveId !== null) {
    $row = fetch_one($db, 'SELECT city_id, latitude, longitude, status FROM drives WHERE drive_id = ?', 'i', $driveId);
    if (!$row) {
        json_out(['ok' => false, 'message' => 'Drive not found'], 404);
    }
    if (!in_array($row['status'], ['scheduled', 'ongoing'], true)) {
        json_out(['ok' => false, 'message' => 'Alerts can only be sent for scheduled or ongoing drives'], 422);
    }
} else {
    $cols = bank_has_coords($db) ? ', bb.latitude, bb.longitude' : '';
    $row = fetch_one(
        $db,
        "SELECT br.status, br.blood_type_id, bb.city_id $cols
           FROM blood_requests br JOIN blood_banks bb ON bb.bank_id = br.bank_id
          WHERE br.request_id = ?",
        'i', $requestId
    );
    if (!$row) {
        json_out(['ok' => false, 'message' => 'Blood request not found'], 404);
    }
    if ($row['status'] !== 'open') {
        json_out(['ok' => false, 'message' => 'Alerts can only be sent for open requests'], 422);
    }
    $typeId = $typeId ?? (int) $row['blood_type_id'];
}
$originCity = (int) $row['city_id'];
if (isset($row['latitude'], $row['longitude'])) {
    $lat = (float) $row['latitude'];
    $lng = (float) $row['longitude'];
}

// ---------- 3. Build the donor filter (structure is ours; values are bound) ----------
$where = [];
$types = '';
$params = [];
if ($typeId !== null) {
    $where[] = 'd.blood_type_id = ?';
    $types .= 'i';
    $params[] = $typeId;
}
if ($lat !== null) {
    $where[] = '((d.latitude IS NOT NULL AND d.longitude IS NOT NULL AND ' . HAVERSINE_KM . ' <= ?)
                 OR ((d.latitude IS NULL OR d.longitude IS NULL) AND d.city_id = ?))';
    $types .= 'ddddi';
    array_push($params, $lat, $lat, $lng, $radius, $originCity);
} else {
    // No coordinates for the origin: every donor in the same province qualifies.
    $where[] = 'd.city_id IN (SELECT c2.city_id FROM cities c2
                              WHERE c2.province = (SELECT c1.province FROM cities c1 WHERE c1.city_id = ?))';
    $types .= 'i';
    $params[] = $originCity;
}

// ---------- 4. Save, all or nothing ----------
try {
    $db->begin_transaction();

    $stmt = $db->prepare(
        'INSERT INTO alerts (drive_id, request_id, blood_type_id, radius_km, title, message)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iiidss', $driveId, $requestId, $typeId, $radius, $title, $message);
    $stmt->execute();
    $alertId = $db->insert_id;
    $stmt->close();

    $stmt = $db->prepare(
        'INSERT INTO alert_recipients (alert_id, donor_id)
         SELECT ?, d.donor_id FROM donors d WHERE ' . implode(' AND ', $where)
    );
    $stmt->bind_param('i' . $types, $alertId, ...$params);
    $stmt->execute();
    $count = $stmt->affected_rows;
    $stmt->close();

    $db->commit();
} catch (mysqli_sql_exception $e) {
    $db->rollback();
    if ($e->getCode() === 1452) {   // foreign key failed (e.g. unknown blood_type_id)
        json_out(['ok' => false, 'message' => 'Unknown drive, request or blood type'], 422);
    }
    throw $e;                       // http.php logs it and answers 500
}

json_out([
    'ok'         => true,
    'alert_id'   => $alertId,
    'recipients' => $count,
    'message'    => "Alert queued for $count donor(s)",
], 201);
