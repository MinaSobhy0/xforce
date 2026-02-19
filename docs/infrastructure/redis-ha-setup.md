# Redis High Availability Setup for XLinic

This document covers Redis Sentinel and Cluster configurations for production-ready high availability.

## Current Single Redis Setup

Your current setup:
```env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=94b4d3f5257878b7
```

This is a single point of failure. For 1000+ tenants, you need Redis HA.

## Option 1: Redis Sentinel (Recommended for Start)

Redis Sentinel provides automatic failover with master-replica setup.

### Architecture
```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  Sentinel 1  │     │  Sentinel 2  │     │  Sentinel 3  │
│  (Quorum)    │     │  (Quorum)    │     │  (Quorum)    │
└──────┬───────┘     └──────┬───────┘     └──────┬───────┘
       │                    │                    │
       └────────────────────┼────────────────────┘
                            │
       ┌────────────────────┼────────────────────┐
       │                    │                    │
┌──────▼──────┐     ┌───────▼──────┐     ┌───────▼──────┐
│   Master    │────►│   Replica 1  │     │   Replica 2  │
│  (Primary)  │     │  (Standby)   │     │  (Standby)   │
└─────────────┘     └──────────────┘     └──────────────┘
```

### Installation

#### Server 1 (Master + Sentinel)
```bash
# Install Redis
sudo apt update
sudo apt install redis-server -y

# Configure as master
sudo nano /etc/redis/redis.conf
```

Master config `/etc/redis/redis.conf`:
```conf
bind 0.0.0.0
port 6379
requirepass 94b4d3f5257878b7
masterauth 94b4d3f5257878b7

# Memory management
maxmemory 2gb
maxmemory-policy allkeys-lru

# Persistence
appendonly yes
appendfsync everysec

# Performance
tcp-backlog 511
tcp-keepalive 300
```

#### Server 2 & 3 (Replicas + Sentinels)
```bash
sudo nano /etc/redis/redis.conf
```

Replica config:
```conf
bind 0.0.0.0
port 6379
requirepass 94b4d3f5257878b7
masterauth 94b4d3f5257878b7

# Replication
replicaof <master-ip> 6379

# Read-only replica
replica-read-only yes

# Same memory/persistence settings as master
maxmemory 2gb
maxmemory-policy allkeys-lru
appendonly yes
appendfsync everysec
```

### Sentinel Configuration

Create `/etc/redis/sentinel.conf` on all three servers:

```conf
port 26379
daemonize yes
pidfile /var/run/redis/redis-sentinel.pid
logfile /var/log/redis/redis-sentinel.log

# Monitor master
sentinel monitor xlinic-master <master-ip> 6379 2
sentinel auth-pass xlinic-master 94b4d3f5257878b7

# Failover timing
sentinel down-after-milliseconds xlinic-master 5000
sentinel failover-timeout xlinic-master 60000
sentinel parallel-syncs xlinic-master 1

# Notifications (optional)
# sentinel notification-script xlinic-master /var/redis/notify.sh
```

Start Sentinel:
```bash
sudo systemctl start redis-sentinel
sudo systemctl enable redis-sentinel
```

### Laravel Configuration for Sentinel

Update `.env`:
```env
REDIS_CLIENT=phpredis
REDIS_SENTINEL_HOST_1=server1-ip
REDIS_SENTINEL_HOST_2=server2-ip
REDIS_SENTINEL_HOST_3=server3-ip
REDIS_SENTINEL_PORT=26379
REDIS_SENTINEL_SERVICE=xlinic-master
REDIS_PASSWORD=94b4d3f5257878b7
```

Update `config/database.php`:
```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', 'xlinic_'),
    ],

    'default' => [
        'tcp://'.env('REDIS_SENTINEL_HOST_1', '127.0.0.1').':'.env('REDIS_SENTINEL_PORT', '26379'),
        'tcp://'.env('REDIS_SENTINEL_HOST_2', '127.0.0.1').':'.env('REDIS_SENTINEL_PORT', '26379'),
        'tcp://'.env('REDIS_SENTINEL_HOST_3', '127.0.0.1').':'.env('REDIS_SENTINEL_PORT', '26379'),
        'options' => [
            'replication' => 'sentinel',
            'service' => env('REDIS_SENTINEL_SERVICE', 'xlinic-master'),
            'parameters' => [
                'password' => env('REDIS_PASSWORD'),
                'database' => 0,
            ],
        ],
    ],

    'cache' => [
        'tcp://'.env('REDIS_SENTINEL_HOST_1', '127.0.0.1').':'.env('REDIS_SENTINEL_PORT', '26379'),
        'tcp://'.env('REDIS_SENTINEL_HOST_2', '127.0.0.1').':'.env('REDIS_SENTINEL_PORT', '26379'),
        'tcp://'.env('REDIS_SENTINEL_HOST_3', '127.0.0.1').':'.env('REDIS_SENTINEL_PORT', '26379'),
        'options' => [
            'replication' => 'sentinel',
            'service' => env('REDIS_SENTINEL_SERVICE', 'xlinic-master'),
            'parameters' => [
                'password' => env('REDIS_PASSWORD'),
                'database' => 1,
            ],
        ],
    ],

    // Similar config for sessions, queue connections...
],
```

