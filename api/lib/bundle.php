<?php
// ---------------------------------------------------------------------
// save_bundle(): saves ONE offline-captured bundle = a screening plus
// (optionally) the donation that followed it.
//
// Why a bundle? Offline, the device does not know the server's
// screening_id yet, so the donation cannot point to it. Both records
// travel together with their own client_uuid, and the server links them.
//
// It is IDEMPOTENT: sending the same bundle twice never creates a second
// row. The UNIQUE client_uuid columns are the final guard.
//
// Used by sync.php (offline queue) and donation_create.php (online save),
// so both paths behave the same.
// ---------------------------------------------------------------------
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validate.php';

// Our own exception type. (mysqli_sql_exception is itself a kind of RuntimeException,
// so catching RuntimeException would swallow database errors by mistake.)
class BundleError extends Exception {}

const WAIT_MONTHS = 3;                 // months between whole-blood donations
// Screening limits (copied from the comment in the .sql file; confirm them with your sources).
const MIN_WEIGHT_KG = 50;
const MIN_HEMOGLOBIN_G_L = 125;
const BP_SYSTOLIC_RANGE = [90, 160];
const BP_DIASTOLIC_RANGE = [60, 100];

function save_bundle(mysqli $db, array $b): array
{
    $sc = $b['screening'] ?? null;
    $dn = $b['donation'] ?? null;

    $out = [
        'screening_uuid' => is_array($sc) ? ($sc['client_uuid'] ?? null) : null,
        'donation_uuid'  => is_array($dn) ? ($dn['client_uuid'] ?? null) : null,
        'status'         => 'failed',   // saved | duplicate | failed
        'flagged'        => false,      // true = saved, but staff must review it
        'message'        => '',
        'screening_id'   => null,
        'donation_id'    => null,
    ];

    if (!is_array($sc)) {
        $out['message'] = 'screening is required';
        return $out;
    }
    if ($dn !== null && !is_array($dn)) {
        $out['message'] = 'donation must be an object or null';
        return $out;
    }

    // ---------- 1. Validate everything first ----------
    $errors = [];
    $s = [
        'client_uuid'    => f_uuid($sc, 'client_uuid', $errors),
        'donor_id'       => f_int($sc, 'donor_id', $errors, true, 1),
        'bank_id'        => f_int($sc, 'bank_id', $errors, true, 1),
        'drive_id'       => f_int($sc, 'drive_id', $errors, false, 1),
        'weight_kg'      => f_float($sc, 'weight_kg', $errors, false, 20, 300),
        'hemoglobin_g_l' => f_int($sc, 'hemoglobin_g_l', $errors, false, 50, 250),
        'bp_systolic'    => f_int($sc, 'bp_systolic', $errors, false, 50, 300),
        'bp_diastolic'   => f_int($sc, 'bp_diastolic', $errors, false, 30, 200),
        'temperature_c'  => f_float($sc, 'temperature_c', $errors, false, 30, 45),
        'result'         => f_enum($sc, 'result', ['eligible', 'temporarily_deferred', 'permanently_deferred'], $errors, true),
        'deferral_reason' => f_str($sc, 'deferral_reason', $errors, false, 255),
        'deferred_until' => f_date($sc, 'deferred_until', $errors),
        'screened_at'    => f_datetime($sc, 'screened_at', $errors),
    ];
    if ($s['result'] === 'temporarily_deferred' && $s['deferred_until'] === null) {
        $errors[] = 'deferred_until is required when result is temporarily_deferred';
    }
    // The app decides "eligible", but the server double-checks the readings.
    if ($s['result'] === 'eligible') {
        if ($s['weight_kg'] !== null && $s['weight_kg'] < MIN_WEIGHT_KG) {
            $errors[] = 'weight is below the minimum, so the result cannot be eligible';
        }
        if ($s['hemoglobin_g_l'] !== null && $s['hemoglobin_g_l'] < MIN_HEMOGLOBIN_G_L) {
            $errors[] = 'hemoglobin is below the minimum, so the result cannot be eligible';
        }
        if ($s['bp_systolic'] !== null && ($s['bp_systolic'] < BP_SYSTOLIC_RANGE[0] || $s['bp_systolic'] > BP_SYSTOLIC_RANGE[1])) {
            $errors[] = 'systolic blood pressure is outside the allowed range, so the result cannot be eligible';
        }
        if ($s['bp_diastolic'] !== null && ($s['bp_diastolic'] < BP_DIASTOLIC_RANGE[0] || $s['bp_diastolic'] > BP_DIASTOLIC_RANGE[1])) {
            $errors[] = 'diastolic blood pressure is outside the allowed range, so the result cannot be eligible';
        }
    }

    $d = null;
    if ($dn !== null) {
        $d = [
            'client_uuid'    => f_uuid($dn, 'client_uuid', $errors),
            'request_id'     => f_int($dn, 'request_id', $errors, false, 1),
            'volume_ml'      => f_int($dn, 'volume_ml', $errors, false, 100, 600) ?? 450,
            'status'         => f_enum($dn, 'status', ['pending_test', 'cleared', 'discarded'], $errors, false, 'pending_test'),
            'remarks'        => f_str($dn, 'remarks', $errors, false, 255),
            'latitude'       => f_float($dn, 'latitude', $errors, false, -90, 90),
            'longitude'      => f_float($dn, 'longitude', $errors, false, -180, 180),
            'gps_accuracy_m' => f_float($dn, 'gps_accuracy_m', $errors, false, 0, 100000),
            'collected_at'   => f_datetime($dn, 'collected_at', $errors),
        ];
        if (($d['latitude'] === null) !== ($d['longitude'] === null)) {
            $errors[] = 'latitude and longitude must be sent together';
        }
        if ($s['result'] !== null && $s['result'] !== 'eligible') {
            $errors[] = 'a donation needs an eligible screening';
        }
        if ($d['collected_at'] !== null && $s['screened_at'] !== null && $d['collected_at'] < $s['screened_at']) {
            $errors[] = 'collected_at cannot be before screened_at';
        }
    }

    if ($errors) {
        $out['message'] = implode('; ', $errors);
        return $out;
    }

    // ---------- 2. Save, all or nothing ----------
    try {
        $db->begin_transaction();

        if ($s['drive_id'] !== null
            && !row_exists($db, 'SELECT 1 FROM drives WHERE drive_id = ? AND bank_id = ?', 'ii', $s['drive_id'], $s['bank_id'])) {
            throw new BundleError('That drive does not belong to the selected blood bank');
        }

        [$screeningId, $screeningNew] = insert_screening($db, $s);
        $out['screening_id'] = $screeningId;

        $donationNew = false;
        if ($d !== null) {
            [$donationId, $donationNew, $notes] = insert_donation($db, $d, $screeningId, $s['donor_id']);
            $out['donation_id'] = $donationId;
            $out['flagged'] = count($notes) > 0;
            if ($out['flagged']) {
                $out['message'] = 'Saved, but needs review: ' . implode('; ', $notes);
            }
        }

        $db->commit();
        $out['status'] = ($screeningNew || $donationNew) ? 'saved' : 'duplicate';
    } catch (BundleError $e) {
        $db->rollback();
        $out['message'] = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $db->rollback();
        if ($e->getCode() === 1452) {          // foreign key failed
            $out['message'] = 'Unknown donor, blood bank, drive or request ID';
        } else {
            error_log((string) $e);
            $out['message'] = 'Database error';
        }
    }
    return $out;
}

