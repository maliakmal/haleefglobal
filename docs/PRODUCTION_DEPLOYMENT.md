# Haleef production deployment

This deployment installs Haleef beside the existing Sendy application. The Droplet uses Apache with mod_php. Do not change Sendy's document root, database, cron jobs or Apache virtual hosts.

## 1. Audit the Droplet first

Run these read-only checks and save the output before changing the server:

```bash
lsb_release -a
php -v
mysql --version
sudo apache2ctl -S
sudo systemctl --no-pager --full status apache2 mariadb
sudo certbot certificates
```

The audited server currently uses Apache 2.4 with `libapache2-mod-php8.3`; Nginx and PHP-FPM are not required.

Take a Sendy database dump and a copy of `/etc/apache2` before proceeding.

## 2. Required packages

Install only missing packages. Haleef requires PHP 8.1+, PDO MySQL, Fileinfo, mbstring, curl, MySQL client tools, Apache, rsync and Certbot. The audited Droplet already has Apache, PHP 8.3, MariaDB 10.11 and all required PHP extensions.

```bash
sudo apt update
sudo apt install rsync git mariadb-client certbot python3-certbot-apache
```

The database can use the MySQL/MariaDB server already supporting Sendy, but Haleef must have a separate database and least-privilege user.

## 3. Deployment account and folders

Create a dedicated account rather than deploying as root:

```bash
sudo adduser --disabled-password --gecos "" haleef-deploy
sudo usermod -aG www-data haleef-deploy
sudo install -d -o haleef-deploy -g www-data -m 2775 /var/www/haleefglobal
sudo install -d -o haleef-deploy -g www-data -m 2775 /var/www/haleefglobal/releases
sudo install -d -o haleef-deploy -g www-data -m 2770 /var/www/haleefglobal/shared
sudo install -d -o haleef-deploy -g www-data -m 2770 /var/www/haleefglobal/shared/storage/private
sudo install -d -o haleef-deploy -g www-data -m 2775 /var/www/haleefglobal/shared/storage/logs
sudo install -d -o haleef-deploy -g www-data -m 2775 /var/www/haleefglobal/shared/public-resources/haleef-global
sudo install -d -o haleef-deploy -g www-data -m 2775 /var/www/haleefglobal/shared/public-resources/haleef-learning
sudo install -d -o haleef-deploy -g www-data -m 2750 /var/www/haleefglobal/shared/backups
```

Add the deployment public key to `/home/haleef-deploy/.ssh/authorized_keys`. Keep the private key only in the GitHub production environment secret.

## 4. Database

Choose a strong password and run the following in MySQL as an administrator. Do not reuse Sendy's database or user.

```sql
CREATE DATABASE haleef CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'haleef_app'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_A_STRONG_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
ON haleef.* TO 'haleef_app'@'127.0.0.1';
FLUSH PRIVILEGES;
```

The `DROP` permission is included because future controlled migrations may need to remove obsolete indexes or columns. The user has no access to Sendy's database.

## 5. Shared environment file

Create `/var/www/haleefglobal/shared/.env` from `.env.example`. Production storage paths must be absolute:

```dotenv
APP_ENV=production
APP_URL=https://www.haleefglobal.com
APP_TIMEZONE=Asia/Dubai
APP_KEY=GENERATE_A_LONG_RANDOM_VALUE

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=haleef
DB_USERNAME=haleef_app
DB_PASSWORD=REPLACE_WITH_THE_DATABASE_PASSWORD

PRIVATE_STORAGE_PATH=/var/www/haleefglobal/shared/storage/private
MAX_PAYMENT_EVIDENCE_MB=10
PUBLIC_STORAGE_DRIVER=local
PUBLIC_STORAGE_PATH=/var/www/haleefglobal/shared/public-resources
MAX_PUBLIC_ASSET_MB=15
BACKUP_STORAGE_PATH=/var/www/haleefglobal/shared/backups
BACKUP_RETENTION_DAYS=14
```

