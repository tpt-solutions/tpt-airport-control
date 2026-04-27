# TPT Flight Control Troubleshooting Guide

## Common Errors and Solutions

---

### ❌ Login 500 Internal Server Error
**Symptoms:**
```
auth.ts:86 Login error: SyntaxError: Failed to execute 'json' on 'Response': Unexpected end of JSON input
```

**Root Cause:**
Backend database connection failure returning empty response instead of valid JSON.

**Fixes:**

1. **Check PHP Extensions:**
   ```
   php -m
   ```
   Verify `pdo_pgsql` or `pdo_sqlite` are listed.

2. **Database Connection Fallback Order:**
   The auth system automatically tries:
   1. ✅ SQLite database: `database/flight_control_demo.db`
   2. ✅ PostgreSQL at localhost:5432
   3. ❌ Returns error with message if neither works

3. **Enable PHP Error Logging:**
   Check `php.ini` for:
   ```ini
   log_errors = On
   error_log = "php_errors.log"
   display_errors = Off
   ```

4. **Test Connection Manually:**
   ```bash
   cd backend
   php -r "require 'api/auth.php'; echo 'Connection working';"
   ```

---

### ❌ Manifest Icon Download Error
**Symptoms:**
```
Error while trying to use the following icon from the Manifest: http://localhost:5173/icon-144.png
```

**Root Cause:**
PWA manifest references icon files that do not exist in public directory.

**Fixes:**
- This is a cosmetic warning only
- Application functions normally
- Does not affect login or system functionality
- Icons will be added in future releases

---

### ❌ CORS Policy Errors
**Symptoms:**
```
Access to fetch at 'http://localhost:8000/api/auth.php' from origin 'http://localhost:5173' has been blocked by CORS policy
```

**Fixes:**
1. Backend already has CORS headers enabled:
   ```php
   header('Access-Control-Allow-Origin: *');
   header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
   ```
   
2. Ensure preflight OPTIONS requests are not being blocked by web server

3. For production set specific allowed origin instead of wildcard

---

### ❌ Service Worker Registration
**Symptoms:**
```
SW registered: ServiceWorkerRegistration
```

**This is normal and expected behaviour.**
- Service worker provides offline capabilities
- Caches core application resources
- Enables PWA functionality

---

### ❌ Database Missing Error
**Symptoms:**
```
SQLSTATE[HY000] [1049] Unknown database 'flight_control'
```

**Fixes:**
1. Run database setup script:
   ```bash
   cd database
   setup.bat
   ```

2. Or use Docker demo:
   ```bash
   start-demo-docker.bat
   ```

---

### ❌ PostgreSQL Connection Refused
**Symptoms:**
```
SQLSTATE[08006] [7] could not connect to server: Connection refused
```

**Fixes:**
1. Verify PostgreSQL service is running
2. Check PostgreSQL is listening on port 5432
3. Verify database credentials match setup
4. Use SQLite demo mode instead: `start-simple-demo.bat`

---

## Log Locations

| Component | Log File Location |
|-----------|-------------------|
| PHP Errors | `backend/php_errors.log` |
| Application Logs | `backend/logs/app.log` |
| Access Logs | `backend/logs/access.log` |
| Frontend Logs | Browser Developer Console |
| Docker Logs | `docker logs flight-control-backend` |

---

## Support

For additional support:
1. Review the [Setup Guide](SETUP_GUIDE.md)
2. Check open issues on GitHub
3. Refer to [Incident Response Playbooks](INCIDENT_RESPONSE_PLAYBOOKS.md) for production issues