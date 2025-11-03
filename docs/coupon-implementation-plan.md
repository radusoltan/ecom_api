# Plan Detaliat: Implementare Completă Funcționalitate Cupon

**Data**: 2025-10-21
**Status**: In Progress
**Proiect**: E-commerce Platform - Coupon System

---

## Situația Curentă

### Backend Status:
✅ **Implementat**:
- Domain Model: `Promotion` aggregate
- Value Objects: `CouponCode`, `Discount`, `PromotionType`
- Repository: `DoctrineORMPromotionRepository`
- Service: `PromotionApplicationService`
- Query: `ValidateCouponQuery` + Handler
- API Resource: `ValidateCouponResource`
- Processor: `ValidateCouponProcessor`
- Database: Cupon DEMOCOUPON cu ULID

❌ **Probleme Identificate**:
- Cuponul nu se găsește/aplică corect în `PromotionApplicationService`
- Posibil bug în `findByCouponCode` repository method
- TenantContext ar putea să nu fie setat corect pentru requesturi publice

### Frontend Status:
✅ **Implementat**:
- CartContext cu state pentru coupon
- UI pentru aplicare cupon
- Validare hardcoded pentru DEMOCOUPON
- Display discount în Summary

❌ **Lipsă**:
- Apel API real către backend
- Loading states
- Error handling complex

---

## Plan de Implementare

### FAZA 1: Debug și Fix Backend (Prioritate Maximă)

#### 1.1 Verificare Repository Layer
**Fișier**: `/var/www/new_ecom/backend/src/Pricing/Infrastructure/Persistence/Doctrine/Repository/DoctrineORMPromotionRepository.php`

**Acțiuni**:
- [ ] Verifică dacă `CouponCode` value object se convertește corect la string
- [ ] Adaugă logging în `findByCouponCode` pentru a vedea parametrii
- [ ] Testează query-ul direct în DB pentru a confirma că găsește cuponul
- [ ] Verifică dacă `PromotionEntity::toDomainModel()` funcționează corect

**Cod de test**:
```php
// Test direct în repository
$couponCode = CouponCode::fromString('DEMOCOUPON');
$tenantId = TenantId::fromString('51bd1332-307c-480c-bd3c-6bfe5ccd7829');
$promotion = $repository->findByCouponCode($couponCode, $tenantId);
dump($promotion); // Trebuie să returneze Promotion object, nu null
```

#### 1.2 Fix PromotionApplicationService
**Fișier**: `/var/www/new_ecom/backend/src/Pricing/Application/Service/PromotionApplicationService.php`

**Probleme Posibile**:
- Metoda `validateCoupon()` returnează `null` în loc de `Promotion`
- Condițiile din `meetsConditions()` nu sunt îndeplinite
- CouponCode value object nu se compară corect

**Debugging Steps**:
1. Adaugă logging în `validateCoupon()`:
   ```php
   error_log('Searching for coupon: ' . $couponCode);
   $promotion = $this->promotionRepository->findByCouponCode($code, $tenantId);
   error_log('Found promotion: ' . ($promotion ? 'YES' : 'NO'));
   ```

2. Verifică fiecare pas de validare:
   ```php
   if ($promotion === null) {
       error_log('Coupon not found in database');
       return null;
   }
   if (!$promotion->isActive()) {
       error_log('Coupon is not active');
       return null;
   }
   // etc.
   ```

3. Fix posibil - verifică dacă conditions `{}` (JSON gol) cauzează probleme

#### 1.3 Fix TenantContext pentru Public Endpoints
**Fișier**: `/var/www/new_ecom/backend/src/Shared/Infrastructure/ApiPlatform/State/TenantContextProvider.php`

**Probleme**:
- TenantContext nu este setat pentru requesturi publice (fără autentificare)
- Trebuie să extragem tenant-ul din header `X-Tenant-ID`

**Soluție**:
```php
// În TenantContextProvider sau un EventSubscriber
public function onKernelRequest(RequestEvent $event): void
{
    $request = $event->getRequest();

    // Check for X-Tenant-ID header
    $tenantIdHeader = $request->headers->get('X-Tenant-ID');

    if ($tenantIdHeader) {
        $tenantId = TenantId::fromString($tenantIdHeader);
        $this->tenantContext->setCurrentTenant($tenantId);
    }
}
```

#### 1.4 Simplificare ValidateCouponProcessor
**Fișier**: `/var/www/new_ecom/backend/src/Pricing/Presentation/Api/Processor/ValidateCouponProcessor.php`

