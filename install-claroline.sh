#!/usr/bin/env bash
# Claroline 一键安装/启动脚本
# 用法: ./install-claroline.sh [--rebuild]
#   --rebuild  强制重新构建前端资源 (webpack + themes)
set -euo pipefail

LAMP_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
CLAROLINE_DIR="${CLAROLINE_DIR:-/home/liu-ai/dev/Claroline}"
ENV_FILE="$LAMP_DIR/.env"
REBUILD=0
[ "${1:-}" = "--rebuild" ] && REBUILD=1

say()  { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31mERROR: %s\033[0m\n' "$*" >&2; exit 1; }

# ---------- 0. 前置检查 ----------
[ -d "$CLAROLINE_DIR" ] || die "Claroline 项目目录不存在: $CLAROLINE_DIR"
command -v podman-compose >/dev/null || command -v docker >/dev/null || die "需要 podman-compose 或 docker"
command -v node >/dev/null || die "需要 node.js"

# ---------- 1. 确保 claro 运行时存在 (以 php83 为模版) ----------
if [ ! -f "$LAMP_DIR/bin/claro/Dockerfile" ]; then
  say "创建 claro 运行时 (复制自 php83)"
  mkdir -p "$LAMP_DIR/bin/claro"
  cp "$LAMP_DIR/bin/php83/Dockerfile" "$LAMP_DIR/bin/claro/Dockerfile"
fi

# ---------- 2. 写入 .env (幂等) ----------
say "配置 .env"
set_env() { # key value
  if grep -q "^$1=" "$ENV_FILE" 2>/dev/null; then
    sed -i "s|^$1=.*|$1=\"$2\"|" "$ENV_FILE"
  else
    echo "$1=\"$2\"" >> "$ENV_FILE"
  fi
}
set_env PHPVERSION "claro"
set_env DOCUMENT_ROOT "$CLAROLINE_DIR"
set_env APACHE_DOCUMENT_ROOT "/var/www/html/public"

# ---------- 3. 修复参数文件权限 (容器内 www-data 需可读) ----------
[ -f "$CLAROLINE_DIR/config/parameters.yml" ] && chmod 644 "$CLAROLINE_DIR/config/parameters.yml"

# ---------- 4. 构建镜像并启动 ----------
say "构建 claro 镜像并启动容器"
cd "$LAMP_DIR"
if command -v podman-compose >/dev/null; then
  # podman 下 docker compose 插件不支持 links, 用 podman-compose
  podman-compose build webserver
  podman-compose up -d
else
  docker compose up -d --build
fi

# ---------- 5. 等待 Web 就绪 ----------
say "等待服务就绪"
for i in $(seq 1 60); do
  curl -sf -o /dev/null http://localhost/ && break
  sleep 2
  [ "$i" = 60 ] && die "服务 120 秒内未就绪, 查看: $LAMP_DIR/logs/apache2/error.log"
done

# ---------- 6. 前端资源 ----------
say "检查前端资源"
cd "$CLAROLINE_DIR"

# node_modules (public/packages -> node_modules 符号链接依赖它)
if [ ! -d node_modules ] || [ ! -f node_modules/webpack/package.json ]; then
  say "安装 node 依赖 (npm install --legacy-peer-deps)"
  npm install --no-audit --no-fund --legacy-peer-deps || die "npm install 失败"
fi

# webpack 构建产物
if [ "$REBUILD" = 1 ] || [ ! -f webpack-prod.json ]; then
  say "webpack prod 构建 (耗时较长)"
  node node_modules/webpack/bin/webpack.js --config=webpack.config.prod.js --bail || die "webpack 构建失败"
fi

# 主题构建产物
if [ "$REBUILD" = 1 ] || [ ! -f theme-assets.json ]; then
  say "构建主题 (theme-assets.json)"
  node src/main/core/Resources/server/themes/bin build --theme=src/main/theme/Resources/themes/claroline || die "主题构建失败"
fi

# ---------- 7. /etc/hosts ----------
if ! grep -q "claroline.localhost" /etc/hosts 2>/dev/null; then
  say "添加 claroline.localhost 到 /etc/hosts (需要 sudo)"
  echo "127.0.0.1  claroline.localhost" | sudo tee -a /etc/hosts >/dev/null || \
    echo "  跳过: 无 sudo 权限, 可手动执行: echo '127.0.0.1  claroline.localhost' | sudo tee -a /etc/hosts"
fi

# ---------- 8. 最终验证 ----------
say "验证"
code=$(curl -s -o /dev/null -w '%{http_code}' http://localhost/ || true)
[ "$code" = "200" ] || die "http://localhost/ 返回 $code"
html=$(curl -s http://localhost/ || true)
echo "$html" | grep -q "Claroline Connect" || die "页面内容异常"
curl -sf -o /dev/null http://localhost/packages/tinymce/tinymce.min.js || die "tinymce 资源缺失"
curl -sf -o /dev/null "http://localhost/packages/mathjax/MathJax.js?config=TeX-AMS-MML_SVG" || die "mathjax 资源缺失"

echo
printf '\033[1;32m✔ Claroline 已就绪: http://localhost/  (https://localhost/ 亦可)\033[0m\n'
