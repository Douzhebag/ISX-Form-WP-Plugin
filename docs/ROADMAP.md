# InsightX Form — Roadmap ระยะยาว (v0.6.x → v1.0)

> เอกสารนี้คือแผนที่ตกลงร่วมกันก่อนเริ่มพัฒนา ทุกเฟสต้องผ่าน Acceptance Criteria ก่อนขึ้นเฟสถัดไป
> สร้างจากการ audit โค้ด v0.6.0 (2026-07)

---

## 1. บริบทและข้อจำกัด (Constraints)

| ข้อจำกัด | ผลกระทบต่อการพัฒนา |
|---|---|
| ใช้เอง/ลูกค้าเฉพาะทีม แจกผ่าน GitHub เท่านั้น | ไม่ต้องทำ readme.txt แบบ wp.org, ไม่ต้อง SVN; ใช้ plugin-update-checker + GitHub Release ต่อได้ |
| **มีข้อมูล production จริง ห้ามพัง** | Migration ทุกชั้นต้องอยู่ครบ, ทุก release ต้องผ่าน upgrade-path test, ทุก DB change ต้อง rollback ได้ |
| ไม่มี tests/CI เดิมเลย | ต้องวางรากฐานการทดสอบก่อน refactor ใหญ่เสมอ |
| ทีมเล็ก | หลีกเลี่ยง over-engineering; เลือกเครื่องมือมาตรฐานที่ดูแลน้อย (PHPUnit + WP test suite, phpcs WPCS, GitHub Actions) |
| **PHP ขั้นต่ำ 8.1** (ตกลงแล้ว) | Refactor ใช้ typed properties/readonly/enum ได้เต็มที่; hosting ลูกค้าคุมเองได้ |
| **Bundle assets ทั้งหมดเข้าปลั๊กอิน** (ตกลงแล้ว) | ไม่พึ่ง CDN ภายนอก (ยกเว้น reCAPTCHA ที่ Google บังคับโหลดจากโดเมนตัวเอง); ตัดปัญหา SRI/`document.write` fallback ออกไปเลย |

## 2. ปัญหาหลักที่ต้องแก้ (สรุปจาก audit)

### กลุ่ม A — เสี่ยงต่อข้อมูล/ความปลอดภัย (ต้องแก้ก่อนเสมอ)
1. `advanced-secure-form.php:62-88` — migration query รันทุก admin page load นอกเงื่อนไข version check และ `REPLACE(meta_key,'_acf_','_isxf_')` อาจชน meta ของปลั๊กอินอื่น
2. `class-isxf-crypto.php:40,46` — encrypt ล้มเหลวแล้ว fallback เป็น plaintext เงียบๆ; AES-256-CBC ไม่มี HMAC
3. `class-isxf-ajax-handler.php:19-36` — เชื่อ `HTTP_CF_CONNECTING_IP`/`X-Forwarded-For` โดยไม่ตรวจ trusted proxy → IP spoofing ทะลุ rate limit
4. `class-isxf-ajax-handler.php:335,350` — CAPTCHA fail-open เมื่อ secret ว่าง
5. `assets/js/isxf-frontend.js:13` — `showToast()` ใช้ `innerHTML` กับ message จาก server (XSS); `isxf-analytics.js:337` ยังเหลือ `time_ago` ไม่ escape
6. `advanced-secure-form.php:100-135` — uninstall ลบไม่หมด (CPT posts, post meta, transients)
7. ตาราง `isxf_form_entries` ไม่มี index นอกจาก PK

### กลุ่ม B — หนี้โครงสร้าง
- ฟังก์ชันยาว 100-270 บรรทัด HTML ปน logic (`render_settings_page`, `render_page`, `handle_secure_submission` ฯลฯ) ไม่มีการแยก view
- `$wpdb` query กระจายทุกคลาส ไม่มี repository layer, ไม่มี caching (status counts query ซ้ำ 3 จุด, `get_posts(-1)` ไม่จำกัด)
- ไม่มี autoloading (require ตรงๆ + instantiate ทุกคลาสทันที)
- i18n ไม่สมบูรณ์: text domain ขัดกัน (header `InsightX` vs โค้ด `insightx-form`), ข้อความไทย hardcode ทั้ง backend และ JS (admin/entries/analytics), ไม่มี `languages/`
- Accessibility ของฟอร์ม: label ไม่ผูก `for`, input ไม่มี `id`/`required`/`aria-required`, error feedback มีแค่ toast (ไม่มี inline error / `aria-live`)
- ใช้ `date()`/`strtotime()` แทน `wp_date()` เกือบทุกที่

### กลุ่ม C — หนี้ legacy (แบกไว้ แต่ต้องมีแผนตัด)
- shortcode alias `[advanced_form]`
- migration `acf_*` → `isxf_*` 4 ชั้น (ตาราง, CPT, meta, options)
- crypto format `ENC:` (deterministic IV, ถอดอย่างเดียว)
- `maybe_migrate_smtp_password()`

