# PROJECT SPECIFICATION
## RDU Dashboard — โรงพยาบาลเชียงกลาง จังหวัดน่าน

---

## ภาพรวมโครงการ

ระบบ **RDU Dashboard** (Rational Drug Use Dashboard) พัฒนาขึ้นเพื่อใช้ติดตามตัวชี้วัดการใช้ยาอย่างสมเหตุผลของโรงพยาบาลเชียงกลาง จังหวัดน่าน

ระบบเชื่อมต่อกับ HOSxP Database เพื่อ **อ่านข้อมูลเท่านั้น** ห้ามแก้ไขข้อมูลต้นทางโดยเด็ดขาด

---

## Technology Stack

| Layer | Technology |
|---|---|
| Web Server | Apache (XAMPP) |
| Backend | Laravel (PHP 8.3+) |
| Frontend | Blade + Chart.js |
| Database App | PostgreSQL |
| Database Source | HOSxP / PostgreSQL (Read-Only) |
| Build Tools | Node.js, NPM |
| Package Manager | Composer |

---

## Non-Negotiable Rules

### RULE 1
**HOSxP Database = READ ONLY**

### RULE 2
**ใช้ SELECT เท่านั้น**

### RULE 3
**ห้าม Migration ไป HOSxP**

### RULE 4
**ห้ามใช้ HOSxP Database เป็น Application Database**

### RULE 5
**Database Credential ต้องไม่ Hard-code**

### RULE 6
**Database Configuration เก็บใน JSON**

### RULE 7
**Password ต้อง Encrypt**

### RULE 8
**JSON ต้องอยู่นอก public directory**

### RULE 9
**Admin เท่านั้นที่แก้ Database Configuration**

### RULE 10
**ต้อง Test Connection ก่อนบันทึก Configuration**

### RULE 11
**ต้อง Verify ว่า Database User เป็น Read-Only**

### RULE 12
**Database Server ต้องเป็น Security Boundary สูงสุด**

### RULE 13
**ใช้ XAMPP แทน Docker**

### RULE 14
ทุก Phase ต้องผ่านกระบวนการ:

```
PLAN → IMPLEMENT → TEST → SECURITY REVIEW → PERFORMANCE REVIEW → REFACTOR → TEST AGAIN
```

### RULE 15
**ห้ามเข้าสู่ Phase ถัดไป หาก Test ของ Phase ปัจจุบันยังไม่ผ่าน**

---

## Final Development Command

เริ่มจาก **PHASE 0 — Architecture & Security Planning**

ก่อนเขียน Code ให้แสดง:

1. Final Architecture
2. Database Architecture
3. Read-Only Security Architecture
4. ERD
5. Database Connections
6. JSON Configuration Structure
7. Security Threat Model
8. XAMPP Setup
9. Directory Structure
10. 13 KPI Data Model
11. Roles & Permissions
12. Testing Strategy
13. Development Roadmap

จากนั้นเริ่ม **PHASE 1 — XAMPP Foundation**

ห้ามสร้าง Feature อื่นจนกว่า Foundation จะผ่าน Test

---

*ดูรายละเอียดเพิ่มเติมใน:*
- [ARCHITECTURE.md](ARCHITECTURE.md)
- [DATABASE-SECURITY.md](DATABASE-SECURITY.md)
- [DATABASE-CONFIGURATION.md](DATABASE-CONFIGURATION.md)
- [KPI-SPECIFICATION.md](KPI-SPECIFICATION.md)
- [UI-UX-SPECIFICATION.md](UI-UX-SPECIFICATION.md)
- [TESTING-STRATEGY.md](TESTING-STRATEGY.md)
- [DEVELOPMENT-PHASES.md](DEVELOPMENT-PHASES.md)
