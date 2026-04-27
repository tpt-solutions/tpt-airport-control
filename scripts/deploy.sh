#!/bin/bash

# Flight Control System Deployment Script
# This script handles the complete deployment process for production

set -e  # Exit on any error

# Configuration
DEPLOY_ENV="${DEPLOY_ENV:-production}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DEPLOY_LOG="/var/log/flight-control/deploy_${TIMESTAMP}.log"
BACKUP_DIR="/var/backups/pre-deploy-${TIMESTAMP}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$DEPLOY_LOG"
}

success() {
    echo -e "${GREEN}✓ $1${NC}" | tee -a "$DEPLOY_LOG"
}

warning() {
    echo -e "${YELLOW}⚠ $1${NC}" | tee -a "$DEPLOY_LOG"
}

error() {
    echo -e "${RED}✗ $1${NC}" | tee -a "$DEPLOY_LOG"
    exit 1
}

# Pre-deployment checks
pre_deployment_checks() {
    log "Performing pre-deployment checks..."

    # Check if running as root or with sudo
    if [[ $EUID -eq 0 ]]; then
        error "This script should not be run as root"
    fi

    # Check required tools
    command -v docker >/dev/null 2>&1 || error "Docker is required but not installed"
    command -v docker-compose >/dev/null 2>&1 || error "Docker Compose is required but not installed"

    # Check environment file
    if [[ ! -f ".env.production" ]]; then
        error "Production environment file (.env.production) not found"
    fi

    # Check SSL certificates
    if [[ ! -f "nginx/ssl/fullchain.pem" ]] || [[ ! -f "nginx/ssl/privkey.pem" ]]; then
        warning "SSL certificates not found. Make sure to obtain them before deployment"
    fi

    success "Pre-deployment checks completed"
}

# Create backup
create_backup() {
    log "Creating pre-deployment backup..."

    mkdir -p "$BACKUP_DIR"

    # Backup current database if exists
    if docker ps | grep -q flight-control-postgres; then
        log "Backing up current database..."
        docker exec flight-control-postgres pg_dump -U flight_user flight_control > "$BACKUP_DIR/database_pre_deploy.sql" 2>> "$DEPLOY_LOG"
    fi

    # Backup current configuration
    if [[ -d "/var/www/flight-control" ]]; then
        log "Backing up current application..."
        cp -r /var/www/flight-control "$BACKUP_DIR/app_backup" 2>> "$DEPLOY_LOG"
    fi

    success "Backup created at $BACKUP_DIR"
}

# Stop existing services
stop_services() {
    log "Stopping existing services..."

    if docker-compose ps | grep -q "Up"; then
        docker-compose down --remove-orphans 2>> "$DEPLOY_LOG"
        success "Existing services stopped"
    else
        log "No existing services to stop"
    fi
}

# Build and deploy services
deploy_services() {
    log "Building and deploying services..."

    # Copy production environment file
    cp .env.production .env

    # Build services
    log "Building Docker images..."
    docker-compose -f docker-compose.prod.yml build --no-cache 2>> "$DEPLOY_LOG"

    # Start services
    log "Starting services..."
    docker-compose -f docker-compose.prod.yml up -d 2>> "$DEPLOY_LOG"

    # Wait for services to be healthy
    log "Waiting for services to be healthy..."
    sleep 30

    success "Services deployed successfully"
}

# Run database migrations
run_migrations() {
    log "Running database migrations..."

    # Wait for database to be ready
    max_attempts=30
    attempt=1
    while [ $attempt -le $max_attempts ]; do
        if docker-compose -f docker-compose.prod.yml exec -T postgres pg_isready -U flight_user -d flight_control >/dev/null 2>&1; then
            success "Database is ready"
            break
        fi
        log "Waiting for database... (attempt $attempt/$max_attempts)"
        sleep 10
        ((attempt++))
    done

    if [ $attempt -gt $max_attempts ]; then
        error "Database failed to become ready"
    fi

    # Run migrations
    docker-compose -f docker-compose.prod.yml exec -T backend php artisan migrate --force 2>> "$DEPLOY_LOG"
    success "Database migrations completed"

    # Seed database if needed
    if [[ "$DEPLOY_ENV" == "production" ]] && [[ ! -f "/var/www/flight-control/.seeded" ]]; then
        log "Seeding database..."
        docker-compose -f docker-compose.prod.yml exec -T backend php artisan db:seed --force 2>> "$DEPLOY_LOG"
        touch /var/www/flight-control/.seeded
        success "Database seeded"
    fi
}

# Configure SSL certificates
configure_ssl() {
    log "Configuring SSL certificates..."

    if [[ -f "nginx/ssl/fullchain.pem" ]] && [[ -f "nginx/ssl/privkey.pem" ]]; then
        success "SSL certificates found and configured"
    else
        warning "SSL certificates not found. Please obtain certificates and place them in nginx/ssl/"
        log "You can use Let's Encrypt:"
        log "  sudo certbot certonly --standalone -d flight-control.local"
        log "  sudo cp /etc/letsencrypt/live/flight-control.local/fullchain.pem nginx/ssl/"
        log "  sudo cp /etc/letsencrypt/live/flight-control.local/privkey.pem nginx/ssl/"
    fi
}

