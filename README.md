# BloodLink front end (F4: display, donation form, global CSS)

Open `index.html` through a local PHP server (for example, `php -S 127.0.0.1:8000 -t .` from the repository root) or copy the repository into XAMPP's `htdocs`. Set up the `bloodlink` database as described in [api/README_B2.md](api/README_B2.md). The donations and blood-bank pages load from the API; the donation form creates and edits real database records.

## Read API contract
- `GET api/donations.php?q=&blood_type=&sort=field:dir` returns `{ok:true,data:[{donation_id,client_uuid,donor_name,blood_type,bank_name,drive_title,units,status,donation_date,latitude,longitude,synced_at}]}`. `blood_type` is the label (`O-`), `sort` fields: `donation_date`, `units`, `donor_name`, `blood_type`.
- The blood-bank stock view counts only donations with status `cleared`.
- `GET api/requests.php?status=open` returns `{ok:true,data:[{request_id,bank_name,blood_type,units_needed,urgency,status,needed_by}]}`. `urgency` is `critical|urgent|routine`.
- The form uses `GET api/donation_form_options.php` for donor, bank, drive, and open-request choices, and `GET api/donation_read.php?donation_id=` to load an existing record for editing.
- Errors return `{ok:false,message:"..."}`.

## Donation form
- **Create:** `donation-form.html` posts a screening and donation bundle to `api/donation_create.php`, using persistent UUIDs for idempotent retries.
- **Edit:** `donation-form.html?id=<donation_id>` loads the record and updates collection status, volume, request, or remarks through `api/donation_update.php`.
- **Delete:** the donations list confirms the action, soft-deletes with `api/donation_delete.php`, and refreshes.
