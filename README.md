# Haleef Website

A lightweight, reusable PHP website for Haleef.

## Run locally

```bash
php -S localhost:8000 router.php
```

Open `http://localhost:8000`.

## Hosting

Upload the project contents to a PHP 8.1+ host with the PDO MySQL and Fileinfo extensions. Ensure PHP `mail()` is configured or replace the contact handler with the production email provider of choice.

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

Keep the `storage/private` directory outside the public web root where possible. Apache and the development router also deny direct access to internal directories, but server-level isolation is preferred.

The Resources screen at `/admin/resources` stores public campaign files separately for Haleef Global and Haleef Learning and generates branded URLs. In production, point `PUBLIC_STORAGE_PATH` to the shared folder outside the release directories so deployments never remove uploaded files.

Use **Trainings**, **Editions**, and **Trainers** to create, edit, or privately preview saved records. Editions have independent publication and registration controls. A published edition needs a published programme to appear in the calendar or accept registrations. Leave the slug blank to generate it; changing an existing slug changes public links.

Set the edition's Zoom **attendee** link in the edition editor (never enter a host link). It appears on the private booking URL after bank transfer is marked paid and the attendee is confirmed. Automated joining emails, reminder emails and attendance tracking are not part of this workflow.

An edition agenda upload is listed in **Resources** under Haleef Learning. Its landing page asks for an email and then sends the visitor to the same public file. The public campaign URL can be shared independently and does not capture leads. View recorded requests under **Agenda interest**. Repeated requests for the same edition/file/email update the latest timestamp; do not use the download request as email marketing consent.

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
