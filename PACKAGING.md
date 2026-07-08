# PACKAGING OPTIONS (WINDOWS)

This project is a **PHP + MySQL web app**. A true single-file `.exe` is not the natural runtime model.

## 1) Local Web App via XAMPP/Laragon (Recommended Baseline)

- Install XAMPP/Laragon on Windows
- Place project in web root
- Import SQL in phpMyAdmin
- Run in browser on localhost

Pros:
- Most reliable
- Minimal custom packaging risk
- Easy support and debugging

## 2) Portable Local Server Bundle

Bundle:
- Portable Apache/PHP
- Portable MySQL/MariaDB
- Project files + SQL bootstrap script

Pros:
- No full installer required
- Works in controlled offline environments

Cons:
- Larger package size
- Requires careful startup/shutdown scripting

## 3) Electron Wrapper Around Local PHP Server

Use Electron only as UI shell while PHP/MySQL run locally.

Pros:
- Desktop-like UX

Cons:
- Heavier runtime
- More moving parts
- Still not a true pure Electron app (backend remains PHP/MySQL)

## 4) Windows Installer Approach

Build installer (Inno Setup / NSIS / WiX) that:
- Installs required local stack (or checks existing)
- Places app files
- Imports DB
- Creates desktop/start-menu shortcuts

Pros:
- Best user onboarding for non-technical users

Cons:
- Higher maintenance burden
- Needs robust install/uninstall scripts

## 5) Why a Single `.exe` is Difficult

- PHP app expects a web server runtime
- MySQL is a separate service/database runtime
- Bundling both into one executable is fragile and uncommon
- Upgrades, data persistence, and service control become harder

## 6) Recommended Practical Approach for BrightBlaze

1. **Primary:** Installer or scripted setup around XAMPP/Laragon model.
2. **Secondary:** Optional portable bundle for offline demo use.
3. Keep browser-based local web architecture intact.

## Simple Launcher/Wrapper Plan (without breaking app)

Create a lightweight launcher (batch/PowerShell) that:
1. Verifies Apache/MySQL are running
2. Starts services if needed
3. Opens `http://localhost/brightblaze/`
4. Optionally opens phpMyAdmin for admin tasks

This preserves the current stable architecture while improving usability.
