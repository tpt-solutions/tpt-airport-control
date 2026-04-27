# Module Installation and Configuration Guide

## 📋 Overview

This guide provides comprehensive instructions for installing, configuring, and deploying the Flight Control System with all optional modules. The system is designed with a modular architecture that allows selective activation of features based on operational requirements.

## 🎯 System Requirements

### Minimum Hardware Requirements
```
CPU: 4-core processor (2.4 GHz or higher)
RAM: 8 GB minimum, 16 GB recommended
Storage: 100 GB SSD minimum, 500 GB recommended
Network: 1 Gbps Ethernet connection
```

### Recommended Hardware Requirements
```
CPU: 8-core processor (3.0 GHz or higher)
RAM: 32 GB or higher
Storage: 1 TB NVMe SSD
Network: 10 Gbps Ethernet connection
Backup Storage: 2 TB for data retention
```

### Software Prerequisites
- **Operating System**: Ubuntu 20.04 LTS or CentOS 8+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Database**: PostgreSQL 13+ or MySQL 8.0+
- **PHP**: Version 8.1+ with required extensions
- **Node.js**: Version 16+ for frontend assets
- **Redis**: Version 6+ for caching and sessions

## 🚀 Installation Process

### Phase 1: Base System Installation

#### 1.1 Environment Preparation
```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install essential packages
sudo apt install -y curl wget git unzip software-properties-common

# Install PHP and required extensions
sudo apt install -y php8.1 php8.1-cli php8.1-fpm php8.1-mysql php8.1-pgsql \
    php8.1-sqlite3 php8.1-redis php8.1-memcached php8.1-xml php8.1-curl \
    php8.1-mbstring php8.1-zip php8.1-bcmath php8.1-gd

# Install Node.js and npm
curl -fsSL https://deb.nodesource.com/setup_16.x | sudo -E bash -
sudo apt install -y nodejs

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

#### 1.2 Database Setup
```bash
# Install PostgreSQL
sudo apt install -y postgresql postgresql-contrib

# Create database and user
sudo -u postgres psql
```

```sql
-- Create database and user
CREATE DATABASE flight_control;
CREATE USER flight_user WITH ENCRYPTED PASSWORD 'secure_password_here';
GRANT ALL PRIVILEGES ON DATABASE flight_control TO flight_user;
ALTER USER flight_user CREATEDB;

-- Create extensions
\c flight_control;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_stat_statements";
CREATE EXTENSION IF NOT EXISTS "pg_buffercache";
\q
```

#### 1.3 Redis Setup
```bash
# Install Redis
sudo apt install -y redis-server

# Configure Redis
sudo sed -i 's/supervised no/supervised systemd/' /etc/redis/redis.conf
sudo sed -i 's/# requirepass foobared/requirepass your_secure_redis_password/' /etc/redis/redis.conf

# Start Redis service
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

#### 1.4 Web Server Configuration
```bash
# Install Nginx
sudo apt install -y nginx

# Create site configuration
sudo tee /etc/nginx/sites-available/flight-control << EOF
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/flight-control;

    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
}
EOF

# Enable site
sudo ln -s /etc/nginx/sites-available/flight-control /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Phase 2: Application Deployment

#### 2.1 Code Deployment
```bash
# Create application directory
sudo mkdir -p /var/www/flight-control
sudo chown -R www-data:www-data /var/www/flight-control

# Clone repository
cd /var/www/flight-control
sudo -u www-data git clone https://github.com/your-org/flight-control-system.git .

# Install PHP dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# Install Node.js dependencies
sudo -u www-data npm install
sudo -u www-data npm run build
```

#### 2.2 Environment Configuration
```bash
# Copy environment file
sudo -u www-data cp .env.example .env.production

# Edit environment configuration
sudo -u www-data nano .env.production
```

```env
# Application
APP_NAME="Flight Control System"
APP_ENV=production
APP_KEY=base64:your_app_key_here
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flight_control
DB_USERNAME=flight_user
DB_PASSWORD=your_secure_db_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your_secure_redis_password
REDIS_PORT=6379

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host.com
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@domain.com
MAIL_FROM_NAME="Flight Control System"

