# Haleef Website

A lightweight, reusable PHP website for Haleef.

## Run locally

To enable the floating WhatsApp contact button, set `WHATSAPP_NUMBER` in `.env` (on production, edit the shared `/var/www/haleefglobal/shared/.env`). Use the full international number with country code, digits only. Leave it blank to hide the button. The configured number is public in the WhatsApp link.

```bash
php -S localhost:8000 router.php
```

Open `http://localhost:8000`.

## Hosting

Upload the project contents to a PHP 8.1+ host with the PDO MySQL and Fileinfo extensions. The contact form uses Brevo's transactional HTTPS API and requires PHP cURL and `BREVO_API_KEY`. Set `CONTACT_EMAIL_FROM` to a verified Brevo sender, or leave it blank to reuse `AGENDA_EMAIL_FROM`. On production, set these in `/var/www/haleefglobal/shared/.env`. Enquiries go to `team@haleefglobal.com` and replies go to the visitor. This works independently of the agenda email worker and does not require local sendmail.

`index.php` is the application front controller and `router.php` provides the same route handling for PHP’s built-in server. Apache/LiteSpeed can use the included `.htaccess` to pass non-file requests to the front controller. Nginx should use `try_files $uri $uri/ /index.php?$query_string;`.

## Before launch

- Confirm the final logo spacing and prepare an alternate dark-on-light logo if required.
- Confirm LinkedIn company URL and contact email.
- Add approved partner logos, event photography, testimonials and verified statistics.
- Connect analytics and review privacy/terms with a UAE legal professional.
- Configure SMTP or a transactional email provider for reliable form delivery.

## Haleef Learning Console

The repository now includes the first training-management foundation: database-backed trainings and editions, public bank-transfer registrations, orders, attendees, configurable payment methods, manual payment confirmation and private payment evidence.

1. Copy `.env.example` to `.env` and configure the MySQL connection.
2. Create the empty database and a least-privilege application user.
3. Run `php bin/migrate.php`.
4. Run `php bin/create-admin.php` and enter the first administrator details.
5. Sign in at `/admin/login` and configure the bank-transfer details before opening an edition.

Run `php bin/expire-unpaid-orders.php` from cron at least hourly. It releases seats held by unpaid bank-transfer orders after their configured payment deadline.

### Bank-transfer invoices and registration emails

Deploy through Actions; it bundles the locked Composer dependencies and the deploy script applies the additive migration. For local development, run `composer install`. The PDF renderer needs PHP DOM, GD and Mbstring on both the web and CLI runtimes (e.g. `php8.3-xml`, `php8.3-gd`, `php8.3-mbstring` on the droplet).

1. Verify the beneficiary, bank, IBAN, SWIFT/BIC, payment window and instructions in **Admin → Payment methods**. The bank details are taken from that configuration and copied into each invoice at issue time.
2. In `/var/www/haleefglobal/shared/.env`, set the approved `INVOICE_ISSUER_NAME`, `INVOICE_ISSUER_ADDRESS` and, if applicable, `INVOICE_ISSUER_LICENSE`. Set `INVOICE_REPLY_TO` and use the existing `BREVO_API_KEY` with a verified `REGISTRATION_EMAIL_FROM` (defaults to `AGENDA_EMAIL_FROM`). The sample's bank details, invoice numbering and company seal are not production settings. This generates a payment request, not a VAT invoice or paid receipt. Confirm the approved invoice format with your accountant before activating.
3. Run `sudo -u haleef-deploy /usr/bin/php /var/www/haleefglobal/current/bin/send-registration-emails.php --check`. It sends nothing. Then add the following via `sudo crontab -u haleef-deploy -e`, preserving existing entries:

   ```cron
   * * * * * /usr/bin/php /var/www/haleefglobal/current/bin/send-registration-emails.php >> /var/www/haleefglobal/shared/storage/logs/registration-email.log 2>&1
   ```

4. Set `BANK_TRANSFER_INVOICES_ENABLED=1` in the shared environment file. Make one test booking to an inbox you control and check the PDF, bank instructions, delivery and admin status.

