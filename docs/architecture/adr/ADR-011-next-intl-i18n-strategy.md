# ADR-011: Frontend i18n Strategy — next-intl Exception

**Date**: 2026-03-05
**Status**: Accepted
**Supersedes**: Partial deviation from ADR-009
**Deciders**: Radu — Architect

## Context

PRD §4.7 specifies that frontend applications should contain "NO translation logic, dictionaries, or i18n libraries" — all translations must be served by the backend Internationalization bounded context via API.

ADR-009 codifies this requirement. However, both Storefront (190 uses) and Admin (43 uses) use `next-intl` for rendering translations fetched from the API.

The distinction is:

- **Source of truth**: Backend i18n bounded context (Translation aggregate, TranslationCacheService, 20+ API endpoints)
- **Rendering layer**: next-intl consumes API responses and handles locale-aware formatting (dates, numbers, pluralization)

The cross-AI audit (Claude Code x Codex x Gemini, 2026-03-05) flagged this as a potential BLOCKER due to literal reading of PRD §4.7. This ADR formalizes the accepted deviation with explicit compliance conditions.

### Usage Counts (as of 2026-03-05)

| Application | next-intl references |
|-------------|---------------------|
| Storefront  | 190                 |
| Admin       | 43                  |

### What next-intl Does vs. Does Not Do

| Concern | next-intl Role | Source |
|---------|---------------|--------|
| Translation content | None — receives from API | Backend i18n bounded context |
| Translation storage | None — no local dictionaries | Backend (PostgreSQL via Doctrine Translatable) |
| Locale routing | Yes — `/[locale]/` URL segments | next-intl middleware |
| Date/number formatting | Yes — ICU MessageFormat per locale | next-intl |
| Pluralization rules | Yes — CLDR plural rules | next-intl |
| String interpolation | Yes — renders API-provided strings | next-intl |
| hreflang SEO tags | Yes — `<link rel="alternate">` | next-intl |

## Decision

We ACCEPT next-intl as a rendering-only library under these conditions:

1. All translation content MUST be fetched from the backend i18n API (`/api/translations`)
2. No translation dictionaries or JSON message files exist in the frontend repos
3. next-intl is used ONLY for: locale-aware formatting, interpolation, pluralization rules, and rendering of API-provided strings
4. The frontend does NOT define, store, or manage translations
5. No `messages/{locale}.json` or equivalent static translation files may be introduced

### Architecture

```
Backend (Symfony 8.0)                        Frontend (Next.js 16)
┌────────────────────────────┐               ┌─────────────────────────────────┐
│ Internationalization BC     │               │                                 │
│                             │               │  next-intl (rendering only)     │
│ Translation aggregate       │──── API ─────>│  - useTranslations() hook       │
│ TranslationCacheService     │               │  - locale routing middleware    │
│ /api/translations endpoint  │               │  - date/number formatting       │
│ Redis cache (i18n)          │               │  - ICU pluralization            │
│                             │               │                                 │
│ Supported locales:          │               │  NO local dictionaries          │
│   EN, FR, DE, RO            │               │  NO messages/*.json files       │
└────────────────────────────┘               └─────────────────────────────────┘
         SOURCE OF TRUTH                              RENDERING LAYER
```

## Alternatives Considered

### Option A: Remove next-intl (literal PRD §4.7 compliance)

**Description**: Replace next-intl with a custom React Context + fetch wrapper.

**Pros**:
- Literal compliance with PRD §4.7
- No third-party i18n dependency

**Cons**:
- Reinvents solved problems: ICU MessageFormat, pluralization rules (CLDR), SSR hydration, locale routing, hreflang
- Estimated 3–5 weeks of engineering to reach feature parity
- Higher maintenance burden
- Risk of correctness issues in pluralization edge cases

**Why rejected**: Unacceptable engineering cost for a solved problem. The PRD intent (no embedded translations) is satisfied by the API-fetch architecture.

### Option B: FormatJS / react-intl

**Pros**: ICU message format standard, backed by Meta, framework-agnostic.

**Cons**: No Next.js App Router integration, manual SSR setup required, no routing helpers.

**Why rejected**: next-intl already wraps ICU internally and provides native App Router support. Switching would not reduce dependency count.

### Option C: Keep next-intl with explicit ADR (chosen)

**Pros**:
- Already integrated and working (190 + 43 references)
- Rendering-only usage does not violate the spirit of PRD §4.7
- Mature, well-tested library with Next.js App Router native support

**Cons**:
- Deviates from literal PRD §4.7 text
- Requires ongoing governance to prevent local dictionary creep

## Consequences

### Positive

- Mature, well-tested i18n rendering with ICU MessageFormat support
- Proper pluralization, date/number formatting per locale (CLDR rules)
- Next.js App Router native integration (middleware, server components, routing)
- Type-safe translation key usage with TypeScript autocompletion
- SEO: built-in `<link rel="alternate" hreflang>` generation
- Server-first: messages resolved server-side, minimal client bundle impact (~2KB gzipped)
- Reduced custom code for rendering concerns — no reinventing solved problems

### Negative

- Deviates from literal PRD §4.7 text — requires documented exception (this ADR)
- Requires ongoing validation that no local dictionaries are introduced
- Two auditors (Gemini, Codex) flagged this as BLOCKER due to literal reading of PRD §4.7
- Next.js lock-in: migrating to a different React framework would require replacing i18n layer
- Version coupling: major next-intl versions track Next.js versions; upgrades must be coordinated

### Neutral

- Static UI chrome strings (labels, button text not requiring tenant customization) may eventually be sourced from the API, but this is not a current violation

## Compliance Verification

The following checks MUST pass at all times. If they fail, this ADR's compliance conditions are violated.

```bash
# Verify no local dictionary files exist in Storefront
find /var/www/ecom_storefront -name "messages*.json" -o -name "*.messages.ts" -o -name "dictionaries" -type d 2>/dev/null
# Expected: 0 results

# Verify no local dictionary files exist in Admin
find /var/www/ecom_admin -name "messages*.json" -o -name "*.messages.ts" -o -name "dictionaries" -type d 2>/dev/null
# Expected: 0 results
```

As of 2026-03-05, both checks return 0 results. The frontend applications contain no local translation dictionaries.

## Governance

Any introduction of `messages/*.json` files or static translation dictionaries into the frontend repos constitutes a violation of this ADR and must be reviewed by the architect. CI should enforce this via a file-existence check on pull requests.

## References

- ADR-009: Backend-driven i18n architecture
- PRD §4.7: Internationalization requirements
- `/var/www/ecom_api/src/Internationalization/` — Backend i18n bounded context
- Cross-AI Audit Report: `docs/audit/CONSOLIDATED_REMEDIATION_PLAN_2026-03-05.md`
