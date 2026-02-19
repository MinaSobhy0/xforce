# PgBouncer Configuration for XLinic Multi-Tenant SaaS

This document provides instructions for setting up PgBouncer connection pooling to handle 1000+ tenants efficiently.

## Why PgBouncer?

PostgreSQL has a limit on concurrent connections (default ~100). With 1000 tenants, each making database connections, you'll quickly exhaust this limit. PgBouncer provides:

- **Connection pooling**: Reuses database connections across requests
- **Connection multiplexing**: Many application connections share fewer database connections
- **Reduced memory usage**: Each PostgreSQL connection uses ~10MB RAM
- **Better performance**: Eliminates connection setup overhead

## Installation

### Ubuntu/Debian
```bash
sudo apt update
sudo apt install pgbouncer -y
```

### Verify Installation
```bash
pgbouncer --version
```

## Configuration

### 1. Main Configuration File

Edit `/etc/pgbouncer/pgbouncer.ini`:

```ini
[databases]
; XLinic central database
xlinic = host=127.0.0.1 port=5432 dbname=xlinic

; Connection pool for tenant schemas (all use same DB, different schemas)
xlinic_pool = host=127.0.0.1 port=5432 dbname=xlinic pool_mode=transaction pool_size=50

[pgbouncer]
; Network settings
listen_addr = 127.0.0.1
listen_port = 6432
unix_socket_dir = /var/run/pgbouncer

; Authentication
auth_type = md5
auth_file = /etc/pgbouncer/userlist.txt

; Pool mode: session, transaction, or statement
; transaction mode is best for Laravel multi-tenant apps
pool_mode = transaction

; Pool size settings
default_pool_size = 25
min_pool_size = 5
reserve_pool_size = 10
reserve_pool_timeout = 3

; Connection limits
max_client_conn = 2000
max_db_connections = 100
max_user_connections = 100

; Timeouts
server_idle_timeout = 60
server_connect_timeout = 15
server_login_retry = 15
client_idle_timeout = 0
client_login_timeout = 60
query_timeout = 0
query_wait_timeout = 120

; Logging
log_connections = 1
log_disconnections = 1
log_pooler_errors = 1
stats_period = 60

; Admin access
admin_users = xlinic
stats_users = xlinic

; TLS (optional but recommended for production)
; server_tls_sslmode = prefer
; client_tls_sslmode = disable
```

### 2. User Authentication File

Create `/etc/pgbouncer/userlist.txt`:

```
"xlinic" "md5_hashed_password"
```

Generate the MD5 hash:
```bash
# Replace 'xlinic123' with your actual password
echo -n "xlinic123xlinic" | md5sum | awk '{print "md5" $1}'
```

Or use plain text format (less secure):
```
"xlinic" "xlinic123"
```

### 3. Set Permissions

```bash
sudo chown pgbouncer:pgbouncer /etc/pgbouncer/pgbouncer.ini
sudo chown pgbouncer:pgbouncer /etc/pgbouncer/userlist.txt
sudo chmod 600 /etc/pgbouncer/userlist.txt
```

## Laravel Configuration

### Update .env

```env
# Change from direct PostgreSQL connection
DB_HOST=127.0.0.1
DB_PORT=6432  # PgBouncer port instead of 5432

# Enable persistent connections for connection reuse
DB_PERSISTENT=true

# Optional: Use separate read/write connections
# DB_READ_HOST=127.0.0.1
# DB_READ_PORT=6432
```

### Update config/database.php

The connections are already configured to support PgBouncer. Key settings:

```php
'pgsql' => [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),  // Change to 6432 in .env
    'database' => env('DB_DATABASE', 'xlinic'),
    'username' => env('DB_USERNAME', 'xlinic'),
    'password' => env('DB_PASSWORD', ''),
    'options' => [
        PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', false),
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
],
```

## Service Management

### Start PgBouncer
```bash
sudo systemctl start pgbouncer
sudo systemctl enable pgbouncer
```

