<?php
// GET /api/requests.php?status=open
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validate.php';

require_method('GET');
$errors = [];
$status = f_enum($_GET, 'status', ['open', 'fulfilled', 'cancelled'], $errors, false);
if ($errors) {
    json_out(['ok' => false, 'message' => implode('; ', $errors)], 422);
}

$sql = "SELECT br.request_id, bb.name AS bank_name, bt.label AS blood_type,
               br.units_needed,
               CASE br.urgency WHEN 'emergency' THEN 'critical' ELSE br.urgency END AS urgency,
               br.status, br.needed_by
          FROM blood_requests br
          JOIN blood_banks bb ON bb.bank_id = br.bank_id
          JOIN blood_types bt ON bt.blood_type_id = br.blood_type_id";
$params = [];
$types = '';
if ($status !== null) {
    $sql .= ' WHERE br.status = ?';
    $params[] = $status;
    $types = 's';
}
$sql .= ' ORDER BY FIELD(br.urgency, \'emergency\', \'urgent\', \'routine\'), br.needed_by, br.request_id';

$stmt = db()->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

json_out(['ok' => true, 'data' => $rows]);
