# M10 — App 订单管理与个人中心 完成记录

> **日期：** 2026-07-12  
> **分支：** `feature-m10-app-orders-profile` @ `d3afa71`  
> **Spec：** [2026-07-12-M10-app-orders-profile-design.md](../specs/2026-07-12-M10-app-orders-profile-design.md)  
> **Plan：** [2026-07-12-M10-app-orders-profile.md](../plans/2026-07-12-M10-app-orders-profile.md)

---

## 交付摘要

- 底部 Tab 导航：商品 / 订单 / 我的（`@react-navigation/bottom-tabs` + 三嵌套 Stack）
- 订单列表：5 个状态 Tab 筛选、下拉刷新、分页加载
- 订单详情：取消、去支付（重选渠道）、确认取餐
- 个人中心：姓名/部门/手机号、修改密码、退出登录
- 后端补充：`POST /api/v1/orders/{id}/complete` 员工确认取餐
- 支付结果页「查看订单」跳转订单详情

---

## 完成任务列表

| Task | 内容 | Commit |
|------|------|--------|
| 1 | 后端员工确认取餐 API（`CompleteMyOrderHandler` + Feature 测试） | `b95b806` |
| 2 | `orderStatus` / `payChannels` 工具与 `orders` API 扩展 | `6fa7692` |
| 3 | Bottom Tab 导航重构（三 Stack） | `be697c3` |
| 4 | 订单列表（`OrderStatusTabs` + `OrderListItem` + `OrdersScreen`） | `f0ebf31` |
| 4-fix | 列表 Tab 竞态修复 + 列表 API 含 items | `b5cf70d` |
| 5 | 订单详情（取消 / 去支付 / 确认取餐） | `1398673` |
| 6 | 个人中心（改密 / 退出） | `8984dc7` |
| 6-fix | 自愿改密后返回个人中心 | `0084fb1` |
| 7 | PaymentResult 衔接 + 验收脚本 | `d3afa71` |
| 8 | 文档记录与模块状态更新 | （本提交） |

---

## 自动测试验收

### 一键命令

```bash
./scripts/test-m10-acceptance.sh
```

### Backend Feature（13 项）

| 套件 | 覆盖 |
|------|------|
| `Catalog\OrderApiTest` | 下单、列表、取消、权限、**员工确认取餐**（ready / 非 ready / 他人订单） |
| `Admin\OrderApiTest` | 后台订单（M10 回归，无 App 改动） |

```bash
./scripts/docker-test.sh --filter=OrderApiTest
```

**结果（2026-07-12）：** 13 passed (27 assertions)

### App Jest（42 项）

| 套件 | 覆盖 |
|------|------|
| `__tests__/api/orders.test.ts` | createOrder、getOrder、listOrders、cancelOrder、completeOrder、payOrder |
| `__tests__/utils/orderStatus.test.ts` | 状态标签与颜色映射 |

```bash
cd app && npm run test:m10
```

**结果（2026-07-12）：** 42 passed (11 suites)

---

## 验收清单

### 自动化（已通过）

- [x] Backend OrderApiTest 13/13 绿
- [x] App Jest M10 套件 42/42 绿
- [x] `./scripts/test-m10-acceptance.sh` 可重复执行

### 手工（待 Android 模拟器 / 真机）

- [ ] 登录后底部显示 商品 / 订单 / 我的 三个 Tab
- [ ] 订单 Tab：5 个状态筛选正确；下拉刷新有效
- [ ] 待支付订单：可取消；可去支付并完成 fake 支付
- [ ] 可取餐订单（后台 mark ready）：可确认取餐，状态变已完成
- [ ] 个人中心：姓名、部门、手机号正确；改密、退出可用
- [ ] 支付成功页「查看订单」跳转订单详情

---

## Follow-up

| 项 | 模块 |
|----|------|
| 订单角标（待支付数量） | 按需迭代 |
| 代付订单分享入口（从详情） | M07 增强 |
| E2E（Detox） | 非本期目标 |