# Health checks
perform_health_checks() {
    log "Performing health checks..."

    # Check if services are running
    if ! docker-compose -f docker-compose.prod.yml ps | grep -q "Up"; then
        error "Some services failed to start"
    fi

    # Check backend health
    max_attempts=10
    attempt=1
    while [ $attempt -le $max_attempts ]; do
        if curl -f -s http://localhost/api/health >/dev/null 2>&1; then
            success "Backend health check passed"
            break
        fi
        log "Backend health check failed, retrying... (attempt $attempt/$max_attempts)"
        sleep 5
        ((attempt++))
    done

    if [ $attempt -gt $max_attempts ]; then
        error "Backend health check failed"
    fi

    # Check frontend
    if curl -f -s http://localhost >/dev/null 2>&1; then
        success "Frontend health check passed"
    else
        warning "Frontend health check failed"
    fi

    success "Health checks completed"
}

# Configure monitoring
configure_monitoring() {
    log "Configuring monitoring..."

    # Wait for monitoring services to be ready
    sleep 10

    # Check Prometheus
    if curl -f -s http://localhost:9090/-/healthy >/dev/null 2>&1; then
        success "Prometheus is running"
    else
        warning "Prometheus health check failed"
    fi

    # Check Grafana
    if curl -f -s http://localhost:3001/api/health >/dev/null 2>&1; then
        success "Grafana is running"
    else
        warning "Grafana health check failed"
    fi

    success "Monitoring configuration completed"
}

# Post-deployment tasks
post_deployment_tasks() {
    log "Running post-deployment tasks..."

    # Clear caches
    docker-compose -f docker-compose.prod.yml exec -T backend php artisan config:cache 2>> "$DEPLOY_LOG"
    docker-compose -f docker-compose.prod.yml exec -T backend php artisan route:cache 2>> "$DEPLOY_LOG"
    docker-compose -f docker-compose.prod.yml exec -T backend php artisan view:cache 2>> "$DEPLOY_LOG"

    # Set proper permissions
    docker-compose -f docker-compose.prod.yml exec -T backend chown -R www-data:www-data /var/www/html/storage 2>> "$DEPLOY_LOG"
    docker-compose -f docker-compose.prod.yml exec -T backend chmod -R 755 /var/www/html/storage 2>> "$DEPLOY_LOG"

    # Generate application key if not set
    if ! grep -q "APP_KEY=base64:" .env; then
        log "Generating application key..."
        docker-compose -f docker-compose.prod.yml exec -T backend php artisan key:generate 2>> "$DEPLOY_LOG"
    fi

    success "Post-deployment tasks completed"
}

# Rollback function
rollback() {
    log "Performing rollback..."

    # Stop current services
    docker-compose -f docker-compose.prod.yml down 2>> "$DEPLOY_LOG"

    # Restore backup if available
    if [[ -d "$BACKUP_DIR" ]]; then
        log "Restoring from backup..."
        # Restore database
        if [[ -f "$BACKUP_DIR/database_pre_deploy.sql" ]]; then
            docker-compose -f docker-compose.prod.yml exec -T postgres psql -U flight_user -d flight_control < "$BACKUP_DIR/database_pre_deploy.sql" 2>> "$DEPLOY_LOG"
        fi

        # Restore application files
        if [[ -d "$BACKUP_DIR/app_backup" ]]; then
            cp -r "$BACKUP_DIR/app_backup"/* /var/www/flight-control/ 2>> "$DEPLOY_LOG"
        fi

        success "Rollback completed"
    else
        error "No backup available for rollback"
    fi
}

# Main deployment function
main() {
    log "Starting Flight Control System deployment..."
    log "Environment: $DEPLOY_ENV"
    log "Timestamp: $TIMESTAMP"

    # Trap for cleanup on error
    trap 'error "Deployment failed, check logs at $DEPLOY_LOG"' ERR

    pre_deployment_checks
    create_backup
    stop_services
    deploy_services
    run_migrations
    configure_ssl
    perform_health_checks
    configure_monitoring
    post_deployment_tasks

    success "Flight Control System deployment completed successfully!"
    log "Application URL: https://flight-control.local"
    log "ATC Dashboard: https://atc.flight-control.local"
    log "API Documentation: https://flight-control.local/docs/API_DOCUMENTATION.md"
    log "Deployment log: $DEPLOY_LOG"

    # Clean up old backups (keep last 5)
    log "Cleaning up old backups..."
    ls -dt /var/backups/pre-deploy-* | tail -n +6 | xargs -r rm -rf 2>> "$DEPLOY_LOG"

    success "Deployment cleanup completed"
}

# Show usage
usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -e, --env ENV       Deployment environment (default: production)"
    echo "  -r, --rollback      Rollback to previous deployment"
    echo "  -h, --help          Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                    # Deploy to production"
    echo "  $0 -e staging        # Deploy to staging"
    echo "  $0 -r                # Rollback deployment"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -e|--env)
            DEPLOY_ENV="$2"
            shift 2
            ;;
        -r|--rollback)
            ROLLBACK=true
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            ;;
    esac
done

# Run rollback or main deployment
if [[ "$ROLLBACK" == "true" ]]; then
    rollback
else
    main
fi
