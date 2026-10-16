# BloodLink: B2 write API

PHP + MySQLi (prepared statements only), JSON in, JSON out. Tested against the v2 schema.

## Setup (XAMPP)
1. Copy the `api` folder to `C:\xampp\htdocs\bloodlink\api`.
2. Import the team `.sql` (phpMyAdmin > Import). Check `api/config.php` (default: root, empty password, db `bloodlink`).
3. Needs the `mysqli` and `mbstring` PHP extensions (both on by default in XAMPP).
4. Test: `curl.exe -X POST http://localhost/bloodlink/api/sync.php -H "Content-Type: application/json" -d "@tests/sync_example.json"`
   First run returns `saved`; run it again and it returns `duplicate` (no new rows).

## Endpoints
| File | Method | Purpose |
|---|---|---|
| `sync.php` | POST | Offline queue upload. Many bundles per request (max 100). |
| `donation_create.php` | POST | Online save. Same body as ONE sync item. |
| `donation_update.php` | PUT / POST | Edit volume_ml, status, remarks, request_id. |
| `donation_delete.php` | DELETE / POST | Soft delete (sets `deleted_at`). |

## Contract for F2 and F3 (do not change without telling them)
A **bundle** is one screening plus the donation that followed it. Offline, the device cannot know the
server's `screening_id`, so both records travel together and the server links them.

```json
{ "items": [ { "screening": { "client_uuid": "...", "donor_id": 5, "bank_id": 1, "drive_id": null,
                "weight_kg": 58, "hemoglobin_g_l": 130, "bp_systolic": 115, "bp_diastolic": 75,
                "temperature_c": 36.6, "result": "eligible", "deferral_reason": null,
                "deferred_until": null, "screened_at": "2026-10-15T13:40:00+08:00" },
              "donation": { "client_uuid": "...", "request_id": null, "volume_ml": 450,
                "status": "pending_test", "remarks": null, "latitude": 16.41, "longitude": 120.59,
                "gps_accuracy_m": 10, "collected_at": "2026-10-15T14:00:00+08:00" } } ] }
```
- `donation` is `null` for a deferral (no blood collected).
- Make both `client_uuid` values on the device with `crypto.randomUUID()` BEFORE saving to IndexedDB, and keep them for retries.
- Times: send ISO 8601 with offset (`+08:00` or `Z`) or plain Manila time. The server stores Manila time.
- Send `latitude` and `longitude` together, or neither (GPS denied is fine).

Reply: `{ "ok": true, "results": [ { screening_uuid, donation_uuid, status, flagged, message, screening_id, donation_id } ] }`
(`results` is in the same order as `items`.)

| `status` | Meaning | F2 should |
|---|---|---|
| `saved` | New rows inserted | remove from queue, update the "waiting" count |
| `duplicate` | Already on the server (a retry) | remove from queue, same as saved |
| `failed` | Rejected; `message` says why | keep in queue, mark failed, show the message |

`flagged: true` = saved, but staff should review it (donor inside the 3-month wait, or deferred at that time).
The server never throws away an offline record because of eligibility; it keeps it and marks `[REVIEW]` at the start of `remarks`.

HTTP codes: `400` bad JSON, `405` wrong method, `422` validation failed (single-record endpoints),
`404` unknown donation, `500` unexpected error. `sync.php` returns `200` even when individual items fail; read each result.

## Rules enforced on the server
- Every query uses `?` placeholders (`bind_param`). No user input is ever pasted into SQL.
- Each bundle is one transaction: screening and donation both save, or neither does.
- Duplicate-proof: `client_uuid` lookup first, plus the UNIQUE index as the last guard. A soft-deleted
  donation is still found by its UUID, so a late offline retry cannot bring it back.
- A donation needs an `eligible` screening. `eligible` is rejected if the readings are below the limits
  at the top of `lib/bundle.php` (taken from the comment in the .sql; confirm them).
- `collected_at` / `screened_at` cannot be in the future (5 min clock drift allowed); `collected_at` >= `screened_at`.
- The drive must belong to the selected blood bank.
- A blood request flips to `fulfilled` when enough non-deleted donations point to it.

## Still to build (B2), same pattern as `donation_update.php`
Validate with `f_*` helpers, `row_exists` check, prepared statement, `json_out`.
1. `request_create/update/cancel.php` (blood_requests).
2. `drive_create/update/cancel.php` (drives; reject end_time <= start_time).
3. `registration_create/update/cancel.php` (drive_registrations; the UNIQUE (drive, donor) gives error 1062 -> answer "already registered"; block full or past drives).
4. `alert_send.php`: insert into `alerts`, then insert one `alert_recipients` row per matching donor
   (Haversine distance <= radius_km, optional blood type match; donors without coordinates match by city).
   Use the Haversine query at the bottom of the .sql. Needs the drive's or bank's coordinates.

## Suggested schema tweaks (ask B1)
- `ALTER TABLE blood_banks ADD latitude DECIMAL(8,6) NULL, ADD longitude DECIMAL(9,6) NULL;` (request alerts need the bank's location)
- Optional: `ALTER TABLE donations ADD needs_review TINYINT(1) NOT NULL DEFAULT 0;` then set it where `[REVIEW]` is added.
