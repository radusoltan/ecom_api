# US-010: Password Reset Flow Implementation

**Date**: 2025-11-26
**Epic**: Epic 2 - JWT Authentication
**Status**: ✅ Complete

## Overview

Implemented secure password reset flow following security best practices and DDD/CQRS architecture patterns.

## Implementation Summary

### 1. Database Entity

**File**: `src/User/Infrastructure/Persistence/Doctrine/Entity/PasswordResetTokenEntity.php`

- UUID-based token storage
- 64-character secure tokens (bin2hex(random_bytes(32)))
- 1-hour expiration window
- Single-use tokens (tracked via `usedAt` timestamp)
- Indexed fields for performance (token, user_id, expires_at)

**Migration**: `migrations/Version20251126132904.php`

### 2. Request Password Reset Flow

**Command**: `src/User/Application/Command/RequestPasswordReset/RequestPasswordReset.php`
**Handler**: `src/User/Application/Command/RequestPasswordReset/RequestPasswordResetHandler.php`

**Security Features**:
- Always returns success (prevents email enumeration attacks)
- Generates cryptographically secure random tokens
- Async email sending via Symfony Messenger
- Silent failure for non-existent emails

**Flow**:
1. User submits email address
2. System validates email format
3. Handler looks up user (silently fails if not found)
4. Generate secure token with 1-hour expiration
5. Save token to database
6. Dispatch async email message
7. Return success message (regardless of email existence)

### 3. Reset Password Flow

**Command**: `src/User/Application/Command/ResetPassword/ResetPassword.php`
**Handler**: `src/User/Application/Command/ResetPassword/ResetPasswordHandler.php`

**Security Features**:
- Validates token exists, not expired, not used
- Password minimum 8 characters
- Invalidates all refresh tokens after reset (forced re-login)
- Marks token as used (prevents replay attacks)

**Flow**:
1. User submits token + new password
2. Validate token format (64 characters)
3. Validate password strength (min 8 chars)
4. Find token entity
5. Check token not expired
6. Check token not already used
7. Update user password (hashed)
8. Mark token as used
9. Delete all user refresh tokens
10. Return success message

### 4. API Endpoints

**Resource**: `src/User/Presentation/Api/Resource/PasswordResetResource.php`

#### POST /api/v1/auth/password/reset-request

Request password reset by email.

**Request**:
```json
{
  "email": "user@example.com"
}
```

**Response** (200 OK):
```json
{
  "message": "If an account with that email exists, a password reset link has been sent."
}
```

#### POST /api/v1/auth/password/reset

Reset password using token.

**Request**:
```json
{
  "token": "a1b2c3d4e5f6...64_character_token",
  "newPassword": "NewSecureP@ssw0rd"
}
```

**Response** (200 OK):
```json
{
  "message": "Password has been reset successfully. Please login with your new password."
}
```

**Error Responses**:
- 400: Invalid request (missing fields, weak password)
- 401: Invalid, expired, or already used token

### 5. Email Templates

**HTML Template**: `templates/emails/password_reset.html.twig`
**Text Template**: `templates/emails/password_reset.txt.twig`

**Features**:
- Professional design extending base email template
- Reset button with frontend URL
- Security warnings
- Expiration time display
- Fallback plain text link

**Translations**: Already exist in `translations/emails.en.yaml`
- `customer.password_reset.subject`
- `customer.password_reset.title`
- `customer.password_reset.body`
- `customer.password_reset.reset_button`
- `customer.password_reset.expire`
- `customer.password_reset.ignore`

### 6. Email Handler

**Message**: `src/User/Application/Command/RequestPasswordReset/SendPasswordResetEmail.php`
**Handler**: `src/User/Application/Command/RequestPasswordReset/SendPasswordResetEmailHandler.php`

**Configuration** (services.yaml):
```yaml
App\User\Application\Command\RequestPasswordReset\SendPasswordResetEmailHandler:
    arguments:
        $frontendUrl: '%env(FRONTEND_URL)%'
        $fromEmail: '%env(MAILER_FROM)%'
```

**Features**:
- Async processing via Symfony Messenger
- Error logging with retry mechanism
- Constructs frontend reset URL: `{FRONTEND_URL}/auth/reset-password?token={token}`
- Calculates expiration time in hours
- Sends both HTML and text versions

## Security Best Practices Implemented

1. **No Email Enumeration**: Always return success message
2. **Secure Tokens**: 64-character cryptographically secure random tokens
3. **Time-Limited**: 1-hour expiration window
4. **Single-Use**: Tokens can only be used once
5. **Session Invalidation**: All refresh tokens deleted after password reset
6. **Password Validation**: Minimum 8 characters
7. **No User Existence Leaks**: Silent failure for non-existent emails
8. **Indexed Queries**: Performance optimized token lookups
9. **Async Processing**: Email sending doesn't block request
10. **Error Handling**: Graceful failure with logging

