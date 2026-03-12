# Raport Audit Post-Remediere — Codex
**Data:** 2026-02-26 (audit executat pe 26 februarie 2026)
**Auditor:** Codex (GPT)
**Referință:** `REMEDIATION_PLAN_CONSOLIDATED_2026-02-26.md` + PRD v5.2
**Audit anterior:** `Codex_audit.md` (scor: ~64%)

## Scor General: 79/100 (anterior: ~64%)

## Rezumat Executiv
Platforma a progresat semnificativ față de auditul anterior: au fost remediate mai multe probleme critice reale (RLS pe tabelele lipsă, HSTS/CSP în `prod`, bug-ul de search URL în storefront, redirect-ul din guest checkout, adăugarea `Draft` în `OrderStatus`, reducerea erorilor PHPStan și TypeScript, locale `ro` în ambele frontends). La nivel de infrastructură și hardening, progresul este clar și cuantificabil.

În același timp, remedierea securității pe `OrderResource` a introdus o regresie funcțională majoră: `security: "is_granted('ROLE_USER')"` aplicat la nivel de resource afectează și `POST /orders`, ceea ce intră în conflict cu guest checkout (PRD cere guest checkout). În plus, GET order detail este acum autentificat, dar storefront-ul continuă să trateze pagina de detaliu comandă ca accesibilă guest, fără token. Rezultatul probabil: guest checkout/confirmare comandă devine rupt(ă) la nivel API, chiar dacă redirect-ul frontend a fost reparat.

Pe scurt: baza este mai bună și scorul crește justificat, dar platforma nu este încă production-ready pentru rollout public B2C fără un nou ciclu scurt de corecții (P0/P1) orientate pe fluxul de checkout guest, tenant hardening și finalizarea unor task-uri rămase doar parțial implementate.

Am verificat atât static (cod/config), cât și runtime unde a fost posibil: `debug:router`, `dbal:run-sql`, quality tooling (`phpstan`, `deptrac`), plus scanuri de TODO/secrets. Nu am rulat end-to-end complet pe mediu integrat și nu am putut confirma coverage curent printr-o rulare completă (artifact-ul din repo este vechi).

## Reverificare Constatări P0 Anterioare

| # | Constatare originală | Status acum | Evidență |
|---|---------------------|-------------|----------|
| P0-1 | Expunere ordini cross-tenant | ⚠️ Parțial remediat + regresie | `security.yaml` nu mai are `GET /orders` public (`/var/www/ecom_api/config/packages/security.yaml:89`), `OrderResource` are `security` explicit (`/var/www/ecom_api/src/Order/Presentation/Api/Resource/OrderResource.php:20`), dar acest `security` se aplică și pe `POST` (guest checkout) și probabil rupe guest order create; `TenantRequestSubscriber` încă acceptă `X-Tenant-ID` pentru request-uri neautentificate și doar loghează mismatch (`/var/www/ecom_api/src/Shared/Infrastructure/Tenant/TenantRequestSubscriber.php:20-21`, `:83-95`). |
| P0-2 | Stripe legacy public | ⚠️ Parțial remediat | `capture-after-success` nu mai este folosit în storefront (pagina comenzii nu îl mai apelează), `StripeWebhookHandler` validează semnătura (`Webhook::constructEvent`) și face deduplicare (`/var/www/ecom_api/src/Payment/Infrastructure/Webhook/StripeWebhookHandler.php:61`, `:72`). Totuși `StripePaymentController` există încă (nu e deprecated/eliminat) și rămân `3` endpoint-uri Stripe publice (`create-intent`, `verify-payment`, `webhook`) conform router/runtime. |
| P0-3 | Guest checkout rupt | ⚠️ Redirect fixat, fluxul rămâne riscant/rupt la API | Redirect-ul din `guest/page.tsx` este corectat la `/checkout` (`/var/www/ecom_storefront/app/[locale]/checkout/guest/page.tsx:50`, `:108`) și bug-ul de rută `/checkout/shipping` a dispărut. Dar `OrderResource` cere `ROLE_USER` pentru toate operațiile (`/var/www/ecom_api/src/Order/Presentation/Api/Resource/OrderResource.php:20`), ceea ce intră în conflict cu guest order create. În plus, testul E2E încă așteaptă `/checkout/shipping` (test depășit): `/var/www/ecom_storefront/e2e/cart.spec.ts` (guest checkout). |
| P0-4 | Search URL dublu | ✅ Remediat | `search.ts` folosește acum `/search` și `/search/autocomplete`, nu `/api/search` (`/var/www/ecom_storefront/lib/api/search.ts:224`, `:268`). `apiFetch()` prefixează în continuare cu `NEXT_PUBLIC_API_BASE_URL` (`/var/www/ecom_storefront/lib/api/client.ts:5`). Scanul `lib/api` nu a mai găsit alte apeluri `apiFetch()` cu prefix `/api/...`. |

