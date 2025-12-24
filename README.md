# Play With Me - PocketMine Companion Plugin  
A feature-rich PocketMine plugin that enables seamless companion services on Minecraft servers, supporting recruitment, teaming, timing, settlement, and reputation management for both employers and companions.
# Core Features
✅ Command (/pw) to launch GUI for intuitive operation  
✅ Two-way Reputation System for secure transactions  
✅ Flexible Timing + Renewal Mechanism to adapt to diverse needs  
✅ Temporary Account Settlement with payments based on task completion  
✅ Automatic Expired Data Cleanup to reduce server load  
✅ Offline Caching to prevent task loss  
✅ Built-in Multilingual Support [Simplified Chinese / English]  
✅ Compatible with Multiple Economy Systems [MEBSociety / EconomyAPI / BedrockEconomy]
# Detailed Features
## 1. Companion Recruitment & Applications  
- Employer Operations: Employers can post companion recruitment signs by filling out a recruitment form (including reward amount, task details, duration, etc.).  
- Prohibition Rule: Players are prohibited from creating signs with the plugin logo in the first line; violating signs will be automatically invalidated.  
- Companion Applications: Companions can submit applications directly by clicking existing recruitment signs, and application notifications are pushed to employers in real-time.  
- Recruitment Validity: All recruitment posts have an automatic 3-day validity period and will expire after the deadline.  
## 2. Teaming & Timing Rules
- After employers confirm an application, the system automatically teams the employer and companion, allowing mutual teleportation.  
- Timing Activation: Timing starts only when both parties are online.  
- Timing Pause: Timing stops immediately if either party goes offline; it can resume when both are online again.  
## 3. Renewal Mechanism  
- Both employers and companions can initiate renewal requests during the companion service period.  
- After initiating a renewal, the requester must fill out a "renewal form".  
- The renewal is completed once the other party confirms, extending the current companion relationship.  
## 4. Reputation & Data Publicity  
- Dedicated Leaderboards: Synchronously displays the "Companion Duration Leaderboard" and "Reputation Score Leaderboard".  
- Reputation Display Positions:  
  - Employer Reputation: Shown directly in their published recruitment information for companions' reference.  
  - Companion Reputation: Displayed on the duration leaderboard and application interface for employers' screening.  
- Data Cleanup Rules:
  - Checks recruitment status hourly and marks unaccepted posts older than 3 days as expired.
  - Cleans up expired recruitments and orders from 7 days ago daily.
  - Cleans up unprocessed applications from 1 day ago daily.
  - Updates order timing every 30 seconds.
## 5. Settlement & Rating System
- Temporary Accounts: The system generates a temporary account for each companion order; rewards are calculated based on task completion (filled in by the employer) and distributed.
- Two-way Rating: After the companion service ends, both parties can rate each other, supporting text reviews + star ratings.
- Offline Caching: If either party is offline, the rating entry is cached until their next login to ensure the completion of the rating process.
## Compatibility
- Supported PocketMine API Versions: [5.x.x]
- Dependency: FormAPI, https://github.com/jojoe77777/FormAPI
# Play With Me - PocketMine 陪玩插件
一款功能丰富的 PocketMine 插件，为 Minecraft 服务器提供便捷的陪玩服务，支持雇主与陪玩者的招募对接、组队联动、计时管理、结算支付及信誉体系全流程功能。
# 核心特性
✅ 指令（/pw）唤醒图形界面，操作直观便捷  
✅ 双向信誉体系，交易安全有保障  
✅ 灵活计时 + 续约机制，适配多样使用需求  
✅ 临时账户结算，按任务完成度精准付费  
✅ 过期数据自动清理，减轻服务器负载  
✅ 离线缓存功能，再也不怕任务丢失  
✅ 内置多语言支持【简体中文 / 英文】  
✅ 适配多经济系统【MEBSociety / EconomyAPI / BedrockEconomy】
# 详细功能说明  
## 1. 陪玩招募与申请  
- 雇主操作：雇主可发布陪玩告示牌，需填写招募表单（含报酬金额、任务内容、服务时长等信息）。  
- 禁止规则：玩家不得创建「第一行包含插件 logo」的告示牌，违规告示牌将自动失效。  
- 陪玩申请：陪玩者可直接点击现有招募告示牌提交申请，申请信息实时推送至雇主。  
- 招募时效：所有招募自动设置 3 天有效期，到期后招募信息失效。  
## 2. 组队与计时规则  
- 雇主确认申请后，系统自动将雇主与陪玩者组队，支持双方相互传送。  
- 计时启动：仅当双方同时在线时，计时功能才会启动。  
- 计时暂停：任意一方离线，计时立即停止；双方重新同时在线后可恢复计时。  
## 3. 续约机制  
- 陪玩服务期间，雇主与陪玩者均有权发起续约申请。  
- 发起续约后，需填写「续约表单」补充相关信息。  
- 对方确认后即可完成续约，延续当前陪玩关系。  
## 4. 信誉与数据公示  
- 专属排行榜：同步展示「陪玩时长排行榜」与「信誉评分榜」，数据实时更新。  
- 信誉展示位置：  
  - 雇主信誉：直接显示在其发布的招募信息中，供陪玩者参考筛选。  
  - 陪玩者信誉：展示在时长排行榜及申请界面，助力雇主精准选择。  
- 数据清理规则：  
  - 每小时检查招募状态，标记超过 3 天未接取的招募为过期。  
  - 每天清理 7 天前的已过期招募及订单数据。  
  - 每天清理 1 天前的未处理陪玩申请。  
  - 每 30 秒更新一次订单计时数据。  
## 5. 结算与评价系统  
- 临时账户：系统为每笔陪玩订单生成独立临时账户，报酬按任务完成度（由雇主填写）核算后自动发放。  
- 双向评价：陪玩服务结束后，双方可相互评价，支持文字评论 + 星级评分双重维度。  
- 离线缓存：若任意一方离线，评价入口将缓存至其下次上线，确保评价流程完整。  
## 兼容性说明  
- 支持的 PocketMine API 版本：【5.x.x】  
- 依赖：FormAPI, https://github.com/jojoe77777/FormAPI 
