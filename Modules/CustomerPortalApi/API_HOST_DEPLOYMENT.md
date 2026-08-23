# API host deployment: api.newworldcargo.com

This guide prepares the existing Laravel installation currently served at `app.newworldcargo.com` to answer API requests at `https://api.newworldcargo.com`. It does not require a second Laravel installation and does not change the existing admin or Blade host.

## Required host layout

| Host | Same Laravel codebase | Purpose |
|---|---:|---|
| `app.newworldcargo.com` | Yes | Existing admin dashboard, Blade UI, and legacy applications |
| `api.newworldcargo.com` | Yes | Versioned customer portal API at `/api/v1` |

Both hosts may use the same Laravel `public/` directory, but they must have separate web-server host entries and a valid TLS certificate covering both names. DNS must point `api.newworldcargo.com` to the server before certificate issuance and verification.

## Nginx example

Use the same PHP-FPM socket and Laravel `public/` directory as the existing application. Replace the placeholders with the real server paths; do not copy production credentials into this repository.

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.newworldcargo.com;

    root /var/www/REPLACE_WITH_LARAVEL_RELEASE/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. {
        deny all;
    }
}
```

Prefer adding the HTTPS virtual host through the server’s existing certificate management process rather than hand-editing certificate paths. Redirect HTTP to HTTPS after the certificate is active. Do not remove or modify the existing `app.newworldcargo.com` host block.

## Laravel environment

Set these values in the API deployment environment, not in source control:

```dotenv
APP_URL=https://app.newworldcargo.com
CUSTOMER_PORTAL_API_URL=https://api.newworldcargo.com
CUSTOMER_PORTAL_COOKIE_SECURE=true
CUSTOMER_PORTAL_COOKIE_SAME_SITE=lax
CUSTOMER_PORTAL_API_ALLOWED_ORIGINS=https://portal.newworldcargo.com
CUSTOMER_PORTAL_BFF_SERVICE_TOKEN=<generated-server-only-token>
CUSTOMER_PORTAL_BFF_SHARED_SECRET=<generated-server-only-secret>
```

`CUSTOMER_PORTAL_API_ALLOWED_ORIGINS` must contain only the real browser/BFF origins that are authorized to reach the API. Never use `*` with credentialed requests. Keep the service token and shared secret out of `VITE_*` variables, browser bundles, logs, Git history, and chat messages.

The existing API route remains:

```text
https://api.newworldcargo.com/api/v1
```

The React BFF should use `https://api.newworldcargo.com` as its server-only `NWC_BACKEND_ORIGIN`. The browser should continue calling the React BFF’s same-origin `/api/gateway/v1` path.

## Activation sequence

1. Create a database backup and confirm rollback access.
2. Deploy the reviewed Laravel release to a separate release directory or maintenance window.
3. Install Composer dependencies using authorized private-package access.
4. Create or confirm the API DNS record and issue the TLS certificate.
5. Add and validate the API virtual host without changing the admin host.
6. Configure the environment values above and generate fresh deployment secrets.
7. Run `php artisan config:clear`, `php artisan config:cache`, and the additive portal migrations against the approved database.
8. Verify `https://api.newworldcargo.com/api/v1/health` and `ready` endpoints.
9. Verify the React BFF session exchange and authenticated calls with a non-production account before enabling customer traffic.
10. Monitor logs and error rates, and keep the previous release available for rollback.

Do not treat DNS resolution or a successful health response as proof that payments, uploads, OTP, or all customer workflows are ready. Those require their own provider and contract checks.
