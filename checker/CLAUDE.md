# KDS - Kitchen Display System

## Project Overview
Web-based Kitchen Display System for restaurants. Shows active and recently finished order rows from a POS database (StoreSales/Belive POS schema). Staff use it to mark orders as finished (checkout) or undo finishes.

## Tech Stack
- **Backend**: PHP 8.4+, MySQLi (no PDO), no Composer
- **Database**: MySQL (StoreSales schema, POS tables)
- **Server**: IIS FastCGI on Windows; `web.config` handles routing
- **Frontend**: Vanilla JS, no build step

## Key Files
| File | Purpose |
|------|---------|
| `config.php` | DB config, app constants, `jsonResponse()`, `getDbConnection()` |
| `api_checker.php` | Main API — all AJAX actions (list, checkout, undo, out-of-stock, etc.) |
| `checker.php` | HTML entry point / UI shell |
| `auth_check.php` | Session-based auth gate; called at bootstrap |
| `screen_lock.php` | PIN lock screen endpoint |
| `settings.local.php` | Per-instance config (DB creds, computer ID, thresholds) |

## DB Configuration
Priority order (first wins):
1. Environment variables: `KDS_DB_HOST`, `KDS_DB_PORT`, `KDS_DB_NAME`, `KDS_DB_USER`, `KDS_DB_PASS`
2. Keys in `settings.local.php`: `db_host`, `db_port`, `db_name`, `db_user`, `db_pass`

Default port: **3307**.

## Critical Constraints

### Never touch the schema
- **DO NOT** `ALTER TABLE`, `CREATE TABLE`, or add columns. The DB is owned by the POS vendor.
- Use `columnExists($conn, $table, $column)` before referencing optional columns (e.g. `IsMoveOrder`).

### HTTP status codes
- **Never** return 4xx or 5xx — IIS intercepts them and replaces the body with an HTML error page.
- Always use HTTP 200. Error info goes in the JSON payload (`success: false`).
- All responses must go through `jsonResponse()` which clears all ob buffers before output.

### Output buffering
- `ob_start()` runs at the very top of `api_checker.php` and `config.php` requires.
- `jsonResponse()` calls `while (ob_get_level()) ob_end_clean()` before writing output.
- Never `echo` or `print` outside of `jsonResponse()`.

## Key PHP Patterns
- `requestString($key)` / `requestInt($key)` — safe `$_REQUEST` accessors
- `jsonResponse($payload)` — sole output path; exits after sending
- `columnExists($conn, $table, $col)` — guards optional POS columns
- `buildStats()`, `fetchActiveRows()`, `fetchFinishedRows()` — core data layer
- `applyCheckoutSplit()` — handles partial-qty checkout with INSERT+UPDATE

## Development
- Active branch: `claude/check-project-files-RNPHG`
- No test suite; manual testing against a dev DB instance.
