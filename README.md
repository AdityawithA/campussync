# CampusSync 📢
> A centralized college notice & group communication portal — with OTP email verification.

---

## ⚡ Quick Setup (Local — XAMPP / WAMP)

### 1. Place the Project
Put `campussync/` inside your server root:
- XAMPP → `C:/xampp/htdocs/campussync/`
- WAMP  → `C:/wamp64/www/campussync/`

### 2. Install PHPMailer (Required for OTP)
Open a terminal inside the `campussync/` folder and run:
```bash
composer require phpmailer/phpmailer
```
> Don't have Composer? Download from https://getcomposer.org

### 3. Create the Database
1. Open phpMyAdmin → `http://localhost/phpmyadmin`
2. Create database named `campussync`
3. Import `config/schema.sql`

### 4. Configure DB + Gmail SMTP
**DB** → Open `config/db.php`:
```php
define('DB_USER', 'root');
define('DB_PASS', '');          // blank for XAMPP default
define('BASE_URL', 'http://localhost/campussync');
```

**Gmail SMTP** → Open `config/mailer.php`:
```php
define('MAIL_USERNAME', 'your_gmail@gmail.com');
define('MAIL_PASSWORD', 'xxxx xxxx xxxx xxxx'); // 16-char App Password
define('MAIL_FROM',     'your_gmail@gmail.com');
```

### 5. Get Gmail App Password
1. Go to https://myaccount.google.com/security
2. Enable 2-Step Verification
3. Search "App Passwords" → Select "Mail" + "Other" → Name it "CampusSync"
4. Copy the 16-character password → paste into `mailer.php`

### 6. Run
Visit: `http://localhost/campussync`

---

## 🔑 Default Admin Login
| Field    | Value                |
|----------|----------------------|
| Email    | admin@campussync.com |
| Password | password             |

---

## 🔐 OTP Verification Flow
1. User registers → OTP generated → email sent via Gmail SMTP
2. User lands on `auth/verify.php` → enters 6-digit code
3. OTP valid + not expired → account verified → redirect to login
4. OTP expired → user clicks Resend → fresh OTP sent
5. Unverified user tries to login → redirected back to verify.php

---

## 👥 Roles
| Role    | Permissions |
|---------|-------------|
| Student | View notices, join groups, comment, react |
| Faculty | + Post notices, create groups |
| Admin   | + Manage all users |

---

## 📁 Project Structure
```
campussync/
├── config/
│   ├── db.php            ← DB credentials + BASE_URL
│   ├── mailer.php        ← Gmail SMTP + OTP functions
│   └── schema.sql        ← Full DB schema with OTP fields
├── auth/
│   ├── login.php         ← Blocks unverified accounts
│   ├── register.php      ← Sends OTP on registration
│   ├── verify.php        ← 6-box OTP entry + countdown timer
│   └── logout.php
├── dashboard/
│   ├── index.php
│   └── notifications.php
├── notices/
│   ├── create.php
│   ├── view.php
│   └── react.php
├── groups/
│   └── list.php
├── admin/
│   └── index.php
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── auth_check.php
├── assets/
│   ├── css/style.css
│   └── js/main.js
├── composer.json
└── index.php
```