---

## 3. Phase 0 — v0.6.1 Hotfix (เสถียรภาพเร่งด่วน)

**เป้าหมาย:** ปิดรูรั่วที่กระทบข้อมูลจริงโดยไม่เปลี่ยนโครงสร้าง

**Scope:**
1. ห่อ `isxf_maybe_upgrade_db()` ด้วย one-shot flag option (`isxf_legacy_migration_done`) + จำกัด `REPLACE` ให้ชนเฉพาะ meta key ที่รู้จัก (whitelist) ไม่ใช่ prefix เปล่าๆ
2. Crypto: encrypt ล้มเหลว → คืน `WP_Error`/log เตือน ห้ามเก็บ plaintext; เพิ่ม HMAC-SHA256 (Encrypt-then-MAC) ใน format `ENC3:` พร้อมถอด format เก่าได้
3. `get_client_ip()`: เชื่อ proxy headers เฉพาะเมื่อ `REMOTE_ADDR` อยู่ใน whitelist trusted proxy (ตั้งค่าได้ผ่าน option/filter, default = เชื่อ `REMOTE_ADDR` อย่างเดียว)
4. CAPTCHA: เพิ่ม option "บล็อกการส่งเมื่อไม่ได้ตั้งค่า CAPTCHA" (default เปิด) + แสดง admin notice ค้างเมื่อเปิดฟอร์มโดยไม่มี CAPTCHA
5. JS: `showToast()` เปลี่ยนเป็น `textContent`; escape `time_ago` ใน analytics
6. Uninstall: ลบ CPT posts, post meta `_isxf_*`, transients ให้ครบ
7. DB: เพิ่ม index `form_id`, `entry_status`, `created_at` ผ่าน dbDelta (bump `ISXF_DB_VERSION`)

**Acceptance Criteria:**
- อัปเกรดจาก v0.6.0 บนข้อมูลจริงแล้ว entries/settings/ฟอร์มเดิมทำงานเหมือนเดิม 100%
- Secret ที่เข้ารหัสด้วย `ENC2:` เดิมถอดและใช้งานได้ต่อ (SMTP/OAuth ไม่หลุด)
- ทุกข้อ 1-7 มีวิธีทดสอบด้วยมือที่ระบุไว้ใน PR

**ความเสี่ยง:** ข้อ 2 และ 7 แตะ crypto + DB schema → ต้องทดสอบบน staging ที่มีข้อมูลจริงก่อน tag

---

## 4. Phase 1 — v0.7.0 Testing & CI Foundation

**เป้าหมาย:** มีตาสิบตาก่อน refactor — ทุกพฤติกรรมสำคัญมี test คุ้ม

**Scope:**
1. `composer.json` (dev deps: phpunit, wp-phpunit/wp-phpunit, squizlabs/php_codesniffer + WPCS, phpcompatibility)
2. Test suite ขั้นต่ำ:
   - **Migration/upgrade path** — จำลอง DB state ของ v0.5.x, v0.6.0, v0.6.1 แล้วอัปเกรด ยืนยันว่าข้อมูลไม่หาย (สำคัญสุด เพราะมี HOTFIX 3 รอบใน history)
   - **Crypto round-trip** — encrypt/decrypt ทุก format (ENC, ENC2, ENC3), tamper detection
   - **Form submission flow** — nonce, honeypot, rate limit, sanitize ตาม field type
   - **Uninstall** — ลบครบทุก artifact
3. `phpcs.xml` ใช้ WordPress-Core + WordPress-Docs (ยกเว้นกฎที่ขัดกันมาก เช่น text domain — แก้ใน Phase 2)
4. GitHub Actions: รัน phpcs + PHPUnit (matrix PHP 8.1–8.3 × WP latest) ทุก PR
5. `CHANGELOG.md` มาตรฐาน Keep a Changelog + ย้าย changelog จาก README มารวม (เติม v0.4.1 ที่หายไป)

**Acceptance Criteria:**
- CI เขียวบน matrix ทั้งหมด
- Coverage ครอบคลุมทุกเส้นทาง migration ที่เคยพังจริง (acf→isxf ทั้ง 4 ชั้น)
- phpcs ผ่านหรือมี baseline ที่ระบุชัด

**หมายเหตุ:** เฟสนี้ไม่เปลี่ยนพฤติกรรมปลั๊กอินเลย เปลี่ยนแค่ tooling

---

## 5. Phase 2 — v0.8.0 Refactor (โครงสร้าง + i18n + a11y)

**เป้าหมาย:** โค้ดพร้อมรับฟีเจอร์ใหม่โดยไม่สะสมหนี้ — ทำทีละก้อน แต่ละก้อนมี test คุ้มจาก Phase 1

