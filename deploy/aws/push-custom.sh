#!/usr/bin/env bash
# Build this repo's customized FleetOps images and push them onto the CloudFormation stack.
# Usage: ./push-custom.sh [stack-name] [region]
set -euo pipefail

STACK_NAME=${1:-unitedfb-dev}
AWS_REGION=${2:-us-east-1}
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

if ! aws sts get-caller-identity &>/dev/null; then
  echo "AWS CLI is not configured. Run: aws configure"
  exit 1
fi

ACCOUNT_ID="$(aws sts get-caller-identity --query Account --output text)"
API_REPO="$(aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$AWS_REGION" --query 'Stacks[0].Outputs[?OutputKey==`ApiRepositoryUri`].OutputValue' --output text)"
CONSOLE_BUCKET="$(aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$AWS_REGION" --query 'Stacks[0].Outputs[?OutputKey==`ConsoleBucketName`].OutputValue' --output text)"
DIST_ID="$(aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$AWS_REGION" --query 'Stacks[0].Outputs[?OutputKey==`CloudFrontDistributionId`].OutputValue' --output text)"
API_URL="$(aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$AWS_REGION" --query 'Stacks[0].Outputs[?OutputKey==`ApiURL`].OutputValue' --output text)"
SOCKET_URL="$(aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$AWS_REGION" --query 'Stacks[0].Outputs[?OutputKey==`SocketURL`].OutputValue' --output text)"
CONSOLE_URL="$(aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$AWS_REGION" --query 'Stacks[0].Outputs[?OutputKey==`ConsoleURL`].OutputValue' --output text)"
CLUSTER="$(aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$AWS_REGION" --query 'Stacks[0].Outputs[?OutputKey==`ECSClusterName`].OutputValue' --output text)"

if [[ -z "$API_REPO" || "$API_REPO" == "None" ]]; then
  echo "Could not read stack outputs. Deploy the stack first: ./deploy.sh $STACK_NAME"
  exit 1
fi

aws ecr get-login-password --region "$AWS_REGION" | docker login --username AWS --password-stdin "${ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com"

TAG="$(git rev-parse --short HEAD 2>/dev/null || date +%Y%m%d%H%M%S)"
API_IMAGE="${API_REPO}:${TAG}"

echo "Building customized API image (includes packages/fleetops)..."
docker build --platform linux/amd64 \
  -f docker/Dockerfile.custom \
  --target app-release \
  -t "$API_IMAGE" \
  -t "${API_REPO}:latest" \
  .

docker push "$API_IMAGE"
docker push "${API_REPO}:latest"

echo "Updating CloudFormation ApiImage parameter..."
APP_KEY_FILE="$(dirname "$0")/.app-key-${STACK_NAME}"
APP_KEY_ARG=()
if [[ -f "$APP_KEY_FILE" ]]; then
  APP_KEY_ARG=("ParameterKey=AppKey,ParameterValue=$(cat "$APP_KEY_FILE")")
fi

aws cloudformation update-stack \
  --stack-name "$STACK_NAME" \
  --region "$AWS_REGION" \
  --use-previous-template \
  --capabilities CAPABILITY_NAMED_IAM \
  --parameters \
    ParameterKey=ApiImage,ParameterValue="$API_IMAGE" \
    ParameterKey=Project,UsePreviousValue=true \
    ParameterKey=EnvironmentType,UsePreviousValue=true \
    "${APP_KEY_ARG[@]}" \
    ParameterKey=RootDomainName,UsePreviousValue=true \
    ParameterKey=DatabaseInstanceType,UsePreviousValue=true \
    ParameterKey=CacheInstanceType,UsePreviousValue=true \
    ParameterKey=ApiDesiredCount,UsePreviousValue=true || true

echo "Building console with stack API host..."
SOCKET_HOST="${SOCKET_URL#*://}"
SOCKET_HOST="${SOCKET_HOST%%/*}"
SOCKET_SECURE=false
[[ "$SOCKET_URL" == wss://* ]] && SOCKET_SECURE=true

mkdir -p console/environments
cat > console/environments/.env.production <<ENV
API_HOST=${API_URL}
API_NAMESPACE=int/v1
API_SECURE=true
SOCKETCLUSTER_PATH=/socketcluster/
SOCKETCLUSTER_HOST=${SOCKET_HOST}
SOCKETCLUSTER_SECURE=${SOCKET_SECURE}
SOCKETCLUSTER_PORT=443
OSRM_HOST=https://router.project-osrm.org
ENV

cat > console/fleetbase.config.json <<JSON
{
  "API_HOST": "${API_URL}",
  "SOCKETCLUSTER_HOST": "${SOCKET_HOST}",
  "SOCKETCLUSTER_PORT": "443",
  "SOCKETCLUSTER_SECURE": "${SOCKET_SECURE}"
}
JSON

docker build --platform linux/amd64 \
  -f console/Dockerfile \
  --build-arg ENVIRONMENT=production \
  -t united-console:latest \
  ./console

TMP_DIR="$(mktemp -d)"
CID="$(docker create united-console:latest)"
docker cp "$CID:/usr/share/nginx/html/." "$TMP_DIR/"
docker rm "$CID" >/dev/null
cp console/fleetbase.config.json "$TMP_DIR/fleetbase.config.json"

aws s3 sync "$TMP_DIR" "s3://${CONSOLE_BUCKET}/" --delete --region "$AWS_REGION"
rm -rf "$TMP_DIR"

aws cloudfront create-invalidation --distribution-id "$DIST_ID" --paths "/*" >/dev/null

for svc in api events scheduler; do
  SVC_NAME="$(aws ecs list-services --cluster "$CLUSTER" --region "$AWS_REGION" --query "serviceArns[?contains(@, '${svc}')]" --output text | awk -F/ '{print $NF}' | head -1)"
  if [[ -n "$SVC_NAME" ]]; then
    aws ecs update-service --cluster "$CLUSTER" --service "$SVC_NAME" --force-new-deployment --region "$AWS_REGION" >/dev/null
  fi
done

echo
echo "Custom FleetOps build pushed."
echo "  Console: $CONSOLE_URL"
echo "  API:     $API_URL"
echo "  Image:   $API_IMAGE"