**Probleme**:
- Poate că avem nevoie de fallback pentru tenant ID
- Trebuie să acceptăm tenant ID din request body sau header

**Fix**:
```php
// Acceptă tenant din header SAU hardcode pentru teste
$tenantId = $this->tenantContext->getTenantId();
if ($tenantId === null) {
    // Fallback pentru dezvoltare - folosește tenant-ul default
    $tenantId = '51bd1332-307c-480c-bd3c-6bfe5ccd7829';
}
```

---

### FAZA 2: Test Backend Izolat

#### 2.1 Test cu curl
```bash
curl -X POST "http://api.ecom.local/api/v1/validate-coupon" \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: 51bd1332-307c-480c-bd3c-6bfe5ccd7829" \
  -d '{
    "couponCode": "DEMOCOUPON",
    "cartTotal": 10000,
    "currency": "USD"
  }'
```

**Expected Response**:
```json
{
  "valid": true,
  "couponCode": "DEMOCOUPON",
  "discountAmount": 1500,
  "discountPercentage": 15.0,
  "discountType": "percentage",
  "finalTotal": 8500,
  "currency": "USD",
  "message": "Coupon applied successfully"
}
```

#### 2.2 Test Cases
- [ ] Cupon valid (DEMOCOUPON)
- [ ] Cupon invalid (WRONGCODE)
- [ ] Cupon expirat (după valid_to)
- [ ] Cart total = 0
- [ ] Valori extreme (cart total foarte mare)

---

### FAZA 3: Integrare Frontend

#### 3.1 Creează API Client
**Fișier NOU**: `/var/www/new_ecom/storefront/lib/api/coupons.ts`

```typescript
export interface ValidateCouponRequest {
  couponCode: string;
  cartTotal: number; // în minor units (cents)
  currency: string;
}

export interface ValidateCouponResponse {
  valid: boolean;
  couponCode?: string;
  discountAmount?: number;
  discountPercentage?: number;
  discountType?: string;
  finalTotal?: number;
  currency?: string;
  message?: string;
}

export async function validateCoupon(
  request: ValidateCouponRequest
): Promise<ValidateCouponResponse> {
  const response = await fetch(
    `${process.env.NEXT_PUBLIC_API_URL}/api/v1/validate-coupon`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Tenant-ID': process.env.NEXT_PUBLIC_TENANT_ID || '51bd1332-307c-480c-bd3c-6bfe5ccd7829',
      },
      body: JSON.stringify(request),
    }
  );

  if (!response.ok) {
    throw new Error('Failed to validate coupon');
  }

  return response.json();
}
```

#### 3.2 Update CartContext
**Fișier**: `/var/www/new_ecom/storefront/lib/context/CartContext.tsx`

**Modificări**:
```typescript
const applyCoupon = async (code: string): Promise<{success: boolean; message: string}> => {
  try {
    const result = await validateCoupon({
      couponCode: code,
      cartTotal: totalPrice,
      currency: items[0]?.product.price.currency || 'USD',
    });

    if (result.valid) {
      setAppliedCoupon(result.couponCode!);
      setDiscountAmount(result.discountAmount!);
      return {
        success: true,
        message: result.message || 'Coupon applied successfully!'
      };
    } else {
      return {
        success: false,
        message: result.message || 'Invalid coupon code'
      };
    }
  } catch (error) {
    console.error('Error validating coupon:', error);
    return {
      success: false,
      message: 'Failed to validate coupon. Please try again.'
    };
  }
};
```

#### 3.3 Add Loading States
**Fișier**: `/var/www/new_ecom/storefront/app/[locale]/cart/page.tsx`

```typescript
const [isApplyingCoupon, setIsApplyingCoupon] = useState(false);

const handleApplyCoupon = async (e: React.FormEvent) => {
  e.preventDefault();
  setIsApplyingCoupon(true);
  setCouponError('');
  setCouponSuccess('');

  // ... rest of the code

  setIsApplyingCoupon(false);
};

// În UI:
<button
  type="submit"
  disabled={isApplyingCoupon}
  className="bg-primary text-white border border-primary hover:bg-transparent hover:text-primary rounded-r-full py-2 px-4 transition-all disabled:opacity-50"
>
  {isApplyingCoupon ? 'Applying...' : t('buttons.applyCoupon')}
</button>
```

---

### FAZA 4: Environment Variables

#### 4.1 Backend .env
```bash
# Existing...
TENANT_DEFAULT_ID=51bd1332-307c-480c-bd3c-6bfe5ccd7829
```