**Note:** For Sentinel with phpredis, you may need predis instead:
```bash
composer require predis/predis
```

Then update `.env`:
```env
REDIS_CLIENT=predis
```

## Option 2: Redis Cluster (For Scale)

Redis Cluster provides automatic sharding across multiple nodes.

### Architecture
```
┌─────────────────────────────────────────────────────────────────┐
│                        Redis Cluster                            │
├─────────────────┬─────────────────┬─────────────────────────────┤
│   Node 1        │   Node 2        │   Node 3                    │
│   Slots 0-5460  │   Slots 5461-   │   Slots 10923-16383         │
│                 │   10922         │                             │
│   ┌──────────┐  │   ┌──────────┐  │   ┌──────────┐              │
│   │  Master  │  │   │  Master  │  │   │  Master  │              │
│   └────┬─────┘  │   └────┬─────┘  │   └────┬─────┘              │
│        │        │        │        │        │                    │
│   ┌────▼─────┐  │   ┌────▼─────┐  │   ┌────▼─────┐              │
│   │ Replica  │  │   │ Replica  │  │   │ Replica  │              │
│   └──────────┘  │   └──────────┘  │   └──────────┘              │
└─────────────────┴─────────────────┴─────────────────────────────┘
```

### Setup (6 nodes minimum - 3 masters, 3 replicas)

On each node, configure `/etc/redis/redis.conf`:

```conf
port 6379
cluster-enabled yes
cluster-config-file nodes.conf
cluster-node-timeout 5000
appendonly yes
requirepass 94b4d3f5257878b7
masterauth 94b4d3f5257878b7
```

Create cluster:
```bash
redis-cli --cluster create \
    node1:6379 node2:6379 node3:6379 \
    node4:6379 node5:6379 node6:6379 \
    --cluster-replicas 1 \
    -a 94b4d3f5257878b7
```

### Laravel Configuration for Cluster

Update `.env`:
```env
REDIS_CLIENT=phpredis
REDIS_CLUSTER=redis
REDIS_CLUSTER_SEEDS="node1:6379,node2:6379,node3:6379"
```

Update `config/database.php`:
```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', 'xlinic_'),
    ],

    'clusters' => [
        'default' => [
            [
                'host' => 'node1',
                'password' => env('REDIS_PASSWORD'),
                'port' => 6379,
                'database' => 0,
            ],
            [
                'host' => 'node2',
                'password' => env('REDIS_PASSWORD'),
                'port' => 6379,
                'database' => 0,
            ],
            [
                'host' => 'node3',
                'password' => env('REDIS_PASSWORD'),
                'port' => 6379,
                'database' => 0,
            ],
        ],
    ],

    // ... other connections
],
```

## Monitoring

### Redis Sentinel Status
```bash
redis-cli -p 26379 SENTINEL masters
redis-cli -p 26379 SENTINEL replicas xlinic-master
redis-cli -p 26379 SENTINEL sentinels xlinic-master
```

### Redis Cluster Status
```bash
redis-cli -a 94b4d3f5257878b7 cluster info
redis-cli -a 94b4d3f5257878b7 cluster nodes
redis-cli -a 94b4d3f5257878b7 --cluster check node1:6379
```

### Memory Usage
```bash
redis-cli -a 94b4d3f5257878b7 INFO memory
```

### Connection Stats
```bash
redis-cli -a 94b4d3f5257878b7 INFO clients
redis-cli -a 94b4d3f5257878b7 CLIENT LIST
```

## Recommendation for XLinic

### Starting (0-500 tenants)
- **Redis Sentinel** with 1 master + 2 replicas
- Total: 3 servers (can be VMs)
- Cost-effective, provides HA

### Growth (500-2000 tenants)
- **Redis Cluster** with 3 masters + 3 replicas
- Total: 6 servers
- Horizontal scaling, automatic sharding

### Enterprise (2000+ tenants)
- **Redis Cluster** with dedicated nodes per function:
  - Cache cluster (3+3 nodes)
  - Session cluster (3+3 nodes)
  - Queue cluster (3+3 nodes)
- Managed Redis (AWS ElastiCache, Azure Cache)

## Health Check

Add to your monitoring:

```php
// app/Console/Commands/CheckRedisHealth.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class CheckRedisHealth extends Command
{
    protected $signature = 'health:redis';
    protected $description = 'Check Redis connection health';

    public function handle()
    {
        try {
            $connections = ['default', 'cache', 'sessions', 'queue'];

            foreach ($connections as $conn) {
                $start = microtime(true);
                Redis::connection($conn)->ping();
                $latency = round((microtime(true) - $start) * 1000, 2);

                $this->info("✓ {$conn}: OK ({$latency}ms)");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Redis health check failed: " . $e->getMessage());
            return 1;
        }
    }
}
```

Schedule in `routes/console.php`:
```php
Schedule::command('health:redis')->everyMinute();
```
