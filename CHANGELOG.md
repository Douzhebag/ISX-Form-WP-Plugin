# Changelog

All notable changes to InsightX Form are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Note:** This file is the canonical changelog as of v0.8.0. `README.md`
> still carries its own (Thai) changelog section — the two will be deduplicated
> in a later docs pass.

## [Unreleased]

### Fixed

- Analytics dashboard stuck on "Loading…" forever: `AnalyticsController` used `DateTimeImmutable` unqualified inside the `ISXF\Ajax` namespace, so the endpoint fataled (class not found) and the JS only logged to the console. The endpoint is fixed, `isxf-analytics.js` now checks HTTP status and shows a visible error state instead of hanging, and a new `AnalyticsTest` integration suite covers the endpoint (structure, custom range, nonce rejection).

## [0.8.0] - 2026-07-27

Phase 0–3 consolidation release: the v0.6.1 security hotfix plus the full
testing/CI foundation (Phase 1), the structural refactor, i18n and
accessibility work (Phase 2), and the legacy-deprecation announcement and
release-process automation (Phase 3) ship together as the first tagged
release since v0.6.0.

### Added

- Per-form submit button style: a 🎨 "Submit Button Style" panel in the form builder meta box with color pickers for background/text/hover (`_isxf_form_button_color`, `_isxf_form_button_text_color`, `_isxf_form_button_hover_color`), border-radius and font-size fields, and an Advanced CSS box (`_isxf_form_button_css`, sanitized against `</style>` breakouts, `expression()`, `javascript:`, `@import`). Structured fields render as CSS custom properties on the form container (empty = stylesheet default; explicit hover color wins over the auto-computed 15%-lighter shade); advanced CSS is output as a `<style>` block scoped to `.isxf-form-{id} .isxf-submit-btn`. The disabled state uses the button color at 55% opacity, and the hover rule's specificity was raised past page-builder hover rules (e.g. Elementor kit CSS).
- PHPUnit test suite: pure unit tests (crypto, trusted-proxy/CIDR logic) that run without WordPress, plus integration tests (legacy migration/upgrade path, AJAX submission flow, uninstall, crypto with real salts) on the WordPress test suite.
- GitHub Actions CI: phpcs lint, unit tests (PHP 8.1–8.3), integration tests against MySQL (PHP 8.1–8.3 × WP latest).
- `composer.json` with dev tooling (PHPUnit 9.6, wp-phpunit, WPCS, PHPCompatibility) and `test` / `test:unit` / `lint` / `lint:fix` scripts.
- `phpcs.xml.dist` (WordPress-Core + WordPress-Docs) with a documented, minimal exclusion list.
- `bin/install-wp-tests.sh` for local and CI test-suite setup.
- `docs/KNOWN-ISSUES.md` — known bugs and deferred phpcs rules.
- Release automation: `.github/workflows/release.yml` builds the distributable plugin zip (per `.distignore`) and attaches it to the GitHub Release on every `v*` tag push; `docs/RELEASING.md` documents the release procedure.
- Deprecation announcement for the v1.0 legacy removal: README section plus a dismissible admin notice (shown only on plugin screens to administrators, and only on sites that came from the legacy `acf` era — detected via the `isxf_legacy_acf_migration_done` flag; clean installs never see it).
- Email template tools in the form builder meta box: a "👁️ Preview" button renders the selected customer-reply template (booking/inquiry/custom) through the real server-side pipeline with type-aware sample data generated from the builder's current (even unsaved) fields, shown in a modal iframe; a "✏️ Edit from this template" button (booking/inquiry) copies the template's inner body markup — merge tags intact, via a new `$body_only` render variant of `templates/emails/booking.php` / `inquiry.php` — into the custom editor (with overwrite confirmation); and a "🧪 Send a test email to me" button sends the sample-data rendering to the current admin user through the plugin's SMTP configuration (🧪-prefixed subject, inline success/failure). Backed by the new `ISXF\Ajax\EmailToolsController` (`wp_ajax_isxf_preview_email` / `wp_ajax_isxf_send_template_test`, nonce + `edit_posts` gated), registered via the `AjaxHandler` facade.
- Plugin headers: `Requires at least: 6.0`, `Requires PHP: 8.1`, `License: GPLv2 or later`, `License URI`.

### Changed

