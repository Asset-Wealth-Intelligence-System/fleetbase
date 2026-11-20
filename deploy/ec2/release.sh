#!/usr/bin/env bash
# Release the local working tree to the production EC2 box (fleet/api.unitedsoftware.lat).
#
#   deploy/ec2/release.sh            # console build + API code, then restart on the server
#   deploy/ec2/release.sh --api-only # skip the console build (backend-only change)
#
# What it does
#   1. ember build --environment production (console/environments/.env.production)
#   2. tars api/ + packages/{fleetops,energyops} (no node_modules) and the console dist
#   3. uploads both to s3://<bucket>/deploy/<timestamp>/
#   4. over SSM: unpacks into /opt/united-fleetbase, runs migrations, restarts the stack
#
# The database is NOT touched (RDS is the source of truth once deployed). The very first
# deployment imported the local dump; see README.md.
set -euo pipefail
export AWS_PAGER=""
REGION=us-east-1
INSTANCE=i-0a0a5d5dc6d1c6191
BUCKET=ulogistics-production-uploads-828885964402
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
STAMP=$(date +%Y%m%d-%H%M%S)
WORK=$(mktemp -d)
API_ONLY=0
[[ "${1:-}" == "--api-only" ]] && API_ONLY=1

cd "$ROOT"
aws sts get-caller-identity >/dev/null || { echo "aws cli not authenticated (run: aws login)"; exit 1; }

if [[ $API_ONLY -eq 0 ]]; then
  echo "==> building console (production)"
  mkdir -p console/environments
  cp deploy/ec2/console.env.production console/environments/.env.production
  (cd console && NODE_OPTIONS=--max-old-space-size=8192 npx ember build --environment production --output-path "$WORK/console-dist" >"$WORK/console-build.log" 2>&1) \
    || { tail -40 "$WORK/console-build.log"; exit 1; }
  COPYFILE_DISABLE=1 tar -czf "$WORK/console-dist.tar.gz" -C "$WORK" console-dist
fi

echo "==> packaging API code"
COPYFILE_DISABLE=1 tar \
  --exclude='api/storage/logs/*' --exclude='api/storage/framework/cache/data/*' \
  --exclude='api/storage/framework/views/*' --exclude='api/storage/framework/sessions/*' \
  --exclude='api/storage/app/*' --exclude='api/node_modules' --exclude='api/.env' \
  --exclude='packages/*/node_modules' --exclude='packages/*/dist' --exclude='packages/*/tmp' \
  --exclude='.DS_Store' \
  -czf "$WORK/code.tar.gz" api packages/fleetops packages/energyops
cp deploy/ec2/docker-compose.yml deploy/ec2/fleetbase.config.json console/nginx.conf "$WORK/"

echo "==> uploading to s3://$BUCKET/deploy/$STAMP/"
aws s3 cp "$WORK/" "s3://$BUCKET/deploy/$STAMP/" --recursive --exclude 'console-dist/*' --exclude '*.log' --only-show-errors

echo "==> applying on $INSTANCE"
REMOTE=$(cat <<EOF
set -euo pipefail
export AWS_DEFAULT_REGION=$REGION
cd /opt/united-fleetbase
mkdir -p releases/$STAMP && aws s3 cp s3://$BUCKET/deploy/$STAMP/ releases/$STAMP/ --recursive --only-show-errors
cp docker-compose.yml releases/$STAMP/docker-compose.prev.yml || true
rm -rf api.new packages.new && mkdir api.new packages.new
tar -xzf releases/$STAMP/code.tar.gz -C api.new --strip-components=1 api 2>/dev/null
tar -xzf releases/$STAMP/code.tar.gz -C packages.new --strip-components=1 packages 2>/dev/null
cp -a api/storage/app/. api.new/storage/app/ 2>/dev/null || true
cp api/.env api.new/.env
mkdir -p api.new/storage/logs api.new/storage/framework/{cache/data,sessions,views}
chown -R www-data:www-data api.new/storage && chmod -R ug+rwX api.new/storage
if [[ -f releases/$STAMP/console-dist.tar.gz ]]; then rm -rf console-dist.new && mkdir console-dist.new && tar -xzf releases/$STAMP/console-dist.tar.gz -C console-dist.new --strip-components=1 2>/dev/null; fi
cp releases/$STAMP/docker-compose.yml docker-compose.yml
cp releases/$STAMP/nginx.conf console-nginx.conf
cp releases/$STAMP/fleetbase.config.json fleetbase.config.json
docker compose stop application queue scheduler console
rm -rf api.prev packages.prev && mv api api.prev && mv packages packages.prev && mv api.new api && mv packages.new packages
[[ -d console-dist.new ]] && { rm -rf console-dist.prev; mv console-dist console-dist.prev; mv console-dist.new console-dist; }
docker compose up -d --remove-orphans
sleep 20
docker compose exec -T application sh -c 'php artisan migrate --force && php artisan sandbox:migrate --force && php artisan fleetbase:create-permissions >/dev/null && php artisan energyops:install && php artisan schedule-monitor:sync && php artisan cache:clear && php artisan route:clear && php artisan config:clear && php artisan queue:restart'
docker compose restart queue scheduler >/dev/null
docker compose ps --format 'table {{.Name}}\t{{.Status}}'
curl -s -o /dev/null -w 'api %{http_code}\n' http://127.0.0.1:8000/
curl -s -o /dev/null -w 'console %{http_code}\n' http://127.0.0.1:4200/
ls -dt releases/* | tail -n +6 | xargs -r rm -rf
EOF
)
B64=$(printf '%s' "$REMOTE" | base64 | tr -d '\n')
PARAMS=$(python3 -c 'import json,sys; print(json.dumps({"commands":[f"echo {sys.argv[1]} | base64 -d > /tmp/release.sh && bash /tmp/release.sh"],"executionTimeout":["3600"]}))' "$B64")
CMD_ID=$(aws ssm send-command --region "$REGION" --instance-ids "$INSTANCE" --document-name AWS-RunShellScript --parameters "$PARAMS" --query 'Command.CommandId' --output text)
while :; do
  sleep 10
  STATUS=$(aws ssm get-command-invocation --region "$REGION" --command-id "$CMD_ID" --instance-id "$INSTANCE" --query Status --output text 2>/dev/null || echo Pending)
  case "$STATUS" in InProgress|Pending|Delayed) ;; *) break;; esac
done
aws ssm get-command-invocation --region "$REGION" --command-id "$CMD_ID" --instance-id "$INSTANCE" --query '[Status,StandardOutputContent,StandardErrorContent]' --output text | grep -v 'Ignoring unknown extended header' | tail -40

echo "==> smoke test"
curl -s -o /dev/null -w "https://api.unitedsoftware.lat -> %{http_code}\n" https://api.unitedsoftware.lat/
curl -s -o /dev/null -w "https://fleet.unitedsoftware.lat -> %{http_code}\n" https://fleet.unitedsoftware.lat/
rm -rf "$WORK"
