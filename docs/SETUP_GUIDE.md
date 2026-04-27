# TPT Flight Control Setup Guide

## Quick Start Options

### ✅ Option 1: 1-Click Docker Demo (Recommended)
No dependencies required, everything runs in containers:

```bash
start-demo-docker.bat
```

* Runs full system with PostgreSQL, Redis, NGINX, Backend, Frontend
* Automatically seeds database with demo data
* Access at: http://localhost:5173
* Default login: `admin / admin123`

---

### ✅ Option 2: Simple Demo (SQLite Zero Setup)
Requires only PHP 8.1+ and Node.js 18+:

```bash
# 1. Start backend server
cd backend
php -S localhost:8000

# 2. Start frontend development server
cd frontend
npm install
npm run dev

# 3. Access application at http://localhost:5173
```

* Uses embedded SQLite database
* No external database required
* Pre-configured with demo users and scenarios

---

### ✅ Option 3: Standard Production Installation
For production deployments with PostgreSQL.

#### Prerequisites:
- PHP 8.2+ with PDO PostgreSQL extension
- PostgreSQL 14+
- Node.js 20+
- Redis 7+ (optional for caching)

#### Step 1: Database Setup
```bash
cd database
setup.bat
```

Creates database, runs schemas and seeds initial data:
* Database: `flight_control`
* User: `flight_user`
* Password: `flight_pass_2025`

#### Step 2: Backend Configuration
```bash
cd backend
composer install
# Start PHP server
php -S localhost:8000
```

#### Step 3: Frontend Setup
```bash
cd frontend
npm install
npm run build
npm run preview
```

---

## Default Credentials

| Role         | Username | Password      |
|--------------|----------|---------------|
| Administrator| admin    | admin123      |
| ATC Controller | controller | atc2026    |
| Airport Manager | manager | manager123   |
| Demo User    | demo     | demo123       |

---

## Troubleshooting

### Common Issues:

1. **500 Internal Server Error on Login**
   - Verify PHP PDO PostgreSQL driver is enabled
   - Check database credentials match `database/setup.bat`
   - Ensure PostgreSQL service is running
   - System will automatically fall back to SQLite if available

2. **Database Connection Failed**
   - The auth endpoint automatically tries:
     1. SQLite database at `database/flight_control_demo.db`
     2. PostgreSQL at localhost:5432
   - Ensure either database driver is available

3. **CORS Errors**
   - Backend is configured with wide CORS for development
   - For production update allowed origins in API files

---

## Environment Variables

Create `.env` file in root directory:
```env
ENVIRONMENT=development
DB_HOST=localhost
DB_NAME=flight_control
DB_USERNAME=flight_user
DB_PASSWORD=flight_pass_2025
JWT_SECRET=your_secure_jwt_secret_here
REDIS_HOST=localhost
```

---

## Additional Documentation

- [Developer Guide](DEVELOPER_GUIDE_MODULE_DEVELOPMENT.md)
- [API Documentation](API_DOCUMENTATION.md)
- [Incident Response Playbooks](INCIDENT_RESPONSE_PLAYBOOKS.md)
- [Disaster Recovery Runbook](DISASTER_RECOVERY_RUNBOOK.md)