# CSP Nonce Migration Plan

**Status**: Planned (post-MVP)
**Priority**: P1 — Security hardening, NOT an MVP blocker
**Scheduled**: Sprint 13–15
**Created**: 2026-03-12
**Author**: DevOps / Security Team

---

## Executive Summary

The platform currently ships `unsafe-inline` in `script-src` and `style-src` CSP directives for the
dev/test environments. The production Nelmio Security config already removes `unsafe-inline` but
has no nonce mechanism in place to replace it — meaning API Platform docs and Flowbite-React
theme scripts would break in production today. This plan defines the phased work needed to reach
a fully nonce-based CSP across all three applications.

React auto-escapes output by default and `isomorphic-dompurify` is active on the storefront,
so the practical XSS risk in the current state is Medium — not Critical. MVP deploy can proceed
without completing this migration.

---

## 1. Current State

### 1.1 Backend API (Symfony / Nelmio Security)

**Dev and Test environments** (`config/packages/nelmio_security.yaml`,
`config/packages/test/nelmio_security.yaml`):

```
script-src: 'self' 'unsafe-inline'   # required for API Platform admin UI
style-src:  'self' 'unsafe-inline'   # required for API Platform admin UI
```

**Production environment** (`config/packages/prod/nelmio_security.yaml`):

```
script-src: 'self'   # unsafe-inline removed — API docs inline scripts will be blocked
style-src:  'self'   # unsafe-inline removed — API docs inline styles will be blocked
```

The prod config removed `unsafe-inline` without adding nonce support. Consequence: the API
Platform Swagger/Redoc UI at `/api/docs` will produce CSP violations in production and may
not function correctly.

Nginx serves no `Content-Security-Policy` header of its own — all CSP originates exclusively
from Nelmio Security in PHP.

### 1.2 Storefront (`/var/www/ecom_storefront` — Next.js 16)

- `next.config.ts` sets `X-Frame-Options`, `X-Content-Type-Options`, and `Referrer-Policy`
  via `headers()`. No CSP header is set.
- `middleware.ts` handles i18n routing only (next-intl). No CSP header injection.
- No `dangerouslySetInnerHTML` usage detected in source files.
- Root `app/layout.tsx` is a minimal pass-through; locale-specific layout lives in
  `app/[locale]/layout.tsx`.
- No `_document.tsx` nonce injection exists.

### 1.3 Admin Panel (`/var/www/ecom_admin` — Next.js 16)

- `next.config.ts` sets no CSP header.
- `middleware.ts` handles auth-guard redirects only. No CSP header injection.
- `app/layout.tsx` renders `<ThemeModeScript />` from `flowbite-react`. This component
  injects an inline `<script>` into `<head>` to set the dark/light theme before hydration.
  This is the primary known source of `unsafe-inline` requirement in the admin app.
- No `dangerouslySetInnerHTML` usage detected in source files.

### 1.4 Summary Table

| Application | CSP Header Present | unsafe-inline (scripts) | unsafe-inline (styles) | Known Inline Scripts |
|---|---|---|---|---|
| Backend API (dev) | Yes (Nelmio) | Yes | Yes | API Platform docs UI |
| Backend API (prod) | Yes (Nelmio) | No | No | API Platform docs UI (broken) |
| Storefront | No | N/A | N/A | None detected |
| Admin Panel | No | N/A | N/A | Flowbite ThemeModeScript |

---

## 2. Risk Assessment

**Overall risk: Medium**

| Factor | Assessment |
|---|---|
| React auto-escaping | Mitigates reflected XSS in JSX templates |
| isomorphic-dompurify active | Sanitizes any rich-text rendering in storefront |
| No dangerouslySetInnerHTML detected | No obvious injection vectors in app code |
| unsafe-inline in dev/test | Acceptable for developer tooling; not exposed to end users |
| unsafe-inline absent in prod API | Prod CSP is stricter but breaks API docs UI |
| No CSP on frontend apps | Frontend apps are unprotected by CSP — any future inline addition is risky |

The production API already has a stronger CSP than the frontend apps. The frontend gap is
the primary concern: if `unsafe-inline` is never added, the risk stays low, but there is
no enforcement layer to catch a future regression.

---

## 3. What Nonce-Based CSP Replaces

Instead of `unsafe-inline` (which allows all inline scripts globally), a nonce approach
allows only inline scripts that carry a specific cryptographically random value generated
per-request:

```
Content-Security-Policy: script-src 'self' 'nonce-<random>'
```

```html
<script nonce="<same-random>">/* this executes */</script>
<script>/* this is blocked */</script>
```

The nonce must be:
- Generated server-side per HTTP response (never reused)
- Injected into the CSP header AND into every legitimate inline `<script>` / `<style>` tag
- Cryptographically random (minimum 128 bits)

---

## 4. Components Requiring Changes

