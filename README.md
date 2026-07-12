# king-shop

Monorepo 电商项目，包含移动端 App、管理后台和 API 后端。

## 项目结构

```
king-shop/
├── backend/    # Laravel 12 API 后端 (MySQL + Redis)
├── frontend/   # React 管理后台 (Vite + TypeScript)
├── app/        # React Native 移动端 (Android + iOS)
└── docker-compose.yml  # 本地开发环境 (PHP + MySQL + Redis)
```

## 技术栈

| 模块 | 技术 | 版本基线 |
|------|------|----------|
| 后端 | Laravel, PHP, MySQL, Redis | PHP **8.4** · Laravel **12** · MySQL **8.0** · Redis **7.4** |
| 管理端 | React, TypeScript, Vite | React **18.3** · TS 5.6 · Vite 5 |
| 移动端 | React Native, TypeScript | RN 0.76.9 · React **18.3** |

> 版本已锁定，禁止浮动。完整约束见 `.cursor/rules/versions.mdc`。

## 环境要求

| 工具 | 说明 |
|------|------|
| Docker | Compose v2（**后端 + 数据库均跑在容器内**） |
| Node.js | 20 LTS（frontend / app，见 `.nvmrc`） |

> **无需在宿主机安装 PHP / Composer**，后端通过 Docker 运行。

## 快速开始

### 1. 一键启动后端 + 数据库

```bash
./scripts/dev-up.sh
# 或
docker compose up -d --build
docker compose ps    # 确认全部 healthy / running
```

> 配置详情见 [`docs/docker-setup.md`](docs/docker-setup.md)

首次启动会自动：`composer install` → 生成 `.env` → 数据库迁移 → 启动 API。

API 地址：`http://localhost:8000`  
健康检查：`GET http://localhost:8000/api/v1/health`

### 2. 常用 Docker 命令

```bash
# 查看后端日志
docker compose logs -f backend

# 执行 artisan 命令（无需本机 PHP）
docker compose exec backend php artisan migrate
docker compose exec backend php artisan tinker
docker compose exec backend composer require 某包名

# 停止所有服务
docker compose down
```

### 3. 管理端

```bash
cd frontend
npm install
npm run dev
```

### 4. 移动端

```bash
cd app
npm install
npm run android   # 或 npm run ios
```

## 开发状态

- [x] 项目目录架构
- [x] Agent 约束规则
- [x] 初始化 Laravel 后端
- [x] 初始化 React 管理端
- [x] 初始化 React Native 移动端
- [x] Docker 本地开发环境 (PHP 8.4 + MySQL + Redis)
- [ ] 业务功能开发

## 生产部署（单机）

MySQL + Redis + PHP **同一台服务器**，Docker 内网互访：

```bash
cp .env.prod.example .env.prod   # 修改密码
./scripts/deploy-prod.sh
```

详见 [`docs/deploy-single-server.md`](docs/deploy-single-server.md)

## API

- 前缀: `/api/v1/`
- 健康检查: `GET /api/v1/health`
- 响应格式: `{ "code": 0, "message": "ok", "data": {} }`