## Reverificare Constatări P1 Anterioare

| # | Constatare | Status acum | Evidență |
|---|------------|-------------|----------|
| P1-1 | 49 resurse API fără `security` | ⚠️ Îmbunătățit, incomplet | Acum: `64` resurse `ApiResource`, `38` cu `security`, `26` fără. (Anterior: 12 cu `security`). |
| P1-2 | Endpoint-uri "dev only" publice | ✅ Remediat (în `security.yaml`) | Nu mai există comentarii/entries "dev only" în `security.yaml`; `PUBLIC_ACCESS` redus la `26` (de la 50+). |
| P1-3 | RLS incomplet pe `catalog_configurable_products` | ✅ Remediat (runtime DB) / ⚠️ test automat nevalidat în env test | `dbal:run-sql` confirmă `rowsecurity=1` și policy `tenant_isolation` pe `catalog_configurable_products` și `i18n_backfill_tracking`. Testul `TenantIsolationRLSTest` există, dar rularea `phpunit` eșuează în `APP_ENV=test` cu `SQLSTATE[08006] [7]` (config/conectivitate test DB). |
| P1-4 | TIMESTAMPTZ | ✅ Aproape complet | Runtime DB: `1` coloană `timestamp without time zone` rămasă (`doctrine_migration_versions.executed_at`). Mappings Doctrine: `97` `datetimetz_immutable`, `6` `datetime_immutable` rămase (în `ShipmentEntity`, `FeatureFlagEntity`). |
| P1-5 | `ignoreBuildErrors` pe FE | ⚠️ Nerezolvat | Este încă `true` pe ambele FE (`/var/www/ecom_admin/next.config.ts:7`, `/var/www/ecom_storefront/next.config.ts:12`). |
| P1-6 | PHPStan ≠ 0 | ⚠️ Îmbunătățit, incomplet | `phpstan` raportează acum `3` erori (anterior 8). |
| P1-7 | Locale `ro` lipsă în frontend | ✅ Parțial remediat (localele există) | `ro.json` există în ambele FE; `routing.ts` include `ro` (`/var/www/ecom_admin/i18n/routing.ts:6`, `/var/www/ecom_storefront/i18n/routing.ts:6`); storefront middleware folosește `createMiddleware(routing)` (`/var/www/ecom_storefront/middleware.ts:4`). |
| P1-8 | Admin UI mock-uri | ⚠️ Parțial remediat | `translation-management.ts` folosește API real pentru CRUD/list (`/var/www/ecom_admin/lib/api/translation-management.ts:127-169`), `product-search-autocomplete.tsx` folosește search API real; dar tax settings rămâne localStorage fallback/TODO (`/var/www/ecom_admin/lib/api/tax.ts:121-191`, `/var/www/ecom_admin/app/settings/tax/calculation/page.tsx:76`, `:100`). În plus, import/export/stats/missing din translation management lovesc endpoint-uri care nu apar în router. |
| P1-9 | Order tracking placeholder | ⚠️ Parțial remediat | Storefront fetch-ează `fulfillments` (`/var/www/ecom_storefront/lib/api/orders.ts:129-131`) și pagina pasează date reale în `TrackingInfo` (`/var/www/ecom_storefront/app/[locale]/orders/[id]/page.tsx:38`). Dar componenta păstrează URL mapping "mock implementation" (`/var/www/ecom_storefront/components/orders/TrackingInfo.tsx:32`) și fetch-ul de tracking nu trimite token, în timp ce `/api` are catch-all auth (`/var/www/ecom_api/config/packages/security.yaml:94-95`). |
| P1-10 | Tenant `X-Tenant-ID` spoofing | ⚠️ Parțial remediat | Derivare actuală: JWT claim -> header `X-Tenant-ID` -> request attribute (`/var/www/ecom_api/src/Shared/Infrastructure/Tenant/TenantRequestSubscriber.php:20-21`, `:81-95`). Mismatch JWT/header doar loghează warning, nu respinge request-ul (`:83-90`). |

