<?php
// POST /api/donation_create.php   (online save from the form)
// Body: { "screening": {...}, "donation": {...} }   (same shape as ONE sync item)
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/bundle.php';

require_method('POST');
$result = save_bundle(db(), read_json_body());

$code = $result['status'] === 'failed' ? 422 : ($result['status'] === 'saved' ? 201 : 200);
json_out(['ok' => $result['status'] !== 'failed', 'result' => $result], $code);
