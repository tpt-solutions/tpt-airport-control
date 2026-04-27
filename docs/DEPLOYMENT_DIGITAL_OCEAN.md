# Flight Control System - Digital Ocean Deployment Guide

## Overview
This guide provides step-by-step instructions for deploying the Flight Control System on Digital Ocean. The deployment includes all components: PostgreSQL database, Redis cache, backend API, frontend application, and monitoring stack.

## Prerequisites
- Digital Ocean account
- SSH key pair
- Domain name (optional but recommended)

## 🚀 Quick Deployment (Marketplace - Coming Soon)

### Option 1: One-Click Marketplace (Recommended)
1. Visit [Digital Ocean Marketplace](https://marketplace.digitalocean.com/)
2. Search for "Flight Control System"
3. Click "Create Flight Control Droplet"
4. Configure:
   - **Plan**: At least 2GB RAM ($12/month)
   - **Region**: Choose closest to your users
   - **Authentication**: Add your SSH key
5. Click "Create Droplet"
6. Access your application at the provided IP address

### Option 2: Manual Deployment

## Step 1: Create Droplet

### Basic Configuration
```bash
# Choose these specifications:
- Image: Ubuntu 22.04 LTS
- Plan: Basic plan with 2GB RAM ($12/month)
- Region: Choose based on your target audience
- Authentication: SSH keys (recommended)
- Hostname: flight-control-demo
```

### Advanced Configuration
- **Monitoring**: Enable
- **Backups**: Enable (additional $1/month)
- **Tags**: `flight-control`, `demo`

## Step 2: Initial Server Setup

### Connect to your droplet
```bash
ssh root@YOUR_DROPLET_IP
```

### Update system and install dependencies
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install essential packages
sudo apt install -y curl wget git unzip software-properties-common

# Install PHP 8.1 and extensions
sudo apt install -y php8.1 php8.1-cli php8.1-fpm php8.1-pgsql php8.1-redis php8.1-curl php8.1-zip php8.1-mbstring php8.1-xml php8.1-gd

# Install Node.js 18
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install PostgreSQL
sudo apt install -y postgresql postgresql-contrib

# Install Redis
sudo apt install -y redis-server

# Install Nginx
sudo apt install -y nginx

# Install Certbot (for SSL)
sudo apt install -y certbot python3-certbot-nginx

# Install Docker (optional, for containerized deployment)
sudo apt install -y docker.io docker-compose
sudo systemctl enable docker
sudo systemctl start docker
```

## Step 3: Database Setup

### Configure PostgreSQL
```bash
# Switch to postgres user
sudo -u postgres psql

# Create database and user
CREATE DATABASE flight_control;
CREATE USER flight_user WITH ENCRYPTED PASSWORD 'your_secure_password_here';
GRANT ALL PRIVILEGES ON DATABASE flight_control TO flight_user;
ALTER USER flight_user CREATEDB;
\q
```

### Configure PostgreSQL for remote access (optional)
```bash
# Edit postgresql.conf
sudo nano /etc/postgresql/14/main/postgresql.conf
# Change: listen_addresses = '*'

# Edit pg_hba.conf
sudo nano /etc/postgresql/14/main/pg_hba.conf
# Add: host    flight_control    flight_user    0.0.0.0/0    md5

# Restart PostgreSQL
sudo systemctl restart postgresql
```

## Step 4: Application Deployment

### Clone the repository
```bash
cd /var/www
git clone https://github.com/yourusername/flight-control-system.git
cd flight-control-system
```

### Backend Setup
```bash
cd backend

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Copy environment file
cp .env.example .env

# Edit environment configuration
nano .env
```

**Update these values in `.env`:**
```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your_app_key_here

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=flight_control
DB_USERNAME=flight_user
DB_PASSWORD=your_secure_password_here

REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=

JWT_SECRET=your_jwt_secret_here
```

### Database Migration
```bash
# Run database migrations
php artisan migrate --seed

# Generate demo data
cd ../database
php demo-data-generator.php run
```

### Frontend Setup
```bash
cd ../frontend

# Install dependencies
npm install

# Build for production
npm run build
```

## Step 5: Web Server Configuration

### Configure Nginx
```bash
# Create Nginx configuration
sudo nano /etc/nginx/sites-available/flight-control
```

**Add this configuration:**
```nginx
server {
    listen 80;
    server_name your_domain.com www.your_domain.com;
    root /var/www/flight-control-system/frontend/dist;
    index index.html;

    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

    # API proxy
    location /api/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket proxy
    location /ws {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Static files
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### Enable the site
```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/flight-control /etc/nginx/sites-enabled/

# Remove default site
sudo rm /etc/nginx/sites-enabled/default

# Test configuration
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

## Step 6: SSL Certificate Setup

### Using Certbot (Let's Encrypt)
```bash
# Obtain SSL certificate
sudo certbot --nginx -d your_domain.com -d www.your_domain.com

# Test renewal
sudo certbot renew --dry-run
```

### Manual SSL Setup (if not using Certbot)
```bash
# Create SSL directory
sudo mkdir -p /etc/nginx/ssl

# Upload your SSL certificates
# /etc/nginx/ssl/fullchain.pem
# /etc/nginx/ssl/privkey.pem

# Update Nginx configuration to use SSL
sudo nano /etc/nginx/sites-available/flight-control
```

**Update server block:**
```nginx
server {
    listen 443 ssl http2;
    server_name your_domain.com www.your_domain.com;

    ssl_certificate /etc/nginx/ssl/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/privkey.pem;

    # SSL Configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # ... rest of configuration
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name your_domain.com www.your_domain.com;
    return 301 https://$server_name$request_uri;
}
```

## Step 7: Process Management

### Using SystemD for Backend
```bash
# Create systemd service for backend
sudo nano /etc/systemd/system/flight-control-backend.service
```

**Add service configuration:**
```ini
[Unit]
Description=Flight Control Backend
After=network.target postgresql.service redis-server.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/flight-control-system/backend
ExecStart=/usr/bin/php -S 127.0.0.1:8000 -t public
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### Using SystemD for WebSocket
```bash
# Create systemd service for WebSocket
sudo nano /etc/systemd/system/flight-control-websocket.service
```

**Add service configuration:**
```ini
[Unit]
Description=Flight Control WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/flight-control-system/backend
ExecStart=/usr/bin/php websocket-server.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### Enable and start services
```bash
# Enable services
sudo systemctl enable flight-control-backend
sudo systemctl enable flight-control-websocket
sudo systemctl enable nginx
sudo systemctl enable postgresql
sudo systemctl enable redis-server

# Start services
sudo systemctl start flight-control-backend
sudo systemctl start flight-control-websocket
```

## Step 8: Firewall Configuration

### Configure UFW
```bash
# Enable UFW
sudo ufw enable

# Allow SSH
sudo ufw allow ssh

# Allow HTTP and HTTPS
sudo ufw allow 80
sudo ufw allow 443

# Allow PostgreSQL (if remote access needed)
# sudo ufw allow 5432

# Check status
sudo ufw status
```

## Step 9: Monitoring Setup

### Install Prometheus and Grafana
```bash
# Install Prometheus
sudo apt install -y prometheus prometheus-node-exporter

# Install Grafana
sudo apt install -y apt-transport-https
sudo apt install -y software-properties-common wget
wget -q -O - https://packages.grafana.com/gpg.key | sudo apt-key add -
echo "deb https://packages.grafana.com/oss/deb stable main" | sudo tee -a /etc/apt/sources.list.d/grafana.list
sudo apt update
sudo apt install -y grafana

# Start services
sudo systemctl enable grafana-server
sudo systemctl start grafana-server
sudo systemctl enable prometheus
sudo systemctl start prometheus
```

## Step 10: Backup Configuration

### Automated Backups
```bash
# Create backup script
sudo nano /usr/local/bin/flight-control-backup.sh
```

**Add backup script:**
```bash
#!/bin/bash

# Flight Control System Backup Script
BACKUP_DIR="/var/backups/flight-control"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
pg_dump -U flight_user -h localhost flight_control > $BACKUP_DIR/db_backup_$DATE.sql

# Application files backup
tar -czf $BACKUP_DIR/app_backup_$DATE.tar.gz -C /var/www flight-control-system

# Keep only last 7 days of backups
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
```

### Setup cron job
```bash
# Make script executable
sudo chmod +x /usr/local/bin/flight-control-backup.sh

# Add to crontab (daily at 2 AM)
sudo crontab -e
# Add: 0 2 * * * /usr/local/bin/flight-control-backup.sh
```

## Step 11: Security Hardening

### SSH Hardening
```bash
# Edit SSH configuration
sudo nano /etc/ssh/sshd_config

# Recommended changes:
# Port 22 (consider changing to non-standard port)
# PermitRootLogin no
# PasswordAuthentication no
# X11Forwarding no
# AllowUsers your_username

# Restart SSH
sudo systemctl restart ssh
```

### Fail2Ban Setup
```bash
# Install Fail2Ban
sudo apt install -y fail2ban

# Configure for SSH
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local
sudo nano /etc/fail2ban/jail.local

# Enable SSH protection
# [sshd]
# enabled = true

# Restart Fail2Ban
sudo systemctl restart fail2ban
```

## Step 12: Performance Optimization

### PHP Optimization
```bash
# Edit PHP-FPM configuration
sudo nano /etc/php/8.1/fpm/php.ini

# Recommended settings:
# memory_limit = 256M
# max_execution_time = 300
# upload_max_filesize = 50M
# post_max_size = 50M

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

### Nginx Optimization
```bash
# Edit Nginx configuration
sudo nano /etc/nginx/nginx.conf

# Add in http block:
# worker_processes auto;
# worker_connections 1024;
# keepalive_timeout 65;

# Restart Nginx
sudo systemctl restart nginx
```

## Step 13: Access Your Application

### Demo Credentials
- **Administrator**: admin / admin123
- **Controller**: controller1 / controller123
- **Operator**: operator1 / operator123
- **Passengers**: passenger1-50 / pass123

### URLs
- **Application**: https://your_domain.com
- **API**: https://your_domain.com/api/
- **WebSocket**: wss://your_domain.com/ws

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   ```bash
   # Check PostgreSQL status
   sudo systemctl status postgresql

   # Check logs
   sudo tail -f /var/log/postgresql/postgresql-14-main.log
   ```

2. **PHP Errors**
   ```bash
   # Check PHP-FPM status
   sudo systemctl status php8.1-fpm

   # Check logs
   sudo tail -f /var/log/php8.1-fpm.log
   ```

3. **Nginx Issues**
   ```bash
   # Test configuration
   sudo nginx -t

   # Check logs
   sudo tail -f /var/log/nginx/error.log
   ```

4. **SSL Certificate Issues**
   ```bash
   # Check certificate validity
   openssl x509 -in /etc/nginx/ssl/fullchain.pem -text -noout

   # Renew certificate
   sudo certbot renew
   ```

## Monitoring & Maintenance

### Log Monitoring
```bash
# Application logs
tail -f /var/www/flight-control-system/backend/logs/app.log

# Nginx access logs
tail -f /var/log/nginx/access.log

# System monitoring
htop
df -h
free -h
```

### Update Procedure
```bash
# Stop services
sudo systemctl stop flight-control-backend flight-control-websocket

# Update code
cd /var/www/flight-control-system
git pull

# Update dependencies
cd backend && composer install --no-dev
cd ../frontend && npm install && npm run build

# Run migrations if needed
php artisan migrate

# Start services
sudo systemctl start flight-control-backend flight-control-websocket
```

## Cost Estimation

### Monthly Costs (Approximate)
- **Droplet**: $12 (2GB RAM)
- **Backups**: $1
- **Domain**: $10-20
- **SSL Certificate**: Free (Let's Encrypt)
- **Total**: ~$23-33/month

### Scaling Options
- **4GB RAM**: $24/month (better performance)
- **Load Balancer**: $10/month (for high traffic)
- **Managed Database**: $15-50/month (optional)

## Support

For issues or questions:
- Check the logs in `/var/log/`
- Review application logs in `/var/www/flight-control-system/backend/logs/`
- Contact support at support@flightcontrol.demo

---

**🎉 Your Flight Control System is now live on Digital Ocean!**

Access your demo at: https://your_domain.com