### Resurse API care încă NU au `security` explicit (26)

```text
/var/www/ecom_api/src/Cart/Presentation/Api/Resource/CartResource.php
/var/www/ecom_api/src/Cart/Presentation/Api/Resource/CheckoutResource.php
/var/www/ecom_api/src/Catalog/Infrastructure/ApiPlatform/Resource/ProductAutocompleteResource.php
/var/www/ecom_api/src/Catalog/Infrastructure/ApiPlatform/Resource/ProductOptionsResource.php
/var/www/ecom_api/src/Catalog/Infrastructure/ApiPlatform/Resource/ProductSearchResource.php
/var/www/ecom_api/src/Catalog/Infrastructure/Persistence/Doctrine/Entity/CategoryEntity.php
/var/www/ecom_api/src/Catalog/Infrastructure/Persistence/Doctrine/Entity/OptionEntity.php
/var/www/ecom_api/src/Catalog/Infrastructure/Persistence/Doctrine/Entity/OptionValueEntity.php
/var/www/ecom_api/src/Catalog/Presentation/Api/Resource/StorefrontCategoryResource.php
/var/www/ecom_api/src/Catalog/Presentation/Api/Resource/StorefrontProductResource.php
/var/www/ecom_api/src/Internationalization/Infrastructure/ApiPlatform/Resource/TranslationManagementResource.php
/var/www/ecom_api/src/Internationalization/Infrastructure/ApiPlatform/Resource/TranslationResource.php
/var/www/ecom_api/src/Inventory/Application/DTO/InventoryStatsDto.php
/var/www/ecom_api/src/Inventory/Infrastructure/ApiPlatform/Resource/StockAvailabilityResource.php
/var/www/ecom_api/src/Media/Presentation/Api/Resource/ImageResource.php
/var/www/ecom_api/src/Order/Infrastructure/Persistence/Doctrine/Entity/FulfillmentEntity.php
/var/www/ecom_api/src/Payment/Infrastructure/Persistence/Doctrine/Entity/TransactionEntity.php
/var/www/ecom_api/src/Pricing/Presentation/Api/Resource/CartPricingResource.php
/var/www/ecom_api/src/Pricing/Presentation/Api/Resource/CouponValidationResource.php
/var/www/ecom_api/src/Privacy/Infrastructure/Persistence/Doctrine/Entity/ConsentEntity.php
/var/www/ecom_api/src/Privacy/Infrastructure/Persistence/Doctrine/Entity/DataSubjectRequestEntity.php
/var/www/ecom_api/src/Review/Presentation/Api/Resource/ProductReviewResource.php
/var/www/ecom_api/src/Shared/Infrastructure/ApiPlatform/Filter/LocaleFilter.php
/var/www/ecom_api/src/Tenant/Presentation/Api/TenantResource.php
/var/www/ecom_api/src/User/Presentation/Api/Resource/AuthResource.php
/var/www/ecom_api/src/User/Presentation/Api/Resource/PasswordResetResource.php
```