// Returns the id of the row with this client_uuid, or null. (Table/column names
// below are written by us in this file, never taken from user input.)
function find_id(mysqli $db, string $table, string $idCol, string $uuid): ?int
{
    $stmt = $db->prepare("SELECT $idCol FROM $table WHERE client_uuid = ?");
    $stmt->bind_param('s', $uuid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return $row ? (int) $row[0] : null;
}

// Returns [screening_id, wasInserted]
function insert_screening(mysqli $db, array $s): array
{
    $existing = find_id($db, 'screenings', 'screening_id', $s['client_uuid']);
    if ($existing !== null) {
        return [$existing, false];
    }
    $stmt = $db->prepare(
        'INSERT INTO screenings (client_uuid, donor_id, bank_id, drive_id, weight_kg, hemoglobin_g_l,
                                 bp_systolic, bp_diastolic, temperature_c, result, deferral_reason,
                                 deferred_until, screened_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'siiidiiidssss',
        $s['client_uuid'], $s['donor_id'], $s['bank_id'], $s['drive_id'], $s['weight_kg'],
        $s['hemoglobin_g_l'], $s['bp_systolic'], $s['bp_diastolic'], $s['temperature_c'],
        $s['result'], $s['deferral_reason'], $s['deferred_until'], $s['screened_at']
    );
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {          // two requests raced: the other one won
            $id = find_id($db, 'screenings', 'screening_id', $s['client_uuid']);
            if ($id !== null) return [$id, false];
        }
        throw $e;
    }
    $id = $db->insert_id;
    $stmt->close();
    return [$id, true];
}

