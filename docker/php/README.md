# king-shop PHP 基础镜像

项目自有的 PHP 8.4 基础镜像，预装常用扩展和 Composer。  
**后续扩展只需改这里，所有后端镜像自动继承。**

## 镜像列表

| 镜像 | 用途 | 基于 |
|------|------|------|
| `king-shop/php:8.4.3-cli` | 本地开发 (artisan serve) | PHP 8.4.3 CLI |
| `king-shop/php:8.4.3-fpm` | 生产环境 (Nginx + FPM) | PHP 8.4.3 FPM |

## 预装扩展

- pdo_mysql, redis, zip, mbstring, xml, bcmath, pcntl
- Composer 2.8.5
- git, unzip

## 构建

```bash
./scripts/build-php-base.sh
```

## 扩展镜像（添加新扩展）

编辑 `install-extensions.sh`，然后重新构建：

```bash
# 例：添加 intl 扩展
# docker-php-ext-install intl

./scripts/build-php-base.sh
docker compose build backend    # 自动使用新基础镜像
```

## 推送到私有仓库（防丢失）

```bash
# 阿里云 ACR 示例
export REGISTRY=registry.cn-hangzhou.aliyuncs.com/your-namespace
./scripts/build-php-base.sh --push

# 或手动
docker tag king-shop/php:8.4.3-cli  $REGISTRY/king-shop/php:8.4.3-cli
docker tag king-shop/php:8.4.3-fpm  $REGISTRY/king-shop/php:8.4.3-fpm
docker push $REGISTRY/king-shop/php:8.4.3-cli
docker push $REGISTRY/king-shop/php:8.4.3-fpm
```

## 本地备份（离线保存）

```bash
./scripts/build-php-base.sh --save
# 生成 docker/images/king-shop-php-8.4.3-cli.tar
# 生成 docker/images/king-shop-php-8.4.3-fpm.tar

# 恢复
docker load -i docker/images/king-shop-php-8.4.3-cli.tar
docker load -i docker/images/king-shop-php-8.4.3-fpm.tar
```

## 依赖关系

```
官方 php:8.4.3 (仅构建基础镜像时用一次)
        │
        ▼
king-shop/php:8.4.3-cli / fpm   ← 自有基础镜像
        │
        ▼
king-shop-backend:dev / prod    ← 应用镜像
```

构建应用镜像前**必须先构建基础镜像**，`dev-up.sh` 和 `deploy-prod.sh` 已自动处理。