# Module Configuration
MODULE_INFRASTRUCTURE_ENABLED=true
MODULE_CARGO_ENABLED=true
MODULE_EMERGENCY_ENABLED=true
MODULE_SUSTAINABILITY_ENABLED=true
MODULE_COMMERCIAL_ENABLED=false
MODULE_SPECIAL_SERVICES_ENABLED=true
MODULE_ADVANCED_ANALYTICS_ENABLED=true
MODULE_DRONES_ENABLED=false
MODULE_CUSTOMS_ENABLED=true
MODULE_ADVANCED_SECURITY_ENABLED=true
MODULE_VIRTUAL_ASSISTANT_ENABLED=true
```

#### 2.3 Database Migration
```bash
# Generate application key
sudo -u www-data php artisan key:generate

# Run database migrations
sudo -u www-data php artisan migrate --seed

# Create storage link
sudo -u www-data php artisan storage:link

# Set proper permissions
sudo chown -R www-data:www-data /var/www/flight-control
sudo chmod -R 755 /var/www/flight-control
sudo chmod -R 775 /var/www/flight-control/storage
```

### Phase 3: Module-Specific Installation

#### 3.1 Infrastructure Management Module
```bash
# Install IoT sensor dependencies
sudo apt install -y mosquitto mosquitto-clients

# Configure MQTT broker
sudo tee /etc/mosquitto/conf.d/flight-control.conf << EOF
listener 1883
allow_anonymous false
password_file /etc/mosquitto/passwd
EOF

# Create MQTT user
sudo mosquitto_passwd -c /etc/mosquitto/passwd iot_sensor
sudo systemctl enable mosquitto
sudo systemctl start mosquitto

# Install building automation integration
sudo -u www-data composer require "building-automation/sdk"
```

#### 3.2 Advanced Security Module
```bash
# Install computer vision dependencies
sudo apt install -y python3-opencv python3-pillow python3-numpy

# Install facial recognition libraries
sudo pip3 install face-recognition dlib

# Configure security camera integration
sudo mkdir -p /var/lib/flight-control/security
sudo chown www-data:www-data /var/lib/flight-control/security
```

#### 3.3 Drone Operations Module
```bash
# Install drone communication libraries
sudo apt install -y rtl-sdr

# Configure drone traffic management
sudo tee /etc/systemd/system/drone-traffic.service << EOF
[Unit]
Description=Drone Traffic Management Service
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/flight-control
ExecStart=/usr/bin/php artisan drone:traffic-monitor
Restart=always

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl enable drone-traffic
```

#### 3.4 Virtual Assistant Module
```bash
# Install speech processing dependencies
sudo apt install -y sox libsox-fmt-all ffmpeg

# Install Python NLP libraries
sudo pip3 install speechrecognition pyttsx3 nltk spacy

# Download language models
sudo python3 -m spacy download en_core_web_sm
sudo python3 -c "import nltk; nltk.download('punkt'); nltk.download('averaged_perceptron_tagger')"
```

### Phase 4: SSL/TLS Configuration

#### 4.1 Let's Encrypt SSL Certificate
```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d your-domain.com

# Configure automatic renewal
sudo crontab -e
# Add this line:
# 0 12 * * * /usr/bin/certbot renew --quiet
```

#### 4.2 Manual SSL Configuration
```bash
# Create SSL directory
sudo mkdir -p /etc/ssl/flight-control

# Place your SSL certificates
sudo cp your-domain.crt /etc/ssl/flight-control/
sudo cp your-domain.key /etc/ssl/flight-control/

# Update Nginx configuration
sudo tee /etc/nginx/sites-available/flight-control-ssl << EOF
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    ssl_certificate /etc/ssl/flight-control/your-domain.crt;
    ssl_certificate_key /etc/ssl/flight-control/your-domain.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    root /var/www/flight-control;
    index index.php index.html;

    # ... rest of configuration
}
EOF
```

### Phase 5: Monitoring and Logging Setup

#### 5.1 Prometheus Monitoring
```bash
# Install Prometheus
sudo useradd --no-create-home --shell /bin/false prometheus
sudo mkdir /etc/prometheus
sudo mkdir /var/lib/prometheus

