#!/bin/sh
# Applique les migrations (puis le seed initial) sur la base DATABASE_URL.
# Usage : DATABASE_URL="postgresql://..." sh bin/migrer.sh
set -e
[ -n "$DATABASE_URL" ] || { echo "DATABASE_URL absente"; exit 1; }
for f in src/database/migrations/*.sql; do
  echo "== $f"
  psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -q -f "$f"
done
echo "== seed 001"
psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -q -f src/database/seeds/001_initial_data.sql
echo "Terminé."