#### 4.2 Storefront .env.local
```bash
NEXT_PUBLIC_API_URL=http://api.ecom.local
NEXT_PUBLIC_TENANT_ID=51bd1332-307c-480c-bd3c-6bfe5ccd7829
```

---

### FAZA 5: Testing Complet

#### 5.1 Unit Tests (Backend)
**Fișier NOU**: `/var/www/new_ecom/backend/tests/Unit/Pricing/Application/Query/ValidateCouponQueryHandlerTest.php`

```php
class ValidateCouponQueryHandlerTest extends TestCase
{
    public function testValidCouponReturnsDiscount(): void
    {
        // Test cu DEMOCOUPON
        // Assert discount = 15%
    }

    public function testInvalidCouponReturnsFalse(): void
    {
        // Test cu cod invalid
    }
}
```

#### 5.2 Integration Tests
**Fișier NOU**: `/var/www/new_ecom/backend/tests/Integration/Pricing/ValidateCouponApiTest.php`

```php
class ValidateCouponApiTest extends ApiTestCase
{
    public function testValidateCouponEndpoint(): void
    {
        $response = $this->request('POST', '/api/v1/validate-coupon', [
            'json' => [
                'couponCode' => 'DEMOCOUPON',
                'cartTotal' => 10000,
                'currency' => 'USD',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['valid' => true]);
    }
}
```

#### 5.3 E2E Test (Frontend)
**Manual Test Checklist**:
- [ ] Deschide ecom.local/cart cu produse în coș
- [ ] Introdu "DEMOCOUPON" în input
- [ ] Click "Apply Coupon"
- [ ] Verifică loading state apare
- [ ] Verifică mesaj succes apare
- [ ] Verifică discount apare în Summary (15%)
- [ ] Verifică taxes recalculate corect
- [ ] Verifică total final corect
- [ ] Click X pentru a șterge cuponul
- [ ] Verifică discount dispare
- [ ] Test cu cod invalid ("WRONGCODE")
- [ ] Verifică mesaj eroare apare

---

### FAZA 6: Optimizări și Polish

#### 6.1 Persistence Coupon în localStorage
```typescript
// În CartContext, salvează coupon împreună cu items
useEffect(() => {
  if (isHydrated) {
    localStorage.setItem('cart', JSON.stringify({
      items,
      coupon: appliedCoupon,
      discount: discountAmount,
    }));
  }
}, [items, appliedCoupon, discountAmount, isHydrated]);
```

#### 6.2 Traduceri Complete
**Fișiere**: `backend/translations/messages.*.yaml`

```yaml
coupon:
  applied: "Coupon applied successfully"
  invalid: "Invalid coupon code"
  expired: "This coupon has expired"
  error: "Failed to validate coupon"
  placeholder: "Enter coupon code"
  discount: "Discount"
```

#### 6.3 Validare Input
```typescript
// Validează format cupon (doar litere și cifre, 3-20 caractere)
const validateCouponFormat = (code: string): boolean => {
  return /^[A-Z0-9]{3,20}$/.test(code.toUpperCase());
};
```

---

## Prioritizare Tasks

### 🔴 CRITICE (Fă mai întâi):
1. Fix `findByCouponCode` în repository - verifică de ce nu găsește cuponul
2. Fix TenantContext pentru requesturi publice
3. Debug `PromotionApplicationService.validateCoupon()`
4. Test backend cu curl până funcționează

### 🟡 IMPORTANTE (După ce backend funcționează):
5. Creează API client în storefront (`lib/api/coupons.ts`)
6. Update CartContext să folosească API real
7. Add loading states și error handling
8. Test E2E complet

### 🟢 NICE TO HAVE:
9. Persistence în localStorage
10. Unit tests
11. Traduceri complete
12. Input validation

---

## Debugging Strategy

### Step 1: Verifică Database
```sql
SELECT * FROM promotions
WHERE coupon_code = 'DEMOCOUPON'
AND is_active = true;
```

**Expected Result**: 1 row cu ULID id și toate câmpurile corecte

### Step 2: Test Repository Direct
```php
// În symfony console command sau test
$repo = $container->get(PromotionRepositoryInterface::class);
$coupon = CouponCode::fromString('DEMOCOUPON');
$tenant = TenantId::fromString('51bd1332-307c-480c-bd3c-6bfe5ccd7829');
$promo = $repo->findByCouponCode($coupon, $tenant);
dd($promo); // Trebuie să fie !== null
```

