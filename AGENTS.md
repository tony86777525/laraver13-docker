# 專案工作指引

## 開始工作

- 先閱讀 `docs/development.md`，確認 Docker 執行環境與驗證命令。
- 本專案的 PHP 8.5 只在 Docker `app` 容器內可用；不要使用本機 `php`、`composer` 或 `artisan` 判斷專案狀態。
- 根據 `composer.json`、`composer.lock`、`docker-compose.yml` 與容器環境確認版本相容性。

## 文件導航

- 修改後端分層或資料存取：閱讀 `docs/architecture/backend.md`。
- 修改資料表、migration、索引或交易邏輯：閱讀 `docs/architecture/database.md`。
- 修改 Blade、CSS 或 JavaScript：閱讀 `docs/architecture/frontend.md`。
- 修改功能行為：閱讀 `docs/specs/` 中對應功能規格。
- 開發 warehouse 功能：以 `docs/specs/warehouse.md` 為正式規格；`docs/archive/warehouse-repository-knowledge.md` 僅為歷史分析，不是目前實作規格。
- 調整既有架構決策：查閱 `docs/decisions/` 中相關紀錄。
- `docs/archive/` 僅保存歷史分析與舊參考資料；除非使用者明確要求，不要把 archive 內容當成目前規格。

## 文件維護

- 功能行為變更時，同步更新對應規格。
- 架構或模組責任變更時，同步更新架構文件。
- 文件與程式行為不一致時，指出差異；不要自行認定哪一方正確。
- 未確認的需求標為待確認，不得當作已確定的商業規則。
- 若從 archive 取回仍有效的內容，先整理進 `docs/specs/`、`docs/architecture/` 或 `docs/decisions/`，再依正式文件開發。

## 驗證原則

- PHP 指令使用 `docker compose exec app ...`。
- Node/Vite 指令使用 `docker compose exec node ...`。
- 若 Docker daemon 或容器無法存取，先回報無法驗證的原因，不要改用本機 PHP 代替。
