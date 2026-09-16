# ARMIS Scalability Implementation — 2026-09-15

## Implemented in this build

1. **Database connections**
   - Persistent PDO connections are configurable with `DB_PERSISTENT`.
   - `getDbConnection()` remains the primary connection used by existing code.
   - `getReadDbConnection()` provides a safe read-replica entry point and falls back to the primary database when no replica is configured or a replica is unavailable.
   - Existing schema/indexes were preserved; obsolete index definitions referencing non-existent `users`, `staff_medals`, and `staff_promotions` were removed from the scalability layer.

2. **Redis caching**
   - Redis is supported through environment variables.
   - Cache operations use JSON rather than PHP `serialize()`/`unserialize()`.
   - When Redis is unavailable, ARMIS automatically uses `cache/*.cache` as a local fallback so the application does not fail.
   - Redis is deliberately disabled by default on a normal WAMP installation.

3. **Compression and static asset caching**
   - PHP gzip fallback is enabled when `ob_gzhandler` exists.
   - `.htaccess` adds Apache `mod_deflate` compression for text responses.
   - `.htaccess` adds cache headers/expires rules for CSS, JavaScript, images and fonts.
   - HSTS is emitted only over HTTPS, avoiding an incorrect HSTS policy on `http://localhost`.

4. **CDN integration**
   - Central `ScalabilityConfig::assetUrl()` supports moving local static assets to a CDN without hardcoding CDN URLs throughout the application.
   - Shared ARMIS assets now use this helper in the common header.
   - Set `CDN_ENABLED=1` and `CDN_BASE_URL=https://...` only after the CDN has been configured to serve the ARMIS static files.

5. **Read replicas**
   - Configure `DB_READ_REPLICAS=host1,host2`.
   - `getReadDbConnection()` selects replicas and automatically falls back to the primary if a replica cannot be reached.
   - The health console actively tests every configured replica.

6. **Load balancing**
   - A deployment readiness switch is provided with `LOAD_BALANCER_ENABLED`.
   - The health console refuses to report load-balancer mode as ready unless shared Redis infrastructure is reachable, because a multi-node deployment must not depend on local PHP session/cache state.
   - A PHP application cannot create an actual network load balancer on localhost; that part must be supplied by Apache/IIS/Nginx/cloud infrastructure.

7. **Health console**
   - `/Armis2/admin/health.php` now reports real database counts, real cache backend, Redis state, CDN state, replica reachability, load-balancer readiness, compression availability, disk usage and runtime status.
   - Random/fake active-user, uptime, response-time, throughput and cache-hit values were removed.

## Local WAMP settings

The safe starting configuration is:

```text
DB_PERSISTENT=1
REDIS_ENABLED=0
CDN_ENABLED=0
DB_READ_REPLICAS=
LOAD_BALANCER_ENABLED=0
```

This means the local installation is optimized without pretending that Redis, a CDN, database replicas or a load balancer exist when they do not.

## Production activation

### Redis
Install a Redis server and the PHP Redis extension in the same PHP runtime used by Apache, then:

```text
REDIS_ENABLED=1
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### CDN
Publish the ARMIS static asset tree to a trusted CDN/static origin and configure:

```text
CDN_ENABLED=1
CDN_BASE_URL=https://static.example.mil/Armis2
```

Do not enable this until the CDN actually contains the matching files.

### Read replicas
Configure MySQL replication separately, then:

```text
DB_READ_REPLICAS=10.0.0.21,10.0.0.22
```

Only convert read-heavy query paths to `getReadDbConnection()` after confirming those queries do not require the latest write immediately.

### Load balancing
Place ARMIS behind a real load balancer/reverse proxy. Before enabling it, ensure:

- shared Redis/session infrastructure is available;
- uploads are on shared storage or an object-storage strategy is used;
- logs are centrally collected;
- health checks target `/Armis2/admin/health.php` or a dedicated non-authenticated infrastructure health endpoint;
- all application nodes run the same code/configuration.
