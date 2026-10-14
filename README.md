# BloodLink front end (F4: display, second role, global CSS)

Open `index.html` through your local server (XAMPP `htdocs/…`). It runs on mock data until `USE_MOCK` in `js/app.js` is set to `false`.

## Assumed API contract (confirm with B3)
- `GET api/donations.php?q=&blood_type=&sort=field:dir` returns `{ok:true,data:[{donation_id,client_uuid,donor_name,blood_type,bank_name,drive_title,units,donation_date,latitude,longitude,synced_at}]}`. `blood_type` is the label (`O-`), `sort` fields: `donation_date`, `units`, `donor_name`, `blood_type`.
- `GET api/requests.php?status=open` returns `{ok:true,data:[{request_id,bank_name,blood_type,units_needed,urgency,status,needed_by}]}`. `urgency` is `critical|urgent|routine`.
- Errors: `{ok:false,error:"message"}`.

## Hooks for teammates
- **F1:** add `<link rel="manifest">`, theme color `#0F1B33`, and register the service worker on both pages. Cache `css/style.css`, `fonts/archivo-wdth.woff2`, all `js/*`, both pages.
- **F2:** call `BL.setPending(n)` to update the status chip. Cache the last `getDonations()` result and return it when offline. Records with `synced_at` empty render as "Waiting to send".
- **F3:** the form page is linked as `donation-form.html?id=`. Listen for `document.addEventListener("donation:delete", e => e.detail)` to show the delete confirmation. Reuse the classes in `design-system.html`.