## Verificare Task-uri Noi din Plan

| # | Task (Opus/Gemini/Codex) | Status | Evidență |
|---|--------------------------|:------:|----------|
| 1 | HSTS + CSP | ✅/⚠️ | `prod/nelmio_security.yaml` activează HSTS (`enabled: true`) și elimină `unsafe-inline`; base/test păstrează `unsafe-inline` (acceptabil dacă strict pentru non-prod) (`/var/www/ecom_api/config/packages/prod/nelmio_security.yaml`). |
| 2 | ACL Order↔Catalog | ❌ | Importuri directe încă există în Order context (`WarehouseRoutingService`, `InventoryStockAvailabilityLookup`, `CatalogProductPriceLookup`). Nu există `CatalogProductReference`. |
| 3 | Test coverage 80% | ❌ (neconfirmat) | Nu există dovadă curentă. Artifact local `coverage/index.html` este vechi (2025-11-27) și arată `20.61%`. |
| 4 | API versioning `/v1/` | ⚠️ Aproape complet | Router runtime: `285` rute `/api*`, `283` sub `/api/v1/`; cele `2` rămase sunt compat (`/api/{path}` redirect) și `/api/login_check`. |
| 5 | Shipping Context | ⚠️ Parțial (implementat, fără teste) | Există context `Shipping` cu Domain/App/Infra/Presentation, `ShipmentResource`, `MockCarrierAdapter`, rute `/api/v1/shipments`; nu am găsit teste pentru Shipping. |
| 6 | Feature flags per tenant | ⚠️ Parțial | Există model/service/migrare și tabel runtime `tenant_feature_flags` cu RLS; nu apar rute CRUD în router (`debug:router`), nici teste. |
| 7 | Backup & DR | ⚠️ Parțial (scripturi+docs prezente) | Scripturi `pg_backup.sh`, `pg_restore.sh`, `wal_archive_setup.sh`, `cron_setup.sh`, `redis_backup.sh`; doc DR există. Nu am validat execuția reală. |
| 8 | Data retention | ⚠️ Parțial | `app:data:cleanup` există (`DataRetentionCommand`), dar `--dry-run` eșuează cu `permission denied to set role ecom_admin`; politicile nu acoperă complet PRD (ex. Orders 7y / Analytics 2y). |
| 9 | OrderStatus Draft | ✅ | `DRAFT` adăugat și tranziții actualizate în `OrderStatus` (`/var/www/ecom_api/src/Order/Domain/Model/OrderStatus.php:17`, `:35-36`). |
| 10 | Bulk import endpoint | ❌ (efectiv neexpus) | `BulkImportController` există cu route `'/api/products/bulk-import'` (`/var/www/ecom_api/src/Catalog/Presentation/Api/Controller/BulkImportController.php:16`), dar ruta NU apare în `debug:router`. |
| 11 | Deptrac uncovered <500 | ❌ | `Uncovered = 5735` (mai rău decât 5608 anterior), `Violations = 0`. |
| 12 | Naming API kebab-case | ❌ | Router runtime: `43` path-uri `/api/v1/...` cu underscore (număr rafinat, excluzând placeholder noise cât s-a putut). Exemple: `product_entities`, `tax_rules`, `media_images`. |
| 13 | Contract tests Payment | ⚠️ Parțial | Există `PaymentGatewayContractTest` + contract tests pentru `FakeStripe` și `FakePayPal`; nu există contract test pentru 2Checkout adapter. |
| 14 | HATEOAS | ⚠️ Parțial | `getLinks()` există pe Order/Product/Customer, dar nu am confirmat `_links` în payload; nu există `SerializedName('_links')` în `src`. |
| 15 | Dual-model pe Monitoring / Internationalization / Media | ⚠️ Parțial | Monitoring are acum `Application/Command` + `Query` + `Presentation`; Internationalization încă nu are `Presentation` namespace (resursele sunt în `Infrastructure/ApiPlatform`); Media are conversii domain↔entity (`fromDomain`/`toDomain`). |

