#!/usr/bin/env bash
#
# Creates the isolated PostgreSQL database used by the Odoo sync test suite
# (tests/Odoo). Safe to re-run: drops and recreates xforce_testing.
#
# The public schema structure is cloned from the production database
# (structure only, no data). The test tenant schema (tenant_odootest) is
# provisioned automatically by the test suite on first run through the real
# TenantService flow.
#
# Usage: tests/Odoo/bin/setup-test-db.sh
set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${TEST_DB_PORT:-5432}"          # direct PostgreSQL, not PgBouncer
DB_USER="${DB_USERNAME:-xforce}"
SOURCE_DB="${SOURCE_DB:-xforce_master}"
TEST_DB="${TEST_DB:-xforce_testing}"

if [[ -z "${PGPASSWORD:-}" ]]; then
    echo "Set PGPASSWORD (the ${DB_USER} database password) first." >&2
    exit 1
fi

psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d postgres -c "DROP DATABASE IF EXISTS ${TEST_DB}"
psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d postgres -c "CREATE DATABASE ${TEST_DB} OWNER ${DB_USER}"

pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$SOURCE_DB" \
    --schema-only --schema=public --no-owner --no-privileges \
    | psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$TEST_DB" -q 2>/dev/null || true

psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$TEST_DB" -c "CREATE EXTENSION IF NOT EXISTS pg_trgm" >/dev/null || true

TABLES=$(psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$TEST_DB" -tc \
    "SELECT count(*) FROM information_schema.tables WHERE table_schema='public'" | tr -d ' ')

echo "OK: ${TEST_DB} ready (${TABLES} public tables). Run: ./vendor/bin/pest tests/Odoo"
