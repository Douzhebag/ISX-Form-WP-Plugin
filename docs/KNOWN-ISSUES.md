# Known Issues — backlog for Phase 2

Collected during Phase 1 (v0.7.0 — Testing & CI Foundation). **Nothing here
was fixed in Phase 1** (tooling/tests only); each item lists where it lives
and the suggested Phase 2 remedy. Bugs are reported, not patched.

## A. Bugs found while writing tests

### A1. ~~Unchecked checkboxes pass `null` to `sanitize_text_field()` (PHP 8.1 deprecation)~~ — **Resolved in Phase 2.7**

- **Was:** `src/Admin.php` — `register_global_settings()`. Checkbox options
  (`isxf_smtp_enable`, `isxf_captcha_required`, `isxf_admin_notify_enable`,
  `isxf_smtp_disable_ssl_verify`) submit nothing when unchecked, so WordPress
  called `sanitize_text_field(null)` → PHP 8.1 deprecation.
- **Fix:** the four checkbox options now register with
  `Admin::sanitize_checkbox()` (`$value === 'yes' ? 'yes' : ''`). Stored
  values are unchanged: `'yes'` when checked, `''` when unchecked (the
  previous effective result of `sanitize_text_field(null)`).

### A2. ~~Unguarded superglobal reads in `handle_secure_submission()`~~ — **Resolved in Phase 2.7**

- **Was:** `src/Ajax/SubmissionController.php` (split out of `AjaxHandler`).
  `intval( $_POST['isxf_form_id'] )` without `isset()` and two unguarded
  `$_SERVER['REMOTE_ADDR']` reads in the CAPTCHA verify payloads.
- **Fix:** `intval( $_POST['isxf_form_id'] ?? 0 )` and
  `$_SERVER['REMOTE_ADDR'] ?? ''`. Behavior unchanged: a missing form ID
  still resolves to `get_post_meta(0, …)` → empty → request rejected.

### A3. `ISXF_Crypto::encrypt()` failure path is not unit-testable

- **Where:** `src/Crypto.php:61` — `function_exists( 'openssl_encrypt' )`.
- **What:** The "openssl missing → return WP_Error, never plaintext" branch
  cannot be exercised without modifying the class (global `function_exists`
  has no override seam). The unit test documents this as `markTestIncomplete`.
- **Remedy:** Phase 2 may add a filter seam (e.g. `isxf_crypto_available`) or
  wrap the openssl calls so availability can be simulated in tests.

### A4. No bugs found in the CIDR/trusted-proxy logic

Writing boundary tests for `ip_matches_cidr()` (IPv4 /0 /17 /24 /32, IPv6 /0
/44 /128, garbage input) initially produced two failing *test expectations*;
on review the implementation was correct in both cases and the test data was
fixed. The Phase 0 implementation is sound.

### A5. `text DEFAULT ''` in the entries-table schema trips dbDelta on strict MySQL

- **Where:** `advanced-secure-form.php` — `isxf_create_db_table()`
  (`admin_note text DEFAULT '' NOT NULL`).
- **What:** On MySQL 5.7 / 8.0 with strict sql_mode, literal defaults on
  TEXT/BLOB columns are rejected (error 1101). The initial `CREATE TABLE`
  still succeeds via dbDelta, but every subsequent `isxf_create_db_table()`
  run (activation, each DB-version upgrade) makes dbDelta issue
  `ALTER COLUMN admin_note SET DEFAULT ''`, which fails with error 1101 and
  is recorded as a `$wpdb` error. Harmless (no schema drift), but noisy in
  logs and in the integration-test output.
- **Remedy (Phase 2/3 — schema change, so it needs an `ISXF_DB_VERSION`
  bump + migration test per project policy):** drop the literal default from
  the TEXT column (`admin_note text NOT NULL`).


## B. phpcs rules deferred to Phase 2

`phpcs.xml.dist` establishes a green baseline (WordPress-Core + WordPress-Docs)
with a documented exclusion list. Full details live in comments inside
`phpcs.xml.dist`; summary:

- **Formatting (conflicts with the codebase's existing consistent style —**
  4-space indent, short array syntax, non-Yoda, single-line guards**):**
  ~22 sniff families excluded. Phase 2 decides on a one-time `phpcbf`
  normalization instead of drive-by reformatting.
- **`WordPress.WP.I18n`** — ~~hardcoded Thai strings + text-domain mismatch~~
  **Resolved in Phase 2.5** (sniff re-enabled, runs clean): plugin header text
  domain fixed to `insightx-form`, all user-facing strings wrapped with
  English msgids, bundled `languages/insightx-form-th.po/.mo` preserves the
  Thai UI, and JS strings are localized via `wp_localize_script` i18n maps.
- **`WordPress.DateTime`** — ~~`date()`/`strtotime()`/`current_time(timestamp)`
  instead of `wp_date()`~~ **Resolved in Phase 2.7** (sniff re-enabled, runs
  clean): all date handling in `src/` and `templates/` now uses
  `wp_date()` / `current_datetime()` / `DateTimeImmutable` with
  `wp_timezone()`; a shared `isxf_local_datetime_to_timestamp()` helper
  parses the WP-local `created_at` strings for `human_time_diff()`/display.
- **`WordPress.DB.DirectDatabaseQuery` / `PreparedSQL` / `PreparedSQLPlaceholders`**
  — direct `$wpdb` everywhere by design pre-refactor; Phase 2 scope item 3
  (repository layer). `PreparedSQLPlaceholders` exclusion covers one false
  positive (dynamic `%d` placeholders in `src/Entries.php:102`).
- **`WordPress.Security.*` (not part of WordPress-Core; ship in Extra):**
  - `EscapeOutput` — 86 pre-existing violations, mostly false positives from
    HTML-heavy templates. Re-enable after the view split.
  - `NonceVerification` — 42 pre-existing warnings; nonces ARE verified, the
    sniff cannot follow `wp_verify_nonce()` across methods.
  - `ValidatedSanitizedInput` — `MissingUnslash` plus a few
    `InputNotSanitized` false positives (dynamic keys sanitized downstream).
  - `SafeRedirect` — ~~4 × `wp_redirect()`~~ **Resolved in Phase 2.7** (sniff
    enabled, runs clean): all redirects are `wp_safe_redirect()`; the OAuth
    consent-screen redirect whitelists the provider host via
    `allowed_redirect_hosts` for that one external redirect.
  - `PluginMenuSlug` — **active**, runs clean.
- **`WordPress.PHP.NoSilencedErrors`** — intentional `@inet_pton()` in
  `ip_matches_cidr()` (false return is the error signal).
- **`Universal.Operators.StrictComparisons` / `WordPress.PHP.StrictInArray`** —
  1–2 pre-existing loose comparisons.
- **`WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase`** —
  false positives on PHPMailer properties (`$phpmailer->Host`, …).
- **`Squiz.Commenting`** — docblock coverage written file-by-file in Phase 2.

## C. Test-coverage gaps accepted in Phase 1

- `ISXF_Admin` settings page rendering and `ISXF_Entries` admin UI are covered
  only indirectly (submission/migration tests touch their data layer). Full
  coverage after the Phase 2 view split, when rendering is separable.
- reCAPTCHA/Turnstile HTTP verification is not tested (would need
  `wp_remote_post` interception); the fail-close path IS tested.
- OAuth flow (`src/OAuth.php`) untested — HTTP-bound; candidate for
  Phase 2 with a transport seam.