# Download and extract Prometheus
cd /tmp
wget https://github.com/prometheus/prometheus/releases/download/v2.30.0/prometheus-2.30.0.linux-amd64.tar.gz
tar xvf prometheus-2.30.0.linux-amd64.tar.gz
sudo cp prometheus-2.30.0.linux-amd64/prometheus /usr/local/bin/
sudo cp prometheus-2.30.0.linux-amd64/promtool /usr/local/bin/

# Create Prometheus configuration
sudo tee /etc/prometheus/prometheus.yml << EOF
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'flight-control'
    static_configs:
      - targets: ['localhost:8000']
EOF

# Create systemd service
sudo tee /etc/systemd/system/prometheus.service << EOF
[Unit]
Description=Prometheus
Wants=network-online.target
After=network-online.target

[Service]
User=prometheus
Group=prometheus
Type=simple
ExecStart=/usr/local/bin/prometheus \
    --config.file /etc/prometheus/prometheus.yml \
    --storage.tsdb.path /var/lib/prometheus/ \
    --web.console.templates=/etc/prometheus/consoles \
    --web.console.libraries=/etc/prometheus/console_libraries

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl enable prometheus
sudo systemctl start prometheus
```

#### 5.2 Grafana Dashboard
```bash
# Install Grafana
sudo apt install -y apt-transport-https
sudo apt install -y software-properties-common wget
wget -q -O - https://packages.grafana.com/gpg.key | sudo apt-key add -
echo "deb https://packages.grafana.com/oss/deb stable main" | sudo tee -a /etc/apt/sources.list.d/grafana.list
sudo apt update
sudo apt install -y grafana

# Start Grafana
sudo systemctl enable grafana-server
sudo systemctl start grafana-server
```

#### 5.3 Log Aggregation
```bash
# Install ELK Stack (Elasticsearch, Logstash, Kibana)
# Note: This is a complex installation - refer to official documentation

# Configure application logging
sudo tee /var/www/flight-control/config/logging.php << EOF
<?php
return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'daily', 'syslog'],
        ],
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
        ],
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'days' => 30,
        ],
        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],
    ],
];
EOF
```

## ⚙️ Module Configuration

### Core Module Configuration

#### Infrastructure Management
```json
{
  "infrastructure": {
    "enabled": true,
    "sensors": {
      "temperature_threshold": 25,
      "humidity_threshold": 60,
      "vibration_threshold": 2.5
    },
    "maintenance": {
      "predictive_enabled": true,
      "auto_scheduling": true,
      "notification_channels": ["email", "sms"]
    },
    "energy": {
      "optimization_enabled": true,
      "peak_demand_management": true,
      "renewable_tracking": true
    }
  }
}
```

#### Advanced Security
```json
{
  "advanced_security": {
    "enabled": true,
    "facial_recognition": {
      "enabled": true,
      "confidence_threshold": 0.85,
      "database_path": "/var/lib/flight-control/security/faces"
    },
    "behavioral_analytics": {
      "enabled": true,
      "anomaly_detection": true,
      "alert_threshold": 0.95
    },
    "threat_detection": {
      "real_time_scanning": true,
      "integration_apis": ["customs", "emergency"]
    }
  }
}
```

#### Virtual Assistant
```json
{
  "virtual_assistant": {
    "enabled": true,
    "nlp_engine": "spacy",
    "voice_recognition": {
      "enabled": true,
      "language": "en-US",
      "wake_word": "flight control"
    },
    "conversation_flow": {
      "max_turns": 10,
      "timeout_seconds": 300,
      "fallback_responses": true
    }
  }
}
```

### Optional Module Configuration

#### Cargo Operations
```json
{
  "cargo": {
    "enabled": true,
    "temperature_monitoring": {
      "enabled": true,
      "critical_threshold": 4,
      "warning_threshold": 8
    },
    "customs_integration": {
      "enabled": true,
      "api_endpoint": "https://customs-api.example.com",
      "auto_clearance": true
    }
  }
}
```

#### Drone Operations
```json
{
  "drones": {
    "enabled": true,
    "traffic_management": {
      "enabled": true,
      "faa_integration": true,
      "no_fly_zones": ["restricted_area_1", "restricted_area_2"]
    },
    "communication": {
      "frequency_bands": ["2.4GHz", "5.8GHz"],
      "encryption": "AES256",
      "backup_channels": true
    }
  }
}
```

## 🔐 Security Configuration

### Firewall Setup
```bash
# Install UFW
sudo apt install -y ufw

