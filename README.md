# Facebook AI Automation SaaS

A multi-tenant SaaS platform that automates Facebook Messenger and comment replies using rule-based automation and AI (OpenAI).

---

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+ and npm
- MySQL 8.0+
- [Cloudflare Tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/) or [ngrok](https://ngrok.com/) for Facebook webhook testing

---

## Local Setup

### 1. Clone and install dependencies

```bash
composer install
npm install
```

### 2. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```env
APP_ENV=local
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=automation_db
DB_USERNAME=root
DB_PASSWORD=your_password

QUEUE_CONNECTION=database

# Meta / Facebook
META_APP_ID=your_app_id
META_APP_SECRET=your_app_secret
META_REDIRECT_URI=https://your-tunnel-domain.com/facebook/callback
META_WEBHOOK_VERIFY_TOKEN=your_random_token
META_GRAPH_API_VERSION=v20.0

# OpenAI (optional for local — test mode activates if empty)
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini

# Stripe
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# CORS (set to your tunnel URL when testing webhooks)
FRONTEND_URL=http://localhost:8000
```

### 3. Create database and migrate

```bash
php artisan migrate
php artisan db:seed
```

This seeds:
- Plans (Starter, Growth, Pro)
- Roles and permissions
- Demo Super Admin: `admin@demo.com` / `password`
- Demo Client: `client@demo.com` / `password`
- Demo Facebook page and automation rules

### 4. Build frontend

```bash
npm run build
# or for hot reload:
npm run dev
```

### 5. Start the server

```bash
php artisan serve
```

### 6. Start queue worker

```bash
php artisan queue:work
```

---

## Facebook Webhook Setup (Cloudflare Tunnel / ngrok)

### Using Cloudflare Tunnel

```bash
cloudflared tunnel --url http://localhost:8000
```

Copy the generated HTTPS URL (e.g. `https://abc123.trycloudflare.com`).

Update `.env`:

```env
APP_URL=https://abc123.trycloudflare.com
META_REDIRECT_URI=https://abc123.trycloudflare.com/facebook/callback
```

### Using ngrok

```bash
ngrok http 8000
```

Update `.env` with the ngrok HTTPS URL.

### Configure Meta Developer App

1. Go to [developers.facebook.com](https://developers.facebook.com)
2. Open your app → Webhooks → Add webhook for **Page**
3. Callback URL: `https://your-tunnel-domain.com/api/webhooks/facebook`
4. Verify token: value of `META_WEBHOOK_VERIFY_TOKEN` in `.env`
5. Subscribe to: `messages`, `messaging_postbacks`, `feed`

---

## Local Testing Commands

### Simulate incoming message webhook

```bash
php artisan simulate:message
php artisan simulate:message --message="What are your prices?" --sender-id=111222333
php artisan simulate:message --page-id=YOUR_PAGE_ID --message="Hello"
```

### Simulate incoming comment webhook

```bash
php artisan simulate:comment
php artisan simulate:comment --message="Love this!" --commenter-name="Jane"
php artisan simulate:comment --page-id=YOUR_PAGE_ID --message="How much does it cost?"
```

After running, process the queued jobs:

```bash
php artisan queue:work --once
```

---

## AI Test Mode

When `OPENAI_API_KEY` is empty and `APP_ENV=local`, the AI reply service returns a stub response instead of calling OpenAI. This lets you test the full automation flow without an API key.

---

## Testing Checklist

### Authentication
- [ ] Register new account
- [ ] Login works
- [ ] Logout works
- [ ] Suspended user cannot login
- [ ] Role-protected routes return 403 for wrong role

### Plans and Usage
- [ ] Plans display on billing page
- [ ] Usage limits display correctly
- [ ] Usage increments after reply
- [ ] Reply blocked after limit exceeded
- [ ] Admin can extend limit via Users panel

### Facebook
- [ ] Facebook connect URL redirects to Meta OAuth
- [ ] OAuth callback saves page and token
- [ ] Page list loads on Facebook Pages screen
- [ ] Automation toggles save correctly
- [ ] Token stored encrypted (not visible in DB or API)

### Webhook
- [ ] Meta verifies webhook (`GET /api/webhooks/facebook`)
- [ ] `simulate:message` creates WebhookEvent and queues job
- [ ] `simulate:comment` creates WebhookEvent and queues job
- [ ] Webhook logs visible in Admin → Webhooks
- [ ] Failed webhook visible and retryable

### Message Automation
- [ ] Incoming message saved as Conversation + Message
- [ ] Matching rule fires reply
- [ ] AI fallback fires if no rule matches (requires page connected and AI enabled)
- [ ] Human handover stops automation
- [ ] Usage increments after each reply

### Comment Automation
- [ ] Incoming comment saved as FacebookComment
- [ ] Matching rule fires reply
- [ ] AI fallback fires if no rule matches
- [ ] Usage increments

### Payment (Stripe test mode)
- [ ] Checkout redirects to Stripe
- [ ] Stripe test card `4242 4242 4242 4242` completes payment
- [ ] Payment success activates plan
- [ ] Usage limits update after plan activation
- [ ] Stripe webhook signature verified

### Admin Panel
- [ ] Admin Dashboard shows platform stats
- [ ] User management: suspend/activate/assign plan
- [ ] Plans CRUD works
- [ ] Payments list shows all payments
- [ ] Webhook logs visible
- [ ] AI logs visible
- [ ] Audit logs visible
- [ ] Email logs visible
- [ ] Analytics page loads

### Notifications
- [ ] Notification appears when usage reaches 80%
- [ ] Notification appears when usage is exceeded
- [ ] Unread count shown in sidebar
- [ ] Mark as read works

---

## Default Credentials

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@demo.com | password |
| Demo Client | client@demo.com | password |

> **Never use these credentials in production.** Change all passwords before deploying.

---

## VPS Deployment (Ubuntu 22.04 / 24.04)

### Prerequisites

```bash
# PHP 8.2
sudo add-apt-repository ppa:ondrej/php
sudo apt update && sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl

# MySQL 8
sudo apt install -y mysql-server

# Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Nginx
sudo apt install -y nginx

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Supervisor
sudo apt install -y supervisor

# Certbot (SSL)
sudo apt install -y certbot python3-certbot-nginx
```

### 1. Upload code

```bash
sudo mkdir -p /var/www/automation-project
sudo chown $USER:www-data /var/www/automation-project
git clone <your-repo-url> /var/www/automation-project
```

### 2. Configure environment

```bash
cd /var/www/automation-project
cp .env.example .env
nano .env
```

Key production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=automation_db
DB_USERNAME=automation_user
DB_PASSWORD=strong_password_here

QUEUE_CONNECTION=database

META_APP_ID=...
META_APP_SECRET=...
META_REDIRECT_URI=https://your-domain.com/facebook/callback
META_WEBHOOK_VERIFY_TOKEN=...

OPENAI_API_KEY=sk-...

# Stripe (default) or SSLCommerz
PAYMENT_GATEWAY=stripe
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

FRONTEND_URL=https://your-domain.com
```

### 3. Install dependencies and migrate

```bash
composer install --no-dev --optimize-autoloader
npm ci --no-audit
npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=PlanSeeder
php artisan db:seed --class=DemoSeeder  # optional demo data
```

### 4. Build config/route caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 5. Set permissions

```bash
sudo chown -R www-data:www-data /var/www/automation-project/storage
sudo chown -R www-data:www-data /var/www/automation-project/bootstrap/cache
sudo chmod -R 775 /var/www/automation-project/storage
sudo chmod -R 775 /var/www/automation-project/bootstrap/cache
```

### 6. Nginx

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/automation-project
# Edit the file: replace 'your-domain.com' with your actual domain
sudo nano /etc/nginx/sites-available/automation-project

sudo ln -s /etc/nginx/sites-available/automation-project /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 7. SSL with Certbot

```bash
# Temporarily serve HTTP only (comment out the HTTPS block, keep port 80 with root)
# Then run:
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Certbot will auto-configure HTTPS and set up renewal
# Verify renewal works:
sudo certbot renew --dry-run
```

### 8. Supervisor (queue workers)

```bash
sudo cp deploy/supervisor.conf /etc/supervisor/conf.d/automation-project.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start automation-workers:*
sudo supervisorctl status
```

### 9. Cron (Laravel scheduler)

```bash
sudo crontab -u www-data -e
# Add:
* * * * * cd /var/www/automation-project && php artisan schedule:run >> /dev/null 2>&1
```

### 10. MySQL setup

```sql
CREATE DATABASE automation_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'automation_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON automation_db.* TO 'automation_user'@'localhost';
FLUSH PRIVILEGES;
```

### Database Backup

```bash
# Manual backup
mysqldump -u automation_user -p automation_db > backup_$(date +%Y%m%d_%H%M%S).sql

# Automated daily backup (add to root crontab)
0 2 * * * mysqldump -u automation_user -pSTRONG_PASSWORD automation_db | gzip > /var/backups/automation_db_$(date +\%Y\%m\%d).sql.gz
```

### Deploy Script

For subsequent deployments:

```bash
bash /var/www/automation-project/deploy/deploy.sh
```

This pulls latest code, installs dependencies, builds assets, runs migrations, rebuilds caches, restarts workers.

### Post-deployment Checklist

- [ ] `APP_DEBUG=false` in `.env`
- [ ] `APP_ENV=production` in `.env`
- [ ] HTTPS working (no browser certificate errors)
- [ ] `php artisan route:list` shows all routes
- [ ] Login works (`admin@demo.com` or your production credentials)
- [ ] Facebook OAuth redirect URI matches `META_REDIRECT_URI` in `.env`
- [ ] Meta webhook callback URL registered in Facebook Developer App
- [ ] Stripe webhook endpoint registered: `https://your-domain.com/api/webhooks/stripe`
- [ ] Queue workers running: `sudo supervisorctl status`
- [ ] Cron running: `sudo crontab -u www-data -l`
- [ ] Storage symlink: `php artisan storage:link`
- [ ] Test `simulate:message` → check webhook logs in admin panel
