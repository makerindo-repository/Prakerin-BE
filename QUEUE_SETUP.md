# Queue Worker Setup & Production Documentation

This document explains how to configure, run, and monitor background queue workers for email and WhatsApp notifications in **Prakerin-BE**.

---

## 🚀 1. Overview & Setup

Notifications in Prakerin use Laravel's `database` queue driver to process emails and WhatsApp messages asynchronously without blocking API responses.

### Prerequisites
1. Ensure `.env` has:
   ```env
   QUEUE_CONNECTION=database
   ```
2. The `jobs` table migration is already migrated (`php artisan migrate`).

---

## 💻 2. Running Queue Worker in Development

Run the following command in your terminal (`Prakerin-BE` root directory):

```bash
php artisan queue:work --queue=default --tries=3 --timeout=90 --backoff=60,300,900
```

- `--tries=3`: Retries a job up to 3 times before declaring failure.
- `--timeout=90`: Kills a job if execution takes longer than 90 seconds.
- `--backoff=60,300,900`: Waits 1 minute, 5 minutes, then 15 minutes between retries.

---

## ⚙️ 3. Production Setup (Supervisor Process Manager)

In a production Linux environment (Ubuntu/Debian), use **Supervisor** to keep `queue:work` running continuously across system reboots.

### Step 1: Install Supervisor
```bash
sudo apt-get update
sudo apt-get install supervisor -y
```

### Step 2: Create Worker Configuration
Create `/etc/supervisor/conf.d/prakerin-worker.conf`:

```ini
[program:prakerin-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/prakerin-be/artisan queue:work database --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/prakerin-be/storage/logs/worker.log
stopwaitsecs=3600
```

### Step 3: Start Supervisor Service
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start prakerin-worker:*
```

---

## 🔍 4. Monitoring & Troubleshooting

### Check Queue Status
```bash
# Monitor active queue size
php artisan queue:monitor default

# List all failed jobs
php artisan queue:failed
```

### Retry Failed Jobs
```bash
# Retry a specific failed job ID
php artisan queue:retry <JOB_ID>

# Retry all failed jobs
php artisan queue:retry all
```

### Flush Failed Jobs
```bash
# Delete all failed jobs
php artisan queue:flush
```

---

## 🧪 5. Testing Queue Execution

Run the custom test command to verify jobs are dispatched to the database queue:

```bash
php artisan notification:test --channel=all
```
Then observe your active `queue:work` console tab process the jobs cleanly!