# Configure firewall rules
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw allow 5432  # PostgreSQL
sudo ufw allow 6379  # Redis (restrict to local)
sudo ufw allow 1883  # MQTT
sudo ufw --force enable
```

### SSL/TLS Configuration
```bash
# Generate strong Diffie-Hellman parameters
sudo openssl dhparam -out /etc/ssl/certs/dhparam.pem 2048

# Configure SSL session cache
sudo tee -a /etc/nginx/sites-available/flight-control << EOF
# SSL Configuration
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 10m;
ssl_dhparam /etc/ssl/certs/dhparam.pem;
EOF
```

### Database Security
```sql
-- Create read-only user for reporting
CREATE USER reporting_user WITH ENCRYPTED PASSWORD 'secure_reporting_password';
GRANT CONNECT ON DATABASE flight_control TO reporting_user;
GRANT USAGE ON SCHEMA public TO reporting_user;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO reporting_user;

-- Create backup user
CREATE USER backup_user WITH ENCRYPTED PASSWORD 'secure_backup_password';
GRANT CONNECT ON DATABASE flight_control TO backup_user;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO backup_user;
```

## 📊 Backup and Recovery

### Automated Backup Configuration
```bash
# Create backup directory
sudo mkdir -p /var/backups/flight-control
sudo chown postgres:postgres /var/backups/flight-control

# Create backup script
sudo tee /usr/local/bin/flight-control-backup.sh << EOF
#!/bin/bash

# Database backup
pg_dump -U flight_user -h localhost flight_control > /var/backups/flight-control/db_backup_\$(date +%Y%m%d_%H%M%S).sql

# Application files backup
tar -czf /var/backups/flight-control/app_backup_\$(date +%Y%m%d_%H%M%S).tar.gz /var/www/flight-control

# Configuration backup
cp /var/www/flight-control/.env.production /var/backups/flight-control/env_backup_\$(date +%Y%m%d_%H%M%S)

# Clean old backups (keep last 30 days)
find /var/backups/flight-control -name "*.sql" -mtime +30 -delete
find /var/backups/flight-control -name "*.tar.gz" -mtime +30 -delete
find /var/backups/flight-control -name "env_backup_*" -mtime +30 -delete

echo "Backup completed at \$(date)"
EOF

# Make script executable
sudo chmod +x /usr/local/bin/flight-control-backup.sh

# Schedule daily backup
sudo crontab -e
# Add this line:
# 0 2 * * * /usr/local/bin/flight-control-backup.sh
```

### Recovery Procedures

#### Database Recovery
```bash
# Stop application
sudo systemctl stop nginx
sudo systemctl stop php8.1-fpm

# Restore database
sudo -u postgres psql -d flight_control < /var/backups/flight-control/db_backup_20231201_020000.sql

# Start application
sudo systemctl start php8.1-fpm
sudo systemctl start nginx
```

#### Application Recovery
```bash
# Restore application files
sudo tar -xzf /var/backups/flight-control/app_backup_20231201_020000.tar.gz -C /var/www/

# Restore configuration
sudo cp /var/backups/flight-control/env_backup_20231201_020000 /var/www/flight-control/.env.production

# Run migrations if needed
cd /var/www/flight-control
sudo -u www-data php artisan migrate
```

## 🚀 Performance Optimization

### PHP Optimization
```bash
# Configure PHP-FPM
sudo tee /etc/php/8.1/fpm/pool.d/flight-control.conf << EOF
[flight-control]

