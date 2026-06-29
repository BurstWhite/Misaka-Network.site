# Xboard

<div align="center">

[![Telegram](https://img.shields.io/badge/Telegram-Channel-blue)](https://t.me/XboardOfficial)
![PHP](https://img.shields.io/badge/PHP-8.2+-green.svg)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-blue.svg)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

</div>

## 📖 项目简介

Xboard 是一个基于 Laravel 12 构建的现代化面板系统，专注于提供简洁、高效的用户体验。

## ✨ 功能特性

- 🚀 基于 Laravel 12 + Octane 构建，带来显著的性能提升
- 🎨 全新设计的管理后台（React + Shadcn UI）
- 📱 现代化用户前端（Vue3 + TypeScript）
- 🐳 开箱即用的 Docker 部署方案
- 🎯 优化后的系统架构，更易维护与扩展

## 🚀 快速开始

```bash
git clone -b compose --depth 1 https://github.com/cedar2025/Xboard && \
cd Xboard && \
cp compose.sample.yaml compose.yaml && \
docker compose run -it --rm \
    -e ENABLE_SQLITE=true \
    -e ENABLE_REDIS=true \
    -e ADMIN_ACCOUNT=admin@demo.com \
    xboard php artisan xboard:install && \
docker compose up -d
```

> 安装完成后访问：`http://服务器 IP:7001`  
> ⚠️ 请务必保存安装过程中输出的管理后台地址、管理员账号和密码。

## 🧩 本地配置主要流程

推荐使用 Docker Compose 在本地启动项目。默认流程会启用 SQLite 和 Redis，不需要额外安装 MySQL。

### 1. 准备环境

- 安装 Git
- 安装 Docker 和 Docker Compose
- 确认 Docker 服务已启动

### 2. 获取代码

```bash
git clone -b compose --depth 1 https://github.com/cedar2025/Xboard
cd Xboard
```

如果你已经在当前项目目录中，可以直接从下一步开始。

### 3. 准备 Compose 配置

```bash
cp compose.sample.yaml compose.yaml
```

项目提供了多个 Compose 模板，可按场景选择后复制为 `compose.yaml`：

| 文件 | 网络模式 | 适用场景 |
| --- | --- | --- |
| `compose.sample.yaml` | bridge，并映射 `7001:7001` | 普通 Docker、本机反向代理、宝塔 Docker 管理器，默认推荐 |
| `compose.host.sample.yaml` | `network_mode: host` | 宝塔原生环境，OpenResty 在宿主机上运行 |
| `compose.1panel.sample.yaml` | bridge，并使用外部 `1panel-network` | 1Panel 用户，需要连接 1Panel 管理的 MySQL/Redis |
| `compose.split.sample.yaml` | 多容器拆分 web/horizon/ws-server/redis | 高级部署、扩容或迁移到 K8s |

### 4. 初始化项目

新手推荐使用 SQLite 快速初始化：

```bash
docker compose run -it --rm \
    -e ENABLE_SQLITE=true \
    -e ENABLE_REDIS=true \
    -e ADMIN_ACCOUNT=admin@demo.com \
    xboard php artisan xboard:install
```

如果你需要自定义数据库、Redis、管理员账号等配置，可以运行交互式安装：

```bash
docker compose run -it --rm xboard php artisan xboard:install
```

### 5. 启动服务

```bash
docker compose up -d
```

启动后访问：

```text
http://localhost:7001
```

如果在服务器上部署，请将 `localhost` 换成服务器 IP 或绑定的域名。

### 6. 常用维护命令

```bash
# 查看容器状态
docker compose ps

# 查看日志
docker compose logs -f

# 重启服务
docker compose restart

# 更新镜像并重启
docker compose pull && docker compose up -d

# 停止服务
docker compose down
```

容器启动时会自动执行 `php artisan xboard:update`，用于处理迁移、插件安装、版本缓存和主题刷新，通常不需要额外手动执行更新命令。

## 📖 文档

### 🔄 升级说明

> 🚨 **重要：** 当前版本包含较大变更。升级前请严格阅读升级文档，并提前备份数据库。升级和迁移是不同操作，请不要混淆。

### 开发指南

- [插件开发指南](./docs/en/development/plugin-development-guide.md) - XBoard 插件开发完整说明

### 部署指南

- [使用 1Panel 部署](./docs/en/installation/1panel.md)
- [使用 Docker Compose 部署](./docs/en/installation/docker-compose.md)
- [使用宝塔面板部署](./docs/en/installation/aapanel.md)
- [使用宝塔面板 + Docker 部署](./docs/en/installation/aapanel-docker.md)（推荐）

### 迁移指南

- [从 v2board dev 迁移](./docs/en/migration/v2board-dev.md)
- [从 v2board 1.7.4 迁移](./docs/en/migration/v2board-1.7.4.md)
- [从 v2board 1.7.3 迁移](./docs/en/migration/v2board-1.7.3.md)

## 🛠️ 技术栈

- 后端：Laravel 12 + Octane
- 管理后台：React + Shadcn UI + TailwindCSS
- 用户前端：Vue3 + TypeScript + NaiveUI
- 部署：Docker + Docker Compose
- 缓存：Redis + Octane Cache

## 📷 预览

![管理后台预览](./docs/images/admin.png)

![用户端预览](./docs/images/user.png)

## ⚠️ 免责声明

本项目仅供学习与交流使用。使用本项目所产生的一切后果由使用者自行承担。

## ❤️ 支持项目

如果本项目对你有帮助，欢迎捐赠支持。你的支持将帮助项目持续维护，也会让作者非常开心。

TRC20：`TLypStEWsVrj6Wz9mCxbXffqgt5yz3Y4XB`

## 🌟 维护说明

本项目目前处于轻量维护状态。我们会：

- 修复严重 Bug 和安全问题
- 审查并合并重要的 Pull Request
- 提供必要的兼容性更新

但新功能开发可能会比较有限。

## 🔔 重要提示

1. 修改管理后台路径后需要重启服务：

```bash
docker compose restart
```

2. 如果使用宝塔面板安装，请重启 Octane 守护进程。

## 🤝 参与贡献

欢迎提交 Issue 和 Pull Request，一起改进项目。

## 📈 Star 历史

[![Stargazers over time](https://starchart.cc/cedar2025/Xboard.svg)](https://starchart.cc/cedar2025/Xboard)
