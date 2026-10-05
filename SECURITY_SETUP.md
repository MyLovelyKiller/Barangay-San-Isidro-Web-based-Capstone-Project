# Security setup and rollout

The application now reads credentials and deployment-specific paths from the PHP process environment. Do not put production values in this repository or in a file under the public document root. On Windows/XAMPP, configure environment variables for the Apache service (or its VirtualHost), then restart Apache so PHP inherits them.

## Required application configuration

Set these environment variables before using the corresponding features:

| Variable | Purpose |
| --- | --- |
| `BMS_DB_HOST` | Main database host |
| `BMS_DB_USER` | Restricted MySQL application user |
| `BMS_DB_PASSWORD` | Main database password |
| `BMS_DB_NAME` | Main database name |
| `BMS_RESEND_API_KEY` | Resend API key; keep it in the deployment environment |
| `BMS_RESEND_FROM_EMAIL` | Sender address verified with Resend |
| `BMS_RESEND_FROM_NAME` | Sender display name |
| `BMS_RECAPTCHA_PROJECT_ID` | reCAPTCHA Enterprise project |
| `BMS_RECAPTCHA_API_KEY` | Newly rotated server-side API key |
| `BMS_CLAMSCAN_PATH` | Absolute path to ClamAV's scanner (`/usr/bin/clamscan` in Docker; the full `clamscan.exe` path on Windows) |
| `BMS_PROFILE_ENCRYPTION_KEY_B64` | Base64 encoding of exactly 32 random bytes for new AES-256-GCM ID encryption |
| `BMS_APP_BASE_URL` | Public application origin, e.g. `https://your-app.up.railway.app` |

`BMS_PROFILE_ENCRYPTION_KEY_B64` is required for new registrations and for decrypting version-2 profile IDs. Generate a fresh random 32-byte key using an approved secret manager or cryptographically secure generator. Keep a protected backup; losing it permanently makes version-2 IDs unreadable.

## Existing encrypted IDs

Profile IDs written by older code used more than one encryption format and keys that were present in source code. Those old values must be migrated before removing the legacy decryption material. Provide the old key/IV values from a protected backup only through these temporary environment variables:

- `BMS_PROFILE_LEGACY_KEY_B64`
- `BMS_PROFILE_LEGACY_IV_B64`
- `BMS_REGISTRATION_LEGACY_KEY_B64`
- `BMS_REGISTRATION_LEGACY_IV_B64`

Back up the database first. Run `php BACKEND\migrate_profile_ids.php` from the project directory for a dry run. If it succeeds and the reported count is expected, run `php BACKEND\migrate_profile_ids.php --apply`. Verify profile ID display for residents and officials, then remove the four legacy variables. The migration tool is CLI-only and uses a transaction; it refuses to commit if any ID cannot be decoded. Do not run it against production without a tested backup and a planned maintenance window.

## Docker deployment

Build the image from this directory with `docker build -t bms .`. The image includes the PHP extensions used by the app, Apache, ClamAV, and virus definitions downloaded during the build. Rebuild regularly to refresh the definitions. `BMS_CLAMSCAN_PATH` is set to `/usr/bin/clamscan` in the image. Configure the database and other required application environment variables when starting the container; do not bake secrets into the image.

The Docker build context excludes `.env` files, `CHATBOT/config.php`, the SQL dump, logs, and uploaded files. Supply `CHATBOT/config.php` as a read-only runtime mount if the chatbot is enabled. The container accepts Railway's `PORT` variable and redirects the old `/BMS/...` links to the application root, preserving local XAMPP URLs.

The container uses one writable data directory at `/data`. It stores `uploads/`, resident uploads, quarantined files, uploaded profile photos, and PHP sessions there. Mount a Railway volume at `/data`; the entrypoint prepares Apache-writable directories and links the application's existing paths into the volume. Do not mount over `/var/www/html`.

For local Docker testing, the container defaults to port 80 internally. If testing session-based flows over plain HTTP, set `BMS_SESSION_COOKIE_SECURE=0`; production Railway should leave this unset (secure cookies default to `1`).

## Railway deployment

1. Deploy the repository root as a Dockerfile service. Include the Dockerfile, entrypoint, PHP configuration, `.htaccess`, Railway config, and application changes in the pushed revision. Railway builds the pushed revision, not uncommitted local files. The configured `/health.php` check remains unhealthy until the database variables are correct and the database is reachable.
2. Add a Railway MySQL service, provision the schema and required tables before directing traffic, then set `BMS_DB_HOST`, `BMS_DB_USER`, `BMS_DB_PASSWORD`, and `BMS_DB_NAME` on the web service. The BPSO module uses the same database and these same credentials; there is no separate `bpso_db`.
3. Attach one Railway volume to the web service at `/data`. The app will not retain uploads or sessions across deployments without this volume. Back up the volume and database independently.
4. Set all feature secrets from the table above in Railway's service variables. Set `BMS_APP_BASE_URL` to the public Railway origin without a path, and register the public domain with reCAPTCHA. If the chatbot is enabled, provide `CHATBOT/config.php` at runtime; it is intentionally excluded from the image.
5. Generate a public domain and wait for the deployment health check to pass. Test login, registration/OTP, password reset, request attachments, profile-photo uploads, and downloads against the deployed service before opening it to users.

The SQL dump `barangay_db.sql` is intentionally excluded from Git and the image because it contains database contents. Do not make that dump public or import personal/sample account data into production. Prepare and review a schema-only migration plus sanitized production data, then import it into Railway MySQL through a protected connection before deployment. The current repository does not automatically create or migrate the application schema.

## Required actions outside the source tree

1. Revoke the previously exposed SMTP app password and reCAPTCHA API key; rotating local code alone does not revoke old credentials. Create a Resend API key and configure the three `BMS_RESEND_*` variables in the PHP service environment (including Railway). Verify the sender domain/address with Resend before sending production mail. The reCAPTCHA site key in HTML is public by design.
2. Create a dedicated MySQL account with only the privileges needed by the app on its database. Do not use MySQL `root` or a blank password. Grant schema-alteration privileges only for a controlled migration account when needed.
3. In Docker deployments, confirm the bundled ClamAV scanner runs and rebuild regularly to refresh virus definitions. Outside Docker, install and update ClamAV on the server. In either case, configure `BMS_CLAMSCAN_PATH` and verify the PHP/Apache account can execute it. Upload scanning intentionally fails closed if it is unavailable.
4. Serve production traffic only over HTTPS. Railway terminates public TLS; the Docker configuration trusts the forwarded HTTPS protocol and enables secure, HttpOnly, SameSite=Lax session cookies for all pages. PHP sessions use the persistent volume.
5. Confirm Apache allows the root and upload `.htaccess` files and has `mod_headers` enabled. The rules disable directory indexes, add baseline security headers, deny access to quarantine, and prevent PHP-like files from executing in upload directories.
6. Treat all previously exposed credentials and encryption keys as compromised. If the repository was pushed to a shared remote, coordinate history cleanup with collaborators after revoking credentials; deleting a secret from the current revision does not remove it from Git history.

The current CSP policy is intentionally not forced: this application has inline scripts and multiple third-party frontend dependencies, so a strict policy needs a separate nonce/hash migration and browser testing to avoid breaking pages. HTTPS enforcement and creation of database credentials are deployment actions and are not performed by these PHP changes.