### 4.1 Backend API — Nelmio Security + API Platform (Sprint 13)

**Problem**: API Platform's Swagger UI and Redoc bundle their own inline scripts and styles.
The prod config removed `unsafe-inline` but these docs pages will emit CSP violations.

**Required changes**:

1. Implement a Symfony `RequestListener` or `ResponseListener` that generates a
   cryptographically random nonce per request and stores it in a request attribute.

2. Configure Nelmio Security to read the nonce from the request attribute. Nelmio Security
   supports nonce injection via the `nonce` key in the CSP config:

   ```yaml
   # config/packages/prod/nelmio_security.yaml
   csp:
     enforce:
       script-src:
         - 'self'
         - 'nonce-REQUEST_ATTRIBUTE_NAME'
       style-src:
         - 'self'
         - 'nonce-REQUEST_ATTRIBUTE_NAME'
   ```

3. Override the API Platform Twig templates to inject the nonce attribute into inline
   `<script>` and `<style>` tags rendered by the docs UI.

4. Update dev/test configs to also use nonce (remove `unsafe-inline` fallback once the
   nonce mechanism is verified).

**Files to modify**:
- `config/packages/nelmio_security.yaml`
- `config/packages/prod/nelmio_security.yaml`
- `config/packages/test/nelmio_security.yaml`
- New: `src/Shared/Infrastructure/Security/CspNonceListener.php`
- New or override: `templates/bundles/api_platform/` (Swagger/Redoc templates)

### 4.2 Admin Panel — Flowbite ThemeModeScript (Sprint 14)

**Problem**: `<ThemeModeScript />` from `flowbite-react` injects an inline `<script>` in
`<head>` without a nonce attribute. Without `unsafe-inline`, this script will be blocked,
breaking dark/light mode initialization.

**Options** (evaluate in order of preference):

**Option A — Nonce via Next.js middleware (recommended)**

Next.js 14+ supports nonce propagation via `headers()` in `middleware.ts`:

```typescript
// app/middleware.ts
import { NextResponse } from 'next/server';
import { nanoid } from 'nanoid';

export function middleware(request: NextRequest) {
  const nonce = Buffer.from(nanoid()).toString('base64');
  const cspHeader = [
    `script-src 'self' 'nonce-${nonce}'`,
    `style-src 'self' 'nonce-${nonce}'`,
    // ... other directives
  ].join('; ');

  const response = NextResponse.next();
  response.headers.set('Content-Security-Policy', cspHeader);
  // Pass nonce to layout via request headers
  const requestHeaders = new Headers(request.headers);
  requestHeaders.set('x-nonce', nonce);
  return NextResponse.next({ request: { headers: requestHeaders } });
}
```

Then read the nonce in `app/layout.tsx` via `headers()` and pass it to `<ThemeModeScript nonce={nonce} />`.

Check whether `flowbite-react`'s `ThemeModeScript` accepts a `nonce` prop — if not, this
requires a custom reimplementation of the theme initialization script.

**Option B — Replace ThemeModeScript with a CSS-only solution**

Use `prefers-color-scheme` media queries and a CSS variable approach instead of a JS-based
theme initializer. Eliminates the inline script entirely. Higher effort but cleanest result.

**Option C — Hash-based CSP for ThemeModeScript**

Compute the SHA-256 hash of the exact inline script content and include it in the CSP:
`script-src 'self' 'sha256-<hash>'`. This works only if the script content is static and
never changes (which it may not be, given build-time variation).

**Files to modify**:
- `middleware.ts`
- `app/layout.tsx`
- `next.config.ts` (remove static `headers()` CSP if migrating to middleware)

### 4.3 Storefront — Next.js (Sprint 14)

**Problem**: No CSP header is currently set. The storefront has no known inline scripts,
making this the easiest target.

**Required changes**:

1. Add CSP header generation in `middleware.ts` (storefront already uses next-intl
   middleware — wrap it or chain middleware).
2. Add the nonce to `app/[locale]/layout.tsx` via `headers()` server function.
3. Verify no inline scripts are introduced by third-party components (analytics, chat
   widgets, payment iframes).
4. Ensure the CSP `connect-src` covers the Symfony API endpoint and any external
   services (Stripe, Elasticsearch if directly queried from browser).

**Files to modify**:
- `middleware.ts` (extend, do not replace next-intl routing)
- `app/[locale]/layout.tsx`
- `next.config.ts` (remove static `headers()` for security headers — move to middleware
   so nonce is available)

---

## 5. Phased Migration Plan

### Sprint 13 — Backend API nonce (2 days)

| Task | Owner | Estimate |
|---|---|---|
| Implement `CspNonceListener` in Symfony | Backend | 3h |
| Configure Nelmio Security nonce in prod | Backend | 1h |
| Override API Platform Twig templates for nonce injection | Backend | 4h |
| Verify API docs render correctly with nonce CSP | QA | 2h |
| Update dev/test nelmio config to remove unsafe-inline | Backend | 1h |
| Run PHPStan + PHPUnit quality gate | CI | automated |

