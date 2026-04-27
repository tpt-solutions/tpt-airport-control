# Flight Control System Installation Guide

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Quick Start Installation](#quick-start-installation)
3. [Detailed Installation](#detailed-installation)
4. [Database Setup](#database-setup)
5. [Configuration](#configuration)
6. [Security Setup](#security-setup)
7. [Testing Installation](#testing-installation)
8. [Troubleshooting](#troubleshooting)
9. [Production Deployment](#production-deployment)

## System Requirements

### Minimum Requirements
- **Operating System**: Linux (Ubuntu 18.04+, CentOS 7+, RHEL 7+), Windows Server 2016+, macOS 10.15+
- **CPU**: 4-core processor (2.4 GHz or higher)
- **RAM**: 8 GB minimum, 16 GB recommended
- **Storage**: 100 GB SSD minimum, 500 GB recommended
- **Network**: 100 Mbps minimum, 1 Gbps recommended

### Software Requirements
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: Version 8.0 or higher
- **Database**: PostgreSQL 12+ or MySQL 8.0+
- **Node.js**: Version 16+ (for frontend build)
- **Redis**: Version 6.0+ (for caching and sessions)
- **Composer**: Latest version (PHP dependency manager)

### Aviation-Specific Requirements
- **ADS-B Receiver**: Compatible with 1090 MHz frequency
- **Radar Data Feed**: ASTERIX format support
- **Weather Data Sources**: NOAA, aviation weather APIs
- **GPS/Time Synchronization**: NTP server access

## Quick Start Installation

### Using Docker (Recommended)
```bash
# Clone the repository
git clone https://github.com/your-org/flight-control-system.git
cd flight-control-system

# Start all services
docker-compose up -d

# Run database migrations
docker-compose exec backend php artisan migrate

# Seed initial data
docker-compose exec backend php artisan db:seed

# Build frontend assets
docker-compose exec frontend npm run build

# Access the application
# Frontend: http://localhost:3000
# Backend API: http://localhost:8000
# ATC Dashboard: http://localhost:3000/atc-dashboard
```

### Manual Installation
```bash
# Install system dependencies
sudo apt update
sudo apt install apache2 php8.1 postgresql redis-server

# Clone repository
git clone https://github.com/your-org/flight-control-system.git
cd flight-control-system

# Install PHP dependencies
cd backend
composer install

# Install Node.js dependencies
cd ../frontend
npm install

# Build frontend
npm run build

# Configure environment
cp backend/.env.example backend/.env
# Edit .env with your database and other settings

# Run database migrations
cd backend
php artisan migrate
php artisan db:seed
```

## Detailed Installation

### Step 1: System Preparation

#### Ubuntu/Debian
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y \
    apache2 \
    php8.1 \
    php8.1-cli \
    php8.1-fpm \
    php8.1-pgsql \
    php8.1-mysql \
    php8.1-redis \
    php8.1-curl \
    php8.1-gd \
    php8.1-mbstring \
    php8.1-xml \
    php8.1-zip \
    postgresql \
    postgresql-contrib \
    redis-server \
    nodejs \
    npm \
    git \
    curl \
    wget \
    unzip

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

#### CentOS/RHEL
```bash
# Update system
sudo yum update -y

# Install EPEL repository
sudo yum install -y epel-release

# Install required packages
sudo yum install -y \
    httpd \
    php \
    php-cli \
    php-fpm \
    php-pgsql \
    php-mysql \
    php-redis \
    php-curl \
    php-gd \
    php-mbstring \
    php-xml \
    php-zip \
    postgresql \
    postgresql-contrib \
    redis \
    nodejs \
    npm \
    git \
    curl \
    wget \
    unzip

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Step 2: Database Setup

#### PostgreSQL Configuration
```bash
# Create database and user
sudo -u postgres psql

# In PostgreSQL shell:
CREATE DATABASE flight_control;
CREATE USER flight_user WITH ENCRYPTED PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE flight_control TO flight_user;
ALTER USER flight_user CREATEDB;
\q

# Configure PostgreSQL for better performance
sudo nano /etc/postgresql/12/main/postgresql.conf

# Add these settings:
shared_buffers = 256MB
effective_cache_size = 1GB
work_mem = 4MB
maintenance_work_mem = 64MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100

# Restart PostgreSQL
sudo systemctl restart postgresql
```

#### Database Migration
```bash
# Navigate to backend directory
cd backend

# Copy environment file
cp .env.example .env

# Edit .env file with database credentials
nano .env

# Run migrations
php artisan migrate

# Seed initial data
php artisan db:seed
```

### Step 3: Web Server Configuration

#### Apache Configuration
```bash
# Enable required modules
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers

# Create virtual host
sudo nano /etc/apache2/sites-available/flight-control.conf

# Add this configuration:
<VirtualHost *:80>
    ServerName flight-control.local
    DocumentRoot /var/www/flight-control/frontend/dist

    <Directory /var/www/flight-control/frontend/dist>
        AllowOverride All
        Require all granted
    </Directory>

    # Proxy API requests to backend
    ProxyPass /api http://localhost:8000/api
    ProxyPassReverse /api http://localhost:8000/api

    # WebSocket proxy for real-time updates
    ProxyPass /ws ws://localhost:8080/
    ProxyPassReverse /ws ws://localhost:8080/
</VirtualHost>

# Enable site
sudo a2ensite flight-control.conf
sudo systemctl reload apache2
```

#### Nginx Configuration
```bash
# Create server block
sudo nano /etc/nginx/sites-available/flight-control

# Add this configuration:
server {
    listen 80;
    server_name flight-control.local;
    root /var/www/flight-control/frontend/dist;
    index index.html;

    # Handle frontend routes
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Proxy API requests
    location /api/ {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket proxy
    location /ws/ {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# Enable site
sudo ln -s /etc/nginx/sites-available/flight-control /etc/nginx/sites-enabled/
sudo systemctl reload nginx
```

### Step 4: Application Setup

#### Backend Setup
```bash
# Navigate to backend directory
cd /var/www/flight-control/backend

# Install dependencies
composer install --no-dev --optimize-autoloader

# Set proper permissions
sudo chown -R www-data:www-data /var/www/flight-control
sudo chmod -R 755 /var/www/flight-control/backend/storage
sudo chmod -R 755 /var/www/flight-control/backend/bootstrap/cache

# Generate application key
php artisan key:generate

# Create symbolic link for storage
php artisan storage:link
```

#### Frontend Setup
```bash
# Navigate to frontend directory
cd /var/www/flight-control/frontend

# Install dependencies
npm ci --only=production

# Build for production
npm run build

# Set proper permissions
sudo chown -R www-data:www-data /var/www/flight-control/frontend/dist
```

### Step 5: Aviation Data Integration Setup

#### ADS-B Receiver Setup
```bash
# Install dump1090 (ADS-B receiver)
sudo apt install -y rtl-sdr

# Clone and build dump1090
git clone https://github.com/antirez/dump1090.git
cd dump1090
make

# Configure dump1090
sudo nano /etc/default/dump1090-fa

# Set configuration:
RECEIVER_OPTIONS="--device-index 0 --gain -10 --ppm 0"
DECODER_OPTIONS="--max-range 360"
NET_OPTIONS="--net --net-http-port 8080"

# Start dump1090 service
sudo systemctl enable dump1090-fa
sudo systemctl start dump1090-fa
```

#### Weather Data Integration
```bash
# Install weather data processing tools
sudo apt install -y python3 python3-pip
pip3 install requests beautifulsoup4

# Configure weather API keys
cd /var/www/flight-control/integrations/weather-api-integration
cp config.example.json config.json
nano config.json

# Add your API keys:
{
  "noaa_api_key": "your_noaa_key",
  "aviation_weather_api_key": "your_aviation_weather_key",
  "openweather_api_key": "your_openweather_key"
}
```

## Configuration

### Environment Configuration
```bash
# Edit .env file
nano /var/www/flight-control/backend/.env

# Essential settings:
APP_NAME="Flight Control System"
APP_ENV=production
APP_KEY=base64:your_app_key_here
APP_DEBUG=false
APP_URL=http://flight-control.local

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flight_control
DB_USERNAME=flight_user
DB_PASSWORD=secure_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Aviation Data Sources
ADSB_HOST=localhost
ADSB_PORT=30003
RADAR_HOST=radar.example.com
RADAR_PORT=15000
WEATHER_API_KEY=your_weather_api_key

# Security
JWT_SECRET=your_jwt_secret_here
ENCRYPTION_KEY=your_encryption_key_here
```

### Security Configuration

#### SSL/TLS Setup
```bash
# Generate SSL certificate
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/flight-control.key \
    -out /etc/ssl/certs/flight-control.crt

# Configure Apache for SSL
sudo nano /etc/apache2/sites-available/flight-control-ssl.conf

# Add SSL configuration:
<VirtualHost *:443>
    ServerName flight-control.local
    DocumentRoot /var/www/flight-control/frontend/dist

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/flight-control.crt
    SSLCertificateKeyFile /etc/ssl/private/flight-control.key

    # ... rest of configuration
</VirtualHost>

# Enable SSL site
sudo a2ensite flight-control-ssl.conf
sudo systemctl reload apache2
```

#### Firewall Configuration
```bash
# Configure UFW firewall
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
sudo ufw allow 30003/tcp  # ADS-B data
sudo ufw allow 8080/tcp   # WebSocket
sudo ufw --force enable
```

### Performance Optimization

#### PHP Optimization
```bash
# Configure PHP-FPM
sudo nano /etc/php/8.1/fpm/php.ini

# Performance settings:
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
post_max_size = 50M
upload_max_filesize = 50M
max_file_uploads = 20

# OPcache settings:
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=7963
opcache.revalidate_freq=0

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

#### Database Optimization
```bash
# PostgreSQL performance tuning
sudo nano /etc/postgresql/12/main/postgresql.conf

# Add performance settings:
shared_buffers = 512MB
effective_cache_size = 2GB
work_mem = 8MB
maintenance_work_mem = 128MB
checkpoint_completion_target = 0.9
wal_buffers = 32MB
default_statistics_target = 500

# Restart PostgreSQL
sudo systemctl restart postgresql
```

## Security Setup

### User and Permissions
```bash
# Create dedicated system user
sudo useradd -r -s /bin/false flightcontrol

# Set proper ownership
sudo chown -R flightcontrol:flightcontrol /var/www/flight-control

# Secure file permissions
find /var/www/flight-control -type f -exec chmod 644 {} \;
find /var/www/flight-control -type d -exec chmod 755 {} \;
chmod 600 /var/www/flight-control/backend/.env
```

### Security Hardening
```bash
# Disable unnecessary services
sudo systemctl disable avahi-daemon
sudo systemctl disable cups

# Configure fail2ban
sudo apt install fail2ban
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local
sudo nano /etc/fail2ban/jail.local

# Add custom jails for flight control
[sshd]
enabled = true

[flight-control]
enabled = true
port = http,https
filter = flight-control
logpath = /var/log/apache2/access.log
maxretry = 3
bantime = 3600

# Restart fail2ban
sudo systemctl restart fail2ban
```

## Testing Installation

### Automated Tests
```bash
# Run backend tests
cd /var/www/flight-control/backend
./vendor/bin/phpunit

# Run frontend tests
cd /var/www/flight-control/frontend
npm test

# Run E2E tests
npx cypress run
```

### Manual Testing
1. **Access the application**
   - Frontend: http://flight-control.local
   - API: http://flight-control.local/api/health

2. **Test user registration and login**
   - Create a test user account
   - Verify login functionality
   - Test password reset

3. **Test flight booking**
   - Search for flights
   - Complete booking process
   - Verify booking confirmation

4. **Test ATC dashboard**
   - Access ATC dashboard
   - Verify real-time flight data
   - Test flight clearance issuance

5. **Test mobile responsiveness**
   - Access on mobile device
   - Test touch interactions
   - Verify PWA functionality

## Troubleshooting

### Common Issues

#### Database Connection Issues
```bash
# Check PostgreSQL status
sudo systemctl status postgresql

# Check database logs
sudo tail -f /var/log/postgresql/postgresql-12-main.log

# Test database connection
psql -h localhost -U flight_user -d flight_control
```

#### Web Server Issues
```bash
# Check Apache/Nginx status
sudo systemctl status apache2
sudo systemctl status nginx

# Check web server logs
sudo tail -f /var/log/apache2/error.log
sudo tail -f /var/log/nginx/error.log

# Test configuration
sudo apache2ctl configtest
sudo nginx -t
```

#### PHP Issues
```bash
# Check PHP-FPM status
sudo systemctl status php8.1-fpm

# Check PHP logs
sudo tail -f /var/log/php8.1-fpm.log

# Test PHP configuration
php -i | grep -i error
```

#### ADS-B Data Issues
```bash
# Check dump1090 status
sudo systemctl status dump1090-fa

# Check ADS-B data reception
curl http://localhost:8080/data.json

# Verify antenna connection
rtl_test -t 10
```

### Performance Issues
```bash
# Monitor system resources
top
htop
iotop

# Check database performance
sudo -u postgres psql -c "SELECT * FROM pg_stat_activity;"

# Monitor PHP performance
php-fpm-status
```

### Network Issues
```bash
# Check network connectivity
ping google.com

# Check firewall rules
sudo ufw status

# Check DNS resolution
nslookup flight-control.local

# Check SSL certificate
openssl s_client -connect flight-control.local:443
```

## Production Deployment

### Load Balancing Setup
```bash
# Install HAProxy
sudo apt install haproxy

# Configure HAProxy
sudo nano /etc/haproxy/haproxy.cfg

# Add frontend and backend configuration:
frontend http_front
    bind *:80
    default_backend http_back

backend http_back
    balance roundrobin
    server web1 192.168.1.101:80 check
    server web2 192.168.1.102:80 check

# Restart HAProxy
sudo systemctl restart haproxy
```

### Monitoring Setup
```bash
# Install monitoring tools
sudo apt install prometheus node-exporter

# Configure Prometheus
sudo nano /etc/prometheus/prometheus.yml

# Add scrape configs for flight control services
scrape_configs:
  - job_name: 'flight-control-backend'
    static_configs:
      - targets: ['localhost:8000']

  - job_name: 'flight-control-frontend'
    static_configs:
      - targets: ['localhost:3000']

# Install Grafana for visualization
sudo apt install grafana
sudo systemctl start grafana-server
```

### Backup Strategy
```bash
# Create backup script
sudo nano /usr/local/bin/flight-control-backup.sh

# Add backup commands:
#!/bin/bash
BACKUP_DIR="/var/backups/flight-control"
DATE=$(date +%Y%m%d_%H%M%S)

# Database backup
pg_dump -U flight_user flight_control > $BACKUP_DIR/db_$DATE.sql

# File system backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/flight-control

# Retention policy (keep last 30 days)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete

# Make executable
sudo chmod +x /usr/local/bin/flight-control-backup.sh

# Add to cron for daily backups
sudo crontab -e
# Add: 0 2 * * * /usr/local/bin/flight-control-backup.sh
```

### SSL Certificate (Let's Encrypt)
```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Obtain SSL certificate
sudo certbot --apache -d flight-control.local

# Set up auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

---

## Support and Resources

### Documentation
- **API Documentation**: `/docs/API_DOCUMENTATION.md`
- **User Manuals**: `/docs/USER_MANUAL_*.md`
- **Troubleshooting Guide**: `/docs/TROUBLESHOOTING.md`

### Support Channels
- **Email Support**: support@flightcontrol.com
- **Emergency Hotline**: 1-800-FLIGHT-HELP
- **Community Forum**: forum.flightcontrol.com
- **GitHub Issues**: github.com/your-org/flight-control-system/issues

### System Health Checks
```bash
# Quick health check script
curl -f http://localhost/api/health
curl -f http://localhost/api/adsb/status
curl -f http://localhost/api/database/status
```

---

*This installation guide is regularly updated. Please check for the latest version before installation.*
