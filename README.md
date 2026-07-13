# Blasting Message

## Overview

Blasting Message is an internal admin tool for sending approved WhatsApp template messages through the Meta WhatsApp Cloud API.

The main flow is intentionally simple:

1. Admin logs in.
2. Admin synchronizes approved templates from Meta.
3. Admin creates a campaign.
4. Admin uploads a CSV or XLSX recipient file.
5. The system detects the name and phone columns.
6. The system validates phone numbers and detects duplicates.
7. Admin maps template variables to uploaded columns or fixed values.
8. Admin previews the rendered message.
9. Admin sends immediately or schedules the campaign.
10. The system sends one API request per recipient through Laravel Queue.
11. Meta webhooks update sent, delivered, read, and failed statuses.
12. Admin reviews campaign history and retries failed recipients only.

## Stack

- Laravel 13
- React
- PostgreSQL
- Pest
- Laravel Queue with database driver
- Laravel Scheduler
- Meta WhatsApp Cloud API

## Documentation Order

1. `01-product-requirements.md`
2. `02-user-flow.md`
3. `03-business-rules.md`
4. `04-data-import-and-mapping.md`
5. `05-meta-whatsapp-integration.md`
6. `06-architecture.md`
7. `07-database-design.md`
8. `08-status-and-processing.md`
9. `09-ui-ux-requirements.md`
10. `10-security-and-operations.md`
11. `11-testing-strategy.md`
12. `plan.md`

## Scope Principle

Build only what is required for a reliable internal blasting tool. Avoid CRM features, contact management, template creation, analytics platforms, external notifications, or multi-role administration unless explicitly added later.

## Operations

Required production configuration:

- PostgreSQL database.
- `QUEUE_CONNECTION=database`.
- `APP_TIMEZONE=Asia/Jakarta` or the timezone used by the business.
- `META_WHATSAPP_BUSINESS_ACCOUNT_ID`, `META_WHATSAPP_PHONE_NUMBER_ID`, and `META_WHATSAPP_ACCESS_TOKEN`.
- `META_WHATSAPP_WEBHOOK_VERIFY_TOKEN`.
- `META_APP_SECRET` for webhook signature verification when configured in Meta.
- HTTPS public URL for `/webhooks/meta/whatsapp`.

Setup commands:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=AdminSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Required running processes:

```bash
php artisan queue:work database --queue=default --tries=3 --backoff=60
php artisan schedule:run
```

Run the scheduler every minute from cron or the process manager:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Operational checks:

- Keep one or more queue workers running, but tune worker concurrency to the approved Meta throughput.
- Monitor failed jobs and application logs.
- Restart queue workers after deployment or config changes.
- Do not expose Meta credentials to React or commit real `.env` values.
- Register the webhook callback URL in Meta as `https://your-domain.com/webhooks/meta/whatsapp`.
