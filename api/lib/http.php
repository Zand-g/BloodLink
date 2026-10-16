<?php
// Shared response helpers. Every endpoint answers with JSON, even on errors.
ini_set('display_errors', '0');          // a PHP warning must never break the JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function json_out(array $data, int $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_method(string ...$allowed): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
    }
}

function read_json_body(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        json_out(['ok' => false, 'message' => 'Request body must be valid JSON'], 400);
    }
    return $data;
}

// Anything unexpected: log the real error, show the user a safe message.
set_exception_handler(function ($e) {
    error_log((string) $e);
    json_out(['ok' => false, 'message' => 'Server error'], 500);
});
