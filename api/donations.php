<?php
// GET /api/donations.php?q=&blood_type=&sort=donation_date:desc
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validate.php';

require_method('GET');
$errors = [];
$query = f_str($_GET, 'q', $errors, false, 100) ?? '';
$bloodType = f_str($_GET, 'blood_type', $errors, false, 3);
$sortValue = f_str($_GET, 'sort', $errors, false, 40) ?? 'donation_date:desc';
$sortParts = explode(':', $sortValue, 2);
$sortFields = [
    'donation_date' => 'dn.collected_at',
    'units' => 'dn.volume_ml',
    'donor_name' => 'donor_name',
    'blood_type' => 'bt.label',
];
$sortField = $sortParts[0] ?? '';
$direction = strtolower($sortParts[1] ?? '');
if (!isset($sortFields[$sortField]) || !in_array($direction, ['asc', 'desc'], true)) {
    $errors[] = 'sort must be a supported field and asc or desc';
}
if ($errors) {
    json_out(['ok' => false, 'message' => implode('; ', $errors)], 422);
}

$where = ['dn.deleted_at IS NULL'];
$types = '';
$params = [];
if ($query !== '') {
    $where[] = "(CONCAT(do.first_name, ' ', do.last_name) LIKE ?
                 OR bb.name LIKE ? OR dr.title LIKE ?)";
    $term = '%' . $query . '%';
    $types .= 'sss';
    array_push($params, $term, $term, $term);
}
if ($bloodType !== null) {
    $where[] = 'bt.label = ?';
    $types .= 's';
    $params[] = $bloodType;
}

$sql = "SELECT dn.donation_id, dn.client_uuid,
               CONCAT(do.first_name, ' ', do.last_name) AS donor_name,
               COALESCE(bt.label, 'Unknown') AS blood_type, bb.name AS bank_name,
               dr.title AS drive_title, dn.volume_ml AS units,
               dn.status,
               DATE(dn.collected_at) AS donation_date, dn.latitude, dn.longitude,
               dn.synced_at
          FROM donations dn
          JOIN screenings sc ON sc.screening_id = dn.screening_id
          JOIN donors do ON do.donor_id = sc.donor_id
          LEFT JOIN blood_types bt ON bt.blood_type_id = do.blood_type_id
          JOIN blood_banks bb ON bb.bank_id = sc.bank_id
          LEFT JOIN drives dr ON dr.drive_id = sc.drive_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY {$sortFields[$sortField]} $direction, dn.donation_id DESC";
$stmt = db()->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

json_out(['ok' => true, 'data' => $rows]);