## Aliniere PRD v5.2

### Scor pe Secțiuni PRD
| Secțiune PRD | Scor | Observații |
|-------------|:----:|-----------|
| §3 Architecture | 87/100 | DDD/CQRS/Hexagonal solid, multe bounded contexts, `deptrac` 0 violări; dar ACL Order↔Catalog neimplementat și `uncovered` foarte mare. |
| §4 Functional | 73/100 | Acoperire largă module/rute; Draft adăugat; shipping context introdus; dar guest checkout/order confirmation regress, tracking parțial, tax settings admin încă local, self-service TODO-uri, bundle/virtual/subscription UX storefront incomplet. |
| §5 Technical | 76/100 | Versioning aproape complet, rate limiting și hardening mai bune; dar cursor pagination / RFC7807 / naming API / feature flags API rămân nealiniate. |
| §6 Data | 82/100 | RLS pe tabele critice confirmat runtime; TIMESTAMPTZ aproape complet (1 coloană de sistem). Deviație importantă: PRD cere JSONB translations, implementarea folosește Gedmo/ext_translations. |
| §8 Security | 74/100 | MFA, JWT, criptare, rate limits, HSTS/CSP prod bune; dar tenant spoofing doar parțial rezolvat, `PUBLIC_ACCESS` încă 26, 26 resurse fără `security`, regresie guest checkout introdusă de securizare generică pe `OrderResource`. |
| §9 Performance | 68/100 | Observabilitate și k6 assets bune; lipsesc dovezi runtime actuale pentru SLA-uri (<200ms API, <2s page load). |
| App E: Quality | 52/100 | `PHPStan` a scăzut la 3 erori (bine), dar încă ≠0; `deptrac uncovered` 5735; coverage actual neconfirmat; FE builds încă ignoră erori TS. |
| App G: DR | 66/100 | Scripturi + documentație există (backup/PITR/WAL), dar nevalidate runtime și țintele DR documentate (RPO/RTO) nu coincid cu PRD. |

### Funcționalități PRD neimplementate / nealiniate (selectiv, cu impact)
- `§2.1 / §4.2 Checkout`: guest checkout este cerut P0; după remediere există risc major de blocare la `POST /orders` din cauza `security` global pe `OrderResource`.
- `§2.1 / §4.2 Order Tracking`: tracking UI este conectat, dar fetch-ul folosește endpoint auth fără token; pentru guest și posibil și pentru useri autentificați client-side poate eșua silent (`null`).
- `§4.1 Product Types (UX)`: backend suportă toate tipurile, dar storefront nu are dovezi clare de UX pentru bundle/virtual/subscription (în afară de configurable).
- `§4.7 i18n Architecture`: PRD v5.2 cere backend-driven i18n și explicit „NO i18n libraries in frontend”; ambele frontends folosesc `next-intl` + `messages/*.json` (deviație arhitecturală față de PRD).
- `§4.7 / §6`: PRD cere JSONB pentru traduceri entități; codul curent folosește Gedmo Translatable + `ext_translations` (nu JSONB-centric).
- `§5.3 Pagination`: PRD cere cursor-based default; nu am găsit configurare globală API Platform pentru cursor pagination.
- `§5.3 Error format`: PRD cere RFC7807; cod/tests/clients indică în principal Hydra/JSON-LD (`hydra:description`).
- `§5.3 Feature Flags`: infrastructura de feature flags există, dar CRUD API funcțional nu este expus în router.
- `§7.2 Shipping`: context intern + mock există, dar integrări reale UPS/FedEx/DHL nu sunt implementate.
- `§7.3 ERP/CRM`: integrații reale lipsesc (doar hook-uri / intenții / TODO).
- `Appendix E`: coverage 80%+ nu este demonstrat cu artifact curent; `PHPStan` încă nu e 0; `ignoreBuildErrors` FE încă active.
- `Appendix G`: DR documentat, dar țintele din doc (`RPO 24h`) nu sunt aliniate cu PRD (`RPO 15 min`).