**Exit criteria**: API `/api/docs` renders with no CSP violations. PHPStan level 8 passes.
`unsafe-inline` absent from all nelmio_security.yaml files.

### Sprint 14 — Frontend CSP headers (3 days)

| Task | Owner | Estimate |
|---|---|---|
| Storefront: Add CSP middleware with nonce generation | Frontend | 4h |
| Storefront: Wire nonce into layout | Frontend | 2h |
| Admin: Evaluate Flowbite ThemeModeScript nonce support | Frontend | 2h |
| Admin: Implement Option A or B for ThemeModeScript | Frontend | 4–8h |
| Admin: Add CSP middleware with nonce generation | Frontend | 4h |
| E2E smoke test for dark/light mode toggle in admin | QA | 2h |
| E2E smoke test for storefront page load / no CSP errors | QA | 2h |

**Exit criteria**: Browser console shows zero CSP violations on storefront and admin.
Dark/light mode works in admin. Playwright E2E suite passes.

### Sprint 15 — Hardening and Report-Only mode (1 day)

| Task | Owner | Estimate |
|---|---|---|
| Add `report-uri` or `report-to` directive pointing to a CSP violation endpoint | DevOps | 2h |
| Set up a lightweight CSP violation logging route in Symfony | Backend | 2h |
| Run one week in `Content-Security-Policy-Report-Only` mode before enforcing | QA | passive |
| Confirm zero violations in production traffic sample | Security | 1h |
| Remove any remaining `unsafe-inline` / `unsafe-eval` from all environments | Backend | 1h |

**Exit criteria**: Zero CSP violations in production report-only logs over 7 days.
Full enforcement enabled. Violation reporting endpoint operational.

---

## 6. MVP Deploy Decision

This migration is NOT required before MVP deploy. The rationale:

1. The production Nelmio Security config already excludes `unsafe-inline` from the API
   responses. The API backend has the stronger posture today.
2. Frontend apps have no CSP header at all, but also have no detected inline script
   vulnerabilities. React's auto-escaping and dompurify provide practical XSS mitigation.
3. The incomplete prod API config (no `unsafe-inline`, no nonce) breaks API Platform docs
   but not customer-facing endpoints. The docs UI is a developer tool, not a user-facing
   feature.
4. P0 security issues are fully resolved (0 remaining as of Sprint 4). This is a P1 hardening
   item appropriate for post-MVP sprints.

**Condition to escalate to blocker**: If the storefront or admin introduces a third-party
script (analytics, payment widget, chat) that requires `unsafe-inline`, this plan must be
executed before that script ships to production.

---

## 7. Immediate Action (Pre-Sprint 13)

One low-effort fix that can be applied now to reduce the prod API CSP gap:

Add `'unsafe-inline'` back to the prod config temporarily with an inline comment marking
it for removal in Sprint 13. This prevents the current silent breakage of `/api/docs` in
production:

```yaml
# config/packages/prod/nelmio_security.yaml
script-src:
  - 'self'
  - 'unsafe-inline' # TEMP: Sprint 13 will replace with nonce — see CSP_NONCE_MIGRATION_PLAN.md
style-src:
  - 'self'
  - 'unsafe-inline' # TEMP: Sprint 13 will replace with nonce — see CSP_NONCE_MIGRATION_PLAN.md
```

This is safer than leaving the broken state (silent CSP violations in production logs are
harder to triage than a known documented temporary allowance).

---

## 8. References

| Resource | Location |
|---|---|
| Current base Nelmio config | `/var/www/ecom_api/config/packages/nelmio_security.yaml` |
| Prod Nelmio override | `/var/www/ecom_api/config/packages/prod/nelmio_security.yaml` |
| Test Nelmio override | `/var/www/ecom_api/config/packages/test/nelmio_security.yaml` |
| Storefront Next.js config | `/var/www/ecom_storefront/next.config.ts` |
| Admin Next.js config | `/var/www/ecom_admin/next.config.ts` |
| Admin layout (ThemeModeScript) | `/var/www/ecom_admin/app/layout.tsx` |
| Admin middleware (auth guard) | `/var/www/ecom_admin/middleware.ts` |
| Storefront middleware (i18n) | `/var/www/ecom_storefront/middleware.ts` |
| Security audit baseline | `/var/www/ecom_api/docs/security/SECURITY_AUDIT_REPORT_2025-12-05.md` |
| Sprint 4 reaudit | `/var/www/ecom_api/docs/security/SECURITY_REAUDIT_SPRINT4.md` |
| Nelmio Security docs | https://github.com/nelmio/NelmioSecurityBundle |
| Next.js CSP guide | https://nextjs.org/docs/app/building-your-application/configuring/content-security-policy |
