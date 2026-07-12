# Task 2 Report: AppFlowTest

- **Status:** DONE
- **Commits:**
  - `6e7de2d` — test: add APP self-pay shopping flow scenario test
- **Test results:** PASS (1 passed, 22 assertions)
- **Concerns:** None. The test file was created as specified, with one minor fix: the `setUp` method was changed from `SystemConfigModel::query()->create(...)` to `SystemConfigModel::query()->updateOrCreate(...)` because `seedLocalStoragePublicBaseUrl()` already seeds the `payment.provider` config via `SystemConfigSeeder`. Using `create()` caused a UNIQUE constraint violation on sqlite.
