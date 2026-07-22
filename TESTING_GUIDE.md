# Prakerin Notification System — Complete Testing Guide

This guide covers step-by-step procedures to test the **Prakerin Notification System** across all channels (Inbox, Email, WhatsApp).

---

## 🚀 Quick Command Reference

### 1. Test Notification via CLI
Run the custom test command from the `Prakerin-BE` root directory:

```bash
# Test sending notification to the first user in DB
php artisan notification:test

# Test sending to a specific user by email
php artisan notification:test --user=student@example.com
```

### 2. Process Queued Notification Jobs
```bash
php artisan queue:work --queue=default --tries=3
```

---

## 🧪 Testing Checklist (7 Key Areas)

### 1. Queue Worker Execution
- [x] Run `php artisan queue:work` in terminal.
- [x] Trigger an event (e.g. submit application or run `php artisan notification:test`).
- [x] Observe console output: `App\Jobs\SendEmailNotification` and `App\Jobs\SendWhatsAppNotification` should display `[DONE]`.

### 2. SMTP & Email Delivery
- [x] Configure SMTP in **Admin Dashboard > Pengaturan > Server Email (SMTP)** or `.env`.
- [x] Click **Uji Koneksi SMTP** button.
- [x] Check inbox for email with subject `[Prakerin] Ada Notifikasi Baru!`.

### 3. WhatsApp Twilio Webhook
- [x] Configure Twilio credentials in **Admin Dashboard > Pengaturan > WhatsApp Gateway**.
- [x] Click **Uji Koneksi WhatsApp**.
- [x] Expose local server via `ngrok http 8000`.
- [x] Set Twilio Callback URL to `https://<ngrok-id>.ngrok-free.app/api/webhooks/whatsapp/status`.
- [x] Inspect `notification_logs` table for status progression (`sent` $\rightarrow$ `delivered` $\rightarrow$ `read`).

### 4. Error Handling & Job Retries
- [x] Stop queue worker, corrupt SMTP password, and trigger notification.
- [x] Inspect `notification_logs` table: `status` becomes `failed` and `error_message` records exact error traceback.
- [x] Jobs automatically retry up to 3 times with exponential backoff (60s, 300s, 900s).

### 5. Security & Sensitive Data Protection
- [x] Verify API keys are masked in log files.
- [x] Verify `X-Twilio-Signature` header validation on public webhook route `/api/webhooks/whatsapp/status`.

### 6. Phone Number Validation & Normalization
- [x] Try saving invalid phone numbers (`abc`, `12345`) in profile settings $\rightarrow$ rejected with clear validation message.
- [x] Test Indonesian formats: `08123456789` is normalized to `628123456789`.

### 7. End-to-End User Flow
- [x] Log in as Student $\rightarrow$ check Notification Bell icon and badge count in sidebar.
- [x] Open `/dashboard/inbox` $\rightarrow$ verify list, click item to mark as read, click **Tandai Semua Dibaca**.
