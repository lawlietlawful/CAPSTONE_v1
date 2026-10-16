# Deploying the Student Referral System

This guide puts the whole system on one Linux server under one HTTPS address:

| Address | What it serves |
|---|---|
| `https://portal.your-school.edu.ph/` | Admin and counselor web pages (Laravel) |
| `https://portal.your-school.edu.ph/api` | The API the two apps call |
| `https://portal.your-school.edu.ph/teacher/` | Teacher Portal |
| `https://portal.your-school.edu.ph/student/` | Student Portal |

The ML engine (port 8001) and MySQL (port 3306) run on the same machine and are **never** opened to the internet.

> **Stage 1 = demo data only.** Until the school has approved the system in writing
> and its Data Protection Officer has signed off (RA 10173), use seeded or made-up
> students only. Do not import real student records onto a server you run.

---

## What you need

- A Linux server: Ubuntu 24.04 LTS or newer, 2 GB RAM, 20 GB disk (a small VPS or a school machine).
- A domain or sub-domain pointed at the server (an `A` record), e.g. `portal.your-school.edu.ph`.
- On your Windows PC: this repository and Flutter (to build the two portals).

Versions the code needs:

| | Version | Why |
|---|---|---|
| PHP | **8.4 or newer** | the locked Symfony packages require PHP ≥ 8.4.1 (`composer.json` says 8.3, but `composer.lock` decides) |
| MySQL / MariaDB | 8.0+ / 10.6+ | |
| Python | 3.12+ | for the ML engine; libraries are pinned in `ml_engine/requirements.txt` |
| Node.js | 20+ | builds the web pages' CSS/JS once (`npm run build`) |

---

## 1. Install the server software

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server git unzip curl python3 python3-venv ufw certbot python3-certbot-nginx

# PHP 8.4 (Ubuntu 24.04 ships 8.3, so add the PHP PPA; newer Ubuntu releases may not need it)
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml \
  php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl

# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# Node.js 20+ (only for the one-time asset build)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash - && sudo apt install -y nodejs
```

Firewall — only SSH and web traffic:

```bash
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
```

## 2. Database and its own user

```bash
sudo mysql_secure_installation
sudo mysql
```
```sql
CREATE DATABASE student_referral_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'referral_app'@'localhost' IDENTIFIED BY 'choose-a-long-random-password';
GRANT ALL PRIVILEGES ON student_referral_db.* TO 'referral_app'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Get the code

```bash
sudo mkdir -p /var/www/referral && sudo chown $USER:$USER /var/www/referral
git clone <your-repository-url> /var/www/referral
cd /var/www/referral/student-referral-system

composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

## 4. Configure Laravel

```bash
cp ../deploy/env.production.example .env
php artisan key:generate
nano .env      # replace every <placeholder>: domain, DB password, ML key
```

Make the ML key a long random string, for example `openssl rand -hex 32`, and use the same value in step 5.

```bash
php artisan migrate --force
php artisan db:seed --force          # demo accounts and sample data (stage 1)
php artisan optimize

sudo chown -R www-data:www-data storage bootstrap/cache
```

## 5. ML engine as a service

```bash
cd /var/www/referral/student-referral-system/ml_engine
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
sudo chown -R www-data:www-data .

sudo mkdir -p /etc/referral
echo "ML_ENGINE_KEY=<the same key as in .env>" | sudo tee /etc/referral/ml-engine.env
sudo chmod 600 /etc/referral/ml-engine.env

sudo cp /var/www/referral/deploy/ml-engine.service /etc/systemd/system/
sudo systemctl daemon-reload && sudo systemctl enable --now ml-engine
curl http://127.0.0.1:8001/      # → {"status":"online","models_loaded":true,...}
```

Do **not** run `train_model.py` or turn on `ML_RETRAIN_ENABLED` on the server; the shipped model is the validated one.

## 6. Scheduler (reminders, risk re-checks, backups)

```bash
sudo crontab -u www-data -e
```
Add this line:
```
* * * * * cd /var/www/referral/student-referral-system && php artisan schedule:run >> /dev/null 2>&1
```

This runs the seminar reminders, risk reassessments, token clean-up and `db:backup` (a daily copy in `storage/app/backups`, newest 14 kept). Copy those backups off the server now and then; a backup on the same disk doesn't survive a lost server.

## 7. Build and upload the two portals (on your Windows PC)

```powershell
cd "D:\CAPSTONE PROJECT"
.\deploy\build-portals.ps1 -Domain portal.your-school.edu.ph
```

That builds both apps with the right sub-path and API address into `deploy\out\teacher` and `deploy\out\student`. Upload them:

```powershell
scp -r deploy\out\teacher deploy\out\student <user>@<server>:/tmp/
```
```bash
sudo mkdir -p /var/www/portals
sudo rm -rf /var/www/portals/teacher /var/www/portals/student
sudo mv /tmp/teacher /tmp/student /var/www/portals/
```

Re-run these two steps whenever the Flutter code changes.

## 8. nginx and HTTPS

```bash
sudo cp /var/www/referral/deploy/nginx-portal.conf /etc/nginx/sites-available/referral
sudo nano /etc/nginx/sites-available/referral     # set server_name to your domain
sudo ln -s /etc/nginx/sites-available/referral /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

sudo certbot --nginx -d portal.your-school.edu.ph   # free certificate, renews itself
```

## 9. Change the demo passwords

`db:seed` creates accounts that all use the password `password` (admin, counselor, teacher, student). Before giving anyone the address:

1. Sign in at `https://portal.your-school.edu.ph/login` as `admin@school.com` / `password`, and change that password right away.
2. In **User Management**, reset or deactivate every other seeded account.

## 10. Check before going live

```bash
cd /var/www/referral/student-referral-system
php artisan deploy:check
```

It checks production mode, debug pages off, HTTPS, allowed browser origins, the database user, the ML engine key, writable folders, backups, and that **no account still uses the demo password**. Any **FAIL** must be fixed. A **WARN** is worth reading. It exits non-zero on failure, so a deploy script can stop on it.

Then open `/teacher/` and `/student/` on a phone and sign in.

---

## Updating later

```bash
cd /var/www/referral && git pull
cd student-referral-system
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
sudo systemctl restart ml-engine php8.4-fpm
php artisan deploy:check
```
Rebuild and upload the portals (step 7) if the Flutter apps changed.

## When something goes wrong

| Symptom | Check |
|---|---|
| Portal shows "Can't reach the server" | `curl https://portal.your-school.edu.ph/api/login` from your PC; `sudo tail -f /var/log/nginx/error.log` |
| 500 error on the web pages | `tail -f storage/logs/laravel-*.log` (debug pages are off in production, by design) |
| Reports stay "Pending AI" | `sudo systemctl status ml-engine`; `sudo journalctl -u ml-engine -n 50`; then `php artisan reports:reassess` |
| No reminders or backups | the cron line in step 6; `php artisan schedule:list` |
| Restore a backup | `mysql -u referral_app -p student_referral_db < storage/app/backups/<file>.sql` |

## Before real students (stage 2 and later)

Not covered by this guide, and needed before real records go on the server:

- Written approval from the school, and the Data Protection Officer's sign-off on consent, access, retention and where the server is hosted.
- An agreed explanation for teachers of what the risk labels mean (a prompt to look closer, not a verdict).
- School approval and a budget before entering a Semaphore SMS key (texts to parents cost money and need consent).
- A named person at the school who owns the server, the backups and updates after the capstone ends.
