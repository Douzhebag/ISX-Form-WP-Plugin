# InsightX Form — ขั้นตอนการ Release

> เอกสารนี้คือกระบวนการ release มาตรฐานของปลั๊กอิน (ออกแบบตาม ROADMAP Phase 3)
> ทุก release ต้องทำครบทุกขั้น — ห้าม tag โดยข้ามขั้นตอน

---

## 1. หลักการ

- ปลั๊กอิน deploy เป็น **raw git checkout** ผ่าน [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) ที่ดึง zip จาก GitHub Release — **ไม่มี composer/npm build step บน production**
- เมื่อ push tag `vX.Y.Z` ขึ้น GitHub → workflow `.github/workflows/release.yml` จะ build zip (ตาม `.distignore`) แล้วแนบเข้า GitHub Release อัตโนมัติ → plugin-update-checker บนเว็บลูกค้าเห็นอัปเดตเอง
- **ทุก release ต้องทดสอบ upgrade จากเวอร์ชันก่อนหน้าบนข้อมูลจริง (staging) ก่อน tag** — ตามนโยบายใน `docs/ROADMAP.md` §8
- Versioning ตาม SemVer: patch = hotfix, minor = refactor/foundation, major = ตัด BC

## 2. ขั้นตอน Release (ทำตามลำดับ)

### 2.1 Bump version (2 จุดเสมอ)

ใน `advanced-secure-form.php`:

1. บรรทัด header `Version: X.Y.Z`
2. ค่าคงที่ `ISXF_PLUGIN_VERSION`

ทั้งสองจุดต้องตรงกันและตรงกับ tag ที่จะออก

### 2.2 ย้าย CHANGELOG

ใน `CHANGELOG.md`:

1. ย้ายเนื้อหาใต้ `## [Unreleased]` ไปไว้ใต้หัวข้อใหม่ `## [X.Y.Z] - YYYY-MM-DD` (จัดกลุ่ม Added/Changed/Deprecated/Fixed/Security ตาม Keep a Changelog)
2. เหลือ `## [Unreleased]` ว่างไว้บนสุด
3. อัปเดต link definitions ท้ายไฟล์ (`[Unreleased]: compare/vX.Y.Z...HEAD` และเพิ่มลิงก์ของเวอร์ชันใหม่)

### 2.3 รัน tests + lint บนเครื่องตัวเอง

```bash
vendor/bin/phpunit     # ต้องเขียวทั้ง Unit + Integration
vendor/bin/phpcs -q    # ต้อง exit 0
```

ถ้ามี string ใหม่ที่แปลได้ ให้ regenerate ไฟล์แปลก่อน:

```bash
wp i18n make-pot . languages/insightx-form.pot --domain=insightx-form
msgmerge --update --backup=none languages/insightx-form-th.po languages/insightx-form.pot
# ...เติม msgstr ภาษาไทยของ string ใหม่...
msgfmt -c -o languages/insightx-form-th.mo languages/insightx-form-th.po
```

### 2.4 ทดสอบ upgrade บน staging (บังคับ)

1. ติดตั้งปลั๊กอิน **เวอร์ชันก่อนหน้า** บน staging ที่มีข้อมูลจริง (forms, entries, SMTP settings)
2. อัปเดตขึ้นเวอร์ชันใหม่ผ่านช่องทางจริง (GitHub Release / plugin-update-checker)
3. ตรวจด้วยมือให้ครบ: ฟอร์มเดิม render และ submit ได้, entries เดิมอยู่ครบ, อีเมลส่งได้, settings เดิมไม่หาย, ไม่มี PHP warning/error ใน debug log
4. ถ้ามี DB schema change ต้องตรวจว่า migration รันครั้งเดียวและ rollback path ยังอยู่

### 2.5 Tag + push → CI ออก Release ให้

```bash
git tag vX.Y.Z
git push origin vX.Y.Z
```

- GitHub Actions (`release.yml`) จะ build `insightx-form-X.Y.Z.zip` และสร้าง/แนบไฟล์เข้า GitHub Release ของ tag นั้นอัตโนมัติ
- ตรวจใน Actions ว่า job เขียว และใน Releases ว่า zip ถูกแนบมา
- เนื้อหา Release notes: คัดลอก section ของเวอร์ชันนั้นจาก `CHANGELOG.md`
- ภายในไม่กี่ชั่วโมง plugin-update-checker บนเว็บที่ติดตั้งจะเห็นอัปเดต (หรือกด "Check for updates" ในหน้า Plugins)

## 3. ไฟล์ zip ที่ Release ต้องมีอะไรบ้าง

รายการไฟล์ใน zip คุมด้วย `.distignore` ที่ root ของ repo — workflow ใช้ `rsync --exclude-from=.distignore` แล้ว zip โฟลเดอร์ `insightx-form/` (เลือก `.distignore` เพราะเป็น manifest มาตรฐานที่ทั้งคนและ CI อ่านตรงกัน ไม่ต้องเขียน exclusion ซ้ำใน workflow)

**ต้องอยู่ใน zip (deploy จริง):**

| ไฟล์/โฟลเดอร์ | เหตุผล |
|---|---|
| `advanced-secure-form.php` | ไฟล์หลักของปลั๊กอิน |
| `src/` | คลาสทั้งหมด (PSR-4, autoload เอง ไม่ใช้ composer) |
| `templates/` | view templates |
| `assets/` | CSS/JS + bundled libs (flatpickr, Chart.js) |
| `languages/` | `.pot` + `.po`/`.mo` ภาษาไทย |
| `libs/plugin-update-checker/` | ตัวอัปเดตจาก GitHub (bundled vendor code) |
| `README.md`, `CHANGELOG.md` | เอกสารติดไปกับปลั๊กอิน |

**ต้องไม่หลุดเข้า zip (exclude ผ่าน `.distignore`):**

- `.git/`, `.github/` — VCS และ CI config
- `vendor/`, `composer.json`, `composer.lock` — dev dependencies (PHPUnit/WPCS) ห้าม deploy
- `tests/`, `phpunit.xml.dist` — test suite
- `bin/` — สคริปต์ติดตั้ง test suite สำหรับ dev/CI
- `docs/` — เอกสารภายในทีม (ROADMAP, KNOWN-ISSUES, ไฟล์นี้)
- `phpcs.xml.dist` — lint config
- `.distignore`, `.gitignore`, `*.zip`, `.DS_Store` — ไฟล์ระบบ/build artifact

### 3.1 ตรวจ zip ก่อน tag (แนะนำ)

จำลอง build บนเครื่องด้วยคำสั่งเดียวกับ workflow:

```bash
BUILD_DIR="$(mktemp -d)"
mkdir -p "$BUILD_DIR/insightx-form"
rsync -a --exclude-from=.distignore ./ "$BUILD_DIR/insightx-form/"
( cd "$BUILD_DIR" && zip -qr /tmp/insightx-form-test.zip insightx-form )
unzip -l /tmp/insightx-form-test.zip
```

ตรวจว่าไม่มี `vendor/`, `tests/`, `docs/`, `.github/`, `bin/`, `composer.*` หลุดเข้าไป

## 4. Hotfix (security)

- แก้บน branch เดิม ออกเป็น **patch release** ทันที ไม่รอรวมเฟส (ตามนโยบาย ROADMAP §8)
- ขั้นตอนเหมือน release ปกติทุกประการ แค่ CHANGELOG ระบุในกลุ่ม `### Security`
