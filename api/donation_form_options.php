<?php
// GET /api/donation_form_options.php
// Select-list data for creating or editing a donation.
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/db.php';

require_method('GET');
$db = db();

$donors = $db->query(
    "SELECT donor_id, CONCAT(first_name, ' ', last_name) AS name
       FROM donors ORDER BY last_name, first_name"
)->fetch_all(MYSQLI_ASSOC);
$banks = $db->query(
    'SELECT bank_id, name FROM blood_banks ORDER BY name'
)->fetch_all(MYSQLI_ASSOC);
$drives = $db->query(
    "SELECT drive_id, bank_id, title, drive_date
       FROM drives WHERE status IN ('scheduled', 'ongoing')
      ORDER BY drive_date, start_time"
)->fetch_all(MYSQLI_ASSOC);
$requests = $db->query(
    "SELECT br.request_id, br.bank_id, bt.label AS blood_type, br.units_needed,
            CASE br.urgency WHEN 'emergency' THEN 'critical' ELSE br.urgency END AS urgency
       FROM blood_requests br
       JOIN blood_types bt ON bt.blood_type_id = br.blood_type_id
      WHERE br.status = 'open'
      ORDER BY br.needed_by, br.request_id"
)->fetch_all(MYSQLI_ASSOC);

json_out([
    'ok' => true,
    'donors' => $donors,
    'banks' => $banks,
    'drives' => $drives,
    'requests' => $requests,
]);
