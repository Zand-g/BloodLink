<?php
// DELETE (or POST) /api/donation_delete.php
// Body: { "donation_id": 7 }
// SOFT delete: the row stays with deleted_at set, so a stale offline copy
// that syncs later cannot bring it back. Every read query uses "deleted_at IS NULL".
// If the donation was counted toward a blood request, that request's status is
// recalculated (a 'fulfilled' request can go back to 'open').
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/bundle.php';   // also loads db.php, validate.php, refresh_request_status()

require_method('DELETE', 'POST');
$in = read_json_body();

$errors = [];
$id = f_int($in, 'donation_id', $errors, true, 1);
if ($errors) {
    json_out(['ok' => false, 'message' => implode('; ', $errors)], 422);
}

$db = db();
$stmt = $db->prepare('SELECT request_id FROM donations WHERE donation_id = ? AND deleted_at IS NULL');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    json_out(['ok' => false, 'message' => 'Donation not found'], 404);
}

try {
    $db->begin_transaction();

    $stmt = $db->prepare('UPDATE donations SET deleted_at = NOW() WHERE donation_id = ? AND deleted_at IS NULL');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    if ($row['request_id'] !== null) {
        refresh_request_status($db, (int) $row['request_id']);
    }

    $db->commit();
} catch (mysqli_sql_exception $e) {
    $db->rollback();
    throw $e;
}

json_out(['ok' => true, 'donation_id' => $id]);
