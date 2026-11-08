# Student Management Dashboard — PHP 8 + MySQL

Arabic (RTL) student evaluation platform converted from the original single-file HTML dashboard.
The admin dashboard page is the **original HTML file, unchanged** (same layout, section order, text, classes, element IDs, icons, modals and JavaScript functions). Only the data layer now uses PHP + MySQL, plus login/logout.

* PHP 8.0+ · MySQL 5.7+/MariaDB 10.3+ · PDO · plain HTML/CSS/JS · no framework, no Node, no backend services, no API keys.
* The page *styling libraries* (Tailwind CDN, Font Awesome, Tajawal font) are loaded from public CDNs exactly like the original file, so the visitor's browser needs internet access to render the design. They are not backend services and need no keys. (To go fully offline, download those three files into `assets/` and change the three tags in `includes/layout.php`.)

## Project structure

```
index.php            Landing page (choose portal)
install.php          One-time setup: creates tables + first admin, then locks itself
config/config.php    DB credentials & security settings   (web access denied)
includes/            bootstrap, db (PDO), auth, csrf, repository (SQL), layout (web access denied)
admin/               login.php · index.php (dashboard) · logout.php
student/             login.php · index.php (own profile)  · logout.php
api/admin.php        Internal JSON endpoint used by the admin dashboard (admin only)
assets/js/            common.js · admin.js (the original dashboard script, now talking to MySQL)
database/database.sql  Schema (web access denied)
```

## Install on XAMPP (local)

1. Copy the `student-dashboard` folder to `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open `http://localhost/phpmyadmin` → **Import** → choose `database/database.sql` → *Go*.
   (This creates the `student_dashboard` database, the 4 tables and the original sample data: 4 groups and 7 students.)
4. Open `config/config.php`. XAMPP defaults (`root`, empty password) already work.
5. Open `http://localhost/student-dashboard/install.php` and create the admin account.
6. **Delete `install.php`**, then sign in at `http://localhost/student-dashboard/admin/login.php`.

## Install on shared hosting

1. In the hosting control panel create a MySQL database + user, and give the user all privileges.
2. In phpMyAdmin select that database and import `database/database.sql`
   (delete its first two lines `CREATE DATABASE…` / `USE…` if your host rejects them — `install.php` ignores them anyway).
3. Edit `config/config.php` with the host, database name, user and password.
4. Upload everything to `public_html/` (or a sub-folder). Make sure PHP 8.0+ is selected and the `pdo_mysql` and `mbstring` extensions are enabled (default on almost every host).
5. Visit `https://your-domain/install.php`, create the admin, then **delete `install.php`**.
6. Use HTTPS. Session cookies automatically become `Secure` when the site is served over HTTPS.

No `.htaccess` rewriting is required; paths are relative so the app works in any sub-folder.

## Sample data (same as the original HTML)

`database.sql` ends with the original project's 4 groups and 7 students so the dashboard looks exactly like your HTML file on first load.
All 7 sample students have the temporary password **`Student@2026`** (e.g. log in at `/student/login.php` as `عمر خالد العتيبي`).
Before going live: change those passwords from the dashboard (edit student → new password) or delete the students.

## What was added to the original page (only what PHP/MySQL requires)

**Admin page** = your original file. Only two additions:
1. A logout icon button in the header (same style as the theme button).
2. One extra field in the add/edit student window: the student's login password (blank on edit = unchanged).

**Student page** = the same original page, read-only and limited to the student's own data: the add-student and group buttons, the quick-score column, the actions column and both modals are removed; the table, stats cards and podium section show only the logged-in student (the 4th card and podium title are reworded to "ترتيبي العام" / "مركزي بين الطلاب" so no other student's name is revealed).

**Login pages** (admin / student) are new, built with the same classes and colours.

## How it works

**Admin** (`/admin/login.php`): username + password. Full control — add/edit/delete students, add/delete groups, quick score ±1/±5 and exact score in the edit form, search (name or ID), group filter, sorting, statistics, average, top student, Top-3 podium, global ranking. The admin account is created only by `install.php`, which refuses to run once any admin exists.

**Student** (`/student/login.php`): registered full name + the password the admin assigned. A student sees the original dashboard layout containing only their own avatar, ID, group, score and overall rank. There is no edit capability and the student page never accepts an id from the request — it always uses the id stored in the server session.

**Students' passwords**: set by the admin in the add/edit form Passwords are stored only as `password_hash()` (bcrypt); the admin can set a new one later by typing it in the edit form (blank = unchanged). Hand the password to the student — it can't be shown again.

**Ranking**: by score (highest first); students with equal scores share the same rank.

**Student ID** (`STD-101`, …) is generated from the database id.

## Security summary

* PDO with real prepared statements everywhere; whitelist for sort fields; LIKE wildcards escaped.
* `password_hash()` / `password_verify()` (+ automatic rehash), constant-work check even for unknown users.
* PHP sessions: HttpOnly, SameSite=Lax, Secure on HTTPS, `use_strict_mode`, ID regenerated on login, idle timeout (2 h, configurable).
* Role-based authorization on the server: every admin page and every `api/admin.php` call verifies role **and** re-checks the admin in the database; students get `403` from the API.
* CSRF tokens on all forms and on every API mutation (`X-CSRF-Token` header); logout is POST + token.
* Input validation on the server for every field; output escaped with `htmlspecialchars` (PHP) / `esc()` (JS).
* Login throttling: 8 failed attempts / 15 min per IP per portal (`config.php`), all attempts stored in `login_logs` (90-day retention).
* `config/`, `includes/`, `database/` are blocked from the web by `.htaccess` (Apache). On **Nginx** add `location ~ ^/(config|includes|database)/ { deny all; }`.

## Manual test checklist

1. Run `install.php`, create the admin; reload `install.php` → it must refuse. The dashboard must show 4 groups, 7 students, podium Omar/Fatima/Sara.
2. Admin login with a wrong password → generic error; correct → dashboard (all cards show 0 / empty podium).
3. Add 2–3 groups; try adding a duplicate → error; delete an empty group → OK.
4. Add several students (with passwords); duplicate name → error; score outside 0–100 → error.
5. Use ±1 / ±5 repeatedly; score never goes below 0 or above 100; podium, rank and average update.
6. Search by name and by `STD-…` ID; filter by group; every sort option; "reset filters".
7. Delete a group that has students → blocked with the Arabic message; move/delete the students, then delete it.
8. Edit a student (name, group, score, avatar, optional new password); delete a student.
9. Reload / reopen the browser → all data still there (MySQL). Dark-mode toggle works as in the original.
10. Log out; log in at `/student/login.php` with a student name + password → only own data is shown.
11. While logged in as a student open `/admin/index.php` → redirected to admin login; open `/api/admin.php` → JSON `403`.
12. Logged out, open `/api/admin.php` → JSON `401`; POST without `X-CSRF-Token` as admin → `403`.
13. Change a score as admin, refresh the student page → new score/rank visible.

## Troubleshooting

* *White page / "Unable to complete request"*: wrong DB settings, or `database.sql` not imported. Set `'debug' => true` temporarily in `config/config.php`.
* *Installer says "already installed" but you have no admin*: delete `config/installed.lock` and make sure the `admins` table is empty.
* *Locked out of login attempts*: wait the lockout period or `DELETE FROM login_logs WHERE success = 0;`.
