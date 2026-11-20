#!/usr/bin/env bash
# Release the local working tree to the Azure VM (distribution.unitedops.app).
#   deploy/azure/release.sh            # console build + API code
#   deploy/azure/release.sh --api-only
# Requires: ssh key ~/.ssh/id_ed25519 (azureuser@20.7.12.37), node/pnpm for the console build.
set -euo pipefail
HOST=azureuser@20.7.12.37
DEST=/opt/united-fleetbase
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WORK=$(mktemp -d)
API_ONLY=0; [[ "${1:-}" == "--api-only" ]] && API_ONLY=1
cd "$ROOT"
if [[ $API_ONLY -eq 0 ]]; then
  echo "==> building console (production)"
  mkdir -p console/environments && cp deploy/azure/console.env.production console/environments/.env.production
  (cd console && NODE_OPTIONS=--max-old-space-size=8192 npx ember build --environment production --output-path "$WORK/console-dist" >"$WORK/console-build.log" 2>&1) || { tail -40 "$WORK/console-build.log"; exit 1; }
  cp deploy/azure/fleetbase.config.json "$WORK/console-dist/fleetbase.config.json"
  rsync -az --delete "$WORK/console-dist/" "$HOST:$DEST/console-dist/"
fi
echo "==> packaging API code"
COPYFILE_DISABLE=1 tar --exclude='api/storage/logs/*' --exclude='api/storage/framework/cache/data/*' \
  --exclude='api/storage/framework/views/*' --exclude='api/storage/framework/sessions/*' --exclude='api/storage/app/*' \
  --exclude='api/node_modules' --exclude='api/.env' --exclude='packages/*/node_modules' --exclude='packages/*/dist' \
  --exclude='packages/*/tmp' --exclude='packages/*/.git' --exclude='.DS_Store' \
  -czf "$WORK/code.tar.gz" api packages/fleetops packages/energyops
rsync -az "$WORK/code.tar.gz" "$HOST:$DEST/incoming/code.tar.gz"
rsync -az deploy/azure/docker-compose.yml deploy/azure/console-nginx.conf deploy/azure/deploy-remote.sh "$HOST:$DEST/"
echo "==> applying on the VM"
ssh "$HOST" "bash $DEST/deploy-remote.sh"
rm -rf "$WORK"
echo "==> smoke test"
curl -s -o /dev/null -w "https://api.distribution.unitedops.app -> %{http_code}\n" https://api.distribution.unitedops.app/ || true
curl -s -o /dev/null -w "https://distribution.unitedops.app -> %{http_code}\n" https://distribution.unitedops.app/ || true
