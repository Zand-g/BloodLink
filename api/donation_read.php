<?php
// GET /api/donation_read.php?donation_id=7
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validate.php';

require_method('GET');
$errors = [];
$id = f_int($_GET, 'donation_id', $errors, true, 1);
if ($errors) {
    json_out(['ok' => false, 'message' => implode('; ', $errors)], 422);
}

$db = db();
$stmt = $db->prepare(
    "SELECT dn.donation_id, dn.request_id, dn.volume_ml, dn.status AS donation_status,
            dn.remarks, dn.latitude, dn.longitude, dn.gps_accuracy_m, dn.collected_at,
            sc.donor_id, CONCAT(do.first_name, ' ', do.last_name) AS donor_name,
            sc.bank_id, bb.name AS bank_name, sc.drive_id, dr.title AS drive_title,
            sc.weight_kg, sc.hemoglobin_g_l, sc.bp_systolic, sc.bp_diastolic,
            sc.temperature_c, sc.screened_at
       FROM donations dn
       JOIN screenings sc ON sc.screening_id = dn.screening_id
       JOIN donors do ON do.donor_id = sc.donor_id
       JOIN blood_banks bb ON bb.bank_id = sc.bank_id
       LEFT JOIN drives dr ON dr.drive_id = sc.drive_id
      WHERE dn.donation_id = ? AND dn.deleted_at IS NULL"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$donation = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$donation) {
    json_out(['ok' => false, 'message' => 'Donation not found'], 404);
}

json_out(['ok' => true, 'data' => $donation]);
