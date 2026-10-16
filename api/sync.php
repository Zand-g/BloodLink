<?php
// POST /api/sync.php   (called by the offline queue when the connection returns)
// Body:  { "items": [ { "screening": {...}, "donation": {...} | null }, ... ] }
// Reply: { "ok": true, "results": [ { screening_uuid, donation_uuid, status, flagged, message, ... } ] }
// status = saved | duplicate | failed.  The device may delete its queued copy
// for "saved" and "duplicate"; "failed" stays in the queue with the message.
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/bundle.php';

require_method('POST');
$body  = read_json_body();
$items = $body['items'] ?? null;

if (!is_array($items) || count($items) === 0) {
    json_out(['ok' => false, 'message' => 'items must be a non-empty array'], 400);
}
if (count($items) > 100) {
    json_out(['ok' => false, 'message' => 'Send at most 100 items per request'], 400);
}

$db = db();
$results = [];
foreach ($items as $item) {
    // One bad item must never block the rest of the queue.
    $results[] = is_array($item)
        ? save_bundle($db, $item)
        : ['status' => 'failed', 'flagged' => false, 'message' => 'Each item must be an object'];
}

json_out(['ok' => true, 'results' => $results, 'server_time' => date('c')]);
