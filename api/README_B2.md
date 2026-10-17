# BloodLink: B2 write API

PHP + MySQLi (prepared statements only), JSON in, JSON out. Tested against the v2 schema.

## Setup (XAMPP)
1. Copy the `api` folder to `C:\xampp\htdocs\bloodlink\api`.
2. Create the `bloodlink` database and import `Dump20261016.sql` (phpMyAdmin > Import).
   Check `api/config.php` (defaults: root, empty password, database `bloodlink`).
3. For a database imported from an older copy of the dump, run
   `api/migrations/20261017_add_blood_bank_coordinates.sql` once in phpMyAdmin.
4. Enable the PHP `mysqli` and `mbstring` extensions (both are enabled by default in XAMPP).
5. From `C:\xampp\htdocs\bloodlink\api`, test:
   `curl.exe -X POST http://localhost/bloodlink/api/sync.php -H "Content-Type: application/json" -d "@tests/sync_example.json"`
   First run returns `saved`; run it again and it returns `duplicate` (no new rows).

## Endpoints
| File | Method | Purpose |
|---|---|---|
| `sync.php` | POST | Offline queue upload. Many bundles per request (max 100). |
| `donation_create.php` | POST | Online save. Same body as ONE sync item. |
| `donation_read.php` | GET | Load one active donation with its screening details for editing. |
| `donation_update.php` | PUT / POST | Edit volume_ml, status, remarks, request_id. |
| `donation_delete.php` | DELETE / POST | Soft delete (sets `deleted_at`). |
| `donation_form_options.php` | GET | Load donor, bank, drive, and open-request choices for the form. |
| `donations.php` | GET | Search, filter, and sort non-deleted donations for the UI. |
| `requests.php` | GET | List blood requests for the blood-bank view. |
| `registration_create.php` | POST | Register a donor for a scheduled or ongoing drive. |
| `registration_cancel.php` | POST / DELETE | Cancel a registration; cancellation preserves its history. |
| `alert_send.php` | POST | Create a drive/request alert and queue it for matching donors. |
| `request_create.php` | POST | Create an open blood request. |
| `request_update.php` | PUT / POST | Update request bank, blood type, units, urgency, or needed-by date. |
| `request_cancel.php` | POST / DELETE | Cancel an open or fulfilled request. |
| `drive_create.php` | POST | Create a scheduled drive. |
| `drive_update.php` | PUT / POST | Update a scheduled or ongoing drive. |
| `drive_cancel.php` | POST / DELETE | Cancel a scheduled or ongoing drive. |

`registration_create.php` accepts `drive_id`, `donor_id`, and optional `slot_time` (`HH:MM`).
It rejects closed or past drives and duplicate active registrations; a cancelled registration is
reactivated. The current schema has no drive-capacity field, so this endpoint cannot reject a full
drive. `registration_cancel.php` accepts either `registration_id` or both `drive_id` and `donor_id`.
Only registrations with status `registered` can be cancelled.

`alert_send.php` accepts exactly one of `drive_id` or `request_id`, plus `title` and `message`.
`radius_km` is optional (default 10); `blood_type_id` is optional, and request alerts default to
the request's blood type. Donors with coordinates are matched by distance; donors without
coordinates are matched by city. If the alert origin has no coordinates, donors in the same
province are matched instead. The response includes the alert ID and recipient count.

`request_create.php` requires `bank_id`, `blood_type_id`, `units_needed` (1-65535), and
`needed_by` (`YYYY-MM-DD`). `urgency` is optional and defaults to `routine`. Updates are partial:
omitted fields remain unchanged; request status is managed by donation fulfillment and cancellation.
A cancelled request cannot be updated. Cancellation is idempotent.

`drive_create.php` requires `organizer_id`, `bank_id`, `city_id`, `title`, `venue`, `drive_date`
(`YYYY-MM-DD`), `start_time`, and `end_time` (`HH:MM` or `HH:MM:SS`). Optional `latitude` and
`longitude` must be provided together, or both be `null`. `end_time` must be later than
`start_time`; overnight drives are not supported by the schema. Drive updates are partial and
cannot change status; only scheduled or ongoing drives can be edited or cancelled. A completed
drive cannot be cancelled.

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

HTTP codes: `400` bad JSON, `405` wrong method, `422` validation or operation-rule failure,
`404` resource not found, `409` duplicate active registration, `500` unexpected error.
`sync.php` returns `200` even when individual items fail; read each result.

## Rules enforced on the server
- Every query uses `?` placeholders (`bind_param`). No user input is ever pasted into SQL.
- Each bundle is one transaction: screening and donation both save, or neither does.
- Duplicate-proof: `client_uuid` lookup first, plus the UNIQUE index as the last guard. A soft-deleted
  donation is still found by its UUID, so a late offline retry cannot bring it back.
- A donation needs an `eligible` screening. `eligible` is rejected below 50 kg, below 125 g/L
  hemoglobin, or outside the systolic (90-160) and diastolic (60-100) blood-pressure ranges.
- `collected_at` / `screened_at` cannot be in the future (5 min clock drift allowed); `collected_at` >= `screened_at`.
- The drive must belong to the selected blood bank.
- A blood request flips to `fulfilled` when enough non-deleted donations point to it.

## Schema note
`Dump20261016.sql` includes nullable latitude/longitude columns on blood banks. For an existing
database imported from an older dump, run the migration in `api/migrations/` once. Populate these
coordinates to enable distance-based request alerts; when they remain `NULL`, alerts use the
bank's city/province fallback.

The `[REVIEW]` marker is stored in donation remarks; a separate `needs_review` column is not
required by the current implementation.
