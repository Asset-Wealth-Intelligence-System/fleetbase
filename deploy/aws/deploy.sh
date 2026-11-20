#!/usr/bin/env bash
# Fleetbase CloudFormation deploy — official template flow.
# Usage:
#   ./deploy.sh [stack-name] [environment] [domain] [region]
#   FLEETBASE_DEPLOY_YES=1 ./deploy.sh unitedfb-dev development
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

STACK_NAME=${1:-unitedfb-dev}
ENVIRONMENT=${2:-development}
DOMAIN_NAME=${3:-}
AWS_REGION=${4:-us-east-1}
AUTO_YES=${FLEETBASE_DEPLOY_YES:-0}

if [[ ! "$ENVIRONMENT" =~ ^(development|staging|qa|production)$ ]]; then
  echo -e "${RED}Error: Environment must be one of: development, staging, qa, production${NC}"
  exit 1
fi

PROJECT_NAME=$(echo "$STACK_NAME" | sed 's/-[^-]*$//')
PROJECT_NAME=$(echo "$PROJECT_NAME" | tr -cd 'A-Za-z0-9' | cut -c1-14)
[[ ${#PROJECT_NAME} -lt 3 ]] && PROJECT_NAME="unitedfb"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Fleetbase CloudFormation Deployment${NC}"
echo -e "${BLUE}========================================${NC}"
echo -e "Stack Name: ${GREEN}$STACK_NAME${NC}"
echo -e "Project: ${GREEN}$PROJECT_NAME${NC}"
echo -e "Environment: ${GREEN}$ENVIRONMENT${NC}"
echo -e "Region: ${GREEN}$AWS_REGION${NC}"
echo -e "Domain: ${GREEN}${DOMAIN_NAME:-"(using AWS-generated URLs)"}${NC}"
echo ""

if [[ "$AUTO_YES" != "1" ]]; then
  read -r -p "Do you want to proceed with the deployment? (y/N): " REPLY
  if [[ ! "$REPLY" =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Deployment cancelled.${NC}"
    exit 0
  fi
fi

if ! command -v aws >/dev/null 2>&1; then
  echo -e "${RED}Error: AWS CLI is not installed${NC}"
  exit 1
fi

if ! aws sts get-caller-identity &>/dev/null; then
  echo -e "${RED}Error: AWS CLI is not configured or credentials are invalid${NC}"
  echo "Run: aws configure"
  exit 1
fi

if [[ ! -f template.yaml ]]; then
  echo -e "${RED}Error: CloudFormation template 'template.yaml' not found${NC}"
  exit 1
fi

echo -e "${YELLOW}Validating CloudFormation template...${NC}"
aws cloudformation validate-template \
  --template-body file://template.yaml \
  --region "$AWS_REGION" >/dev/null
echo -e "${GREEN}✓ Template validation passed${NC}"

APP_KEY_FILE=".app-key-${STACK_NAME}"
if [[ -f "$APP_KEY_FILE" ]]; then
  APP_KEY="$(cat "$APP_KEY_FILE")"
else
  APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  umask 077
  printf '%s' "$APP_KEY" > "$APP_KEY_FILE"
  echo -e "${GREEN}✓ Generated APP_KEY (saved to $APP_KEY_FILE — do not commit)${NC}"
fi

PARAM_FILE="parameters-${ENVIRONMENT}.json"
PARAMS=(
  "ParameterKey=Project,ParameterValue=$PROJECT_NAME"
  "ParameterKey=EnvironmentType,ParameterValue=$ENVIRONMENT"
  "ParameterKey=AppKey,ParameterValue=$APP_KEY"
)

if [[ -n "$DOMAIN_NAME" ]]; then
  PARAMS+=("ParameterKey=RootDomainName,ParameterValue=$DOMAIN_NAME")
fi

if [[ -f "$PARAM_FILE" ]]; then
  echo -e "${BLUE}Using $PARAM_FILE plus generated AppKey${NC}"
fi

if [[ "$ENVIRONMENT" == "development" ]]; then
  PARAMS+=(
    "ParameterKey=DatabaseInstanceType,ParameterValue=db.t3.micro"
    "ParameterKey=CacheInstanceType,ParameterValue=cache.t3.micro"
    "ParameterKey=ApiDesiredCount,ParameterValue=1"
    "ParameterKey=EnableDetailedMonitoring,ParameterValue=false"
  )
elif [[ "$ENVIRONMENT" == "production" ]]; then
  PARAMS+=(
    "ParameterKey=DatabaseInstanceType,ParameterValue=db.t3.small"
    "ParameterKey=CacheInstanceType,ParameterValue=cache.t3.small"
    "ParameterKey=ApiDesiredCount,ParameterValue=3"
    "ParameterKey=DatabaseMultiAZ,ParameterValue=true"
    "ParameterKey=EnableDetailedMonitoring,ParameterValue=true"
  )
fi

if aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$AWS_REGION" &>/dev/null; then
  echo -e "${YELLOW}Stack '$STACK_NAME' already exists. This will update the existing stack.${NC}"
  if [[ "$AUTO_YES" != "1" ]]; then
    read -r -p "Do you want to continue with the update? (y/N): " REPLY
    if [[ ! "$REPLY" =~ ^[Yy]$ ]]; then
      echo -e "${YELLOW}Update cancelled.${NC}"
      exit 0
    fi
  fi
  OPERATION="update"
  WAIT_COMMAND="stack-update-complete"
else
  OPERATION="create"
  WAIT_COMMAND="stack-create-complete"
fi

echo -e "${YELLOW}${OPERATION}ing CloudFormation stack...${NC}"

if [[ "$OPERATION" == "create" ]]; then
  aws cloudformation create-stack \
    --stack-name "$STACK_NAME" \
    --template-body file://template.yaml \
    --parameters "${PARAMS[@]}" \
    --capabilities CAPABILITY_NAMED_IAM \
    --tags Key=Environment,Value="$ENVIRONMENT" Key=ManagedBy,Value=CloudFormation Key=Project,Value="$PROJECT_NAME" \
    --region "$AWS_REGION"
else
  aws cloudformation update-stack \
    --stack-name "$STACK_NAME" \
    --template-body file://template.yaml \
    --parameters "${PARAMS[@]}" \
    --capabilities CAPABILITY_NAMED_IAM \
    --region "$AWS_REGION"
fi

echo -e "${YELLOW}Waiting for stack ${OPERATION} (15–30 minutes)...${NC}"
aws cloudformation wait "$WAIT_COMMAND" \
  --stack-name "$STACK_NAME" \
  --region "$AWS_REGION"

echo -e "${GREEN}✓ Stack $OPERATION completed${NC}"
aws cloudformation describe-stacks \
  --stack-name "$STACK_NAME" \
  --region "$AWS_REGION" \
  --query 'Stacks[0].Outputs[*].[OutputKey,OutputValue]' \
  --output table

echo -e "${YELLOW}Next: upload a customized console/API with ./push-custom.sh $STACK_NAME $AWS_REGION${NC}"