- Phase 2.1 refactor: plugin classes moved from `includes/class-isxf-*.php` to PSR-4 `src/` under the `ISXF\` namespace (`Crypto`, `OAuth`, `OAuthTokenProvider`, `Admin`, `Frontend`, `AjaxHandler`, `Entries`), loaded via a runtime `spl_autoload_register` autoloader in the main file (no Composer dependency on production). Old global class names (`ISXF_Crypto`, …) keep working via `class_alias`. Classes are now instantiated on `plugins_loaded` via `isxf_bootstrap()` instead of at file load. No behavior change.
- Phase 2.3 refactor: new `ISXF\Repository\EntryRepository` (`src/Repository/EntryRepository.php`) owns all SQL touching the `isxf_form_entries` table — submission insert, filtered/paginated list reads, status/note updates, single & bulk deletes, CSV export batch reads, and every analytics/dashboard aggregation. `Entries` and `AjaxHandler` now call the repository instead of direct `$wpdb`; the entries table name is resolved centrally via `EntryRepository::table_name()` (also used by the main file's schema/migration/uninstall code). No query shape or behavior change.
- Caching for entry aggregates: status counts and analytics/dashboard aggregates are cached in the non-persistent `isxf` wp_cache group with a version-salt scheme — every cache key embeds `isxf_entries_cache_version`, which is bumped on every entry mutation (submission insert, status/note update, delete, bulk delete, legacy import). No TTL-based invalidation.
- The entries-page form filter dropdown (`get_posts(..., -1)`) is now served from a cached ID=>title list in the `isxf` group, invalidated on `save_post_isxf_form`, `deleted_post`, `trashed_post` and `untrashed_post`. The unbounded `-1` semantics are kept intentionally (sites have few forms).
- Phase 2.2 refactor: all inline HTML moved out of `src/` into `templates/` (16 template files for the settings page, entries page, analytics page, dashboard widget, docs page, form builder meta box, frontend form fields, and email templates), loaded via the new `ISXF\Template` view loader. Byte-identical rendered output (verified with a render-capture harness).
- Phase 2.4 refactor: `ISXF\AjaxHandler` split into focused controllers under `src/Ajax/` (namespace `ISXF\Ajax`) — `SubmissionController` (public form submission incl. honeypot/CAPTCHA fail-close/rate limit and the phpmailer SMTP configuration, Basic + XOAUTH2), `SettingsController` (SMTP test email), `EntriesController` (entry status/note) and `AnalyticsController` (`isxf_get_analytics_data`, moved out of `Entries`). Shared pieces (client-IP/trusted-proxy, email header style, mail/SMTP helpers) live in `ISXF\Ajax\AbstractAjaxController`. `ISXF\AjaxHandler` remains as a slim facade that instantiates the controllers, so hook names/registration, JSON responses, nonce/capability checks, sanitization and the `ISXF_AJAX_Handler` alias are all unchanged.
- Phase 2.5 i18n: full internationalization. Plugin header text domain fixed (`InsightX` → `insightx-form`); every user-facing string in `src/` and `templates/` converted from hardcoded Thai to English msgids wrapped in the proper i18n functions (`__`/`esc_html__`/`esc_attr__`/`esc_js`, `sprintf` for interpolated strings with `translators:` comments, `_x` where contexts collide); the previous Thai UI is preserved verbatim as the bundled translation `languages/insightx-form-th.po/.mo` (generated from `languages/insightx-form.pot`), so Thai-locale sites see no change. Hardcoded Thai in the four JS bundles moved into `wp_localize_script` i18n maps (`isxf_env`, `isxf_admin_env`, `isxf_entries_env`, `isxf_analytics_env`). The `WordPress.WP.I18n` phpcs sniff is re-enabled and runs clean. New `I18nTest` integration tests verify the bundled `.mo` loads and renders Thai (English fallback without it); `SubmissionTest` now loads the shipped `.mo` to assert the Thai user-facing messages.
- Phase 2.6 form accessibility: the rendered frontend form is now screen-reader and keyboard friendly. Labels are associated with inputs via unique ids (`isxf-field-{form_id}-{name}` + `for`); radio/checkbox groups render as `fieldset`/`legend`; required fields carry `aria-required="true"` (deliberately *not* the native `required` attribute — the form is `novalidate` so browser popups cannot fight the JS submit flow, which keeps gating the submit button via `data-required`). Each field has a linked (`aria-describedby`), initially hidden inline error element (`role="alert"`) that the JS fills on validation failure and clears on input; invalid fields are marked `aria-invalid="true"` with a red border. Submit-time client-side validation (required fields + email format) shows inline errors plus a summary toast and focuses the first invalid field; server-side field errors (e.g. "Please fill in: X") are mapped back to the matching field by label. The toast container is a live region (`role="status"` + `aria-live="polite"`), and the disabled submit button now has a visible, `aria-describedby`-linked hint ("fill in all required fields") that hides once the form is valid. Field names, existing CSS classes and the submission flow are unchanged. Four new i18n strings (3 in the `isxf_env` JS map, 1 submit hint in the form template) added to the POT/`th` translation. New `FrontendRenderTest` integration tests assert the a11y markup.
- Phase 2.7 hardening & cleanup: all date/time handling migrated from `date()`/`strtotime()`/`current_time()` to `wp_date()`/`current_datetime()`/`DateTimeImmutable` with `wp_timezone()` (new shared `isxf_local_datetime_to_timestamp()` helper for the WP-local `created_at` strings; the `WordPress.DateTime` phpcs sniff is re-enabled and runs clean). Displayed values are unchanged on typical sites (WP-local timezone); the one intentional behavior fix is the CSV export filename, now stamped in site-local time instead of the server timezone. Static assets are bundled instead of CDN-loaded: flatpickr 4.6.13 (js/css + Thai locale, already in `assets/libs/flatpickr/`) now enqueues locally and the jsDelivr `document.write` fallback/dns-prefetch were deleted; Chart.js 4.4.1 UMD is vendored to `assets/libs/chartjs/chart.umd.min.js` (source URL noted at the enqueue) and enqueued locally. reCAPTCHA/Turnstile stay on their vendors' hosts (vendor requirement). The unused `jquery` script dependency was dropped from the frontend enqueue (`isxf-frontend.js` is vanilla). The single-entry delete on the entries page is now a POST submit button (nonce-verified, styled as the old link) instead of a state-changing GET link; bulk actions were already POST. All `wp_redirect()` calls are now `wp_safe_redirect()` — the OAuth consent-screen redirect whitelists the provider host via `allowed_redirect_hosts` — and the `WordPress.Security.SafeRedirect` sniff is enabled and runs clean. Checkbox settings (`isxf_smtp_enable`, `isxf_captcha_required`, `isxf_admin_notify_enable`, `isxf_smtp_disable_ssl_verify`) got a null-safe `sanitize_checkbox()` callback (stored values unchanged: `'yes'`/`''`), fixing the PHP 8.1 `sanitize_text_field(null)` deprecation (KNOWN-ISSUES A1); unguarded `$_POST['isxf_form_id']`/`$_SERVER['REMOTE_ADDR']` reads in the submission controller were null-coalesced (KNOWN-ISSUES A2).
- Database version bumped to 1.1 — indexes added on `form_id`, `entry_status`, `created_at` via dbDelta.

### Deprecated

- The `[advanced_form]` shortcode alias, the legacy `acf_*` → `isxf_*` auto-migration, and the legacy `ENC:` crypto format are deprecated and will be **removed in v1.0**. Upgrading to v1.0 will require passing through v0.8.x first — do not skip v0.8.x when coming from ≤ v0.7.

### Fixed

- Submit button styling no longer loses to theme/page-builder CSS: the `.isxf-submit-btn` rules are now scoped under `.isxf-form-container` so they outrank rules like Elementor's `.elementor-kit-N button` (which was overriding both the color and border-radius).
- Thai-locale sites rendering the whole plugin in English — two compounding causes: (1) the bundled Thai translation files were named `insightx-form-th_TH.*` but the WordPress Thai locale code is `th` (a site set to ไทย looks for `insightx-form-th.mo`), so the translation never matched; files renamed to `insightx-form-th.po/.mo`. (2) `Entries` built its translated status map in the constructor, which runs at `plugins_loaded` (before `init`); WordPress's just-in-time loader then cached a NOOP translation for the entire `insightx-form` domain before the textdomain path was registered — the status map is now built lazily on first use (regression test: `EntriesLazyStatusMapTest`).
- Legacy `acf_` → `isxf_` migration now runs once (guarded by `isxf_legacy_acf_migration_done` flag) instead of on every admin page load, and renames only the 4 whitelisted meta keys instead of a blanket `_acf_` prefix replace that could clobber other plugins' meta.
- Uninstall now removes everything: `isxf_form` posts, `_isxf_*` post meta, and rate-limit/OAuth transients (previously left behind).

### Security

- Crypto: new `ENC3:` format — AES-256-CBC with random IV + HMAC-SHA256 (Encrypt-then-MAC). Encryption failure now returns `WP_Error` instead of silently storing plaintext. `ENC2:` and legacy `ENC:` values remain decryptable.
- Client-IP detection: proxy headers (`CF-Connecting-IP`, `X-Forwarded-For`, `X-Real-IP`) are only trusted when `REMOTE_ADDR` matches a configurable trusted-proxy list (option `isxf_trusted_proxies` / filter, IPv4/IPv6 CIDR). Default trusts no proxy — closes the rate-limit IP-spoofing hole.
- CAPTCHA now fails closed: new `isxf_captcha_required` option (default on) blocks all submissions when the selected CAPTCHA service has no secret key configured, with a persistent admin notice.
- JS XSS fixes: `showToast()` uses `textContent` instead of `innerHTML`; unescaped `time_ago` output in analytics is escaped.

## [0.6.0] - 2026-07-10

### Added

- OAuth2 SMTP (XOAUTH2) for Google and Microsoft 365 — send email without username/password, automatic refresh tokens, CSRF `state` protection throughout the flow.
- In-app "คู่มือการใช้งาน" (user guide) admin page with accordion documentation.
- SMTP preset dropdown (Gmail / Microsoft 365 / Custom) with host/port auto-fill.
- GitHub-based auto-update via plugin-update-checker with release assets.

### Changed

- Encryption upgraded to `ENC2:` format (random IV per encryption) for SMTP password and OAuth secret/refresh token, with automatic migration of old values.

## [0.5.4] - 2026-02-27

### Fixed

- SMTP test button on the settings page not working (hook name mismatch with submenu slug).
- Legacy settings (SMTP, CAPTCHA, admin notification) automatically restored after the prefix change.

## [0.5.3] - 2026-02-27

> Retroactively documented (present in git history, missing from the README changelog).

### Fixed

- Hotfix: migrate legacy `acf_*` options to `isxf_*` for SMTP/CAPTCHA settings.

## [0.5.2] - 2026-02-27

### Fixed

- Hotfix: restored the legacy `[advanced_form]` shortcode alias so existing embedded forms keep working, and restored the CPT admin list view after the prefix change.

## [0.5.1] - 2026-02-27

### Fixed

- Hotfix: automatic database migration copying `acf_form_entries` rows into the new `isxf_form_entries` table (old data appeared lost after upgrading to 0.5.0).

## [0.5.0] - 2026-02-27

### Changed

- Prefix changed to `ISXF_` throughout to avoid collisions with other plugins.
- i18n groundwork (`insightx-form` text domain) for future multi-language support.
- Improved Flatpickr loading with local fallback when the CDN is unavailable.
- Submit-button accessibility improvements (`aria-busy`, `aria-label`).

### Added

- Central `isxf_log_error` logging for email-send failures.

## [0.4.1] - 2026-02

> Retroactively documented — this version shipped (per the README encryption
> section and the `@since 0.4.1` tag in `class-isxf-crypto.php`) but was never
> added to the README changelog. Exact release date unknown; between v0.4.0
> and v0.5.0.

### Changed

- Centralized `ISXF_Crypto` utility; encryption upgraded to `ENC2:` format (random IV via `openssl_random_pseudo_bytes` per encryption) replacing the legacy deterministic-IV `ENC:` format. Old values auto-migrate; `ENC:` support kept decrypt-only.

## [0.4.0] - 2026-02-26

### Added

- Analytics dashboard: stat cards, submissions-per-day line chart, status-share doughnut chart, top forms.
- Chart.js integration with animated transitions.
- Date-range filter (7/30/90/365 days or custom range) and automatic period-over-period comparison.
- Database migration system with version tracking and auto-upgrade.
- CSS/JS extraction from inline blocks into separate cacheable files.

### Fixed

- Error logging to `debug.log` when email sending fails.

## [0.3.0] - 2026-02-26

### Added

- SMTP password encryption (AES-256-CBC) before storage, with auto-migration of existing passwords.
- SSL verification enabled by default, with a dev-only toggle.
- Custom email templates (subject + body) with merge tags: `{site_name}`, `{form_title}`, `{all_fields}`, `{field:ชื่อ}`.

## [0.2.1] - 2026-02-24

### Added

- Dashboard widget with data overview.
- Entries management (statuses, notes, filtering, search).
- SMTP + admin notification emails.
- CAPTCHA support (reCAPTCHA v3 + Cloudflare Turnstile).

[Unreleased]: https://github.com/Douzhebag/ISX-Form-WP-Plugin/compare/v0.8.0...HEAD
[0.8.0]: https://github.com/Douzhebag/ISX-Form-WP-Plugin/compare/v0.6.0...v0.8.0
[0.6.0]: https://github.com/Douzhebag/ISX-Form-WP-Plugin/releases/tag/v0.6.0