### Check Status
```bash
sudo systemctl status pgbouncer
```

### View Logs
```bash
sudo tail -f /var/log/postgresql/pgbouncer.log
# or
sudo journalctl -u pgbouncer -f
```

### Connect to Admin Console
```bash
psql -h 127.0.0.1 -p 6432 -U xlinic pgbouncer
```

Admin console commands:
```sql
SHOW POOLS;      -- View pool statistics
SHOW CLIENTS;    -- View client connections
SHOW SERVERS;    -- View server connections
SHOW STATS;      -- View overall statistics
SHOW CONFIG;     -- View current configuration
RELOAD;          -- Reload configuration
```

## Performance Tuning for 1000+ Tenants

### Recommended Settings

| Setting | Value | Reason |
|---------|-------|--------|
| `pool_mode` | transaction | Best for short-lived Laravel queries |
| `default_pool_size` | 25 | Per database pool size |
| `max_client_conn` | 2000 | Support 1000+ tenants with headroom |
| `max_db_connections` | 100 | Match PostgreSQL max_connections |
| `reserve_pool_size` | 10 | Extra connections for burst traffic |

### PostgreSQL Settings

Update `postgresql.conf`:

```ini
# Increase max connections if needed
max_connections = 150

# Memory settings for connection handling
shared_buffers = 4GB
effective_cache_size = 12GB
work_mem = 64MB
maintenance_work_mem = 512MB

# Connection settings
tcp_keepalives_idle = 60
tcp_keepalives_interval = 10
tcp_keepalives_count = 6
```

### Monitor Connection Usage

Create a monitoring query:

```sql
-- Check current connections
SELECT
    datname,
    usename,
    count(*) as connections
FROM pg_stat_activity
GROUP BY datname, usename
ORDER BY connections DESC;

-- Check connection wait times
SELECT * FROM pg_stat_activity WHERE wait_event IS NOT NULL;
```

## Testing

### Verify Connection Through PgBouncer

```bash
# Direct to PostgreSQL (should work)
psql -h 127.0.0.1 -p 5432 -U xlinic -d xlinic -c "SELECT 1;"

# Through PgBouncer (should also work)
psql -h 127.0.0.1 -p 6432 -U xlinic -d xlinic -c "SELECT 1;"
```

### Test Laravel Connection

```bash
cd /var/www/html/x_linic
php artisan tinker
>>> DB::connection()->getPdo()
```

### Load Test

```bash
# Install pgbench if not available
sudo apt install postgresql-contrib

# Run benchmark
pgbench -h 127.0.0.1 -p 6432 -U xlinic -d xlinic -c 100 -j 4 -T 60
```

## Troubleshooting

### Common Issues

1. **"no more connections allowed"**
   - Increase `max_client_conn` in pgbouncer.ini
   - Check for connection leaks in Laravel code

2. **"server connection failed"**
   - Verify PostgreSQL is running
   - Check authentication in userlist.txt
   - Verify pg_hba.conf allows connections from PgBouncer

3. **Slow queries after enabling PgBouncer**
   - Check `query_wait_timeout` setting
   - Monitor pool utilization with `SHOW POOLS`

4. **"prepared statement does not exist"**
   - Use `pool_mode = session` instead of transaction
   - Or disable prepared statements in Laravel

### Health Check Script

Save as `/usr/local/bin/check-pgbouncer.sh`:

```bash
#!/bin/bash
result=$(psql -h 127.0.0.1 -p 6432 -U xlinic -d pgbouncer -c "SHOW STATS;" 2>&1)
if [ $? -eq 0 ]; then
    echo "OK: PgBouncer is healthy"
    exit 0
else
    echo "CRITICAL: PgBouncer is not responding"
    echo "$result"
    exit 2
fi
```

## Next Steps

1. Switch DB_PORT in .env from 5432 to 6432
2. Clear Laravel config cache: `php artisan config:clear`
3. Restart application servers
4. Monitor connection usage via PgBouncer admin console
