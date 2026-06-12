#!/usr/bin/env bash
set -euo pipefail

SONAR_URL="http://localhost:9000"
PROJECT_KEY="kibi-backend"
COMPOSE_FILES="-f docker-compose.yml -f docker-compose.sonar.yml"

# host.docker.internal is available by default on Mac Docker Desktop.
# On Linux we need to inject it manually via host-gateway.
if [[ "$(uname)" == "Linux" ]]; then
  ADD_HOST="--add-host=host.docker.internal:host-gateway"
else
  ADD_HOST=""
fi
SONAR_SCANNER_HOST="http://host.docker.internal:9000"

echo "==> Starting SonarQube"
docker-compose $COMPOSE_FILES up -d sonarqube

echo "==> Waiting for SonarQube to be ready (up to 5 min)..."
for i in $(seq 1 30); do
  STATUS=$(curl -sf "$SONAR_URL/api/system/status" | jq -r '.status' 2>/dev/null || echo "DOWN")
  if [ "$STATUS" = "UP" ]; then
    echo "    Ready after $((i * 10))s"
    break
  fi
  printf "    attempt %d/30: %s\n" "$i" "$STATUS"
  sleep 10
  if [ "$i" -eq 30 ]; then
    echo "SonarQube did not start in time"
    exit 1
  fi
done

echo ""
echo "==> Running tests with coverage"
docker-compose exec app php vendor/bin/pest --coverage-clover coverage.xml || true

echo ""
echo "==> Configuring SonarQube project"
curl -sf -u admin:admin -X POST \
  "$SONAR_URL/api/projects/create?project=$PROJECT_KEY&name=Kibi+Backend" \
  > /dev/null || true

TOKEN=$(curl -sf -u admin:admin -X POST \
  "$SONAR_URL/api/user_tokens/generate?name=local-$(date +%s)" \
  | jq -r '.token')

echo ""
echo "==> Running sonar-scanner"
# shellcheck disable=SC2086
docker run --rm \
  $ADD_HOST \
  -v "$(pwd):/usr/src" \
  sonarsource/sonar-scanner-cli \
  -Dsonar.host.url="$SONAR_SCANNER_HOST" \
  -Dsonar.token="$TOKEN"

echo ""
echo "==> Checking Quality Gate"
sleep 10
RESULT=$(curl -sf -u "$TOKEN:" \
  "$SONAR_URL/api/qualitygates/project_status?projectKey=$PROJECT_KEY")
STATUS=$(echo "$RESULT" | jq -r '.projectStatus.status')

echo "    Quality Gate: $STATUS"
echo ""
echo "    Dashboard: $SONAR_URL/dashboard?id=$PROJECT_KEY"

if [ "$STATUS" = "ERROR" ]; then
  echo ""
  echo "    Failing conditions:"
  echo "$RESULT" | jq -r \
    '.projectStatus.conditions[] | select(.status=="ERROR") | "    \(.metricKey): \(.actualValue) (threshold: \(.errorThreshold))"'
  exit 1
fi