## Probleme Noi Descoperite (regresii / probleme suplimentare)

### P0
1. **Regresie guest checkout la nivel API (`OrderResource security` global)**
- `OrderResource` are `security: "is_granted('ROLE_USER')"` la nivel de resource (`/var/www/ecom_api/src/Order/Presentation/Api/Resource/OrderResource.php:20`), iar `POST` nu are override per-operation.
- `security.yaml` lasă `POST /api/v1/orders` public (`/var/www/ecom_api/config/packages/security.yaml:77`), dar API Platform security probabil blochează guest users.
- Impact: PRD `Guest checkout` (P0) poate fi rupt după remediere.

2. **Regresie guest order confirmation / order detail după checkout**
- Storefront redirecționează după payment success la `/orders/{id}` (`/var/www/ecom_storefront/app/[locale]/checkout/page.tsx:224`).
- `useOrderDetail` afirmă suport guest (`/var/www/ecom_storefront/lib/hooks/orders/useOrderDetail.ts:18`) dar folosește `getOrderById()` fără token (`:35`), iar `GET /api/v1/orders*` este autentificat (`/var/www/ecom_api/config/packages/security.yaml:89`).
- Impact: pagina de confirmare/detaliu comandă pentru guest poate eșua.

### P1
1. **Tenant spoofing nu este complet blocat**
- Mismatch JWT/header doar loghează warning, nu respinge request-ul (`TenantRequestSubscriber`).
- Pentru request-uri neautentificate se acceptă în continuare `X-Tenant-ID` din client.

2. **Tracking real conectat, dar probabil nefuncțional fără auth**
- `getOrderTracking()` apelează `/fulfillments` fără token (`/var/www/ecom_storefront/lib/api/orders.ts:131`), iar API-ul are catch-all auth (`/var/www/ecom_api/config/packages/security.yaml:95`).
- UI maschează eroarea și returnează `null`, ceea ce poate ascunde defectul.

3. **Admin Translation Management este doar parțial conectat**
- CRUD/list route există în router (`/api/v1/translation-management`).
- Admin client mai apelează `import/export/stats/missing` (`/api/translations/import`, `/api/translations/export`, `/api/translation-management/stats`) care nu apar în `debug:router`.

4. **2Checkout este încă incomplet funcțional la nivel adapter**
- `TwoCheckoutPaymentGateway` aruncă `RuntimeException(... not implemented yet)` pentru operații esențiale (`create/confirm/capture/cancel/refund`).
- PRD §7.1 listează 2Checkout/Custom extensibil; starea actuală este incompletă.

5. **Data retention command există, dar nu rulează în runtime curent**
- `app:data:cleanup --dry-run` eșuează cu `permission denied to set role ecom_admin`.
- Politicile nu acoperă explicit Orders 7y / Analytics 2y conform PRD.

### P2
1. **Admin middleware și i18n sunt nealiniate structural**
- `ro` este adăugat în routing, dar admin app nu are rute `[locale]`; middleware-ul custom auth nu este locale-aware (`/var/www/ecom_admin/middleware.ts`).
- Nu e neapărat bug runtime imediat, dar este inconsistență arhitecturală.

2. **E2E test depășit după fix-ul guest redirect**
- Testul încă așteaptă `/checkout/shipping`, deși aplicația redirecționează la `/checkout`.

3. **Documentație DR nealiniată cu PRD**
- DR doc declară RPO 24h / RTO 1h, PRD cere RPO 15min / RTO 2h.

