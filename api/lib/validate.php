<?php
// Field checkers. Each one returns the clean value (or null) and pushes a
// readable message into $errors when the input is wrong. The calling code
// collects ALL problems first, then answers once.

function is_uuid($v): bool
{
    return is_string($v)
        && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v) === 1;
}

function f_uuid(array $src, string $key, array &$errors): ?string
{
    $v = $src[$key] ?? null;
    if (!is_uuid($v)) {
        $errors[] = "$key must be a UUID";
        return null;
    }
    return strtolower($v);
}

function f_int(array $src, string $key, array &$errors, bool $required = false, ?int $min = null, ?int $max = null): ?int
{
    $v = $src[$key] ?? null;
    if ($v === null || $v === '') {
        if ($required) $errors[] = "$key is required";
        return null;
    }
    if (filter_var($v, FILTER_VALIDATE_INT) === false) {
        $errors[] = "$key must be a whole number";
        return null;
    }
    $v = (int) $v;
    if (($min !== null && $v < $min) || ($max !== null && $v > $max)) {
        $errors[] = "$key is out of range";
        return null;
    }
    return $v;
}

function f_float(array $src, string $key, array &$errors, bool $required = false, ?float $min = null, ?float $max = null): ?float
{
    $v = $src[$key] ?? null;
    if ($v === null || $v === '') {
        if ($required) $errors[] = "$key is required";
        return null;
    }
    if (!is_numeric($v)) {
        $errors[] = "$key must be a number";
        return null;
    }
    $v = (float) $v;
    if (($min !== null && $v < $min) || ($max !== null && $v > $max)) {
        $errors[] = "$key is out of range";
        return null;
    }
    return $v;
}

function f_str(array $src, string $key, array &$errors, bool $required = false, int $max = 255): ?string
{
    $v = $src[$key] ?? null;
    if ($v === null) {
        if ($required) $errors[] = "$key is required";
        return null;
    }
    if (!is_string($v)) {
        $errors[] = "$key must be text";
        return null;
    }
    $v = trim($v);
    if ($v === '') {
        if ($required) $errors[] = "$key is required";
        return null;
    }
    if (mb_strlen($v) > $max) {
        $errors[] = "$key must be at most $max characters";
        return null;
    }
    return $v;
}

function f_enum(array $src, string $key, array $allowed, array &$errors, bool $required = false, ?string $default = null): ?string
{
    $v = $src[$key] ?? null;
    if ($v === null || $v === '') {
        if ($default !== null) return $default;
        if ($required) $errors[] = "$key is required";
        return null;
    }
    if (!in_array($v, $allowed, true)) {
        $errors[] = "$key must be one of: " . implode(', ', $allowed);
        return null;
    }
    return $v;
}

// 'YYYY-MM-DD'
function f_date(array $src, string $key, array &$errors, bool $required = false): ?string
{
    $v = $src[$key] ?? null;
    if ($v === null || $v === '') {
        if ($required) $errors[] = "$key is required";
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', (string) $v);
    if (!$d || $d->format('Y-m-d') !== $v) {
        $errors[] = "$key must be a date (YYYY-MM-DD)";
        return null;
    }
    return $v;
}

// 'HH:MM' or 'HH:MM:SS' -> 'HH:MM:SS'
function f_time(array $src, string $key, array &$errors, bool $required = false): ?string
{
    $v = $src[$key] ?? null;
    if ($v === null || $v === '') {
        if ($required) $errors[] = "$key is required";
        return null;
    }
    if (!is_string($v) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $v, $m)) {
        $errors[] = "$key must be a time (HH:MM or HH:MM:SS)";
        return null;
    }
    return sprintf('%s:%s:%s', $m[1], $m[2], $m[3] ?? '00');
}

// Accepts '2026-10-15 14:00:00', '2026-10-15T14:00:00' or ISO with an offset/Z.
// Always returns Manila time as 'Y-m-d H:i:s'. A time with no offset is read as Manila time.
function f_datetime(array $src, string $key, array &$errors, bool $required = true, bool $noFuture = true): ?string
{
    $v = $src[$key] ?? null;
    if ($v === null || $v === '') {
        if ($required) $errors[] = "$key is required";
        return null;
    }
    try {
        $d = new DateTime((string) $v, new DateTimeZone('Asia/Manila'));
    } catch (Exception $e) {
        $errors[] = "$key is not a valid date and time";
        return null;
    }
    $d->setTimezone(new DateTimeZone('Asia/Manila'));
    if ($noFuture && $d->getTimestamp() > time() + 300) {   // 5 minutes of clock drift allowed
        $errors[] = "$key cannot be in the future";
        return null;
    }
    return $d->format('Y-m-d H:i:s');
}
