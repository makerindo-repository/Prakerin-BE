# Twilio WhatsApp Webhook Integration & Setup Guide

This guide explains how to set up, secure, and test Twilio WhatsApp status webhooks for delivery tracking (`sent`, `delivered`, `read`, `failed`).

---

## 🔗 1. Webhook URL & Endpoint

The public webhook endpoint in **Prakerin-BE** is:
```
POST https://your-domain.com/api/webhooks/whatsapp/status
```

### Route Throttle Security
The route is rate-limited (`60 requests / minute`) to prevent spam/DDoS while allowing Twilio callback traffic.

---

## 🛠️ 2. Configuring Twilio Console Webhook

1. Log in to [Twilio Console](https://console.twilio.com/).
2. Go to **Messaging** $\rightarrow$ **Settings** $\rightarrow$ **WhatsApp sandbox settings** (or active Phone Number settings).
3. Under **Status Callback URL**, enter:
   `https://your-domain.com/api/webhooks/whatsapp/status`
4. Set HTTP Method to **POST**.
5. Save changes.

---

## 💻 3. Local Development Testing (ngrok Guide)

Since Twilio needs a public URL to send webhooks, use **ngrok** during local development:

### Step 1: Start ngrok tunnel
```bash
ngrok http 8000
```
*(Assuming your Laravel app runs on port 8000)*

### Step 2: Copy HTTPS Forwarding URL
Copy the forwarding URL (e.g. `https://a1b2-180-252-xx.ngrok-free.app`).

### Step 3: Paste into Twilio Sandbox
Paste: `https://a1b2-180-252-xx.ngrok-free.app/api/webhooks/whatsapp/status` into the Status Callback URL field.

---

## 🔒 4. Webhook Security & Signature Verification

To verify that requests come strictly from Twilio in production:
Set your Auth Token in `.env`:
```env
TWILIO_AUTH_TOKEN=your_auth_token_here
```
The `WebhookController` verifies the signature header (`X-Twilio-Signature`) matching Twilio's signature validator algorithm.

---

## 🔍 5. Verification & Log Inspection

Watch Laravel logs when sending WhatsApp messages:
```bash
tail -f storage/logs/laravel.log
```
Sample log output:
```text
[2026-07-22 10:48:00] local.INFO: [Webhook/WhatsApp] SID=SMxxxxxxxx, status=delivered
```
`notification_logs` table automatically records `sent_at`, `delivered_at`, or `read_at` timestamps!
