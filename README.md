# InsightX Form

![InsightX Form — Build forms. Send emails. Manage entries.](assets/banner-1544x500.png)

ระบบฟอร์มและจัดการข้อมูลลูกค้าสำหรับธุรกิจ — สร้างฟอร์มง่าย ส่งอีเมลอัตโนมัติ (รองรับ OAuth2) จัดการข้อมูลครบจบในที่เดียว

**Version:** 0.9.3
**Author:** [InsightX](https://www.insightx.in.th)

---

## ⚠️ ประกาศเลิกใช้งาน (Deprecation notice — v1.0)

ฟีเจอร์ด้านล่างนี้ **deprecated** ตั้งแต่ v0.8.0 และจะถูก **ลบออกใน v1.0**:

- shortcode alias `[advanced_form]` — เปลี่ยนไปใช้ `[isxf_form id="..."]` แทน
- migration อัตโนมัติ `acf_*` → `isxf_*` (ตาราง, CPT, meta, options)
- รูปแบบการเข้ารหัส legacy `ENC:` (ค่าเก่าจะถูกย้ายไปเป็น format ใหม่ใน v0.8.x ก่อน)

> **สำคัญ:** การอัปเกรดไป v1.0 **ต้องผ่าน v0.8.x ก่อนเสมอ** — ห้ามข้ามจากเวอร์ชัน ≤ 0.7 ไป v1.0 โดยตรง
> รายละเอียดเพิ่มเติมดูใน [CHANGELOG.md](CHANGELOG.md)

---

## สารบัญ

1. [การติดตั้ง](#-การติดตั้ง)
2. [การสร้างฟอร์ม](#-การสร้างฟอร์ม)
3. [การแสดงฟอร์มบนหน้าเว็บ](#️-การแสดงฟอร์มบนหน้าเว็บ)
4. [ตั้งค่าระบบอีเมล (SMTP)](#-ตั้งค่าระบบอีเมล-smtp)
5. [วิธีเชื่อมต่อผู้ให้บริการอีเมลแต่ละเจ้า](#-วิธีเชื่อมต่อผู้ให้บริการอีเมลแต่ละเจ้า)
6. [ตั้งค่า Captcha](#️-ตั้งค่า-captcha)
7. [ทดสอบส่งอีเมล](#-ทดสอบส่งอีเมล)
8. [Custom Email Template](#-custom-email-template)
9. [คู่มือการใช้งานในระบบ](#-คู่มือการใช้งานในระบบ)
10. [จัดการรายการข้อมูล (Entries)](#-จัดการรายการข้อมูล)
11. [สถานะ & โน้ต (Status & Notes)](#️-สถานะ--โน้ต)
12. [ส่งออกข้อมูล (CSV Export)](#-ส่งออกข้อมูล)
13. [Dashboard Widget](#-dashboard-widget)
14. [Analytics Dashboard](#-analytics-dashboard)
15. [ความปลอดภัย (Security)](#-ความปลอดภัย)
16. [ฐานข้อมูล & สถาปัตยกรรม (สำหรับ Dev)](#️-ฐานข้อมูล--สถาปัตยกรรม-สำหรับ-dev)
17. [Migration จาก acf* → isxf* (สำหรับ Dev)](#-migration-จาก-acf_--isxf_-สำหรับ-dev)
18. [ข้อจำกัดที่ทราบอยู่แล้ว](#️-ข้อจำกัดที่ทราบอยู่แล้ว)
19. [FAQ](#-faq)
20. [Changelog](#-changelog)

---

## 📦 การติดตั้ง

ต้องใช้ **WordPress 7.0 ขึ้นไป** และ **PHP 8.1 ขึ้นไป** ตามข้อกำหนดในไฟล์ปลั๊กอิน

### ติดตั้งผ่านหน้า WordPress

1. ดาวน์โหลดไฟล์ `insightx-form-<version>.zip` ในส่วน **Assets** ของ [GitHub Releases](https://github.com/Douzhebag/ISX-Form-WP-Plugin/releases) (ไฟล์ติดตั้งที่ workflow สร้างให้)
2. ไปที่ **Plugins → Add New → Upload Plugin** แล้วเลือกไฟล์ ZIP และกด **Install Now**
3. กด **Activate** ที่ปลั๊กอิน **InsightX Form**
4. เปิดเมนู **InsightX Form** เพื่อสร้างฟอร์ม จากนั้นนำ shortcode เช่น `[isxf_form id="123"]` ไปวางในหน้าเว็บ โดยเปลี่ยน `123` เป็น ID ของฟอร์ม
5. ตั้งค่าการส่งอีเมลและ CAPTCHA ในหน้าตั้งค่าของปลั๊กอิน (ระบบบันทึกอัตโนมัติ) แล้วทดลองส่งฟอร์มหนึ่งครั้ง

### ติดตั้งด้วยการอัปโหลดโฟลเดอร์

แตกไฟล์ ZIP และอัปโหลดโฟลเดอร์ `insightx-form` ไปยัง `wp-content/plugins/` โดยไฟล์หลักต้องอยู่ที่:

```text
wp-content/plugins/insightx-form/advanced-secure-form.php
```

จากนั้นเปิดใช้งาน **InsightX Form** ใน **Plugins → Installed Plugins** แพ็กเกจ Release มีไฟล์ runtime ครบแล้ว ไม่ต้องรัน Composer บนเว็บจริง

### เปลี่ยนจากโฟลเดอร์ชื่อเดิม

หากติดตั้งด้วยโฟลเดอร์ `ISX-Form-WP-Plugin` อยู่แล้ว ให้สำรองเว็บไซต์ ปิดใช้งานปลั๊กอินชั่วคราว เปลี่ยนชื่อโฟลเดอร์เป็น `insightx-form` แล้วเปิดใช้งานอีกครั้ง ฟอร์ม รายการข้อมูล และการตั้งค่ายังคงใช้ข้อมูลเดิม

**อย่ากด Delete ปลั๊กอินเดิม** เพราะขั้นตอนถอนการติดตั้งจะลบข้อมูลของปลั๊กอิน และไม่ควรเปิดใช้งานสำเนาสองโฟลเดอร์พร้อมกัน

> **หมายเหตุ:** หลังจาก Activate ระบบจะสร้างตารางฐานข้อมูล `wp_isxf_form_entries` อัตโนมัติ (ดูโครงสร้างตารางเต็มใน [ฐานข้อมูล & สถาปัตยกรรม](#️-ฐานข้อมูล--สถาปัตยกรรม-สำหรับ-dev))

หลังติดตั้งแล้ว ตัวตรวจอัปเดตจะตรวจ GitHub Releases และแสดงแจ้งเตือนในหน้า **Plugins** เมื่อมีเวอร์ชันใหม่ ให้กด **Update now** เพื่อติดตั้งอัปเดต

---

## 📝 การสร้างฟอร์ม

1. ไปที่ **InsightX Form → สร้างฟอร์มใหม่**
2. ตั้ง **ชื่อฟอร์ม** (เช่น "แบบฟอร์มจองห้องพัก")
3. เพิ่มฟิลด์ที่ต้องการ — รองรับ 12 ประเภท:

| ประเภทฟิลด์    | คำอธิบาย                                                                                                               |
| -------------- | ---------------------------------------------------------------------------------------------------------------------- |
| Text           | ข้อความสั้น (ชื่อ, ที่อยู่)                                                                                            |
| Textarea       | ข้อความยาว (ข้อความเพิ่มเติม)                                                                                          |
| Email          | อีเมล (ใช้ส่งอีเมลยืนยันให้ลูกค้าอัตโนมัติ — ต้องมีฟิลด์นี้อย่างน้อย 1 ตัวถ้าต้องการอีเมลตอบกลับ)                      |
| Telephone      | เบอร์โทร (กรองเฉพาะตัวเลขขณะพิมพ์ฝั่ง client)                                                                          |
| Number         | ตัวเลข                                                                                                                 |
| Date           | ปฏิทินเลือกวันที่ทั่วไป (flatpickr)                                                                                    |
| Check-in Date  | ปฏิทินวันเช็คอิน (เลือกได้ตั้งแต่วันนี้)                                                                               |
| Check-out Date | ปฏิทินวันเช็คเอาท์ (เชื่อมกับ Check-in อัตโนมัติ — เลือก check-in แล้ว minDate ของ check-out จะขยับเป็นวันถัดไปให้เอง) |
| Select         | Dropdown เลือกได้ 1 ตัวเลือก                                                                                           |
| Radio          | เลือกได้ 1 ข้อ                                                                                                         |
| Checkbox       | เลือกได้หลายข้อ (ส่งข้อมูลเป็น array)                                                                                  |
| Heading        | หัวข้อแบ่งกลุ่มฟิลด์ (ไม่ส่งข้อมูล ไม่นับเป็น field ที่ต้องกรอก)                                                       |

4. สำหรับ Select / Radio / Checkbox ให้กรอกตัวเลือกคั่นด้วยลูกน้ำ `,`
   ตัวอย่าง: `ห้อง Deluxe, ห้อง Suite, ห้อง Family`
5. ตั้ง **Width** เป็น 100% หรือ 50% (วางคู่กันบนจอกว้าง — บนมือถือ/หน้าจอแคบกว่า 650px ทุกฟิลด์จะเต็มความกว้างเสมอ)
6. ตั้ง **Required** (บังคับกรอก) ยกเว้น Heading ที่ระบบล็อกเป็นไม่บังคับเสมอ
7. เลือก **รูปแบบอีเมลตอบกลับลูกค้า:**
   - 🏨 **Booking Confirmation** — สำหรับฟอร์มจองห้องพัก
   - 📩 **General Inquiry** — สำหรับฟอร์มติดต่อสอบถามทั่วไป
   - ✏️ **Custom Template** — กำหนดหัวข้อและเนื้อหาเอง (ดู [Custom Email Template](#-custom-email-template))
8. กด **เผยแพร่ (Publish)**

> **Tips:** ลากไอคอน ☰ เพื่อเรียงลำดับฟิลด์ กดปุ่ม ❌ เพื่อลบฟิลด์

**ข้อจำกัดที่ควรรู้ก่อนออกแบบฟอร์ม:** ไม่รองรับ field ประเภทอัปโหลดไฟล์, ไม่มี conditional logic (ซ่อน/แสดง field ตามค่าของ field อื่น), ไม่มี custom validation pattern ต่อ field (เช็คได้แค่ required หรือไม่), และฟอร์มแสดงเป็นหน้าเดียวเสมอ (ไม่รองรับ multi-step/wizard) — ดูรายละเอียดเพิ่มที่ [ข้อจำกัดที่ทราบอยู่แล้ว](#️-ข้อจำกัดที่ทราบอยู่แล้ว)

---

## 🖥️ การแสดงฟอร์มบนหน้าเว็บ

1. ไปที่ **InsightX Form → ฟอร์มทั้งหมด**
2. คัดลอก **Shortcode** จากคอลัมน์ขวา เช่น:
   ```
   [isxf_form id="123"]
   ```
3. นำ Shortcode ไปวางในหน้า (Page) หรือโพสต์ (Post) ที่ต้องการ
4. ฟอร์มจะแสดงบนหน้าเว็บพร้อม validation ฝั่ง client แบบ realtime (ปุ่ม "ส่งข้อมูล" จะถูกล็อกไว้จนกว่าจะกรอกฟิลด์ที่บังคับครบทุกช่อง) ส่งข้อมูลผ่าน AJAX ไม่รีโหลดหน้า พร้อม spinner และ toast แจ้งผลลัพธ์

รองรับ shortcode 2 แบบ ทำงานเหมือนกันทุกประการ (ใช้ attribute `id` เดียวกัน ไม่มี attribute อื่น):

```
[isxf_form id="123"]       ← แนะนำใช้ตัวนี้
[advanced_form id="123"]   ← legacy shortcode เก็บไว้ backward-compat สำหรับเว็บที่ติดฟอร์มไปแล้วก่อนเปลี่ยนชื่อปลั๊กอิน
```

Asset (CSS/JS) โหลดเฉพาะหน้าที่มี shortcode นี้ปรากฏอยู่เท่านั้น ไม่กระทบความเร็วหน้าอื่น

---

## 📧 ตั้งค่าระบบอีเมล (SMTP)

ไปที่ **InsightX Form → ⚙️ ตั้งค่าระบบ** → ส่วน **📧 ตั้งค่าระบบส่งอีเมล (SMTP)**

1. ✅ ติ๊ก **"เปิดใช้งาน SMTP"**
2. ที่หัวข้อ **วิธียืนยันตัวตน** กดการ์ดของผู้ให้บริการที่ใช้:

| การ์ด          | ยืนยันตัวตนแบบ               | กดแล้วระบบเติมให้                                     | เหมาะกับ                                          |
| -------------- | ---------------------------- | ----------------------------------------------------- | ------------------------------------------------- |
| **Google**     | OAuth2 (XOAUTH2)             | — (กรอก Client ID / Secret แล้วกดเชื่อมต่อบัญชี)     | Gmail / Google Workspace แบบไม่เก็บรหัสผ่าน      |
| **Resend**     | Username / Password          | `smtp.resend.com` · `587` · user `resend`             | มีโดเมนแต่ไม่มีเมลเซิร์ฟเวอร์                    |
| **Cloudflare** | Username / Password          | `smtp.mx.cloudflare.net` · `465` · user `api_token`   | โดเมนที่อยู่บน Cloudflare อยู่แล้ว                 |
| **กำหนดเอง**   | Username / Password          | — (กรอก Host / Port / Username / Password เอง)       | Gmail App Password, Microsoft 365, เมลของโฮสติ้ง |

3. กรอกช่องที่เหลือตามหัวข้อของแต่ละเจ้า การตั้งค่าจะบันทึกอัตโนมัติหลังหยุดพิมพ์หรือเปลี่ยนค่า และมีข้อความแจ้งเมื่อบันทึกสำเร็จ
4. กดปุ่ม **"เชื่อมต่อ"** ในส่วน SMTP เพื่อตรวจว่าเชื่อมต่อเซิร์ฟเวอร์และยืนยันตัวตนได้หรือไม่ ปุ่มนี้ **ไม่ส่งอีเมล**
5. หากต้องการทดสอบการส่งจริง ให้เลื่อนลงไปส่วน **"📨 ทดสอบส่งอีเมล"** แล้วส่งอีเมลไปยังที่อยู่ปลายทางที่ระบุ

> 💡 เปิดหน้ามา การ์ดที่ถูกเลือกจะดูจากค่าที่บันทึกไว้ (OAuth → Google, host ของ Resend / Cloudflare → การ์ดนั้น, นอกนั้น → กำหนดเอง) และกล่องคำแนะนำจะโชว์เฉพาะของการ์ดที่เลือก

> 🔐 **Password ถูกเข้ารหัส (AES-256-CBC)** ก่อนบันทึกลงฐานข้อมูลอัตโนมัติ — เว้นว่างหากไม่ต้องการเปลี่ยนรหัสเดิม (ดูรายละเอียดใน [ความปลอดภัย](#-ความปลอดภัย))

> 💾 **ไม่ต้องกดปุ่มบันทึก:** ค่าในหน้าตั้งค่าระบบจะบันทึกอัตโนมัติเมื่อแก้ไขและหยุดพิมพ์ชั่วครู่ ระบบแสดง toast แจ้งระหว่างบันทึกและเมื่อบันทึกสำเร็จ หากขึ้นข้อความผิดพลาด ให้ลองแก้ไขหรือเปลี่ยนค่าอีกครั้งแล้วรอผลการบันทึก

> Port `465` ใช้ SSL ให้อัตโนมัติ ส่วน port อื่นใช้ TLS

### การแจ้งเตือนผู้ดูแลระบบ

- ✅ ติ๊ก **"เปิดใช้งานการส่งอีเมลแจ้งเตือน Admin"**
- ระบุอีเมลผู้รับ (หากเว้นว่าง จะส่งไปที่อีเมลแอดมินของ WordPress)
- ระบบบันทึกตัวเลือกและอีเมลผู้รับอัตโนมัติหลังแก้ไข
- เมื่อมีผู้ส่งฟอร์ม แอดมินจะได้รับ Email แจ้งเตือนทันที (ตั้ง `Reply-To` เป็นอีเมลลูกค้าอัตโนมัติ ตอบกลับได้ทันทีจากอีเมล)

### SSL Verification

- ค่า default: **เปิด SSL verification** (ปลอดภัยสำหรับ Production)
- สำหรับ Development: ติ๊ก **"⚠️ ปิดการตรวจสอบ SSL Certificate"** ในส่วน SMTP

---

## 🔌 วิธีเชื่อมต่อผู้ให้บริการอีเมลแต่ละเจ้า

### 🟦 Google (OAuth2)

ยืนยันตัวตนผ่าน OAuth2 (XOAUTH2) แทน username/password — ไม่ต้องเก็บรหัสผ่านอีเมลไว้ในเว็บ

**ตั้งค่าฝั่ง Google Cloud Console**

1. สร้างโปรเจกต์ใน [Google Cloud Console](https://console.cloud.google.com/) → เปิดใช้งาน Gmail API
2. ตั้งค่า **OAuth consent screen** และเพิ่ม scope `https://mail.google.com/`
3. สร้าง **OAuth Client ID** (ประเภท Web application) แล้วเพิ่ม **Authorized redirect URI** เป็นค่าในช่อง Redirect URI ของหน้าตั้งค่า (ต้องตรงกัน 100%):
   ```
   https://เว็บของคุณ/wp-admin/admin-post.php?action=isxf_oauth_callback
   ```
4. คัดลอก **Client ID** และ **Client Secret** มาใส่ในหน้าตั้งค่าระบบ

**เชื่อมต่อในปลั๊กอิน**

1. กดการ์ด **Google** → กรอก Client ID / Client Secret แล้วรอ toast **"บันทึกการตั้งค่าสำเร็จ"**
2. กดปุ่ม **"เชื่อมต่อบัญชี"** → ไปหน้ายืนยันตัวตนของ Google → อนุญาตสิทธิ์ → เด้งกลับมาที่ปลั๊กอินอัตโนมัติ
3. เชื่อมต่อสำเร็จจะเห็นอีเมลบัญชีที่เชื่อมอยู่ พร้อมปุ่ม **"ตัดการเชื่อมต่อ"**

หลังเชื่อมต่อแล้ว ระบบใช้ **Access Token** (ต่ออายุอัตโนมัติผ่าน **Refresh Token** ที่เก็บแบบเข้ารหัส) ทุกครั้งที่ส่งอีเมล

> **หมายเหตุความปลอดภัย:** ขั้นตอนเชื่อมต่อตรวจ `state` parameter (ป้องกัน CSRF) ทุกครั้งก่อนแลก authorization code เป็น token

### ⬛ Resend

1. สมัครที่ [resend.com](https://resend.com) → เพิ่มโดเมนในเมนู **Domains** → ใส่ DNS record ตามที่บอกจนขึ้น **Verified**
2. สร้าง API key ที่ [resend.com/api-keys](https://resend.com/api-keys) (สิทธิ์ Sending access ก็พอ)
3. กดการ์ด **Resend** แล้ววาง API key ในช่อง **Password**

| ช่อง     | ค่า                                                            |
| -------- | -------------------------------------------------------------- |
| Host     | `smtp.resend.com`                                              |
| Port     | `587` (หรือ `465` สำหรับ SSL)                                  |
| Username | `resend`                                                       |
| Password | API key จาก [resend.com/api-keys](https://resend.com/api-keys) |

> ⚠️ โดเมนของ **From Email** ต้อง verify แล้วใน Resend ไม่งั้นส่งไม่ออก

### 🟧 Cloudflare

ใช้ SMTP ของ [Cloudflare Email Service](https://developers.cloudflare.com/email-service/api/send-emails/smtp/)

1. ใน [Cloudflare Dashboard](https://dash.cloudflare.com) ไปที่ **Email Service → Email Sending** แล้วเพิ่มโดเมน
2. สร้าง API token (**My Profile → API Tokens**) ที่มีสิทธิ์ `Email Sending: Edit`
3. กดการ์ด **Cloudflare** แล้ววาง API token ในช่อง **Password**

| ช่อง     | ค่า                                              |
| -------- | ------------------------------------------------ |
| Host     | `smtp.mx.cloudflare.net`                         |
| Port     | `465` (รับแค่ SSL — ไม่รองรับ 587 / STARTTLS)    |
| Username | `api_token` (คำนี้ตรงตัว)                        |
| Password | API token ที่มีสิทธิ์ `Email Sending: Edit`      |

> ⚠️ โดเมนของ **From Email** ต้องเพิ่มไว้ใน Email Sending แล้ว · ส่งได้สูงสุด 50 ผู้รับต่อฉบับ · ขนาดไม่เกิน 5 MiB · มีโควตาต่อวันของบัญชี

### ⚙️ กำหนดเอง (SMTP เจ้าอื่น)

กดการ์ด **กำหนดเอง** แล้วกรอก Host / Port / Username / Password ตามที่ผู้ให้บริการกำหนด เช่น Gmail แบบ App Password:

| ช่อง     | ค่าตัวอย่าง (Gmail)    |
| -------- | ---------------------- |
| Host     | `smtp.gmail.com`       |
| Port     | `587`                  |
| Username | `your-email@gmail.com` |
| Password | รหัสผ่านแอป 16 หลัก    |

**วิธีขอ App Password (Gmail)**

1. ไปที่ [Google Account → Security](https://myaccount.google.com/security)
2. เปิดใช้งาน **2-Step Verification**
3. ค้นหา **"App passwords"**
4. สร้างรหัสผ่านใหม่ (เช่นชื่อ "Website SMTP")
5. คัดลอกรหัส 16 หลักมาใส่ช่อง Password

---

## 🛡️ ตั้งค่า Captcha

ไปที่ **InsightX Form → ⚙️ ตั้งค่าระบบ → ส่วน Captcha**

### Google reCAPTCHA v3

1. เลือก **Google reCAPTCHA v3** จาก dropdown (ระบบจะบันทึกตัวเลือกอัตโนมัติ)
2. ไปสร้าง key ที่ [Google reCAPTCHA](https://www.google.com/recaptcha/admin)
3. กรอก **Site Key** และ **Secret Key** แล้วรอ toast ยืนยันว่าบันทึกสำเร็จ
4. ระบบยิง `grecaptcha.execute()` แบบ invisible ก่อน submit ทุกครั้ง แล้วตรวจ score ฝั่ง server (ผ่านเกณฑ์ที่ score ≥ 0.5)

### Cloudflare Turnstile

1. เลือก **Cloudflare Turnstile** จาก Dropdown (ระบบจะบันทึกตัวเลือกอัตโนมัติ)
2. ไปสร้าง widget ที่ [Cloudflare Dashboard](https://dash.cloudflare.com/?to=/:account/turnstile)
3. กรอก **Site Key** และ **Secret Key** แล้วรอ toast ยืนยันว่าบันทึกสำเร็จ
4. ฟอร์มจะไม่ยอมให้ submit จนกว่า widget จะออก token ก่อน (ตรวจซ้ำฝั่ง server เสมอ)

> ระบบจะตรวจสอบ Token ทั้งฝั่ง Frontend และ Server-side อัตโนมัติ

> ⚠️ ตัวเลือก **"บล็อกการส่งฟอร์มเมื่อยังไม่ได้ตั้งค่า CAPTCHA"** เปิดไว้เป็นค่าเริ่มต้น — ถ้ายังไม่ได้ใส่ Secret Key ระบบจะ**ปฏิเสธการส่งฟอร์มทั้งหมด**แทนการปล่อยผ่าน ปิดตัวเลือกนี้เฉพาะเมื่อตั้งใจใช้ฟอร์มโดยไม่มี CAPTCHA

### 🌐 Trusted Proxies (เว็บที่อยู่หลัง Cloudflare / CDN)

ถ้าเว็บอยู่หลัง Cloudflare, CDN หรือ reverse proxy ผู้เข้าชมทุกคนจะดูเหมือนมาจาก IP ของ proxy — ตัวจำกัดการส่งต่อ IP (Rate Limiting) จะบล็อกทุกคนพร้อมกัน

ใส่ช่วง IP ของ proxy (บรรทัดละหนึ่ง CIDR) ในหัวข้อ **Trusted Proxies** ของหน้าตั้งค่าระบบ แล้วรอ toast แจ้งว่าบันทึกสำเร็จ ระบบจะอ่าน IP จริงจาก header `CF-Connecting-IP` / `X-Forwarded-For` เฉพาะ request ที่มาจากช่วง IP เหล่านั้น — ช่วง IP ของ Cloudflare ดูได้ที่ [cloudflare.com/ips](https://www.cloudflare.com/ips/) · เว้นว่าง = เชื่อแค่ `REMOTE_ADDR` (ค่าเริ่มต้น ปลอดภัยที่สุด)

---

## 📨 ทดสอบส่งอีเมล

ไปที่ **InsightX Form → ⚙️ ตั้งค่าระบบ → ส่วน "ทดสอบส่งอีเมล"**

1. กรอก **อีเมลปลายทาง** ที่ต้องการทดสอบ (pre-fill จากอีเมลแอดมิน)
2. กด **"📨 ส่งอีเมลทดสอบ"** — ขั้นตอนนี้ส่งอีเมลจริงไปยังที่อยู่ปลายทางที่กรอกไว้
3. ผลลัพธ์:
   - ✅ **สีเขียว** — ส่งสำเร็จ! (ไปเช็คกล่องจดหมายได้เลย)
   - ❌ **สีแดง** — ล้มเหลว พร้อม Error Message สำหรับแก้ไข

> **Tips:** การแก้ไข SMTP จะบันทึกอัตโนมัติ รอ toast ยืนยันก่อนทดสอบ หากต้องการตรวจเฉพาะการเชื่อมต่อโดยไม่ส่งเมล ให้ใช้ปุ่ม **"เชื่อมต่อ"** ในส่วน SMTP แทน

---

## ✉️ Custom Email Template

ตั้งแต่ v0.3.0 สามารถกำหนดเนื้อหาอีเมลตอบกลับลูกค้าได้เองจากหน้าแก้ไขฟอร์ม

### วิธีใช้

1. แก้ไขฟอร์ม → เลือก **"✏️ กำหนดเอง (Custom Template)"** จาก dropdown
2. กรอก **หัวข้ออีเมล (Subject)** และ **เนื้อหาอีเมล (Body)**
3. ใช้ **Merge Tags** เพื่อแทรกข้อมูลอัตโนมัติ (คลิกที่ tag เพื่อแทรก)

### Merge Tags ที่รองรับ

| Tag                 | ผลลัพธ์                                |
| ------------------- | -------------------------------------- |
| `{site_name}`       | ชื่อเว็บไซต์                           |
| `{form_title}`      | ชื่อฟอร์ม                              |
| `{all_fields}`      | ตาราง HTML ของข้อมูลทุกฟิลด์           |
| `{field:ชื่อฟิลด์}` | ค่าของฟิลด์ที่ระบุ เช่น `{field:ชื่อ}` |

### ตัวอย่าง

**Subject:**

```
ขอบคุณที่ติดต่อ {form_title} - {site_name}
```

**Body:**

```
สวัสดีครับ {field:ชื่อ}

เราได้รับข้อมูลจากฟอร์ม "{form_title}" เรียบร้อยแล้ว

รายละเอียด:
{all_fields}

ขอบคุณครับ
{site_name}
```

> **หมายเหตุ:** ระบบจะครอบเนื้อหาด้วย layout สวยงามอัตโนมัติ (header + footer เดียวกับ template สำเร็จรูป)

---

## 📖 คู่มือการใช้งานในระบบ

นอกจาก README นี้ ปลั๊กอินยังมีหน้า **"📖 คู่มือการใช้งาน"** ในตัวเอง (เมนู **InsightX Form → คู่มือการใช้งาน**) เป็น in-admin documentation แบบ accordion ครอบคลุมทุกหัวข้อรวมถึงขั้นตอนตั้งค่า OAuth2 แบบ step-by-step — เปิดดูได้ทันทีโดยไม่ต้องออกจาก wp-admin เหมาะเป็นจุดอ้างอิงเวลาส่งต่อให้ทีมอื่นดูแลเว็บต่อ

---

## 📥 จัดการรายการข้อมูล

ไปที่ **InsightX Form → 📥 รายการข้อมูล**

### ตัวกรอง (Filters)

- **ฟอร์ม:** เลือกดูเฉพาะฟอร์มที่ต้องการ (แสดงข้อมูลแยกคอลัมน์ตามฟิลด์ของฟอร์มนั้น — ถ้าไม่เลือกฟอร์มจะแสดงข้อมูลดิบแบบ JSON แทน)
- **ช่วงวันที่:** กรองตามวันที่ส่ง
- **ค้นหา:** พิมพ์ชื่อ / เบอร์ / อีเมล / IP เพื่อค้นหา

### การลบข้อมูล

- **ทีละรายการ:** กด "ลบ" ในคอลัมน์จัดการ
- **ลบหลายรายการ:** ✅ เลือก checkbox → เลือก "ลบข้อมูลที่เลือก" จาก Bulk Actions → กด "นำไปใช้"

---

## 🏷️ สถานะ & โน้ต

### ระบบสถานะ (Status)

แต่ละรายการมี 4 สถานะ เปลี่ยนเป็นสถานะไหนก็ได้อิสระ ไม่มีลำดับบังคับ:

| สถานะ             | ความหมาย                            |
| ----------------- | ----------------------------------- |
| 🔵 ใหม่           | ข้อมูลเข้ามาใหม่ ยังไม่ได้ดำเนินการ |
| 🟡 กำลังดำเนินการ | อยู่ระหว่างติดต่อ/จัดการ            |
| ✅ เสร็จสิ้น      | จัดการเสร็จเรียบร้อยแล้ว            |
| 🔴 ขยะ            | Spam หรือข้อมูลไม่เกี่ยวข้อง        |

**วิธีเปลี่ยนสถานะ:**

- **ทีละรายการ:** เปลี่ยนจาก Dropdown ในแต่ละแถว (อัพเดตทันที ไม่ต้อง reload)
- **หลายรายการ:** เลือก checkbox → เลือกสถานะจาก Bulk Actions

**แถบกรองสถานะ (Status Tabs):**
ด้านบนตารางจะมีแถบแสดงจำนวนแต่ละสถานะ คลิกเพื่อกรอง

### โน้ตแอดมิน (Admin Notes)

- คลิก **"✏️ + เพิ่มโน้ต"** ในคอลัมน์โน้ต
- พิมพ์บันทึก เช่น _"โทรหาลูกค้าแล้ว รอยืนยัน"_
- กด **"💾 บันทึก"** — อัพเดตทันทีไม่ต้อง reload หน้า

---

## 📊 ส่งออกข้อมูล

1. ตั้งค่าตัวกรองตามต้องการ (ฟอร์ม / ช่วงวันที่ / ค้นหา / สถานะ)
2. กดปุ่ม **"📊 ส่งออก CSV"** ด้านบนขวา
3. ไฟล์ CSV จะดาวน์โหลดอัตโนมัติ รองรับภาษาไทย (UTF-8 BOM) — export เป็น batch ภายในเพื่อไม่ให้ค้างหรือหมด memory แม้ข้อมูลเยอะ

**คอลัมน์ที่ส่งออก:** วันที่ | ฟอร์ม | ข้อมูลฟิลด์ต่างๆ | สถานะ | โน้ตแอดมิน | IP Address

> 🔐 ค่าที่ขึ้นต้นด้วย `=`, `+`, `-`, `@` จะถูกป้องกันอัตโนมัติ (เติม `'` นำหน้า) กัน CSV/Formula Injection เวลาเปิดด้วย Excel

---

## 📈 Dashboard Widget

เมื่อ Login เข้า **WP-Admin Dashboard** จะเห็น Widget "📊 InsightX Form — ภาพรวม" แสดง:

- **สถิติ 4 ช่อง:** วันนี้ / 7 วันล่าสุด / 30 วันล่าสุด / ทั้งหมด
- **แถบสถานะ:** กราฟแท่งสีสัดส่วนแยกตามสถานะ
- **5 รายการล่าสุด:** ชื่อฟอร์ม + เวลา + สถานะ พร้อมลิงก์ "ดูทั้งหมด →"

---

## 📊 Analytics Dashboard

ไปที่ **InsightX Form → 📊 Analytics** เพื่อดูสถิติแบบเจาะลึก

### Stat Cards

- **ทั้งหมด** — จำนวน entries ทั้งหมดในระบบ
- **ช่วงที่เลือก** — จำนวนในช่วงเวลาที่กรอง + % เปรียบเทียบกับช่วงก่อนหน้า (คำนวณจากช่วงก่อนหน้าที่มีความยาวเท่ากันโดยอัตโนมัติ)
- **วันนี้** — จำนวนที่เข้ามาวันนี้
- **เฉลี่ย/วัน** — ค่าเฉลี่ยต่อวันในช่วงที่เลือก

### กราฟ

- **📈 Line Chart** — แสดง submissions ต่อวัน (Chart.js)
- **📊 Doughnut Chart** — สัดส่วนสถานะ (ใหม่ / กำลังดำเนินการ / เสร็จสิ้น / ขยะ)

### ฟอร์มยอดนิยม & รายการล่าสุด

- **🏆 Top Forms** — Ranking 10 อันดับฟอร์มที่มี entries มากที่สุด พร้อม progress bar
- **🕐 รายการล่าสุด** — 10 รายการล่าสุดพร้อม status badge

### ตัวกรองช่วงเวลา

- เลือกจาก Dropdown: **7 วัน / 30 วัน / 90 วัน / 1 ปี**
- หรือเลือก **กำหนดเอง** แล้วระบุวันเริ่มต้น-สิ้นสุด

---

## 🔐 ความปลอดภัย

InsightX Form มีมาตรการความปลอดภัยหลายชั้น:

| มาตรการ                      | รายละเอียด                                                                                                               |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| **Nonce Verification**       | ตรวจสอบ token ทุกการส่งฟอร์ม/ทุก AJAX action ป้องกัน CSRF                                                                |
| **Honeypot Trap**            | ดักจับ bot ด้วย hidden field — ถ้าถูกกรอก (บอทเท่านั้นที่เห็น field นี้) ระบบจะตอบสำเร็จแบบเงียบๆ โดยไม่บันทึกข้อมูลจริง |
| **Rate Limiting**            | จำกัดการส่งซ้ำ 1 ครั้ง / 30 วินาที ต่อ IP                                                                                |
| **CAPTCHA**                  | รองรับ reCAPTCHA v3 (score-based) + Cloudflare Turnstile ตรวจซ้ำฝั่ง server เสมอ                                         |
| **Input Sanitization**       | `sanitize_text_field` / `sanitize_email` / `sanitize_textarea_field` ตามประเภทฟิลด์                                      |
| **Prepared Statements**      | ใช้ `$wpdb->prepare()` ป้องกัน SQL Injection                                                                             |
| **CSV Injection Protection** | ป้องกันสูตรอันตรายในไฟล์ CSV ที่ส่งออก                                                                                   |
| **Secret Encryption**        | SMTP Password, OAuth2 Client Secret และ Refresh Token เข้ารหัส AES-256-CBC ก่อนเก็บลงฐานข้อมูลทุกตัว                     |
| **SSL Verification**         | เปิด SSL verify เป็น default (v0.3.0)                                                                                    |
| **OAuth2 CSRF Protection**   | ตรวจสอบ `state` parameter (random, ผูกกับ user + หมดอายุ 10 นาที) ทุกครั้งก่อนแลก authorization code เป็น token          |

### รายละเอียดการเข้ารหัส (สำหรับ Dev)

- Cipher: **AES-256-CBC**, key derive จาก `sha256(wp_salt('auth'))` (256-bit)
- **`ENC2:` (ปัจจุบัน)** — สุ่ม IV ใหม่ทุกครั้งที่เข้ารหัส (`openssl_random_pseudo_bytes`) ปลอดภัยกว่า เพราะ ciphertext จะไม่ซ้ำกันแม้ plaintext เดิม
- **`ENC:` (legacy)** — ใช้ IV คงที่ (derive จาก salt) เก็บไว้เพื่อถอดรหัสค่าที่เคยเข้ารหัสไว้ก่อน v0.4.1 เท่านั้น **ไม่มีการเข้ารหัสด้วย format นี้อีกแล้ว** — ระบบตรวจ prefix อัตโนมัติและ migrate password เก่าเป็น ENC2 ให้ทันทีที่ตรวจพบว่ายังเป็น plain text
- ย้ายฐานข้อมูลข้ามเว็บ (เปลี่ยน `wp_salt`) จะทำให้ค่าที่เข้ารหัสไว้ถอดไม่ออก ต้องตั้งค่า SMTP/OAuth ใหม่

---

## 🗄️ ฐานข้อมูล & สถาปัตยกรรม (สำหรับ Dev)

### โครงสร้างไฟล์

```
advanced-secure-form.php        entry point — constants, activation/uninstall hook, DB migration, autoloader + bootstrap
src/                            PSR-4 classes ใน namespace `ISXF\` (autoload ผ่าน spl_autoload_register ใน main file)
  Crypto.php                    เข้ารหัส/ถอดรหัส (AES-256-CBC, ENC/ENC2 format)
  OAuth.php                     OAuth2 flow (Google)
  OAuthTokenProvider.php        PHPMailer XOAUTH2 token provider
  Admin.php                     form builder CPT, meta box, global settings page (ไฟล์ใหญ่สุด)
  Frontend.php                  shortcode [isxf_form]/[advanced_form] + render ฟอร์มหน้าเว็บ
  AjaxHandler.php               submit ฟอร์ม, ทดสอบอีเมล, อัปเดตสถานะ/โน้ต, SMTP config ผ่าน phpmailer_init
  Entries.php                   หน้ารายการข้อมูล, dashboard widget, analytics, CSV export
libs/plugin-update-checker/     bundled library สำหรับ auto-update ผ่าน GitHub Release
```

คลาสทั้งหมดถูก instantiate บน hook `plugins_loaded` ผ่าน `isxf_bootstrap()` และยังมี `class_alias` ชื่อเก่า (`ISXF_Crypto` → `ISXF\Crypto` ฯลฯ) ไว้เพื่อ backward compatibility

### ตาราง `wp_isxf_form_entries`

| Column         | Type                                            | หมายเหตุ                                                                                           |
| -------------- | ----------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| `id`           | `bigint(20)` AUTO_INCREMENT                     | PRIMARY KEY                                                                                        |
| `form_id`      | `bigint(20)` NOT NULL                           | อ้างถึง post ID ของ `isxf_form`                                                                    |
| `form_title`   | `text` NOT NULL                                 | snapshot ชื่อฟอร์มตอนส่ง (ไม่ join กับ posts table — ฟอร์มลบ/เปลี่ยนชื่อภายหลังไม่กระทบข้อมูลเก่า) |
| `entry_data`   | `longtext` NOT NULL                             | JSON (`{label: value}` ของทุกฟิลด์, unicode ไม่ escape)                                            |
| `user_ip`      | `varchar(100)` DEFAULT `''` NOT NULL            | รองรับตรวจจาก header ผ่าน proxy/Cloudflare                                                         |
| `entry_status` | `varchar(20)` DEFAULT `'new'` NOT NULL          | `new` / `in_progress` / `done` / `junk`                                                            |
| `admin_note`   | `text` DEFAULT `''` NOT NULL                    |                                                                                                    |
| `created_at`   | `datetime` DEFAULT `CURRENT_TIMESTAMP` NOT NULL |                                                                                                    |

> ไม่มี index เพิ่มเติมนอกจาก PRIMARY KEY — เว็บที่มี entries จำนวนมากมาก (หลักแสนขึ้นไป) อาจพิจารณาเพิ่ม index บน `form_id`/`entry_status`/`created_at` เอง

### Custom Post Type `isxf_form`

`public => false`, `show_ui => true`, `supports => ['title']` เท่านั้น — ข้อมูลฟอร์มทั้งหมดเก็บใน post meta ไม่ใช้ `post_content`:

| Meta key                   | เก็บอะไร                                                                    |
| -------------------------- | --------------------------------------------------------------------------- |
| `_isxf_form_fields`        | array ของ field config (label/name/type/placeholder/options/width/required) |
| `_isxf_form_email_type`    | `booking` \| `inquiry` \| `custom`                                          |
| `_isxf_form_email_subject` | ใช้เมื่อ email_type เป็น custom                                             |
| `_isxf_form_email_body`    | ใช้เมื่อ email_type เป็น custom (sanitize ผ่าน `wp_kses_post`)              |

### Options ทั้งหมด (prefix `isxf_`)

```
isxf_db_version
isxf_captcha_service, isxf_recaptcha_site_key, isxf_recaptcha_secret_key
isxf_turnstile_site_key, isxf_turnstile_secret_key
isxf_smtp_enable, isxf_smtp_host, isxf_smtp_port, isxf_smtp_user, isxf_smtp_pass
isxf_smtp_secure, isxf_smtp_from_email, isxf_smtp_from_name, isxf_smtp_disable_ssl_verify
isxf_smtp_auth_method, isxf_smtp_oauth_client_id, isxf_smtp_oauth_client_secret
isxf_smtp_oauth_refresh_token, isxf_smtp_oauth_connected
isxf_admin_notify_enable, isxf_admin_notify_email
```

### AJAX actions ทั้งหมด

| Action                          | ทำอะไร                                                                           | Nonce                     |
| ------------------------------- | -------------------------------------------------------------------------------- | ------------------------- |
| `isxf_submit_form` (+ `nopriv`) | รับข้อมูลจากฟอร์มหน้าเว็บ, ตรวจ honeypot/CAPTCHA/rate-limit, บันทึก DB, ส่งอีเมล | `isxf_secure_nonce`       |
| `isxf_send_test_email`          | ทดสอบส่งอีเมลจากหน้าตั้งค่า                                                      | — (ตรวจ `manage_options`) |
| `isxf_update_entry_status`      | เปลี่ยนสถานะ entry แบบ inline                                                    | `isxf_entry_action_nonce` |
| `isxf_update_entry_note`        | แก้โน้ต entry แบบ inline                                                         | `isxf_entry_action_nonce` |
| `isxf_get_analytics_data`       | ดึงข้อมูลกราฟ Analytics                                                          | `isxf_analytics_nonce`    |

การบันทึก entry ไม่ได้ insert ตรงในตัว AJAX handler แต่ทำผ่าน action hook `isxf_form_after_submission` (decoupled — `ISXF_Entries::save_to_db()` เป็นตัวรับ hook นี้ไป insert จริง)

---

## 🔄 Migration จาก acf* → isxf* (สำหรับ Dev)

ปลั๊กอินนี้เคยใช้ prefix `acf_` มาก่อน (v0.5.0 เปลี่ยนมาเป็น `isxf_`/`ISXF_` เพื่อกันชนกับปลั๊กอิน ACF จริงๆ) ฟังก์ชัน `isxf_maybe_upgrade_db()` (hook เข้า `admin_init` ทุกครั้ง ไม่ใช่แค่ตอน activate) จัดการ migrate ให้อัตโนมัติ แบ่งเป็น 4 ขั้นตอนตามลำดับ ทุกขั้นตอน **รันซ้ำได้อย่างปลอดภัย (idempotent)**:

1. **ตรวจ `isxf_db_version`** เทียบกับ `ISXF_DB_VERSION` ปัจจุบัน — ถ้าต่ำกว่า จะรัน `dbDelta()` สร้าง/ปรับตารางใหม่
2. **ย้ายข้อมูลจากตารางเก่า** `wp_acf_form_entries` → `wp_isxf_form_entries` (เฉพาะกรณีตารางใหม่ยังว่างเปล่า เพื่อไม่ insert ซ้ำทุกครั้งที่ระบบเช็ค)
3. **เปลี่ยน post type และ meta key**: `acf_form` → `isxf_form`, `_acf_*` → `_isxf_*` (ผ่าน SQL `UPDATE ... REPLACE`)
4. **เปลี่ยนชื่อ options**: `acf_smtp_*`, `acf_recaptcha_*`, `acf_turnstile_*`, `acf_captcha_service`, `acf_admin_notify_*`, `acf_db_version` → เทียบเท่าฝั่ง `isxf_` (options ของฟีเจอร์ OAuth ที่เพิ่มทีหลังไม่ต้อง migrate เพราะไม่เคยมีในระบบ `acf_` เดิม)

ถ้าเจอเว็บที่ยังใช้ shortcode/data แบบเก่าหลังอัปเดต ให้เข้าหน้า admin สักครั้ง (โหลด `admin_init`) ระบบจะ migrate ให้เองอัตโนมัติ ไม่ต้องรันคำสั่งอะไรเพิ่ม

---

## ⚠️ ข้อจำกัดที่ทราบอยู่แล้ว

- **ไม่รองรับ field อัปโหลดไฟล์** — ฟอร์มไม่มี field type สำหรับให้ผู้ใช้แนบไฟล์
- **ไม่มี conditional logic** — ไม่สามารถซ่อน/แสดง field ตามค่าของ field อื่นได้
- **ไม่มี custom validation pattern ต่อ field** — เช็คได้แค่ required/ไม่ required เท่านั้น (ไม่ validate รูปแบบ เช่น regex เฉพาะ)
- **ไม่รองรับ multi-step form** — ฟอร์มแสดงเป็นหน้าเดียวเสมอ ไม่มีระบบแบ่งขั้นตอน (wizard)
- **ไม่มี deactivation hook** — ปิดใช้งานปลั๊กอิน (Deactivate) ไม่ลบข้อมูล/ตาราง/ตั้งค่าใดๆ (ข้อมูลปลอดภัย) แต่ต้อง **Uninstall (Delete)** เท่านั้นถึงจะล้างข้อมูลทั้งหมด — ดูคำเตือนใน FAQ
- **Text Domain ไม่ตรงกันระหว่าง header กับโค้ด** — plugin header ระบุ `InsightX` แต่ `load_plugin_textdomain()`/`__()` ทั้งหมดใช้ `insightx-form` จริง (ไม่กระทบการทำงาน แต่ควรรู้ไว้ถ้าจะทำ translation .po/.mo)
- **ไม่มี index เพิ่มเติมบนตาราง entries** นอกจาก PRIMARY KEY — พิจารณาเพิ่มเองถ้าข้อมูลเยอะมาก

---

## ❓ FAQ

**Q: ฟอร์มไม่ส่งอีเมล ทำอย่างไร?**
A: ตรวจสอบการตั้งค่า SMTP และรอ toast ยืนยันการบันทึก จากนั้นกด **"เชื่อมต่อ"** เพื่อตรวจ server และข้อมูลเข้าสู่ระบบ หากเชื่อมต่อได้แต่ยังไม่ได้รับเมล ให้ใช้ **"ส่งอีเมลทดสอบ"** ซึ่งส่งอีเมลจริงและแสดงรายละเอียดเมื่อเกิดข้อผิดพลาด

**Q: ลูกค้ากรอก Email แล้วไม่ได้รับอีเมลยืนยัน**
A: ให้ตรวจสอบว่าฟิลด์ประเภท `Email` ถูกตั้งค่าในฟอร์ม — ระบบจะส่งอีเมลไปที่ Email ที่กรอกในฟอร์มอัตโนมัติ

**Q: อยากใช้ฟอร์มเดียวกันหลายหน้าได้ไหม?**
A: ได้ คัดลอก Shortcode เดียวกันไปวางได้หลายหน้า

**Q: รองรับ Captcha อะไรบ้าง?**
A: Google reCAPTCHA v3 และ Cloudflare Turnstile (เลือกได้จากหน้าตั้งค่า)

**Q: SMTP Password / OAuth Secret ปลอดภัยไหม?**
A: ปลอดภัย — เข้ารหัสด้วย AES-256-CBC (IV สุ่มทุกครั้ง) ก่อนบันทึก โดยใช้ WordPress authentication salt เป็นฐานของ key

**Q: อัพเดตปลั๊กอินแล้วข้อมูลเก่าหายไหม?**
A: ไม่หาย ข้อมูลเก็บอยู่ในฐานข้อมูล WordPress แยกจากไฟล์ปลั๊กอิน แค่ Deactivate ก็ไม่มีผลกับข้อมูลเลย

**Q: Uninstall ปลั๊กอินแล้วข้อมูลจะหายไหม?**
A: จะหาย — เมื่อ **Uninstall (Delete)** ปลั๊กอิน (ไม่ใช่แค่ Deactivate) ระบบจะลบตารางข้อมูล, การตั้งค่าทั้งหมด (รวม SMTP/OAuth credential ที่เข้ารหัสไว้) หากต้องการเก็บข้อมูลไว้ ให้ Export CSV ก่อนเสมอ

---

## 📋 Changelog

ประวัติการเปลี่ยนแปลงทั้งหมดอยู่ใน [CHANGELOG.md](CHANGELOG.md)

---

<p align="center">
  <strong>InsightX Form</strong><br>
  Made by <a href="https://www.insightx.in.th">InsightX</a>
</p>
