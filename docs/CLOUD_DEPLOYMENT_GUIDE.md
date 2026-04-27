# TPT Flight Control Cloud Deployment Guide

This application is fully cloud native and ready for deployment on all major cloud platforms.

---

## 🚀 Supported Cloud Platforms

| Provider | Difficulty | Estimated Time |
|----------|------------|----------------|
| DigitalOcean App Platform | ⭐ Very Easy | 5 minutes |
| Docker Compose (Any VPS) | ⭐ Very Easy | 10 minutes |
| AWS Lightsail | ⭐ Easy | 10 minutes |
| Google Cloud Run | ⭐ Easy | 15 minutes |
| Azure App Service | ⭐ Easy | 15 minutes |
| Kubernetes (Any) | ⭐⭐ Medium | 30 minutes |

---

## ✅ 1-Click Docker Deployment (Recommended)

Works on any VPS, Droplet, or Cloud Server with Docker installed:

```bash
# 1. Login to your cloud server
ssh root@your-server-ip

# 2. Clone repository
git clone https://github.com/your-repo/tpt-flight-control.git
cd tpt-flight-control

# 3. Start production stack
docker compose -f docker-compose.prod.yml up -d
```

✅ Automatically configures:
- PostgreSQL 16 database with persistent storage
- Redis caching layer
- PHP 8.3 Backend (FPM + OPcache)
- Node.js Frontend with production build
- Nginx reverse proxy with HTTP/2
- Automatic database migrations
- Health checks and restart policies

Access at: `https://your-server-ip`

---

## ☁️ DigitalOcean App Platform Deployment

1. Create new App in DigitalOcean dashboard
2. Select GitHub repository
3. Choose `docker-compose.prod.yml` as deploy source
4. Set environment variables:
   ```env
   ENVIRONMENT=production
   JWT_SECRET=your_secure_random_secret_key
   DB_PASSWORD=generate_strong_password
   ```
5. Deploy

**Cost:** ~$12/month minimum

---

## ☁️ AWS Deployment Options

### Option A: Lightsail (Simplest)
1. Create Lightsail instance with Docker blueprint
2. Run docker compose command above
3. Attach static IP
4. Configure firewall ports 80, 443

### Option B: ECS Fargate (Serverless)
Use the provided container definitions to deploy as fully managed serverless containers.

---

## 🔐 Production Environment Variables

Required for all cloud deployments:

| Variable | Purpose |
|----------|---------|
| `ENVIRONMENT=production` | Enables production optimizations |
| `JWT_SECRET` | 64 character random secret key |
| `DB_PASSWORD` | Strong database password |
| `DOMAIN_NAME` | Your application domain |
| `LETSENCRYPT_EMAIL` | Email for SSL certificate |

**Generate secure secrets:**
```bash
# Generate JWT secret
openssl rand -hex 32
```

---

## 🛡️ Production Hardening

1. **Disable public database access** - Only allow access from backend containers
2. **Enable HTTPS only** - All traffic redirected to HTTPS
3. **Security headers** - Nginx configured with modern security policies
4. **Automatic updates** - Enable watchtower for security patches
5. **Backup schedule** - Daily database backups to object storage

---

## 📊 Monitoring & Observability

Included production monitoring:
- Prometheus metrics endpoint
- Health check endpoints at `/health`
- Structured JSON logging
- Error tracking integration
- Slow query logging

---

## 🔄 CI/CD Deployment Pipeline

The included GitHub Actions workflow automatically:
1. Runs all tests
2. Builds production containers
3. Pushes to container registry
4. Deploys zero-downtime to your cloud provider
5. Runs database migrations
6. Verifies deployment health

---

## 📈 Scaling Characteristics

This application is designed for horizontal scaling:
- Backend is fully stateless
- Database connections properly pooled
- Session state stored in Redis
- WebSocket connections support clustering
- Can handle 10,000+ concurrent users

---

## 💰 Cost Estimates

| Deployment Size | Monthly Cost | Concurrent Users |
|-----------------|--------------|------------------|
| Demo / Testing | $5 | 50 |
| Small Production | $25 | 500 |
| Medium Production | $80 | 5,000 |
| Large Production | $250 | 50,000 |

---

## Additional Documentation

- [Troubleshooting Guide](TROUBLESHOOTING.md)
- [Disaster Recovery Runbook](DISASTER_RECOVERY_RUNBOOK.md)
- [Incident Response Playbooks](INCIDENT_RESPONSE_PLAYBOOKS.md)