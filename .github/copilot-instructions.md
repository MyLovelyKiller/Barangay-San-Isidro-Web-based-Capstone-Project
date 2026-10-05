# Copilot instructions

## Project architecture

- This is a PHP application intended to run under Apache with MySQL/MariaDB (the repository layout and URLs assume an XAMPP-style install at `/BMS`). The root `index.php` is the landing page; most pages combine PHP-rendered HTML with endpoint or form-processing logic.
- The application is organized around user roles and services: `Barangay_user` handles resident profiles and service requests; `barangay_admin` handles administration and request review; `Clearance` handles clearance-office workflows; `BPSO` handles public-safety operations; and `Lupon_Office` handles cases, hearings, and its calendar. `BACKEND` and `CODES` contain shared account, authentication, and registration flows. The chatbot has its own PHP endpoint and browser assets.
- Most modules connect to the shared `barangay_db` database and pass a MySQLi connection in `$conn`. Connection files are not fully centralized, so inspect the specific module's include and database name before changing a query or schema. The Lupon calendar schema is an additive SQL migration in `Lupon_Office/database/calendar.sql`; keep it distinct from the base schema and check for other feature-specific SQL before altering tables.
- Authentication and authorization are session-based, with role-specific session keys such as `resident_id` and `official_id`. A page's session check and its database-side role/department check are part of the access boundary; follow the relevant module's flow when adding pages or endpoints.
- Lupon case scheduling and calendar events are coupled: `Lupon_Office/includes/calendar_helpers.php` synchronizes the case's next schedule with calendar events. Use these helpers from case/calendar mutations rather than duplicating scheduling logic.
- Browser assets are generally kept beside their module (`style`, `css`, `js`, or `assets`). Keep module-specific changes with that module and preserve its existing page/endpoint flow.

## Build, test, and lint

- There is no root `package.json`, application Composer manifest, or configured application test suite. Run the application through Apache/PHP with a configured MySQL/MariaDB database; do not assume a local database dump or upload data is a distributable schema.
- PHP syntax-check one changed file from the repository root:
  `& C:\xampp\php\php.exe -l .\path\to\file.php`
- PHP syntax-check application files from the repository root:
  `Get-ChildItem -Path . -Recurse -Filter *.php -File | Where-Object { $_.FullName -notmatch '\\\.git\\' } | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName; if ($LASTEXITCODE -ne 0) { throw "PHP syntax error: $($_.FullName)" } }`

## Repository-specific conventions

- PHP pages commonly own both rendering and request handling. Before extracting or relocating logic, check the matching form, browser JavaScript, and backend endpoint together; preserve their existing field names, response shapes, and redirects.
- Use the module's established session checks and MySQLi connection. For database operations, follow the existing prepared-statement patterns and bind values with the correct types; schema changes must account for every role/service that reads or writes the affected table.
- Include shared PHP files using paths anchored to the current file (`__DIR__`) where the surrounding module already does so; nested directories make working-directory-relative paths fragile.
- Forms and state-changing endpoints may rely on session CSRF tokens. Preserve the token generation, submission, and verification flow when modifying those requests.
- Keep Lupon calendar event mutations and case schedule synchronization consistent in both directions. Calendar API handlers also use the shared calendar guard; retain its authentication and request checks.
- Keep secrets and local-only configuration out of commits. `.gitignore` excludes local configuration/data paths; check ignore rules before adding or relying on local files as shared project setup.
