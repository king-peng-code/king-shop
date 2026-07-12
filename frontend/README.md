# Frontend — React 管理后台

React + TypeScript + Vite 构建的管理端 Web 应用。

## 快速开始

```bash
npm install
npm run dev
```

开发服务器默认地址：`http://localhost:5173`

## 目录说明

```
frontend/
├── public/           # 静态资源
└── src/
    ├── api/          # API 请求封装
    ├── components/   # 通用组件
    ├── pages/        # 页面
    ├── hooks/        # 自定义 Hooks
    ├── store/        # 状态管理
    └── utils/        # 工具函数
```

## 环境变量

创建 `.env.local` 配置后端 API 地址：

```
VITE_API_BASE_URL=http://localhost:8000/api/v1
```
