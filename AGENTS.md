# Agent 工作指引

## 项目概览

king-shop 是一个 Monorepo 电商项目，包含三个子项目：

| 目录 | 技术栈 | 说明 |
|------|--------|------|
| `backend/` | Laravel + MySQL + Redis | API 后端 |
| `frontend/` | React + TypeScript + Vite | 管理后台 |
| `app/` | React Native + TypeScript | 移动端 (Android / iOS) |

## Superpowers 工作流（默认启用）

Superpowers 已安装，**所有任务默认按 Superpowers 技能流程处理**，除非用户明确要求「不使用 superpowers」或跳过某技能。

### 核心规则

1. 收到任务后，先判断是否有适用技能（哪怕只有 1% 可能，也必须读取对应 SKILL.md）
2. 使用技能前声明：`Using [skill] to [purpose]`
3. 技能有 checklist 时，用 TodoWrite 逐项跟踪
4. 严格按技能流程执行，不因「任务简单」而跳过

### 技能路径

```
~/.cursor/plugins/local/superpowers/skills/{skill-name}/SKILL.md
```

### 常用技能与触发场景

| 技能 | 何时使用 |
|------|----------|
| `brainstorming` | 新功能、新模块、改行为——任何创造性工作之前 |
| `writing-plans` | 设计确认后，编码前生成实施计划 |
| `executing-plans` | 已有书面计划，分步执行 |
| `test-driven-development` | 实现功能或修复 bug |
| `systematic-debugging` | 排查错误、异常、测试失败 |
| `using-git-worktrees` | 需要隔离分支并行开发 |
| `subagent-driven-development` | 多任务自主迭代 |
| `dispatching-parallel-agents` | 可并行的独立子任务 |
| `requesting-code-review` / `receiving-code-review` | 提交审查前 / 收到审查意见后 |
| `finishing-a-development-branch` | 开发完成，决定 merge / PR / 清理 |
| `verification-before-completion` | 声明完成前验证修复有效 |

### 技能优先级

1. **流程类优先**（brainstorming、systematic-debugging）——决定 HOW
2. **实施类其次**（writing-plans、TDD 等）——指导 WHAT

示例：「做一个用户认证模块」→ 先 `brainstorming`，再 `writing-plans`，再实施。

### 例外

仅当用户**明确**说「不用 superpowers」「直接做」「跳过 brainstorming」等，才可跳过对应技能。

## Code Review 与 MR 流程

开发完成后，按 Superpowers 技能链处理：**review → 修复 → MR/合并**。

### 快捷指令：`MR-CODE`

**通用昵称，一条指令完成「Code Review → 创建 MR」。**

用户说 **`MR-CODE`**（或「code review 然后创建 MR」）时，Agent **自动执行以下流程，不再询问合并方式**：

```
MR-CODE 触发
  │
  ├─ 1. verification-before-completion
  │     跑测试 / 确认变更有效
  │
  ├─ 2. requesting-code-review
  │     dispatch reviewer 审查 BASE_SHA..HEAD_SHA
  │
  ├─ 3. 修复 Critical / Important 问题
  │     Minor 可记入 PR 描述，不阻塞
  │
  └─ 4. 直接创建 MR（跳过 finishing 选项菜单）
        git push -u origin HEAD
        gh pr create
```

**MR-CODE 规则：**
- 审查未通过（有 Critical / Important）→ 先修复，修复后重新 review，再创建 MR
- 用户未明确要求时，**不**走本地 merge（选项 1）或丢弃（选项 4）
- PR body 须包含 Summary + Test plan + Review 结论摘要

### 流程总览

```
开发完成 → verification-before-completion（验证）
         → requesting-code-review（发起审查）
         → 修复 Critical / Important 问题
         → finishing-a-development-branch（决定合并方式）
         → 创建 MR（Pull Request）或本地 merge
         → receiving-code-review（处理 MR 反馈）
```

### 1. 发起 Code Review（`requesting-code-review`）

**必须审查的时机：**
- 每个子任务完成后（subagent-driven / executing-plans）
- 主要功能开发完成
- **合并到 main 之前**

**步骤：**
1. 获取 git SHA：`BASE_SHA`（基准提交）和 `HEAD_SHA`（当前 HEAD）
2. 用 `Task` 工具 dispatch `generalPurpose` 子 agent，按模板审查：
   - 模板路径：`~/.cursor/plugins/local/superpowers/skills/requesting-code-review/code-reviewer.md`
   - 填入：变更描述、计划/需求、BASE_SHA、HEAD_SHA
3. 按反馈分级处理：
   - **Critical** → 立即修复
   - **Important** → 继续前修复
   - **Minor** → 记录，可后续处理

**快捷触发：** 说「帮我 code review」「review 一下当前分支」

