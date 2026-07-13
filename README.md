# Misaka Network Xboard

<div align="center">

面向订阅制网络服务的开源管理面板，基于 Laravel、Vue 3 与 Docker 构建。

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%5E8.2-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20.svg?logo=laravel&logoColor=white)](https://laravel.com/)
[![Node.js](https://img.shields.io/badge/Node.js-%3E%3D20.19-339933.svg?logo=node.js&logoColor=white)](https://nodejs.org/)
[![Docker](https://img.shields.io/badge/Docker-supported-2496ED.svg?logo=docker&logoColor=white)](https://www.docker.com/)

</div>

## 目录

- [项目概览](#项目概览)
- [功能范围](#功能范围)
- [系统要求](#系统要求)
- [快速部署](#快速部署)
- [部署方案](#部署方案)
- [配置与数据](#配置与数据)
- [运维](#运维)
- [本地开发](#本地开发)
- [测试与质量检查](#测试与质量检查)
- [文档](#文档)
- [安全](#安全)
- [贡献](#贡献)
- [许可证](#许可证)

## 项目概览

Misaka Network Xboard 是一个 Laravel 12 应用，用于管理用户、套餐、订阅、订单、工单、节点与扩展插件。项目包含用户端、管理端资源和面向生产环境的 Docker Compose 部署方案。

项目适合有服务器、数据库、缓存和网络服务运维能力的团队使用。它不是托管服务，也不包含服务器节点、域名、支付渠道或第三方客户端授权；这些基础设施需要由部署方自行准备并负责合规运营。

## 功能范围

- 用户、套餐、订单、订阅和邀请体系
- 节点及多种协议相关配置管理
- 工单、公告、通知和邮件能力
- 管理后台与用户端界面
- 插件机制及支付相关扩展
- Redis 缓存、队列、Laravel Horizon 和 WebSocket 服务
- SQLite 快速部署，以及 MySQL/Redis 外部服务接入
- Docker Compose 单体部署和进程拆分部署
- 数据库备份、R2 对象存储备份与容器健康检查脚本

具体能力以当前代码、迁移文件和插件实现为准。版本升级可能改变配置和数据库结构，请在生产环境升级前阅读迁移文档并完成备份。

## 系统要求

### Docker 部署

- Docker Engine 20.10+，以及 Docker Compose v2
- 建议使用 Linux x86_64 或 arm64 主机
- 生产环境建议使用域名、TLS、反向代理和独立的数据库备份策略
- 开放并保护应用端口，默认端口为 `7001`

### 源码开发

- PHP `8.2+`
- Composer 2+
- Node.js `20.19+`
- npm
- SQLite，或 MySQL 5.7+/兼容版本
- Redis 7+（推荐）

## 快速部署

以下流程使用项目提供的默认 Compose 模板，并以 SQLite 与容器内 Redis 快速初始化。生产环境请按实际情况改用外部数据库、反向代理和安全凭据。

```bash
git clone https://github.com/BurstWhite/Misaka-Network.site.git
cd Misaka-Network.site
cp compose.sample.yaml compose.yaml

docker compose build
docker compose run -it --rm \
  -e ENABLE_SQLITE=true \
  -e ENABLE_REDIS=true \
  -e ADMIN_ACCOUNT=admin@example.com \
  xboard php artisan xboard:install

docker compose up -d
```

部署完成后访问 `http://<服务器地址>:7001`。安装过程输出的管理员入口、账号和密码必须妥善保存；不要继续使用示例账号或默认口令。

首次生产部署至少应完成以下事项：

1. 将 `APP_ENV` 设为 `production`，并确认 `APP_DEBUG=false`。
2. 设置唯一且高强度的管理员密码、数据库密码和 Redis 凭据。
3. 通过反向代理启用 HTTPS，不要直接将管理入口暴露在公网。
4. 配置数据库、上传目录、日志和 Redis 数据的备份与恢复演练。
5. 限制服务器、数据库、Redis 和 WebSocket 端口的网络访问范围。

## 部署方案

项目提供多个 Compose 模板。复制模板后再按部署环境修改，`compose.yaml` 通常不会纳入版本控制。

| 模板 | 适用场景 | 说明 |
| --- | --- | --- |
| [`compose.sample.yaml`](compose.sample.yaml) | 默认部署 | bridge 网络，发布 `7001:7001` |
| [`compose.host.sample.yaml`](compose.host.sample.yaml) | 宿主机反向代理 | 使用 host 网络，适合宿主机 OpenResty 等场景 |
| [`compose.1panel.sample.yaml`](compose.1panel.sample.yaml) | 1Panel | 接入外部 `1panel-network` |
| [`compose.split.sample.yaml`](compose.split.sample.yaml) | 高级部署/扩容 | 拆分 web、Horizon、WebSocket 和 Redis 服务 |
| [`compose.deploy.yaml`](compose.deploy.yaml) | 镜像部署覆盖 | 由部署脚本注入不可变镜像地址 |

### 镜像部署

CI 会为 `master` 和 `new-dev` 分支构建多架构镜像并推送到 GitHub Container Registry。生产环境建议固定到提交 SHA 或明确版本标签，而不是无条件使用 `latest`。镜像部署脚本会执行部署前备份（按配置启用）、启动服务和 HTTP 健康检查：

```bash
cp compose.sample.yaml compose.yaml
XBOARD_IMAGE=ghcr.io/burstwhite/misaka-network.site:<tag> \
  docker compose -f compose.yaml -f compose.deploy.yaml up -d
```

如使用仓库提供的脚本，请先阅读其配置项和备份行为：

```bash
scripts/deploy-container.sh ghcr.io/burstwhite/misaka-network.site:<tag>
```

## 配置与数据

### 环境变量

`.env.example` 提供基础配置示例。不要把真实密钥、生产数据库凭据、支付密钥或云存储密钥提交到 Git；生产环境应通过主机权限管理、密钥管理系统或受控的环境变量注入。

常见配置包括：

| 配置 | 作用 |
| --- | --- |
| `APP_ENV` / `APP_DEBUG` / `APP_URL` | 运行环境、调试开关和公开地址 |
| `APP_KEY` | Laravel 应用密钥，初始化后不得随意更换 |
| `DB_*` | 数据库连接 |
| `REDIS_*` | Redis 连接 |
| `QUEUE_CONNECTION` | 队列驱动，生产环境通常使用 Redis |
| `MAIL_*` | 邮件服务 |
| `GOOGLE_CLOUD_*` | 自动备份或对象存储相关配置 |

### 持久化目录

默认 Compose 模板挂载以下数据：

- `.env`：应用配置和密钥
- `.docker/.data/`：运行时数据
- `storage/logs/`：应用日志
- `storage/theme/`：主题运行时数据
- `plugins/`：插件文件
- `redis-data`：Redis 数据卷

数据库本身由 SQLite 文件或外部 MySQL 保存。升级、迁移和更换镜像前必须先确认上述数据和数据库均可恢复。

## 运维

```bash
# 查看服务状态
docker compose ps

# 查看实时日志
docker compose logs -f

# 重启服务
docker compose restart

# 停止服务
docker compose down

# 拉取新镜像并启动
docker compose pull && docker compose up -d
```

容器启动流程会调用 `php artisan xboard:update` 处理应用更新相关任务。仍建议在更新前固定镜像版本、备份数据库并先在预发布环境验证。

### 备份到 Cloudflare R2

R2 备份依赖 `rclone` 和已配置的对象存储远端。使用前复制配置模板，检查脚本内容和文件权限：

```bash
cp .backup.env.example .backup.env
chmod 600 .backup.env
scripts/backup-r2.sh
```

需要定时执行时：

```bash
chmod +x scripts/backup-r2.sh scripts/install-r2-backup-cron.sh
scripts/install-r2-backup-cron.sh
```

备份成功不等于可恢复。请定期验证数据库、Redis 和文件快照的恢复流程，并控制备份桶的访问权限与保留周期。

## 本地开发

```bash
composer install
cp .env.example .env
php artisan key:generate

cd frontend
npm ci
npm run dev
```

后端开发服务和数据库/Redis 的具体连接方式取决于本机环境。也可以使用 Compose 启动完整依赖，再在宿主机运行前端开发命令。

前端构建命令：

```bash
cd frontend
npm run build
```

## 测试与质量检查

提交变更前建议执行：

```bash
cd frontend
npm run lint
npm run typecheck
npm test
npm run build
npm run test:e2e
```

PHP 依赖安装后，可根据变更范围运行 PHPUnit 与 PHPStan：

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

端到端测试可能需要已启动的应用、浏览器和可用的测试数据库。不要在生产数据库上运行测试或迁移验证。

## 文档

### 部署

- [Docker Compose 部署](docs/en/installation/docker-compose.md)
- [1Panel 部署](docs/en/installation/1panel.md)
- [宝塔面板部署](docs/en/installation/aapanel.md)
- [宝塔面板 Docker 部署](docs/en/installation/aapanel-docker.md)
- [GitHub Actions 部署](docs/en/installation/github-actions-deploy.md)
- [R2 备份](docs/en/installation/r2-backup.md)

### 开发与迁移

- [插件开发指南](docs/en/development/plugin-development-guide.md)
- [性能说明](docs/en/development/performance.md)
- [设备限制](docs/en/development/device-limit.md)
- [v2board 迁移文档](docs/en/migration/v2board-dev.md)
- [配置迁移说明](docs/en/migration/config.md)

## 安全

请不要在公开 Issue、Pull Request 或日志中提交密码、令牌、私钥、完整订阅地址或用户数据。发现安全漏洞时，请避免公开披露利用细节，优先通过仓库维护者提供的私密渠道联系，并提供复现步骤、影响范围和修复建议。

部署方应自行遵守所在地法律法规、网络服务商政策、数据保护义务和第三方服务条款。项目仅提供软件能力，不对具体业务用途、数据处理或网络资源承担合规责任。

## 贡献

欢迎提交 Issue 和 Pull Request。提交前请：

1. 使用最小、单一目的的改动，并说明背景、方案和兼容性影响。
2. 更新受影响的文档、迁移说明或示例配置。
3. 添加或更新测试，并执行与改动相关的质量检查。
4. 不提交 `.env`、备份文件、构建产物、敏感数据或不可追溯的二进制文件。
5. 在 Issue 中提供版本、部署方式、复现步骤、日志片段和脱敏后的配置。

## 许可证

本项目以 [MIT License](LICENSE) 发布。请在再分发时保留许可证和版权声明。
