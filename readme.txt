=== InsightX Form ===
Tags: forms, contact form, email, entries, form builder
Requires at least: 7.0
Tested up to: 7.1.2
Requires PHP: 8.1
Stable tag: 0.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

สร้างฟอร์มธุรกิจ ส่งอีเมลแจ้งเตือน และจัดการรายการข้อมูลใน WordPress

== Description ==

InsightX Form รองรับการสร้างฟอร์มด้วยฟิลด์หลายประเภท การส่งอีเมลผ่าน SMTP/OAuth2 การป้องกันสแปมด้วย CAPTCHA และการจัดการรายการข้อมูลพร้อมส่งออก CSV

แสดงฟอร์มบนหน้าเว็บด้วย shortcode [isxf_form id="123"] โดยเปลี่ยน 123 เป็น ID ของฟอร์ม

ดูคู่มือฉบับเต็มและภาพปกใน README.md ที่มากับปลั๊กอิน

== Installation ==

1. ดาวน์โหลด insightx-form-<version>.zip จากส่วน Assets ของ GitHub Releases: https://github.com/Douzhebag/ISX-Form-WP-Plugin/releases
2. ใน WordPress ไปที่ Plugins → Add New → Upload Plugin เลือกไฟล์ ZIP แล้วกด Install Now
3. กด Activate ที่ InsightX Form
4. สร้างฟอร์มในเมนู Forms แล้วนำ shortcode ไปวางในหน้าเว็บ
5. ตั้งค่าอีเมลและ CAPTCHA แล้วทดลองส่งฟอร์ม

หากอัปโหลดด้วยตนเอง ให้ไฟล์หลักอยู่ที่ wp-content/plugins/insightx-form/advanced-secure-form.php

ผู้ที่ใช้โฟลเดอร์ ISX-Form-WP-Plugin เดิม: สำรองเว็บไซต์ ปิดใช้งานชั่วคราว เปลี่ยนชื่อโฟลเดอร์เป็น insightx-form แล้วเปิดใช้งานใหม่ ห้ามกด Delete เพราะจะลบข้อมูลปลั๊กอิน และห้ามเปิดใช้งานสองสำเนาพร้อมกัน

== Frequently Asked Questions ==

= ต้องติดตั้ง Composer บนเว็บจริงหรือไม่? =

ไม่ต้อง แพ็กเกจ ZIP จาก GitHub Releases มีไฟล์ runtime ครบแล้ว

= อัปเดตปลั๊กอินอย่างไร? =

ตัวตรวจอัปเดตจะตรวจ GitHub Releases เมื่อมีเวอร์ชันใหม่จะแสดงแจ้งเตือนในหน้า Plugins ให้กด Update now