## Code Quality

### PHPStan Level 8
```bash
vendor/bin/phpstan analyse src/User/Application/Command/RequestPasswordReset/ \
  src/User/Application/Command/ResetPassword/ \
  src/User/Presentation/Api/Processor/ \
  src/User/Presentation/Api/Resource/PasswordResetResource.php \
  src/User/Infrastructure/Persistence/Doctrine/Entity/PasswordResetTokenEntity.php \
  --level 8
```
✅ **Result**: No errors

### PHP-CS-Fixer (PSR-12)
```bash
vendor/bin/php-cs-fixer fix src/User/
```
✅ **Result**: All files compliant

### Syntax Check
```bash
find src/User/ -name "*.php" -exec php -l {} \;
```
✅ **Result**: No syntax errors

## Configuration Requirements

### Environment Variables

Add to `.env`:
```env
# Frontend URL for password reset links
FRONTEND_URL=http://localhost:3000

# Mailer configuration
MAILER_FROM=noreply@ecommerce.local
```

### Messenger Configuration

Ensure `SendPasswordResetEmail` is routed to async transport in `config/packages/messenger.yaml`:
```yaml
framework:
    messenger:
        routing:
            App\User\Application\Command\RequestPasswordReset\SendPasswordResetEmail: async
```

## Migration

```bash
# Run migration to create password_reset_tokens table
symfony console doctrine:migrations:migrate
```

## Testing

### Manual Testing

1. **Request Reset**:
```bash
curl -X POST http://localhost:8000/api/v1/auth/password/reset-request \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com"}'
```

2. **Check Email** (or check logs for token)

3. **Reset Password**:
```bash
curl -X POST http://localhost:8000/api/v1/auth/password/reset \
  -H "Content-Type: application/json" \
  -d '{
    "token": "YOUR_64_CHAR_TOKEN_HERE",
    "newPassword": "NewSecurePassword123"
  }'
```

### Consumer for Async Processing

```bash
# Start messenger consumer to process email sending
symfony console messenger:consume async -vv
```

## Files Created

1. **Domain/Infrastructure**:
   - `src/User/Infrastructure/Persistence/Doctrine/Entity/PasswordResetTokenEntity.php`

2. **Application Layer**:
   - `src/User/Application/Command/RequestPasswordReset/RequestPasswordReset.php`
   - `src/User/Application/Command/RequestPasswordReset/RequestPasswordResetHandler.php`
   - `src/User/Application/Command/RequestPasswordReset/SendPasswordResetEmail.php`
   - `src/User/Application/Command/RequestPasswordReset/SendPasswordResetEmailHandler.php`
   - `src/User/Application/Command/ResetPassword/ResetPassword.php`
   - `src/User/Application/Command/ResetPassword/ResetPasswordHandler.php`

3. **Presentation Layer**:
   - `src/User/Presentation/Api/Resource/PasswordResetResource.php`
   - `src/User/Presentation/Api/Processor/RequestPasswordResetProcessor.php`
   - `src/User/Presentation/Api/Processor/ResetPasswordProcessor.php`

4. **Templates**:
   - `templates/emails/password_reset.html.twig`
   - `templates/emails/password_reset.txt.twig`

5. **Migration**:
   - `migrations/Version20251126132904.php`

6. **Configuration**:
   - Updated `config/services.yaml` (email handler configuration, repository binding)

7. **Documentation**:
   - `docs/US-010_PASSWORD_RESET_IMPLEMENTATION.md` (this file)

## Architecture Compliance

✅ **DDD**: Pure domain logic, no framework dependencies
✅ **CQRS**: Separate command handlers for write operations
✅ **Hexagonal**: Infrastructure isolated, domain at center
✅ **Security**: Best practices implemented (OWASP)
✅ **Code Quality**: PHPStan level 8, PSR-12 compliant
✅ **Symfony 7.3**: Latest patterns, attributes, readonly properties
✅ **PHP 8.3**: Constructor promotion, typed properties, readonly

## Next Steps

1. ✅ Run migration: `symfony console doctrine:migrations:migrate`
2. ✅ Configure environment variables (FRONTEND_URL, MAILER_FROM)
3. ⬜ Write unit tests for handlers
4. ⬜ Write functional tests for API endpoints
5. ⬜ Implement frontend password reset page
6. ⬜ Add rate limiting for password reset requests (future US)
7. ⬜ Add monitoring/alerts for failed password resets (future US)

## References

- **OWASP Password Reset Best Practices**: https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html
- **Symfony Mailer**: https://symfony.com/doc/current/mailer.html
- **Symfony Messenger**: https://symfony.com/doc/current/messenger.html
- **JWT Refresh Token Bundle**: https://github.com/markitosgv/JWTRefreshTokenBundle