user = www-data
group = www-data

listen = /var/run/php/flight-control.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

slowlog = /var/log/php8.1-fpm.slow
request_slowlog_timeout = 10s

catch_workers_output = yes
EOF

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

### Database Optimization
```sql
-- Create indexes for better performance
CREATE INDEX CONCURRENTLY idx_flights_departure_time ON flights(departure_time);
CREATE INDEX CONCURRENTLY idx_flights_status ON flights(status);
CREATE INDEX CONCURRENTLY idx_bookings_user_id ON bookings(user_id);
CREATE INDEX CONCURRENTLY idx_infrastructure_alerts_severity ON infrastructure_alerts(severity);

-- Configure PostgreSQL for better performance
ALTER SYSTEM SET shared_buffers = '256MB';
ALTER SYSTEM SET effective_cache_size = '1GB';
ALTER SYSTEM SET work_mem = '4MB';
ALTER SYSTEM SET maintenance_work_mem = '64MB';
ALTER SYSTEM SET checkpoint_completion_target = 0.9;
ALTER SYSTEM SET wal_buffers = '16MB';
ALTER SYSTEM SET default_statistics_target = 100;
```

### Caching Configuration
```bash
# Configure Redis for better performance
sudo tee /etc/redis/redis.conf << EOF
# Memory optimization
maxmemory 256mb
maxmemory-policy allkeys-lru

# Persistence
save 900 1
save 300 10
save 60 10000

# Security
bind 127.0.0.1
protected-mode yes
requirepass your_secure_redis_password
EOF

sudo systemctl restart redis-server
```

## 🔍 Troubleshooting

### Common Installation Issues

#### PHP Dependencies
```bash
# Check PHP modules
php -m

# Install missing modules
sudo apt install php8.1-missing-module
```

#### Database Connection Issues
```bash
# Test database connection
sudo -u www-data php artisan tinker
# In tinker: DB::connection()->getPdo();
```

#### Permission Issues
```bash
# Fix storage permissions
sudo chown -R www-data:www-data /var/www/flight-control/storage
sudo chmod -R 775 /var/www/flight-control/storage

# Fix bootstrap cache
sudo chown -R www-data:www-data /var/www/flight-control/bootstrap/cache
sudo chmod -R 775 /var/www/flight-control/bootstrap/cache
```

#### Module Loading Issues
```bash
# Check module status
sudo -u www-data php artisan module:list

# Enable specific module
sudo -u www-data php artisan module:enable infrastructure

# Check module dependencies
sudo -u www-data php artisan module:check-dependencies
```

## 📞 Support and Maintenance

### Regular Maintenance Tasks

#### Daily Tasks
- Monitor system logs for errors
- Check disk space usage
- Verify backup completion
- Review security alerts

#### Weekly Tasks
- Update system packages
- Review performance metrics
- Check module health status
- Verify SSL certificate validity

#### Monthly Tasks
- Full system backup verification
- Security patch application
- Performance optimization review
- User access review

### Support Resources

#### Documentation
- **Installation Guide**: Current document
- **User Manuals**: Module-specific documentation
- **API Documentation**: Developer integration guides
- **Troubleshooting Guide**: Common issues and solutions

#### Support Channels
- **Technical Support**: support@flight-control.com
- **Emergency Hotline**: +1 (555) 911-0000
- **Community Forum**: community.flight-control.com
- **GitHub Issues**: github.com/your-org/flight-control-system/issues

---

## ✅ Post-Installation Checklist

- [ ] System packages updated
- [ ] PHP and extensions installed
- [ ] Database configured and accessible
- [ ] Redis cache configured
- [ ] Web server configured and running
- [ ] SSL certificate installed
- [ ] Application deployed and accessible
- [ ] Database migrations completed
- [ ] Modules configured and enabled
- [ ] Monitoring and logging configured
- [ ] Backup system operational
- [ ] Security measures implemented
- [ ] Performance optimizations applied

---

*This installation guide is maintained by the Flight Control System deployment team. Please report any issues or suggest improvements to the development team.*