### Step 3: Test Service Direct
```php
$service = $container->get(PromotionApplicationService::class);
$result = $service->applyPromotions(
    tenantId: $tenantId,
    subtotal: Money::fromScalars(10000, 'USD'),
    couponCode: 'DEMOCOUPON'
);
dd($result); // Check finalPrice și appliedPromotions
```

### Step 4: Test API Endpoint
```bash
curl -v http://api.ecom.local/api/v1/validate-coupon \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: 51bd1332-307c-480c-bd3c-6bfe5ccd7829" \
  -d '{"couponCode":"DEMOCOUPON","cartTotal":10000,"currency":"USD"}'
```

---

## Known Issues & Solutions

### Issue 1: Tenant ID not found
**Symptom**: "Tenant ID not found in context"
**Solution**: Implement X-Tenant-ID header extraction în EventSubscriber

### Issue 2: Promotion not found
**Symptom**: "Coupon code is not valid or cannot be applied"
**Solution**:
- Verifică dacă coupon_code din DB este exact "DEMOCOUPON" (case sensitive)
- Verifică dacă CouponCode::toString() returnează string corect

### Issue 3: Discount amount is 0
**Symptom**: valid=true dar discountAmount=0
**Solution**: Verifică calculul în `Discount::applyTo()` și `PromotionStackingService`

---

## Files Modified/Created

### Backend Files:
- ✅ Created: `src/Pricing/Application/Query/ValidateCoupon/ValidateCouponQuery.php`
- ✅ Created: `src/Pricing/Application/Query/ValidateCoupon/ValidateCouponQueryHandler.php`
- ✅ Created: `src/Pricing/Application/DTO/CouponValidationResultDTO.php`
- ✅ Created: `src/Pricing/Presentation/Api/Resource/ValidateCouponResource.php`
- ✅ Created: `src/Pricing/Presentation/Api/Processor/ValidateCouponProcessor.php`
- ✅ Modified: `config/packages/security.yaml` (added validate-coupon route)
- 🔄 To Fix: `src/Pricing/Infrastructure/Persistence/Doctrine/Repository/DoctrineORMPromotionRepository.php`
- 🔄 To Fix: `src/Pricing/Application/Service/PromotionApplicationService.php`
- ⏳ To Create: EventSubscriber pentru X-Tenant-ID header

### Frontend Files:
- ✅ Modified: `lib/context/CartContext.tsx` (added coupon state)
- ✅ Modified: `app/[locale]/cart/page.tsx` (added UI and handlers)
- ⏳ To Create: `lib/api/coupons.ts` (API client)
- ⏳ To Modify: `CartContext.tsx` (replace hardcoded with API call)

### Database:
- ✅ Inserted: DEMOCOUPON promotion with ULID

---

## Success Criteria

### Backend:
- [ ] curl request returnează `valid: true` pentru DEMOCOUPON
- [ ] curl request returnează `discountAmount: 1500` pentru cart de $100
- [ ] curl request returnează `valid: false` pentru cod invalid

### Frontend:
- [ ] UI afișează loading state când se aplică cupon
- [ ] UI afișează mesaj de succes când cuponul este valid
- [ ] UI afișează mesaj de eroare când cuponul este invalid
- [ ] Summary afișează discount-ul corect
- [ ] Taxes sunt recalculate după aplicarea discount-ului
- [ ] Buton X șterge cuponul și resetează calculele

### Integration:
- [ ] Frontend → Backend → Database → Frontend flow funcționează end-to-end
- [ ] Refresh page păstrează cuponul aplicat (localStorage)
- [ ] Multiple apply/remove cycles funcționează corect

---

## Timeline Estimate

- **FAZA 1** (Debug Backend): 2-3 ore
- **FAZA 2** (Test Backend): 30 min
- **FAZA 3** (Frontend Integration): 1-2 ore
- **FAZA 4** (Environment Setup): 15 min
- **FAZA 5** (Testing): 1 ora
- **FAZA 6** (Polish): 1 ora

**Total**: 6-8 ore

---

## Next Steps

1. ✅ Plan created and saved
2. ⏳ Start FAZA 1.1 - Verify Repository Layer
3. ⏳ Add comprehensive logging
4. ⏳ Test each component in isolation
5. ⏳ Integration testing
6. ⏳ Frontend implementation
7. ⏳ End-to-end testing

---

**Last Updated**: 2025-10-21
**Author**: Development Team
**Status**: 🔴 Backend Debugging Phase