**Scope (เรียงลำดับ ทำทีละ PR):**
1. **Autoload + bootstrap** — PSR-4 ผ่าน Composer (`ISXF\` namespace), lazy instantiate, main file เหลือแค่ wiring
2. **แยก view** — ย้าย HTML ออกเป็น `templates/` (settings page, entries page, form fields, docs page) ใช้ `load_template()` pattern
3. **Repository layer** — `EntryRepository` คุม query ทั้งหมดของตาราง entries + object cache สำหรับ status counts / analytics
4. **แตก AJAX handler** — แยก controller: Submission, Settings (test email), Entries (status/note), Analytics
5. **i18n จริง** — แก้ text domain ให้ตรง (`insightx-form`), ครอบ `__()`/`esc_html__()` ทุกข้อความ, ย้ายข้อความ JS ทั้งหมดเข้า `wp_localize_script`/`wp_set_script_translations`, สร้าง `languages/insightx-form.pot`
6. **Accessibility ของฟอร์ม** — label `for` + input `id`, `required`/`aria-required`, inline error ใต้ field + `aria-live` region, ปุ่ม disable พร้อมข้อความบอกเหตุผล
7. **แก้จุดเล็กที่ค้าง** — `wp_date()` แทน `date()`, ตัด dependency jQuery ที่ไม่ได้ใช้, **bundle Chart.js/flatpickr เข้าปลั๊กอินทั้งหมด** (ตัด CDN + `document.write` fallback ทิ้ง; reCAPTCHA ยังโหลดจาก Google ตามที่บังคับ), destructive action จาก GET เป็น POST

**Acceptance Criteria:**
- Test จาก Phase 1 ผ่านทั้งหมดโดยไม่แก้ test (ยืนยันว่า behavior เดิม)
- ผ่านการทดสอบด้วยมือ: สร้างฟอร์ม → ฝัง shortcode → ส่ง → เช็ค entries/analytics/email ครบ
- ไฟล์ PHP ไม่มีฟังก์ชัน render ยาวเกิน ~50 บรรทัด (HTML อยู่ใน templates)

**สิ่งที่จะไม่ทำในเฟสนี้:** ฟีเจอร์ใหม่ทุกชนิด, เปลี่ยน DB schema

---

## 6. Phase 3 — v1.0.0 ตัด Legacy + ปิดกระบวนการ Release

**เป้าหมาย:** v1.0 สะอาด มีนโยบาย BC ชัดเจน และ release ครั้งต่อไปไม่ต้อง HOTFIX

**Scope:**
1. **นโยบายตัด legacy:** ประกาศใน v0.8.x (README + admin notice) ว่า v1.0 ต้องอัปเกรดผ่าน v0.8+ ก่อน แล้วใน v1.0 ลบ:
   - shortcode alias `[advanced_form]`
   - migration `acf_*` → `isxf_*` ทั้ง 4 ชั้น (เหลือแค่ one-shot guard)
   - crypto `ENC:` legacy (ย้ายข้อมูลที่ยังเป็น ENC ใน v0.8 ก่อน)
   - `maybe_migrate_smtp_password()`
2. **Release process เขียนเป็นเอกสาร** (`docs/RELEASING.md`): version bump → CHANGELOG → tag → GitHub Release (zip build ผ่าน CI) → PUC ดึงอัตโนมัติ
3. เติม plugin header: `Requires at least`, `Requires PHP: 8.1`, `License: GPLv2 or later`
4. CI เพิ่ม job: build zip แนบเข้า GitHub Release อัตโนมัติเมื่อ tag

**Acceptance Criteria:**
- Upgrade path v0.8 → v1.0 ผ่าน test; upgrade ข้ามจาก ≤v0.7 ถูกบล็อกพร้อมข้อความชัดเจน
- Release v1.0.1 (patch ทดสอบ) ออกได้โดยกด tag อย่างเดียว

---

## 7. Feature Backlog (หลัง v1.0 เท่านั้น)

จัดลำดับตามความต้องการจริงของทีม ณ ตอนนั้น:
- File upload field (ต้องออกแบบ storage + security เพิ่ม: MIME whitelist, เก็บนอก webroot หรือ protect ด้วย .htaccess)
- Conditional logic / multi-step form
- Custom validation pattern ต่อ field
- REST API สำหรับดึง entries
- Notification ช่องทางอื่น (LINE Notify / webhook)
- reCAPTCHA score threshold ตั้งค่าได้ (ตอนนี้ hardcode 0.5)

---

## 8. นโยบายที่ใช้ตลอดโปรเจกต์

- **ทุก DB/schema change** ต้อง bump `ISXF_DB_VERSION`, มี migration test, และมีวิธี rollback
- **ทุก security fix** ออกเป็น patch release ทันที ไม่รอรวมเฟส
- **ห้ามเปลี่ยน behavior ใน refactor PR** — refactor ต้องผ่าน test เดิมโดยไม่แก้ test
- **ทุก release ต้องทดสอบ upgrade จากเวอร์ชันก่อนหน้าบนข้อมูลจริง (staging)** ก่อน tag
- Versioning: SemVer — patch = hotfix, minor = refactor/foundation, major = ตัด BC