**Bugbot 审查（可选）：** 说「review bugbot」或 `/review-bugbot`，会启动只读 Bugbot 子 agent 审查分支 diff。

### 2. 处理 Review 反馈（`receiving-code-review`）

收到 MR / PR 评论或人工反馈时：

1. **先理解** — 复述技术需求，不清楚的先问
2. **再验证** — 对照代码库确认建议是否成立
3. **后实施** — 逐项修复，每项单独测试
4. **可 pushback** — 建议不合理时用技术理由反驳，不盲目照做

**禁止：** 表演式附和（「你说得对！」）、未验证就改代码。

**GitHub 回复：** 在对应 inline comment 线程回复，不用顶层 PR comment。

### 3. 完成分支与创建 MR（`finishing-a-development-branch`）

所有测试通过后，Agent 应给出 **4 个选项**（不要开放式提问）：

| 选项 | 操作 |
|------|------|
| 1. 本地 merge 回 base 分支 | checkout → pull → merge → 跑测试 → 删分支 |
| 2. **Push 并创建 Pull Request** | `git push -u origin <branch>` → `gh pr create` |
| 3. 保持现状 | 保留分支，稍后处理 |
| 4. 丢弃 | 需输入 `discard` 确认 |

**创建 MR 规范（选项 2）：**
```bash
git push -u origin HEAD
gh pr create --title "..." --body "$(cat <<'EOF'
## Summary
- ...

## Test plan
- [ ] ...
EOF
)"
```

**MR 标题风格：** 简洁说明「为什么」，聚焦变更目的而非文件列表。

### 4. king-shop 审查要点

审查时额外关注：

- **架构**：frontend/app 是否直连数据库（禁止）
- **API**：是否遵循 `/api/v1/` 前缀和统一响应格式
- **范围**：是否只改了当前子项目相关文件（最小改动）
- **DDD / 测试**：是否按分层实现；是否有 Unit + Feature 测试；`php artisan test` 是否通过

### 快捷指令参考

| 昵称 / 指令 | Agent 做什么 |
|-------------|-------------|
| **`MR-CODE`** | Code Review → 修复 → 直接创建 MR（首选，一条指令） |
| 「code review」 | 仅 `requesting-code-review` |
| 「review bugbot」 | 启动 Bugbot 只读审查 |
| 「创建 MR / PR」 | 跳过 review，直接 `gh pr create` |
| 「合并到 main」 | `finishing-a-development-branch` 选项 1 |
| 「处理 review 意见」 | `receiving-code-review` |

## DDD 开发与自动化测试（强制）

进入业务功能开发后，后端**必须**按 DDD 分层实现，且**每个领域能力都必须有自动化测试**。

完整约束见 `.cursor/rules/ddd-development.mdc`，核心要求：

| 要求 | 说明 |
|------|------|
| 分层 | Domain → Application → Infrastructure → Http |
| TDD | 先写失败测试，再实现，保持测试绿色 |
| Unit 测试 | 覆盖实体、值对象、领域服务、用例 |
| Feature 测试 | 覆盖 API 端点与完整请求链路 |
| 完成门槛 | `./scripts/docker-test.sh` 或 `docker compose exec backend php artisan test` 全部通过 |

**Docker 测试规范（所有 Mxx 模块统一）：** [docs/superpowers/docker-testing.md](docs/superpowers/docker-testing.md)

**没有测试的领域代码不得合并，测试失败不得声明任务完成。**

Superpowers `test-driven-development` 技能在 DDD 开发时**默认启用**，不得跳过。

## 工作流程

1. 先阅读 `.cursor/rules/` 中的约束规则
2. **检查并应用 Superpowers 技能**（见上文）
3. 确认当前任务属于哪个子项目（backend / frontend / app）
4. 只修改与任务相关的文件，保持最小改动
5. frontend 和 app 不直连数据库，统一通过 backend API 通信

## 目录约定

```
backend/    → Laravel API
frontend/   → React 管理端
app/        → React Native 移动端
```

## API 约定

- 前缀: `/api/v1/`
- 响应: `{ "code": 0, "message": "ok", "data": {} }`
- 鉴权: Bearer Token (Laravel Sanctum)

## 版本约束

完整矩阵与禁止版本见 `.cursor/rules/versions.mdc`。Agent 不得引入该文件中列出的禁止版本。

**锁定版本：** PHP 8.4 · Laravel 12 · MySQL 8.0 · Redis 7.4 · React 18.3 · Node 20 · RN 0.76.9

**本地基础设施：** `./scripts/dev-up.sh` 启动 PHP 8.4 backend + MySQL + Redis。

## 当前阶段

各子项目框架已初始化。后端通过 `docker compose up -d --build` 启动。
业务开发进入 **DDD + 自动化测试** 阶段，详见 `.cursor/rules/ddd-development.mdc`。
