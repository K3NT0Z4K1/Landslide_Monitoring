# SlopeGuard — Landslide Monitoring System
### PHP + MySQL · Hostinger Shared Hosting · FTP Deployment

---

## 📁 Project Structure

```
LANDSLIDE_MONITORING/
├── .htaccess                  ← Security + HTTPS redirect
├── index.php                  ← Root redirect → login
│
├── api/
│   ├── get_latest.php         ← Live sensor card data (JS polls every 5s)
│   ├── get_history.php        ← Chart + export data (JS polls every 10s)
│   ├── get_nodes.php          ← Map node status (JS polls every 10s)
│   └── receive_data.php       ← ESP32 POST endpoint (API key protected)
│
├── assets/
│   ├── css/
│   │   ├── style.css          ← Dashboard styles
│   │   └── login.css          ← Login page styles
│   └── js/
│       ├── charts.js          ← Chart.js logic
│       └── map.js             ← Leaflet map logic
│
├── auth/
│   ├── auth_check.php         ← Session guard (included by all pages)
│   ├── login.php              ← Login form + bcrypt verification
│   └── logout.php             ← Session destroy + redirect
│
├── config/
│   └── db.php                 ← MySQL connection (edit before upload)
│
├── dashboard/
│   ├── index.php              ← Main dashboard
│   ├── alerts.php             ← Alert history
│   └── map.php                ← Sensor map
│
└── db/
    └── landslide_monitoring.sql ← Import into phpMyAdmin
```

---

## 🚀 Hostinger Deployment — Step by Step

### Step 1 — Create the Database

1. Log in to **Hostinger hPanel**
2. Go to **Databases → MySQL Databases**
3. Create a new database (e.g. `u123456789_slopeguard`)
4. Create a new DB user and a strong password
5. Assign that user to the database (grant ALL privileges)
6. Note down: **Database Name**, **Username**, **Password**

### Step 2 — Import the SQL Schema

1. In hPanel go to **Databases → phpMyAdmin**
2. Select your new database on the left
3. Click the **Import** tab
4. Choose `db/landslide_monitoring.sql` and click **Go**

### Step 3 — Edit config/db.php

Open `config/db.php` and replace:

```php
define('DB_HOST', 'localhost');                    // keep as-is
define('DB_USER', 'u123456789_slopeguard');        // your DB username
define('DB_PASS', 'YourStrongPassword123!');       // your DB password
define('DB_NAME', 'u123456789_slopeguard');        // your DB name
```

### Step 4 — Set the API Key (for ESP32)

Open `api/receive_data.php` and change:

```php
define('API_KEY', 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET');
```

Use a strong random string (e.g. 32+ characters). Set the same value in your ESP32 sketch:

```cpp
http.addHeader("X-API-Key", "your-secret-key-here");
```

### Step 5 — Upload via FTP

Use **FileZilla** (or Hostinger's File Manager):

| Setting  | Value                        |
|----------|------------------------------|
| Host     | Your Hostinger FTP host      |
| Username | Your FTP username            |
| Password | Your FTP password            |
| Port     | 21                           |

Upload the **entire `LANDSLIDE_MONITORING/` folder** contents into `public_html/`.
Your file tree on the server should look like:

```
public_html/
├── .htaccess
├── index.php
├── api/
├── assets/
├── auth/
├── config/
├── dashboard/
└── db/
```

> ⚠️ Do NOT upload the `.git/` folder.
> ⚠️ Do NOT upload `db/` if you don't want the raw SQL accessible — it's blocked by .htaccess anyway, but it's cleaner to exclude it after importing.

### Step 6 — Enable SSL (HTTPS)

1. In hPanel go to **SSL → Manage**
2. Enable **Let's Encrypt** (free, auto-renews)
3. Wait ~5 min, then your site will be HTTPS

The `.htaccess` already redirects HTTP → HTTPS automatically.

### Step 7 — Change the Default Admin Password

The default login is `admin / admin123`.

**Change it immediately** by running this in phpMyAdmin → SQL tab:

```sql
UPDATE users
SET password = '$2y$10$YOUR_NEW_BCRYPT_HASH'
WHERE username = 'admin';
```

To generate a bcrypt hash, use: https://bcrypt-generator.com (cost = 10)

Or add a one-time script (delete after use):

```php
<?php
// tools/hash.php — DELETE THIS FILE AFTER USE
echo password_hash('your-new-password', PASSWORD_BCRYPT);
```

---

## 🔌 ESP32 Integration

Your ESP32 should POST to:

```
POST https://yourdomain.com/api/receive_data.php
Content-Type: application/json
X-API-Key: your-secret-key-here

{
  "node_id": 1,
  "temperature": 27.5,
  "humidity": 82.0,
  "soil_moisture": 65.0,
  "rainfall": 12.3
}
```

The API returns:
```json
{ "success": true, "status": "WARNING", "message": "Reading saved" }
```

---

## 🛡️ Alert Thresholds

| Status  | Condition                           |
|---------|-------------------------------------|
| SAFE    | Soil ≤ 50% AND Rainfall ≤ 10 mm    |
| WARNING | Soil > 50% OR  Rainfall > 10 mm    |
| DANGER  | Soil > 80% OR  Rainfall > 25 mm    |

---

## ⚠️ Common Hostinger Issues & Fixes

### Blank white page (500 error)
- Check `config/db.php` credentials match your Hostinger DB exactly
- Enable PHP error display temporarily: add `ini_set('display_errors',1);` to db.php top, remove after fixing

### Session not persisting / redirect loop
- Hostinger shared hosting uses the same session directory — already handled by `session_start()` guard in `auth_check.php`
- Make sure `.htaccess` `php_flag` lines are not being rejected (some plans need `php.ini` instead)

### fetch() API calls returning 404
- All JS fetch paths use `../api/` — this works when pages are inside `/dashboard/`
- If you move pages, update the relative paths accordingly

### Images not loading
- `bg.jpg` is referenced in CSS as a background-image — make sure it's uploaded to `assets/css/bg.jpg`
- Paths in CSS are relative to the CSS file location, not the PHP page

### FTP upload shows files but site still shows old version
- Hostinger caches aggressively — clear browser cache + try incognito
- In hPanel → Cache Manager → purge cache

### Database import fails
- Ensure you selected the correct database before importing
- Check the SQL file encoding is UTF-8

---

## 🔐 Security Checklist Before Going Live

- [ ] Changed default `admin / admin123` password
- [ ] Set a real API key in `receive_data.php` and your ESP32
- [ ] SSL/HTTPS is active and .htaccess redirect is working
- [ ] `display_errors` is Off in `.htaccess`
- [ ] `.git/` folder was NOT uploaded via FTP
- [ ] DB credentials use a dedicated user (not root)
