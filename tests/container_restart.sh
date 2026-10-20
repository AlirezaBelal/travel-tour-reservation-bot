#!/usr/bin/env bash
# Disposable CI only. Run before HTTP fixtures; never contact Telegram.
set -euo pipefail
docker compose exec -T app php -r 'if (getenv("APP_ENV") !== "test" || getenv("DB_DATABASE") !== "travel_tour_test") exit(1);'
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -N -uroot "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM Bookings; SELECT COUNT(*) FROM NotificationOutbox"' > restart-counts.txt
test "$(cat restart-counts.txt)" = $'0\n0'
rm restart-counts.txt
docker compose up -d worker
docker compose stop app
worker_id=$(docker compose ps -q worker)
before=$(docker inspect --format '{{.RestartCount}}' "$worker_id")
runtime_connections() {
  docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -N -uroot -e "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER = '\''$MYSQL_USER'\''"' 2>/dev/null
}
for attempt in {1..60}; do
  if test "$(runtime_connections)" -ge 1; then break; fi
  sleep 1
done
test "$(runtime_connections)" -ge 1
docker compose restart db
recovered=false
for attempt in {1..90}; do
  after=$(docker inspect --format '{{.RestartCount}}' "$worker_id")
  if test "$after" -gt "$before" && test "$(runtime_connections || echo 0)" -ge 1; then
    recovered=true
    break
  fi
  sleep 1
done
test "$recovered" = true
# Only the worker holds this runtime connection: Apache is stopped. Its new
# process must remain alive after reconnecting, not enter an exit/restart loop.
after=$(docker inspect --format '{{.RestartCount}}' "$worker_id")
sleep 5
test "$(docker inspect --format '{{.RestartCount}}' "$worker_id")" = "$after"
test "$(runtime_connections)" -ge 1
docker compose stop worker
docker compose up -d app
curl --retry 15 --retry-delay 2 --retry-all-errors -fsS http://127.0.0.1:8080/health.php
echo 'Container database outage, supervised worker recovery and web restart passed.'