## Comparație cu Auditul Anterior
| Metric | Anterior | Actual | Trend |
|--------|:--------:|:------:|:-----:|
| Scor general | ~64% | 79% | ↑ semnificativ |
| Constatări P0 (active) | 4 | 2 | ↓ dar cu regresii noi pe guest flow |
| Constatări P1 (active) | 10 | 12 | ↔/↑ (multe au fost reduse, dar au apărut regresii și task-uri parțiale) |
| Mock-uri active (majore) | 3+ | 6+ | ↔ (unele închise, altele persistă/au ieșit mai clar) |
| TS errors admin | 342 | 147 | ↑ bun |
| TS errors storefront | ? | 82 | nou metric |
| PHPStan errors | 8 | 3 | ↑ bun |
| PUBLIC_ACCESS | 50+ | 26 | ↑ bun, dar încă peste ținta planului |

## Verdict: PRODUCȚIE-READY? NU
Nu pentru un rollout public complet, în forma actuală.

**Motivarea principală (runtime reality):**
- Există risc major ca remedierea securității pe `OrderResource` să fi rupt guest checkout și guest order confirmation (cerințe P0 în PRD).
- Tenant hardening este doar parțial finalizat (`X-Tenant-ID` încă acceptat pentru request-uri publice; mismatch JWT/header nu este respins).
- Mai există goluri importante de integrare (feature flags CRUD API, bulk import expunere efectivă, 2Checkout adapter incomplet, tracking auth wiring, admin tax settings backend).
- Quality gates sunt îmbunătățite, dar nu atinse: `PHPStan != 0`, FE `ignoreBuildErrors` active, coverage 80% neprobat, Deptrac uncovered mult peste țintă.

**Concluzie pragmatică:** platforma este mult mai aproape de producție decât la auditul anterior, dar necesită încă un sprint scurt de stabilizare centrat pe guest checkout/post-order flow + security hardening + finalizare task-uri parțiale.

## Top 10 Acțiuni Rămase (prioritizate)
1. **[P0] Repară regresia guest checkout în API**: mută `security` de pe `OrderResource` la nivel per-operație (`POST /orders` guest-safe controlat; `GET/PATCH` auth/voter).
2. **[P0] Introdu flux sigur pentru guest order confirmation**: endpoint de vizualizare comandă cu token semnat (one-time/expirabil) sau alt mecanism explicit, nu `GET /orders/{id}` generic public.
3. **[P1] Finalizează tenant hardening**: respinge mismatch JWT vs `X-Tenant-ID`, derivează tenant din host/JWT pentru public, permite header doar server-to-server validat.
4. **[P1] Închide gap-ul de tracking**: `getOrderTracking` să trimită auth unde e necesar sau oferă endpoint guest-safe pentru tracking; adaugă test E2E real.
5. **[P1] Reduce `PUBLIC_ACCESS` la ținta planului (<15)** și revizuiește dacă `create-intent` / `verify-payment` pot fi securizate (order token / nonce / rate limit mai strict).
6. **[P1] Finalizează Admin translation management**: implementează rutele lipsă (`stats`, `missing`, `import`, `export`) sau elimină/feature-flag aceste funcții până există backend.
7. **[P1] Expune efectiv Feature Flags CRUD API + teste** (tabel și service există deja; lipsește suprafața API/runtime).
8. **[P1] Repară expunerea Bulk Import în router**: înregistrează controller-ul corect și standardizează ruta sub `/api/v1/...`.
9. **[P1] Închide quality gaps**: `PHPStan` la 0, storefront TS sub 50, păstrează `no new TS errors` gate (lipsește azi) și plan de eliminare `ignoreBuildErrors`.
10. **[P2] Aliniază DR/Data Retention la PRD**: fixează `SET ROLE` privilege pentru `app:data:cleanup`, extinde politicile (Orders/Analytics), actualizează țintele DR doc la RPO/RTO PRD și validează scripts prin execuții controlate.