Protect it:

```bash
sudo chown haleef-deploy:www-data /var/www/haleefglobal/shared/.env
sudo chmod 640 /var/www/haleefglobal/shared/.env
```

## 6. Apache

Copy `deploy/apache/haleefglobal.conf` to `/etc/apache2/sites-available/haleefglobal.conf`, enable the required modules and site, then test it:

```bash
sudo a2enmod rewrite headers expires
sudo a2ensite haleefglobal.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Do not disable or edit `sendy.conf`, `sendy-le-ssl.conf`, `haleeflearning.com.conf` or `haleeflearning.com-le-ssl.conf`. Apache chooses the application by `ServerName`, so these applications safely share ports 80 and 443.

## 7. GitHub production environment

In repository **Settings → Environments**, create `production` and restrict deployments to `main`. Add these environment secrets:

| Secret | Value |
| --- | --- |
| `DEPLOY_HOST` | Droplet public IP or deployment hostname |
| `DEPLOY_PORT` | Normally `22` |
| `DEPLOY_USER` | `haleef-deploy` |
| `DEPLOY_SSH_KEY` | Private Ed25519 deployment key |
| `DEPLOY_KNOWN_HOSTS` | Verified SSH host-key line for the Droplet |

Verify the Droplet's SSH fingerprint from the DigitalOcean console before storing `DEPLOY_KNOWN_HOSTS`. Do not blindly trust a host key obtained across an unverified network.

Every push to `main` then performs:

1. PHP syntax validation.
2. Upload into a new immutable release folder.
3. Link the shared environment and persistent storage.
4. Create a compressed database backup.
5. Apply pending migrations.
6. Atomically switch the `current` symlink.
7. Call `/health` through local Apache.
8. Roll the application symlink back if the health check fails.
9. Keep the five newest releases.

Database migrations are intentionally forward-compatible because application rollback does not reverse an already-applied schema migration.

## 8. Initial deployment and administrator

Run the GitHub workflow manually for the first release. After it passes:

```bash
cd /var/www/haleefglobal/current
php bin/create-admin.php
```

Add the unpaid-order expiry job under the deployment account:

```cron
7 * * * * cd /var/www/haleefglobal/current && php bin/expire-unpaid-orders.php >> /var/www/haleefglobal/shared/storage/logs/expire-orders.log 2>&1
```

## 9. DNS records

Create these website records only after the first release works through a local hosts-file test. Preserve all Google Workspace, Brevo, DKIM, SPF, DMARC, verification, bounce and tracking records.

| Zone | Type | Name | Value |
| --- | --- | --- | --- |
| haleefglobal.com | A | `@` | Droplet IPv4 |
| haleefglobal.com | CNAME | `www` | `haleefglobal.com` |
| haleefglobal.com | A | `resources` | Droplet IPv4 |
| haleeflearning.com | A | `@` | Droplet IPv4 |
| haleeflearning.com | CNAME | `www` | `haleeflearning.com` |
| haleeflearning.com | A | `resources` | Droplet IPv4 |

Do not add AAAA records unless IPv6 is configured and tested on the Droplet firewall and Nginx.

## 10. TLS certificates

After the three Haleef Global hostnames resolve to the Droplet, issue their certificate:

```bash
sudo certbot --apache --redirect \
  -d haleefglobal.com \
  -d www.haleefglobal.com \
  -d resources.haleefglobal.com
```

After `resources.haleeflearning.com` resolves, give that resource host its own certificate. This avoids modifying the existing Haleef Learning and Sendy certificates:

```bash
sudo certbot --apache --redirect \
  --cert-name haleeflearning-resources \
  -d resources.haleeflearning.com

sudo certbot renew --dry-run
```

Test the website, both resource domains, Sendy's campaign hostname, Google Workspace delivery and Brevo tracking before considering the migration complete.