New bank-transfer orders issue one invoice for the whole group. The registration session and signed-in admins can download it; there is no publicly served invoice file. Each delegate receives a separate email. Only the first delegate (the booking contact) receives the PDF. Admin Orders shows queue status and lets admins retry failed pending-payment notifications. PDFs are stored in MariaDB; protect database backups as customer data. Existing orders are not backfilled or emailed automatically.

If a PDF is missing, open the specific booking under **Admin → Orders**. “No automatic invoice was issued” means no PDF exists, and changing browsers or starting the worker will not create one. If automatic issuance is disabled, run the preflight and check the shared environment setting before making a fresh test booking. If staff can download the PDF but the customer cannot, check the original booking session or the booking contact’s email.

Keep the `storage/private` directory outside the public web root where possible. Apache and the development router also deny direct access to internal directories, but server-level isolation is preferred.

The Resources screen at `/admin/resources` stores public campaign files separately for Haleef Global and Haleef Learning and generates branded URLs. In production, point `PUBLIC_STORAGE_PATH` to the shared folder outside the release directories so deployments never remove uploaded files.

Use **Trainings**, **Editions**, and **Trainers** to create, edit, or privately preview saved records. Editions have independent publication and registration controls. A published edition needs a published programme to appear in the calendar or accept registrations. Leave the slug blank to generate it; changing an existing slug changes public links.

Set the edition's Zoom **attendee** link in the edition editor (never enter a host link). It appears on the private booking URL after bank transfer is marked paid and the attendee is confirmed. Automated joining emails, reminder emails and attendance tracking are not part of this workflow.

An edition agenda upload is listed in **Resources** under Haleef Learning. Course pages and the Training calendar accept agenda requests for published editions with an uploaded PDF. View requests under **Agenda interest**, and edit the subject and message or inspect queue statuses under **Agenda email**. With email enabled, a request queues the link for delivery; with email disabled the submitter gets the link on the page instead. The direct campaign URL bypasses the form. Do not treat an agenda request as marketing consent.

### Enable agenda email on the droplet

1. Configure a verified Brevo transactional sender and API key. Edit the existing server-side `/var/www/haleefglobal/shared/.env` and set `AGENDA_EMAIL_FROM`, `AGENDA_EMAIL_FROM_NAME` (optional), and `BREVO_API_KEY`. Set `AGENDA_EMAIL_ENABLED=1` once the worker cron is in place. Do not commit or share the API key.
2. Add this entry with `sudo crontab -u haleef-deploy -e`:

   ```cron
   * * * * * /usr/bin/php /var/www/haleefglobal/current/bin/send-agenda-emails.php >> /var/www/haleefglobal/shared/storage/logs/agenda-email.log 2>&1
   ```

3. Run `sudo -u haleef-deploy /usr/bin/php /var/www/haleefglobal/current/bin/send-agenda-emails.php --check` to verify the settings are present without sending mail. Then submit a real request to your own inbox and check **Agenda email** and the Brevo transactional log.

The CLI worker runs at most one batch at a time, retries failures with backoff, and records the Brevo response status. Pending jobs keep the template version used at the time of the request. Repeat requests for a file/email already queued or sent in the last 15 minutes do not generate duplicate mail.

Use **FAQ Library** to manage reusable FAQs and choose which appear by default. **Editions → Edit → Manage edition FAQs** controls what appears on one edition, including overrides, edition-only questions, and display order (lower numbers first). Empty overrides inherit the library content; **Reset to library defaults** restores both content and default visibility.

Production deployment assets are in `deploy/`, the GitHub Actions workflow is `.github/workflows/deploy-production.yml`, and the complete Apache/Sendy-safe server checklist is in `docs/PRODUCTION_DEPLOYMENT.md`.

### Nginx route and protection

Use the front controller and explicitly deny internal paths:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ ^/(app|bin|database|includes|storage)/ {
    deny all;
}

location ~ /\. {
    deny all;
}
```
