# M09 App 下单与支付 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** App 端实现详情页直接购买 → 确认订单 → 自付/代付 → 三通道支付调起完整链路。

**Architecture:** MainStack 线性扩展；`api/orders.ts` 对接 M05/M06/M07；`paymentLauncher` 按 channel 分发 fake/WebView/微信 SDK；轮询订单状态确认支付。

**Tech Stack:** React Native 0.76.9 · React 18.3 · react-native-webview · react-native-wechat-lib

## Global Constraints

- React 18.3.1 / RN 0.76.9 锁定
- 纯 StyleSheet，无 UI 库
- Android 优先验收
- 不改 backend API

---

## Tasks（已完成）

- [x] `types/order.ts` + `api/orders.ts`
- [x] `utils/pollOrderStatus.ts` + `services/paymentLauncher.ts`
- [x] `QuantityStepper` / `PaymentMethodPicker` / `PaymentChannelPicker`
- [x] `CheckoutScreen` / `PaymentScreen` / `ProxyShareScreen` / `PaymentResultScreen`
- [x] `ProductDetailScreen` 购买入口 + 导航注册
- [x] npm 依赖 + Android 微信 Activity 配置

## 验收

```bash
./scripts/test-m09-acceptance.sh   # App Jest + Backend Order/Payment
cd app && npm run test:m09         # 仅 App 单元测试
```
