# App — React Native 移动端

React Native 0.76 移动端应用，支持 Android 和 iOS。

## 前置要求

- Node.js 18+（见根目录 `.nvmrc`，推荐 20）
- **Android 开发：** 见 **[Mac Android 环境配置指南](../docs/app-android-setup-mac.md)**（小白向，含 Android Studio 安装步骤）
- Xcode（仅 iOS，本期 Android 优先）

快捷脚本（网络良好时）：

```bash
./scripts/setup-android-mac.sh   # 自动装 JDK + SDK + 模拟器
./scripts/run-android-dev.sh     # 启动模拟器 + 后端 + App
```

## 支付配置（M09）

自付三通道：`fake`（开发）、`alipay_sandbox`（WebView）、`wechat`（APP SDK）。

1. **模拟支付：** 开发构建默认在确认页显示「模拟支付」；需 backend 运行在 `local` 环境。
2. **微信 APP 支付：** 在 `src/config/payment.ts` 设置 `WECHAT_APP_ID`（微信开放平台 App 应用 ID），并在 Android 平台登记包名 `com.kingshop` 与签名。
3. **支付宝沙箱：** 在管理后台 M01 配置 `payment.alipay.*`，确认页选择「支付宝」即可 WebView 打开收银台。

## 快速开始

```bash
npm install

# Android
npm run android

# iOS（首次需安装 CocoaPods）
cd ios && bundle install && bundle exec pod install && cd ..
npm run ios
```

## 目录说明

```
app/
├── android/          # Android 原生工程
├── ios/              # iOS 原生工程
├── App.tsx           # 应用入口组件
└── src/
    ├── api/          # API 请求封装
    ├── components/   # 通用组件
    ├── screens/      # 页面/屏幕
    ├── navigation/   # 路由导航
    ├── hooks/        # 自定义 Hooks
    ├── store/        # 状态管理
    └── utils/        # 工具函数
```

## 环境配置

API 地址等配置后续在 `src/api/` 中统一管理。