// Returns [donation_id, wasInserted, reviewNotes[]]
function insert_donation(mysqli $db, array $d, int $screeningId, int $donorId): array
{
    // Looked up WITHOUT "deleted_at IS NULL": a soft-deleted donation must not come back.
    $existing = find_id($db, 'donations', 'donation_id', $d['client_uuid']);
    if ($existing !== null) {
        return [$existing, false, []];
    }

    // Re-check eligibility on the server. Two devices may have screened the same
    // donor offline, so we never reject the record (the blood was already
    // collected); we keep it and mark it for staff review.
    $notes = review_notes($db, $donorId, $d['collected_at']);
    $remarks = $d['remarks'];
    if ($notes) {
        $remarks = mb_substr('[REVIEW] ' . implode('; ', $notes) . ($remarks ? ' | ' . $remarks : ''), 0, 255);
    }

    $stmt = $db->prepare(
        'INSERT INTO donations (client_uuid, screening_id, request_id, volume_ml, status, remarks,
                                latitude, longitude, gps_accuracy_m, collected_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'siiissddds',
        $d['client_uuid'], $screeningId, $d['request_id'], $d['volume_ml'], $d['status'], $remarks,
        $d['latitude'], $d['longitude'], $d['gps_accuracy_m'], $d['collected_at']
    );
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            $id = find_id($db, 'donations', 'donation_id', $d['client_uuid']);
            if ($id !== null) return [$id, false, []];
        }
        throw $e;
    }
    $id = $db->insert_id;
    $stmt->close();

    if ($d['request_id'] !== null) {
        refresh_request_status($db, $d['request_id']);
    }
    return [$id, true, $notes];
}

function review_notes(mysqli $db, int $donorId, string $collectedAt): array
{
    $notes = [];
    $m = WAIT_MONTHS;

    if (row_exists(
        $db,
        "SELECT 1 FROM donations dn JOIN screenings s ON s.screening_id = dn.screening_id
          WHERE s.donor_id = ? AND dn.deleted_at IS NULL
            AND dn.collected_at > DATE_SUB(?, INTERVAL $m MONTH)
            AND dn.collected_at < DATE_ADD(?, INTERVAL $m MONTH)
          LIMIT 1",
        'iss', $donorId, $collectedAt, $collectedAt
    )) {
        $notes[] = "another donation within $m months";
    }
    if (row_exists(
        $db,
        "SELECT 1 FROM screenings WHERE donor_id = ? AND result = 'permanently_deferred' LIMIT 1",
        'i', $donorId
    )) {
        $notes[] = 'donor is permanently deferred';
    }
    if (row_exists(
        $db,
        "SELECT 1 FROM screenings
          WHERE donor_id = ? AND result = 'temporarily_deferred'
            AND screened_at < ? AND deferred_until > DATE(?) LIMIT 1",
        'iss', $donorId, $collectedAt, $collectedAt
    )) {
        $notes[] = 'donor was temporarily deferred at that time';
    }
    return $notes;
}

// Keeps a blood request's status in step with its donations, in BOTH directions:
// 'open' -> 'fulfilled' once enough donations point to it, and back to 'open' if a
// donation is deleted, discarded or moved to another request. Donations that are
// soft-deleted or 'discarded' do not count. A 'cancelled' request is never touched.
// Call it for the old AND the new request_id after any change.
function refresh_request_status(mysqli $db, int $requestId): void
{
    $stmt = $db->prepare(
        "UPDATE blood_requests br
            SET br.status = IF(
                  (SELECT COUNT(*) FROM donations d
                    WHERE d.request_id = br.request_id
                      AND d.deleted_at IS NULL AND d.status <> 'discarded') >= br.units_needed,
                  'fulfilled', 'open')
          WHERE br.request_id = ? AND br.status IN ('open', 'fulfilled')"
    );
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $stmt->close();
}
