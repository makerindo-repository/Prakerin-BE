# SMTP & Email Setup Documentation

This document explains how to set up SMTP email sending for **Prakerin-BE**, including Gmail, Google Workspace, Custom Corporate Mail servers, and Mailtrap for development.

---

## ⚙️ 1. Dynamic Database vs `.env` Configuration

Prakerin supports **dynamic runtime SMTP settings**:
1. When configuring SMTP in **Admin Dashboard > Pengaturan > Server Email (SMTP)**, values are saved to the `settings` database table.
2. `AppServiceProvider::boot()` overrides standard Laravel mailer config at runtime with these DB values.
3. If no DB setting is set, Laravel falls back to `.env` variables.

---

## 📧 2. Setup Guides

### Option A: Gmail (Free / Personal Account)
1. Go to your Google Account $\rightarrow$ Security $\rightarrow$ **2-Step Verification** (must be ON).
2. Generate an **App Password**: [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords). Select app *Mail* and device *Other (Prakerin)*.
3. Use the generated 16-character password:

- **SMTP Host**: `smtp.gmail.com`
- **SMTP Port**: `587`
- **Encryption**: `tls`
- **Username**: `your_email@gmail.com`
- **Password**: `xxxx xxxx xxxx xxxx` (16-char App Password without spaces)
- **Sender Email**: `your_email@gmail.com`

---

### Option B: Google Workspace (Corporate Gmail)
Same as Option A, or enable SMTP Relay in Google Admin Console under *Apps > Google Workspace > Gmail > Routing > SMTP relay service*.

---

### Option C: Custom Corporate SMTP (cPanel / Zimbra / Microsoft 365)
- **Microsoft 365**: Host `smtp.office365.com`, Port `587`, Encryption `tls`.
- **cPanel/WHM**: Host `mail.yourdomain.com`, Port `465` (ssl) or `587` (tls).

---

### Option D: Mailtrap (Local Development)
- **SMTP Host**: `sandbox.smtp.mailtrap.io`
- **SMTP Port**: `2525`
- **Encryption**: `tls`
- **Username / Password**: Copy from your Mailtrap Inbox Settings.

---

## 🧪 3. How to Test Email Setup

### Method 1: Admin Dashboard Button
Go to **Admin > Pengaturan > Server Email (SMTP)**, click **Uji Koneksi SMTP**.

### Method 2: Artisan Tinker
Run in terminal:
```bash
php artisan tinker
```
```php
Mail::raw('Tes notifikasi Prakerin', function($msg) {
    $msg->to('user@example.com')->subject('Tes SMTP Prakerin');
});
```

---

## 🔍 4. Troubleshooting

- **Connection Timeout (`SSL/TLS handshake failed`)**: Try changing port `587` (tls) to `465` (ssl) or vice versa.
- **`535 5.7.8 Authentication credentials invalid`**: Check username/password. For Gmail, ensure you're using an **App Password**, not your main account password.
