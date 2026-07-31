# Odoo Sync Test Suite

End-to-end tests for the OdooIntegration module covering every HR entity the
platform syncs with Odoo:

| Suite | Covers |
| --- | --- |
| `ImportUsersSyncTest` | `res.users` → users (delta/full, key-field linking, domain filters, failure isolation) |
| `ImportEmployeesSyncTest` | `hr.employee` → staff_profiles (user relations, placeholder users, approvers, archived filter) |
| `ImportTimeOffSyncTest` | `hr.leave.type`, `hr.leave.allocation`, `hr.leave` imports (timezones, state mapping, date windows, missing dependencies) |
| `ImportPayrollSyncTest` | salary rule categories/structures/rules + `hr.payslip` → payroll runs/lines (bucketed amounts, period runs, dedupe) |
| `ExportAttendanceSyncTest` | attendances → `hr.attendance` (create/update, conflicts, deleted-remotely, watermark delta) |
| `TimeOffRoundTripSyncTest` | Mobile-app flow: local request → Odoo, `action_approve`/`action_refuse` workflows, Odoo decisions flowing back, local-wins conflicts |
| `SyncEngineBehaviorTest` | Batching/pagination, rate-limit retry, auth failure, priorities, realtime job dispatch |
| `FieldTransformerTest` | Every transform type in both directions + RelationResolver caching |

## One-time setup

The suite runs against a dedicated `xforce_testing` database on the direct
PostgreSQL port (5432, bypassing PgBouncer) — production data is never
touched, and `OdooSyncTestCase` refuses to run against any other database.

```bash
PGPASSWORD=<db password> tests/Odoo/bin/setup-test-db.sh
```

On the first test run, the suite provisions a real tenant
(`tenant_odootest`) through `TenantService` — all module migrations and
seeders — which takes ~1 minute once. Subsequent runs reuse it.

## Running

```bash
./vendor/bin/pest tests/Odoo                       # everything (~3 min)
./vendor/bin/pest tests/Odoo/TimeOffRoundTripSyncTest.php
```

## How it works

- **No Odoo server needed** — `Tests\Odoo\Support\FakeOdooClient` is an
  in-memory Odoo: domain filtering, pagination, many2one `[id, name]`
  tuples, `false`-for-empty semantics, workflow actions (`action_approve`
  sets `state=validate`), injectable failures (rate limits, auth), and the
  "cannot marshal None" fault real Odoo emits from `action_*` methods.
- `Tests\Odoo\Support\OdooScenario` builds the connection + entity/field
  mappings **replicating the production tenant's live configuration**
  (same models, transforms, enum value maps, key fields, conflict rules).
- Each test starts with truncated HR/odoo tables inside the test tenant
  schema and a fresh fake bound through the `OdooApiFactory` container
  instance (also covering `applyOdooImport` hooks that resolve clients
  themselves).

## Semantics worth knowing (asserted by the tests)

- **Delta sync** imports only unknown records and pushes local changes —
  this is the mode for mobile-app-driven flows (attendance, time off).
- **Full sync** *overrides local records from Odoo* by design; don't run it
  after making local changes you haven't exported.
- `hr.leave` state changes are pushed as workflow calls
  (`__odoo_actions`), never as direct `state` writes.
