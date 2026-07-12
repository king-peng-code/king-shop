# Mac 跑 Android App — 小白指南

你的报错是因为电脑还缺这三样东西：

1. **Java（JDK 17）** — 编译 Android 用  
2. **Android SDK（含 adb）** — 和手机/模拟器通信  
3. **模拟器或真机** — 运行 App 的「手机」

下面按**最简单**的方式做（推荐装 Android Studio，一个软件全搞定）。

---

## 方案 A：装 Android Studio（推荐，适合小白）

### 第 1 步：下载 Android Studio

1. 打开浏览器访问：https://developer.android.com/studio  
2. 点 **Download Android Studio**  
3. 下载完成后，双击 `.dmg`，把 Android Studio 拖进「应用程序」

> 若官网很慢，可搜「Android Studio 国内下载」用镜像站（注意选 macOS Apple 芯片版）。

### 第 2 步：首次打开并完成向导

1. 打开 **Android Studio**
2. 选 **Standard** 标准安装
3. 等待它自动下载：
   - Android SDK  
   - Android Emulator  
   - 内置 JDK（不用单独装 Java）

### 第 3 步：创建一个虚拟手机（模拟器）

1. 顶部菜单 **Tools → Device Manager**
2. 点 **Create Device**
3. 选 **Pixel 6** → Next  
4. 选 **API 34** 或 **API 35**（带 Google APIs）→ Download → Next → Finish  
5. 在 Device Manager 里点 ▶️ 启动模拟器，等桌面出现

### 第 4 步：配置环境变量（只需做一次）

打开 **终端**，粘贴下面整段回车：

```bash
cat >> ~/.zshrc << 'EOF'

# king-shop android dev
export ANDROID_HOME=$HOME/Library/Android/sdk
export ANDROID_SDK_ROOT=$ANDROID_HOME
export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home"
export PATH=$JAVA_HOME/bin:$ANDROID_HOME/emulator:$ANDROID_HOME/platform-tools:$PATH
EOF

source ~/.zshrc
```

验证是否成功：

```bash
java -version
adb version
emulator -list-avds
```

三条命令都有正常输出即可。

### 第 5 步：启动项目

**终端 1 — 后端：**

```bash
cd /Users/king/king-shop
./scripts/dev-up.sh
```

**终端 2 — App：**

```bash
cd /Users/king/king-shop/app
npm install
npm run android
```

第一次编译可能要 **5～15 分钟**，属正常现象。

---

## 方案 B：一键脚本（网络好时用）

项目里已准备好脚本（会自动下载 JDK + SDK + 模拟器）：

```bash
cd /Users/king/king-shop
./scripts/setup-android-mac.sh
```

完成后**新开一个终端**：

```bash
./scripts/run-android-dev.sh
```

> 若下载卡在 1% 很久，说明访问 GitHub/Google 较慢，请改用 **方案 A**。

---

## 方案 C：用真机（有 Android 手机时最快）

1. 手机：**设置 → 关于手机 → 连点版本号 7 次** 打开开发者模式  
2. **设置 → 开发者选项 → USB 调试** 打开  
3. USB 连 Mac，手机上点「允许调试」  
4. 终端执行 `adb devices`，应看到 `device`  
5. 把 `app/src/config/api.ts` 里的地址改成 Mac 局域网 IP（手机和电脑同一 WiFi）：

```typescript
export const API_BASE_URL = 'http://192.168.x.x:8000/api/v1';
```

6. `./scripts/dev-up.sh` 后执行 `cd app && npm run android`

---

## 登录测试账号

Backend 跑起来后，可用 M03 创建的测试员工：

- 手机号：管理员在后台创建的员工手机号  
- 默认密码：`123456`（首次登录会强制改密）

---

## 常见问题

| 现象 | 处理 |
|------|------|
| `adb: command not found` | 没配 `ANDROID_HOME`，重做第 4 步 |
| `Unable to locate Java Runtime` | 没配 `JAVA_HOME`，或改用 Android Studio 自带 JBR |
| `No emulators found` | Device Manager 里创建并启动模拟器 |
| App 白屏 / 网络错误 | 确认 `./scripts/dev-up.sh` 已启动，模拟器用 `10.0.2.2` |
| 编译很慢 | 第一次正常，保持网络畅通 |

---

## 你只需要记住两条命令

环境配好后，日常开发：

```bash
./scripts/dev-up.sh          # 启动后端
cd app && npm run android    # 启动 App（模拟器需先打开）
```

有问题把终端完整报错截图发出来即可。
