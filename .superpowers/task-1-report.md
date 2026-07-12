# Task 1 Report: AppAuthFlowTest

## Status: DONE

## Commits

| SHA | Subject |
|-----|---------|
| `d15e4f8` | test: add APP auth flow scenario test |

## Files Created

| File | Purpose |
|------|---------|
| `backend/tests/Feature/Scenarios/AppAuthFlowTest.php` | Scenario test: login → change password → me → logout → re-login with new password |

## Test Results

```
docker compose exec backend php artisan test --filter=AppAuthFlowTest
```

**Result: 1 passed (9 assertions)**

- Login returns token + `must_change_password=true`
- Password change succeeds
- `/api/v1/auth/me` returns correct phone
- Logout succeeds
- Old password rejected on re-login (401)
- New password login succeeds with `must_change_password=false`

## Concerns

None. Test passes cleanly and covers the full APP auth flow end-to-end.
