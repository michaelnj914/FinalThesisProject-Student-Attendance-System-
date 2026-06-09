# Student Attendance Management System
### Web-Based Thesis Project — PHP / MySQL

---

## Overview

A web-based Student Attendance Management System built with PHP and MySQL. The system provides two separate portals — an **Admin** portal for school-wide management and a **Faculty** portal for day-to-day attendance taking. It supports real-time attendance tracking, per-student attendance reports with standing evaluation, and export to Excel and PDF.

---

## Features

### Admin Portal
- Dashboard with live stats: total students, faculty, programs, and students present today
- 14-day attendance trend chart and per-program attendance rate chart
- Full CRUD for Students, Faculty, Programs, and Sections
- School-wide attendance viewer with filters (date, program, section)
- Per-student attendance report with monthly breakdown and standing evaluation
- Export attendance records to Excel (.xlsx) and PDF

### Faculty Portal
- Dashboard showing assigned section info and today's attendance summary
- Take attendance — auto-populates all students as Absent, faculty marks Present
- Prevents duplicate submissions for the same day
- View section attendance history with date filter
- View list of students in assigned section
- Export section attendance to Excel and PDF

---

## Tech Stack

| Layer      | Technology                        |
|------------|-----------------------------------|
| Backend    | PHP 7.4+                          |
| Database   | MySQL 5.7+ / MariaDB 10.3+        |
| Frontend   | HTML, CSS, Vanilla JS             |
| Icons      | Font Awesome 6.4                  |
| Charts     | Chart.js (Admin Dashboard)        |
| Export     | SheetJS (xlsx), jsPDF + AutoTable |
| Server     | Apache / Nginx (XAMPP recommended)|

---

## Folder Structure

```
ama-updated002/
├── index.php                        ← Unified login page (Admin / Faculty)
├── create_admin.php                 ← One-time admin account creation utility
│
├── includes/
│   ├── dbcon.php                    ← Database connection (configure credentials here)
│   ├── session_admin.php            ← Admin session guard (redirects if not logged in)
│   ├── session_teacher.php          ← Faculty session guard
│   └── logout.php                   ← Destroys session and redirects to login
│
├── admin/
│   ├── index.php                    ← Admin dashboard (stats + charts)
│   ├── students.php                 ← Manage students (Add / Edit / Delete)
│   ├── student-report.php           ← Per-student attendance report with standing
│   ├── teachers.php                 ← Manage faculty accounts (Add / Edit / Delete)
│   ├── classes.php                  ← Manage programs and sections
│   ├── attendance.php               ← View all attendance records (with filters)
│   ├── semesters.php                ← Manage academic semesters/terms
│   ├── report-body.php              ← Print-ready report body partial
│   ├── report-styles.php            ← Print-specific CSS styles
│   ├── sidebar.php                  ← Admin sidebar navigation
│   └── topbar.php                   ← Admin topbar (page title + user badge)
│
├── teacher/
│   ├── index.php                    ← Faculty dashboard (section info + today summary)
│   ├── take-attendance.php          ← Take today's attendance (mark Present / Absent)
│   ├── view-attendance.php          ← View section attendance history
│   ├── my-students.php              ← View students in assigned section
│   ├── sidebar.php                  ← Faculty sidebar navigation
│   └── topbar.php                   ← Faculty topbar
│
├── assets/
│   ├── css/style.css                ← Global stylesheet (layout, components, badges)
│   └── js/app.js                    ← Global JS (sidebar toggle, UI interactions)
│
└── DATABASE FILE/
    └── attendancemsystem.sql        ← Full database schema with sample data
```

---

## Database Schema

| Table             | Description                                              |
|-------------------|----------------------------------------------------------|
| `tbladmin`        | Administrator accounts                                   |
| `tblclassteacher` | Faculty accounts (linked to a program and section)       |
| `tblclass`        | Programs (e.g. BSIT, BSCS, BSA, BSBA)                   |
| `tblclassarms`    | Sections (e.g. BSIT-1A, linked to a program)            |
| `tblstudents`     | Student records (linked to a program and section)        |
| `tblsessionterm`  | Academic semesters (1st Sem, 2nd Sem, Summer)            |
| `tblterm`         | Semester types                                           |
| `tblattendance`   | Attendance records (one row per student per day)         |

---

## Terminology

| Database Term  | Real-World Meaning          |
|----------------|-----------------------------|
| Class          | Program (e.g. BSIT)         |
| Class Arm      | Section (e.g. BSIT-1A)      |
| Class Teacher  | Faculty member              |
| Admission No   | Student Number              |
| Session/Term   | Semester                    |

---

## Setup Instructions

### 1. Requirements
- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache or Nginx (XAMPP / WAMP recommended for local development)

### 2. Import the Database
```bash
mysql -u root -p < "DATABASE FILE/attendancemsystem.sql"
```
Or import via **phpMyAdmin**: create a database named `attendancemsystem`, then import the SQL file.

### 3. Configure Database Credentials
Edit `includes/dbcon.php` and update the following:
```php
$host = "localhost";
$user = "your_mysql_username";
$pass = "your_mysql_password";
$db   = "attendancemsystem";
```

### 4. Deploy
Place the project folder inside your server's web root:
- XAMPP: `C:/xampp/htdocs/ama-updated002/`
- Linux: `/var/www/html/ama-updated002/`

### 5. Open in Browser
```
http://localhost/ama-updated002/
```

---

## Default Login Credentials

| Role    | Email                 | Password     |
|---------|-----------------------|--------------|
| Admin   | admin@school.edu      | admin123     |
| Faculty | faculty@school.edu    | faculty123   |

> ⚠️ **Change these credentials immediately after your first login.**

---

## Student Standing Rules

| Attendance Rate  | Standing      |
|------------------|---------------|
| 80% and above    | Good Standing |
| 60% – 79%        | At Risk       |
| Below 60%        | Dropped       |

---

## Security Notes

- Passwords are hashed using PHP's `password_hash()` (bcrypt).
- All database queries use **prepared statements** (`mysqli`) to prevent SQL injection.
- User inputs are sanitized with `htmlspecialchars()` to prevent XSS.
- Session IDs are regenerated on login to prevent session fixation.
- Role-based access: admin and faculty routes are protected by separate session guards.

> ⚠️ Before deploying to production, remove or restrict access to `create_admin.php`.

---

## Export Functionality

Both Admin and Faculty portals support exporting attendance records:

- **Excel (.xlsx)** — via [SheetJS](https://sheetjs.com/), exported directly from the browser
- **PDF** — via [jsPDF](https://parall.ax/products/jspdf) + AutoTable plugin, formatted with school header and section label

---

## Author

Developed as a thesis project.  
Built with PHP, MySQL, and vanilla web technologies.