# 代付三方用户（External Payer）— 执行与验收记录

> **模块：** External Payer（M07 扩展）  
> **日期：** 2026-07-12  
> **状态：** ✅ 完成  
> **分支：** `feature/external-payer`  
> **关联 Spec：** [2026-07-12-external-payer-design.md](../specs/2026-07-12-external-payer-design.md)  
> **Implementation Plan：** [2026-07-12-external-payer.md](../plans/2026-07-12-external-payer.md)  
> **前置依赖：** M07（找人代付）、M06（支付网关）

---

## 1. 执行摘要

引入 `external_users` 三方付款人实体，替换 M07 中 `orders.paid_by_user_id` / `payments.payer_user_id` 对员工 `users` 的依赖。代付 H5 无需 Sanctum 登录，付款人由支付渠道（wechat/alipay/fake）识别；API 响应字段 `paid_by_user` → `paid_by_payer`。

| 交付项 | 状态 |
|--------|------|
| `external_users` 表 + FK 替换 migration | ✅ |
| `ExternalUser` Domain + `UpsertExternalUserHandler` | ✅ |
| 代付 pay 移出 auth；fake 全链路写入 `paid_by_external_user_id` | ✅ |
| API 返回 `paid_by_payer`（含 provider） | ✅ |
| H5 无登录 UI；管理后台/App 展示更新 | ✅ |
| Unit + Feature 测试 | ✅ |
| `./scripts/docker-test.sh` 全绿 | ✅ |
| `cd frontend && npm run build` | ✅ |

---

## 2. 验收清单

- [x] `external_users` 表 + FK 替换完成
- [x] `UpsertExternalUserHandler` Unit 测试通过
- [x] 代付 pay 无需 Sanctum；fake 全链路写入 `paid_by_external_user_id`
- [x] API 返回 `paid_by_payer`
- [x] H5 无登录 UI；管理后台/App 展示更新
- [x] `./scripts/docker-test.sh` 全绿（212 passed）
- [x] Spec + Plan + Record 文档齐全

---

## 3. 测试结果

| 命令 | 结果 |
|------|------|
| `./scripts/docker-test.sh` | **212 passed** (540 assertions), 16.76s |
| `./scripts/docker-test.sh --filter=UpsertExternalUserHandlerTest` | 2 passed |
| `./scripts/docker-test.sh --filter=ProxyPayApiTest` | 5 passed |
| `cd frontend && npm run build` | exit 0 |
| `docker compose exec backend php artisan migrate --force` | Nothing to migrate |

---

## 4. 关键文件

### Backend — Domain / Application

| 文件 | 说明 |
|------|------|
| `backend/database/migrations/2026_07_12_231000_create_external_users_and_replace_payer_fks.php` | 建表 + FK 替换 |
| `backend/app/Domain/ExternalUser/Entities/ExternalUser.php` | 实体 |
| `backend/app/Domain/ExternalUser/ValueObjects/ExternalUserProvider.php` | wechat/alipay/fake |
| `backend/app/Application/ExternalUser/UpsertExternalUser/UpsertExternalUserHandler.php` | upsert 用例 |
| `backend/app/Application/ProxyPay/InitiateProxyPayment/InitiateProxyPaymentHandler.php` | 公开代付发起 |
| `backend/app/Application/Payment/ConfirmPayment/ConfirmPaymentHandler.php` | 回调写入 external user |
| `backend/app/Http/Controllers/Catalog/ProxyPayController.php` | 代付 API（pay 无 auth） |
| `backend/app/Http/Resources/Catalog/OrderResource.php` | `paid_by_payer` 响应 |
| `backend/app/Http/Resources/Admin/OrderResource.php` | 管理端 `paid_by_payer` |

### Backend — Tests

| 文件 | 说明 |
|------|------|
| `backend/tests/Unit/Application/ExternalUser/UpsertExternalUserHandlerTest.php` | Unit |
| `backend/tests/Unit/Domain/ExternalUser/ValueObjects/ExternalUserProviderTest.php` | Unit |
| `backend/tests/Feature/Infrastructure/EloquentExternalUserRepositoryTest.php` | Feature |
| `backend/tests/Feature/Catalog/ProxyPayApiTest.php` | 代付全链路 |
| `backend/tests/Feature/Admin/OrderApiTest.php` | 管理端 `paid_by_payer` |

### Frontend / App

| 文件 | 说明 |
|------|------|
| `frontend/src/pages/proxy-pay/ProxyPayPage.tsx` | H5 无登录代付 |
| `frontend/src/api/proxyPay.ts` | 公开 pay API |
| `frontend/src/types/order.ts` | `paid_by_payer` 类型 |
| `frontend/src/components/OrderDetailDrawer.tsx` | 管理端展示 |
| `app/src/types/order.ts` | App 类型 |
| `app/src/screens/OrderDetailScreen.tsx` | App 代付人展示 |

### Docs

| 文件 | 说明 |
|------|------|
| `docs/superpowers/specs/2026-07-12-external-payer-design.md` | Design Spec（✅ 已实现） |
| `docs/superpowers/specs/2026-07-12-M07-proxy-pay-design.md` | superseded 注记 |
| `docs/superpowers/plans/2026-07-12-external-payer.md` | Implementation Plan |

---

## 5. 后续（M16）

- [ ] H5 微信 OAuth 获取 openid
- [ ] 支付宝回调解析 buyer_id upsert
- [ ] JSAPI 真实调起（非 fake notify 模拟）
