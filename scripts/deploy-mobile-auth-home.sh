#!/usr/bin/env bash
# Быстрая выкладка Mobile Auth API v1 на lombard.home (без полного rsync).
set -euo pipefail

DEPLOY_SSH="${DEPLOY_SSH:-server_ubunta@192.168.1.67}"
LOMBARD_STACK_DIR="${LOMBARD_STACK_DIR:-/opt/stacks/prod/lombard-portal}"
CONTAINER="${LOMBARD_CONTAINER:-lombard-portal-lombard-app-1}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

TAR="/tmp/pawn-deploy-api.tgz"
tar czf "$TAR" \
  app/Http/Controllers/Api/V1 \
  app/Http/Requests/Api/V1 \
  app/Models/PawnContract.php \
  app/Models/Item.php \
  app/Models/Client.php \
  app/Services/PawnContractCreationService.php \
  app/Services/PawnContractRedemptionService.php \
  app/Http/Controllers/PawnContractController.php \
  app/Http/Controllers/ClientController.php \
  app/Http/Controllers/AcceptItemController.php \
  routes/api.php \
  tests/Feature/Api/V1 \
  tests/TestCase.php \
  tests/CreatesApplication.php \
  database/factories/UserFactory.php \
  phpunit.xml \
  app/Http/Middleware/Authenticate.php

scp "$TAR" "${DEPLOY_SSH}:/tmp/pawn-deploy-api.tgz"
ssh "$DEPLOY_SSH" "docker cp /tmp/pawn-deploy-api.tgz ${CONTAINER}:/tmp/pawn-deploy-api.tgz && \
  docker exec -u root ${CONTAINER} tar xzf /tmp/pawn-deploy-api.tgz -C /var/www/html && \
  docker exec -u root ${CONTAINER} php artisan storage:link --force && \
  docker exec ${CONTAINER} php artisan route:clear && \
  docker exec ${CONTAINER} php artisan route:cache"

echo "Проверка login:"
ssh "$DEPLOY_SSH" "curl -s -X POST http://lombard.home/api/v1/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{\"email\":\"appraiser@example.com\",\"password\":\"password\",\"device_name\":\"test\"}' | head -c 120"
