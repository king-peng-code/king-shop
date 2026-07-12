# M09 — App 下单与支付 完成记录

> **日期：** 2026-07-12  
> **分支：** main @ `d9e95b1`  
> **Spec：** [2026-07-12-M09-app-checkout-payment-design.md](./specs/2026-07-12-M09-app-checkout-payment-design.md)

---

## 交付摘要

- App 详情页直接购买 → 确认订单 → 自付/代付 → 支付/分享 → 结果页
- 自付三通道：fake、支付宝 WebView、微信 APP SDK
- 对接 Backend：`POST /orders`、`/pay`、`/proxy-pay-link`、`GET /orders/{id}`

---

## 自动测试验收

### 一键命令

```bash
./scripts/test-m09-acceptance.sh
```

### App Jest（17 项）

| 套件 | 覆盖 |
|------|------|
| `__tests__/api/orders.test.ts` | createOrder、getOrder、payOrder、proxyPayLink、simulateFakeNotify |
| `__tests__/utils/pollOrderStatus.test.ts` | 轮询 paid / timeout / 延迟 paid |
| `__tests__/services/paymentLauncher.test.ts` | channel 类型守卫、fake 代调、微信 SDK 成功/取消/失败 |

```bash
cd app && npm run test:m09
```

### Backend Feature（15 项）

| 套件 | 覆盖 |
|------|------|
| `Catalog\OrderApiTest` | 下单、列表、取消、权限 |
| `Catalog\PaymentApiTest` | 发起支付、fake notify、全链路、42204 |
| `Admin\OrderApiTest` | 后台订单（M09 回归，无 App 改动） |

```bash
docker exec king-shop-backend php artisan test --filter='OrderApiTest|PaymentApiTest'
```

---

## 验收清单

### 自动化（已通过）

- [x] App Jest M09 套件 17/17 绿
- [x] Backend OrderApiTest + PaymentApiTest 15/15 绿
- [x] `./scripts/test-m09-acceptance.sh` 可重复执行

### 手工（待真机/沙箱）

- [ ] 自付支付宝 WebView（需 M01 沙箱凭证）
- [ ] 自付微信 SDK（需 `WECHAT_APP_ID` + 开放平台签名）
- [ ] 代付 Share 面板 + H5 预览（M07 已有，App 侧手工点一次）

---

## Follow-up

| 项 | 模块 |
|----|------|
| 订单列表 / 详情 / 取消 | M10 |
| 支付结果页「查看订单」 | M10 |
| E2E（Detox） | 非本期目标 |
