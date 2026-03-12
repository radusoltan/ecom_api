# Deploy Checklist

## Pre-Deploy Verification

### Services
- [ ] PostgreSQL running and accepting connections (port 5432)
- [ ] PgBouncer running (port 6432) — required for RLS SET context
- [ ] Redis running
- [ ] RabbitMQ running
- [ ] Elasticsearch running (HTTPS, port 9200)
- [ ] PHP-FPM pool `[ecom]` active

### JWT Keys Pre-flight
- [ ] `config/jwt/private.pem` — readable by PHP-FPM user (www-data), mode 640, group www-data
- [ ] `config/jwt/public.pem` — readable by PHP-FPM user
- [ ] `config/jwt/private-test.pem` — readable (chmod 644) for test runner
- [ ] `config/jwt/public-test.pem` — readable (chmod 644) for test runner

### Database
- [ ] Migrations up to date: `php bin/console doctrine:migrations:status`
- [ ] Schema in sync: `php bin/console doctrine:schema:validate`
- [ ] RLS enabled on all tenant-scoped tables (43/43)
- [ ] Expression indexes present (24 Sprint 4 indexes)

### Quality Gates
- [ ] PHPStan Level 8: `vendor/bin/phpstan analyse`
- [ ] Deptrac: `vendor/bin/deptrac analyse`
- [ ] PHPUnit: `vendor/bin/phpunit --no-coverage`
- [ ] PSR-12: `vendor/bin/php-cs-fixer fix --dry-run`

### Security
- [ ] `server_tokens off` in nginx config
- [ ] Security headers: X-Content-Type-Options, X-Frame-Options, Referrer-Policy
- [ ] TLS 1.2+ only (when SSL is enabled)
- [ ] Prod secrets vault decrypts: `php bin/console secrets:list --reveal --env=prod`
- [ ] `OTEL_TRACES_SAMPLE_RATE=0` in FPM env (unless collector is running)

### Frontend
- [ ] Admin build passes: `cd /var/www/ecom_admin && pnpm build`
- [ ] Storefront build passes: `cd /var/www/ecom_storefront && pnpm build`
- [ ] No high/critical npm audit findings

### Post-Deploy
- [ ] Smoke test: `curl -s http://localhost:8000/api | jq .`
- [ ] Auth test: login endpoint returns JWT token
- [ ] Tenant isolation: verify RLS blocks cross-tenant access
