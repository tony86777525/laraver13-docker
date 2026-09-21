#!/usr/bin/env bash
# =============================================================================
# env.sh — Laravel Docker 環境開關工具
# 啟動：docker compose up -d app web db
# 停止：docker compose down
# node 服務請手動控制
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE="$SCRIPT_DIR/docker-compose.yml"
MANAGED_SERVICES=("app" "web" "db")

# ── 顏色 ──────────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

info()    { echo -e "${CYAN}[INFO]${RESET}  $*"; }
success() { echo -e "${GREEN}[OK]${RESET}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${RESET}  $*"; }
error()   { echo -e "${RED}[ERROR]${RESET} $*" >&2; }
header()  { echo -e "\n${BOLD}${BLUE}── $* ──${RESET}\n"; }

# ── 前置檢查 ──────────────────────────────────────────────────────────────────
check_deps() {
    command -v docker &>/dev/null || { error "未安裝 docker"; exit 1; }
    docker compose version &>/dev/null 2>&1 || \
    command -v docker-compose &>/dev/null || \
        { error "未安裝 docker compose / docker-compose"; exit 1; }
}

# ── docker compose 相容指令（V2 優先）────────────────────────────────────────
compose_cmd() {
    if docker compose version &>/dev/null 2>&1; then
        docker compose -f "$COMPOSE_FILE" "$@"
    else
        docker-compose -f "$COMPOSE_FILE" "$@"
    fi
}

# ── 偵測服務是否全部 Running ──────────────────────────────────────────────────
is_running() {
    local running_count
    running_count=$(compose_cmd ps --status running "${MANAGED_SERVICES[@]}" 2>/dev/null \
        | grep -cE 'running|Up' || true)
    [[ "$running_count" -ge "${#MANAGED_SERVICES[@]}" ]]
}

# ── 啟動服務（重建容器）──────────────────────────────────────────────────────
start_services() {
    header "🐳 啟動 Docker 環境（重建容器）"
    info "執行：docker compose up -d ${MANAGED_SERVICES[*]}"
    compose_cmd up -d "${MANAGED_SERVICES[@]}"
    echo ""
    success "Docker 環境已啟動！"
    echo -e "  ${CYAN}App URL :${RESET} http://localhost:8000"
    echo -e "  ${CYAN}DB Port :${RESET} 127.0.0.1:33066"
    echo -e "  ${YELLOW}Note    :${RESET} node 服務請手動執行 docker compose up -d node"
    echo ""
    compose_cmd ps "${MANAGED_SERVICES[@]}"
}

# ── 停止服務（移除容器）──────────────────────────────────────────────────────
stop_services() {
    header "🛑 停止 Docker 環境（移除容器）"
    info "執行：docker compose down"
    compose_cmd down
    echo ""
    success "Docker 環境已停止，容器已移除。"
    warn "dbdata volume 保留，資料不遺失。"
}

# ── 查看狀態 ──────────────────────────────────────────────────────────────────
show_status() {
    header "📊 目前容器狀態"
    compose_cmd ps 2>/dev/null || warn "無法取得容器狀態"
}

# ── 互動選單 ──────────────────────────────────────────────────────────────────
show_menu() {
    local status_label
    if is_running 2>/dev/null; then
        status_label="${GREEN}${BOLD}● 執行中${RESET}"
    else
        status_label="${RED}${BOLD}● 已停止${RESET}"
    fi

    echo -e "${BOLD}${BLUE}"
    echo "╔══════════════════════════════════════════╗"
    echo "║      Laravel Docker 環境開關工具          ║"
    echo "╚══════════════════════════════════════════╝"
    echo -e "${RESET}"
    echo -e "  服務狀態（app / web / db）：${status_label}"
    echo ""
    echo -e "  ${CYAN}1)${RESET} 啟動環境（docker compose up -d app web db）"
    echo -e "  ${CYAN}2)${RESET} 停止環境（docker compose down）"
    echo -e "  ${CYAN}3)${RESET} 查看所有容器狀態"
    echo -e "  ${CYAN}q)${RESET} 離開"
    echo ""
    echo -n "  請輸入選項 [1/2/3/q]："
}

# ── 主程式 ────────────────────────────────────────────────────────────────────
main() {
    check_deps

    # CLI 參數模式
    case "${1:-}" in
        start|up)   start_services; exit 0 ;;
        stop|down)  stop_services;  exit 0 ;;
        status)     show_status;    exit 0 ;;
    esac

    # 互動選單模式
    while true; do
        show_menu
        read -r choice
        echo ""
        case "$choice" in
            1) start_services ;;
            2) stop_services  ;;
            3) show_status    ;;
            q|Q) echo -e "${CYAN}Bye!${RESET}"; exit 0 ;;
            *) warn "無效選項：$choice" ;;
        esac
        echo -e "\n${YELLOW}按 Enter 返回選單...${RESET}"
        read -r
    done
}

main "$@"
