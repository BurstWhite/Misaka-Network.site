<?php

namespace Database\Seeders;

use App\Models\Knowledge;
use Illuminate\Database\Seeder;

class KnowledgeTutorialSeeder extends Seeder
{
    private const CATEGORY = '客户端下载与配置';

    public function run(): void
    {
        foreach ($this->tutorials() as $index => $tutorial) {
            Knowledge::updateOrCreate(
                [
                    'language' => 'zh-CN',
                    'title' => $tutorial['title'],
                ],
                [
                    'category' => self::CATEGORY,
                    'body' => trim($tutorial['body']),
                    'sort' => $index + 1,
                    'show' => true,
                ]
            );
        }
    }

    private function tutorials(): array
    {
        return [
            [
                'title' => '客户端下载与订阅导入总览',
                'body' => <<<'MARKDOWN'
# 客户端下载与订阅导入总览

本页适合第一次使用的用户。核心流程只有三步：下载对应系统客户端，复制本站订阅链接，在客户端里添加订阅并开启连接。

![客户端下载流程](/assets/tutorials/client-setup/download-flow.svg)

## 推荐客户端

| 系统 | 首选客户端 | 备用客户端 | 说明 |
| --- | --- | --- | --- |
| iPhone / iPad | Shadowrocket | Stash、Quantumult X、Loon、Hiddify | Shadowrocket 等部分应用通常需要外区 App Store 账号下载 |
| Android | v2rayNG | Hiddify | Android 用户建议优先下载 APK 的 universal 版本 |
| Windows | Clash Verge Rev | v2rayN、Hiddify | 普通用户优先用 Clash Verge Rev，界面更适合订阅管理 |
| macOS | Clash Verge Rev | Stash、Surge、Hiddify | Apple M 芯片下载 aarch64 / arm64，Intel 芯片下载 x64 |

## 你的订阅链接

复制下面的订阅地址，导入客户端：

```text
{{subscribeUrl}}
```

> 订阅链接属于你的账号私密信息，不要发给他人。泄露后请到用户中心重置订阅链接。

## 官方下载入口

以下链接整理于 2026-07-05。项目更新较快，如果下载失败，请优先进入“最新发布页”选择最新版。

| 客户端 | 官方入口 | 推荐下载 |
| --- | --- | --- |
| Shadowrocket | https://apps.apple.com/us/app/shadowrocket/id932747118 | App Store 下载 |
| Stash | https://apps.apple.com/us/app/stash-rule-based-proxy/id1596063349 | App Store 下载 |
| Quantumult X | https://apps.apple.com/us/app/quantumult-x/id1443988620 | App Store 下载 |
| Loon | https://apps.apple.com/us/app/loon/id1373567447 | App Store 下载 |
| Clash Verge Rev | https://github.com/clash-verge-rev/clash-verge-rev/releases/latest | Windows x64 安装包、macOS dmg |
| v2rayN | https://github.com/2dust/v2rayN/releases/latest | Windows x64 desktop 包 |
| v2rayNG | https://github.com/2dust/v2rayNG/releases/latest | Android universal APK |
| Hiddify | https://hiddify.com/app/ | iOS、Android、Windows、macOS、Linux |

## 关于 CDN 加速与备用下载

GitHub Release 有时会因为网络环境导致下载慢。建议优先使用官方商店或官方 Release；如果你需要给用户提供加速下载，可以将官方发布页里的安装包下载后，上传到自己的对象存储或 CDN，再把下表占位链接替换成你的实际地址。

| 平台 | 自建 CDN 镜像位 |
| --- | --- |
| Windows Clash Verge Rev | `https://download.example.com/client/clash-verge/windows-x64.exe` |
| Windows v2rayN | `https://download.example.com/client/v2rayn/windows-x64.zip` |
| Android v2rayNG | `https://download.example.com/client/v2rayng/android-universal.apk` |
| Android Hiddify | `https://download.example.com/client/hiddify/android-universal.apk` |
| macOS Clash Verge Rev | `https://download.example.com/client/clash-verge/macos-arm64.dmg` |

> 不建议把用户直接导向来路不明的“高速下载站”。如果使用第三方 GitHub 加速服务，请让用户核对文件名、版本号，并优先以官方发布页为准。

## 常见导入方式

1. 在本站用户中心点击“复制订阅”。
2. 打开客户端，找到 Profile、配置、订阅、Subscription、订阅分组等入口。
3. 粘贴订阅链接 `{{subscribeUrl}}`。
4. 更新订阅，选择延迟较低的节点。
5. 开启系统代理、VPN 或 TUN 模式。

如果导入后没有节点，请检查套餐是否有效、订阅链接是否复制完整，并尝试在客户端里点“更新订阅”。
MARKDOWN,
            ],
            [
                'title' => 'iOS：Shadowrocket 下载与订阅导入',
                'body' => <<<'MARKDOWN'
# iOS：Shadowrocket 下载与订阅导入

Shadowrocket 是 iPhone / iPad 上常用的代理客户端。它在部分地区 App Store 无法直接搜索或下载，遇到“此 App 在当前地区不可用”时，需要使用支持地区的 App Store 账号。

## 下载前先看

| 项目 | 说明 |
| --- | --- |
| 官方下载 | https://apps.apple.com/us/app/shadowrocket/id932747118 |
| 备用客户端 | Stash、Quantumult X、Loon、Hiddify |
| 是否需要外区 Apple ID | 通常需要 |
| 是否需要在 iCloud 登录 | 不需要，且不要登录 iCloud |

<!--access start-->
## 套餐用户可用 Apple ID

购买套餐后，可复制下方 Apple ID 用于 App Store 下载。只用于 App Store，不要登录 iCloud，不要绑定手机号，不要修改密码，不要开启双重认证。

{{appleIds}}
<!--access end-->

## 使用外区 Apple ID 下载

![iOS App Store 登录流程](/assets/tutorials/client-setup/ios-app-store.svg)

1. 打开 App Store，点击右上角头像。
2. 滑到底部，退出当前 App Store 账号。
3. 登录上方提供的 Apple ID。
4. 搜索 `Shadowrocket`，或直接打开官方链接。
5. 下载完成后，退出提供的 Apple ID，切回你自己的 Apple ID。

> 重要：不要在“设置 - Apple ID / iCloud”里登录提供的账号。只在 App Store 里登录，用完即退出。

## 导入订阅

![Shadowrocket 添加订阅](/assets/tutorials/client-setup/shadowrocket-import.svg)

下面是真实 Shadowrocket 操作界面截图，截图中的站点名称和订阅内容仅作演示，请以本站用户中心复制的订阅链接为准。

![Shadowrocket 首页点击加号](/assets/tutorials/client-setup/real/shadowrocket-add-button.png)

![Shadowrocket 类型选择 Subscribe 并粘贴订阅](/assets/tutorials/client-setup/real/shadowrocket-subscribe-form.png)

![Shadowrocket 订阅保存成功](/assets/tutorials/client-setup/real/shadowrocket-saved-profile.png)

1. 打开 Shadowrocket。
2. 点击右上角 `+`。
3. 类型选择 `Subscribe` 或 `订阅`。
4. URL 粘贴下面的订阅链接：

```text
{{subscribeUrl}}
```

5. 名称可填写 `{{siteName}}`。
6. 保存后回到首页，下拉或点击更新订阅。

## 开启连接

![Shadowrocket 开启连接](/assets/tutorials/client-setup/shadowrocket-connect.svg)

![Shadowrocket 首次开启时允许添加 VPN 配置](/assets/tutorials/client-setup/real/shadowrocket-vpn-allow.png)

1. 选择一个延迟较低的节点。
2. 打开顶部开关。
3. 首次使用会弹出 VPN 配置授权，点击允许。
4. 状态栏出现 VPN 标识后即可使用。

## 常见问题

| 问题 | 处理方式 |
| --- | --- |
| App Store 搜不到 Shadowrocket | 先确认是否登录外区 Apple ID，再用官方链接打开 |
| 要求付款方式 | 不要修改账号地区；退出后换另一个可用 Apple ID |
| 订阅为空 | 确认套餐有效，重新复制完整订阅链接并更新订阅 |
| 连接后打不开网页 | 更换节点，或切换 Shadowrocket 的路由模式后重试 |

截图来源：Shadowrocket 公开教程页面 `https://world.crisp.help/zh/article/shadowrocket-1gsqbz8/`。界面会随 App 版本变化，实际操作以当前 App 显示为准。
MARKDOWN,
            ],
            [
                'title' => 'Android：v2rayNG / Hiddify 下载与订阅导入',
                'body' => <<<'MARKDOWN'
# Android：v2rayNG / Hiddify 下载与订阅导入

Android 推荐 v2rayNG，界面简单、兼容性好；如果你想要跨平台一致体验，可以使用 Hiddify。

## 下载地址

以下版本整理于 2026-07-05。下载失败时，请进入最新发布页选择最新版本。

| 客户端 | 官方入口 | 推荐文件 |
| --- | --- | --- |
| v2rayNG | https://github.com/2dust/v2rayNG/releases/latest | `v2rayNG_2.2.5_universal.apk` |
| v2rayNG arm64 | https://github.com/2dust/v2rayNG/releases/download/2.2.5/v2rayNG_2.2.5_arm64-v8a.apk | 适合大多数新手机 |
| v2rayNG universal | https://github.com/2dust/v2rayNG/releases/download/2.2.5/v2rayNG_2.2.5_universal.apk | 不确定架构时下载这个 |
| Hiddify | https://hiddify.com/app/ | Android / Google Play / APK |

> 如果浏览器提示“此类型文件可能有风险”，这是 APK 侧载的常见提示。请只从官方发布页或本站镜像下载。

## 安装 APK

1. 下载 APK 后打开安装。
2. 如果提示“禁止安装未知来源应用”，进入设置允许当前浏览器安装。
3. 安装完成后打开客户端。

## v2rayNG 导入订阅

![v2rayNG 导入订阅](/assets/tutorials/client-setup/android-v2rayng-import.svg)

1. 打开 v2rayNG，点击左上角菜单。
2. 进入“订阅分组设置”。
3. 点击右上角 `+`。
4. 备注填写 `{{siteName}}`。
5. 地址粘贴：

```text
{{subscribeUrl}}
```

6. 保存后回到首页，点击右上角菜单，选择“更新订阅”。
7. 选择一个节点，点击右下角连接按钮。

## Hiddify 导入订阅

1. 打开 Hiddify。
2. 点击 `+` 或 `New Profile`。
3. 选择 `Add from clipboard` 或 `Import from URL`。
4. 粘贴订阅链接 `{{subscribeUrl}}`。
5. 导入后点击主界面连接按钮。

## 常见问题

| 问题 | 处理方式 |
| --- | --- |
| 安装失败 | 删除旧版本后重装，或下载 universal 版本 |
| 没有节点 | 确认套餐有效，重新复制订阅并更新订阅 |
| 无法连接 | 换节点，检查手机系统时间是否准确 |
| 只想给某些 App 使用 | 在客户端设置里开启分应用代理 |
MARKDOWN,
            ],
            [
                'title' => 'Windows：Clash Verge Rev / v2rayN 下载与订阅导入',
                'body' => <<<'MARKDOWN'
# Windows：Clash Verge Rev / v2rayN 下载与订阅导入

Windows 推荐 Clash Verge Rev。它的配置文件管理、节点测速和系统代理开关都比较直观。v2rayN 可作为备用客户端。

## Clash Verge Rev 下载

| 文件 | 下载地址 |
| --- | --- |
| 最新发布页 | https://github.com/clash-verge-rev/clash-verge-rev/releases/latest |
| Windows 64 位安装包 | https://github.com/clash-verge-rev/clash-verge-rev/releases/download/v2.5.1/Clash.Verge_2.5.1_x64-setup.exe |
| Windows 64 位内置 WebView2 版 | https://github.com/clash-verge-rev/clash-verge-rev/releases/download/v2.5.1/Clash.Verge_2.5.1_x64_fixed_webview2-setup.exe |

普通用户优先下载 `x64-setup.exe`。如果安装后打开白屏、提示缺少 WebView2，再下载内置 WebView2 版。

## v2rayN 下载

| 文件 | 下载地址 |
| --- | --- |
| 最新发布页 | https://github.com/2dust/v2rayN/releases/latest |
| Windows 64 位 desktop 包 | https://github.com/2dust/v2rayN/releases/download/7.22.7/v2rayN-windows-64-desktop.zip |
| Windows 64 位普通包 | https://github.com/2dust/v2rayN/releases/download/7.22.7/v2rayN-windows-64.zip |

如果不确定下载哪个，优先下载 desktop 包。

## Clash Verge Rev 导入订阅

![Clash Verge Rev 导入订阅](/assets/tutorials/client-setup/clash-verge-import.svg)

下面是真实 Clash Verge Rev 操作界面截图，截图中的示例机场名称仅作演示，请粘贴本站用户中心复制的订阅链接。

![Clash Verge Rev 订阅页面粘贴 URL 并导入](/assets/tutorials/client-setup/real/clash-verge-import-dialog.png)

1. 安装后打开 Clash Verge Rev。
2. 进入左侧 `Profiles` / `订阅`。
3. 点击 `New` / `新建`。
4. 类型选择 URL。
5. 名称填写 `{{siteName}}`。
6. URL 粘贴：

```text
{{subscribeUrl}}
```

7. 保存后点击更新，等待节点加载完成。

## 开启系统代理

![Clash Verge Rev 开启代理](/assets/tutorials/client-setup/clash-verge-enable.svg)

![Clash Verge Rev 代理页面选择节点](/assets/tutorials/client-setup/real/clash-verge-proxy-page.png)

![Clash Verge Rev 开启系统代理开关](/assets/tutorials/client-setup/real/clash-verge-system-proxy.png)

1. 进入 `Proxies` / `代理` 页面，选择延迟较低节点。
2. 打开 `System Proxy` / `系统代理`。
3. 如果需要全局接管所有流量，再打开 `TUN Mode`。
4. 测试浏览器能否访问目标网站。

## v2rayN 导入订阅

v2rayN 适合需要备用客户端，或习惯传统 Windows 桌面软件的用户。

![v2rayN 菜单栏进入订阅分组设置](/assets/tutorials/client-setup/real/v2rayn-subscription-menu.png)

![v2rayN 填写订阅名称和订阅地址](/assets/tutorials/client-setup/real/v2rayn-subscription-settings.png)

1. 解压并打开 `v2rayN.exe`。
2. 双击任务栏右下角的 v2rayN 图标，打开主窗口。
3. 点击菜单栏“订阅分组”，进入“订阅分组设置”。
4. 点击“添加”。
5. 别名填写 `{{siteName}}`。
6. 可选地址 / URL 粘贴：

```text
{{subscribeUrl}}
```

7. 开启“启用更新”，保存后回到主界面。

![v2rayN 主界面选择节点并设置系统代理](/assets/tutorials/client-setup/real/v2rayn-main-proxy.png)

![v2rayN 更新订阅配置](/assets/tutorials/client-setup/real/v2rayn-update-subscription.png)

8. 选择一个节点并按回车，或右键选择节点。
9. 在系统代理菜单里选择“自动配置系统代理”。
10. 后续节点失效时，点击“更新订阅”获取最新节点。

## 常见问题

| 问题 | 处理方式 |
| --- | --- |
| 下载很慢 | 使用你站内提供的 CDN 镜像，或复制官方链接到管理员配置的加速服务 |
| Windows Defender 提示 | 只保留官方发布页或本站镜像下载的文件，未知来源不要运行 |
| 导入失败 | 检查订阅 URL 是否完整，尝试复制纯文本订阅 |
| 开启代理后仍无效 | 确认浏览器没有独立代理插件，重启客户端后再开系统代理 |

截图来源：Clash Verge Rev 官方文档 `https://www.clashverge.dev/guide/quickstart.html`、FreeV2 v2rayN 教程 `https://freev2.net/blog/v2rayn`。界面会随客户端版本变化，实际操作以当前客户端显示为准。
MARKDOWN,
            ],
            [
                'title' => 'macOS：Clash Verge Rev / Stash 下载与订阅导入',
                'body' => <<<'MARKDOWN'
# macOS：Clash Verge Rev / Stash 下载与订阅导入

macOS 推荐 Clash Verge Rev。习惯 App Store 客户端的用户，也可以使用 Stash 或 Surge。

## 下载地址

| 客户端 | 下载地址 | 说明 |
| --- | --- | --- |
| Clash Verge Rev 最新发布页 | https://github.com/clash-verge-rev/clash-verge-rev/releases/latest | 官方 Release |
| Clash Verge Rev Apple M 芯片 | https://github.com/clash-verge-rev/clash-verge-rev/releases/download/v2.5.1/Clash.Verge_2.5.1_aarch64.dmg | M1 / M2 / M3 / M4 |
| Clash Verge Rev Intel 芯片 | https://github.com/clash-verge-rev/clash-verge-rev/releases/download/v2.5.1/Clash.Verge_2.5.1_x64.dmg | Intel Mac |
| Stash | https://apps.apple.com/us/app/stash-rule-based-proxy/id1596063349 | App Store |
| Hiddify | https://hiddify.com/app/ | macOS dmg / pkg |

## Clash Verge Rev 导入订阅

![macOS Clash Verge Rev 导入订阅](/assets/tutorials/client-setup/macos-clash-verge.svg)

![Clash Verge Rev 真实订阅导入界面](/assets/tutorials/client-setup/real/clash-verge-import-dialog.png)

![Clash Verge Rev 真实代理选择界面](/assets/tutorials/client-setup/real/clash-verge-proxy-page.png)

1. 下载 dmg 后拖入 Applications。
2. 打开 Clash Verge Rev，若系统提示无法打开，请到“系统设置 - 隐私与安全性”允许打开。
3. 进入 `Profiles`。
4. 新建 URL 配置，名称填写 `{{siteName}}`。
5. URL 粘贴：

```text
{{subscribeUrl}}
```

6. 保存并更新订阅。
7. 回到代理页面选择节点，开启系统代理。

## Stash 导入订阅

1. 从 App Store 安装 Stash。
2. 打开 Stash，进入 `Profiles`。
3. 点击 `+`，选择 `Download from URL`。
4. 粘贴订阅链接 `{{subscribeUrl}}`。
5. 下载完成后选择该配置并开启连接。

## 常见问题

| 问题 | 处理方式 |
| --- | --- |
| 不知道芯片类型 | 点击左上角苹果图标，进入“关于本机”查看芯片 |
| 打开提示损坏或无法验证 | 只下载官方发布页文件；允许打开后仍失败就重新下载 |
| App Store 提示地区不可用 | 使用外区 App Store 账号下载，下载后切回自己的账号 |
| 订阅更新失败 | 复制完整订阅链接，关闭代理后再更新一次 |

截图来源：Clash Verge Rev 官方文档 `https://www.clashverge.dev/guide/quickstart.html`。界面会随客户端版本变化，实际操作以当前客户端显示为准。
MARKDOWN,
            ],
        ];
    }
}
