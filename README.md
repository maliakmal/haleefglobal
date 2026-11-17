# Haleef Website

A lightweight, reusable PHP website for Haleef.

## Run locally

```bash
php -S localhost:8000 router.php
```

Open `http://localhost:8000`.

## Hosting

Upload the project contents to any PHP 8+ web host. Ensure PHP `mail()` is configured or replace the contact handler with the production email provider of choice.

`index.php` is the application front controller and `router.php` provides the same route handling for PHP’s built-in server. Apache/LiteSpeed can use the included `.htaccess` to pass non-file requests to the front controller. Nginx should use `try_files $uri $uri/ /index.php?$query_string;`.

## Before launch

- Confirm the final logo spacing and prepare an alternate dark-on-light logo if required.
- Confirm LinkedIn company URL and contact email.
- Add approved partner logos, event photography, testimonials and verified statistics.
- Connect analytics and review privacy/terms with a UAE legal professional.
- Configure SMTP or a transactional email provider for reliable form delivery.
