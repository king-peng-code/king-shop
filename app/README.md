# App — React Native 移动端

React Native 0.76 移动端应用，支持 Android 和 iOS。

## 前置要求

- Node.js 18+
- Android Studio（Android 开发）
- Xcode（iOS 开发，仅 macOS）

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
