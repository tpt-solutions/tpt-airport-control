#!/bin/bash

# Flight Control System Backup Script
# This script performs comprehensive backups of the Flight Control system

set -e  # Exit on any error

# Configuration
BACKUP_ROOT="/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR="${BACKUP_ROOT}/${TIMESTAMP}"
LOG_FILE="${BACKUP_ROOT}/backup_${TIMESTAMP}.log"

# Database configuration
DB_HOST="${POSTGRES_HOST:-postgres}"
DB_PORT="${POSTGRES_PORT:-5432}"
DB_NAME="${POSTGRES_DB:-flight_control}"
DB_USER="${POSTGRES_USER:-flight_user}"
DB_PASSWORD="${POSTGRES_PASSWORD}"

# AWS S3 configuration (optional)
S3_BUCKET="${AWS_BUCKET:-flight-control-backups}"
S3_REGION="${AWS_DEFAULT_REGION:-us-east-1}"

# Logging function
log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Error handling
error_exit() {
    log "ERROR: $1"
    exit 1
}

# Create backup directory
create_backup_dir() {
    log "Creating backup directory: $BACKUP_DIR"
    mkdir -p "$BACKUP_DIR" || error_exit "Failed to create backup directory"
}

# Database backup function
backup_database() {
    log "Starting database backup..."

    local db_backup_file="${BACKUP_DIR}/database_${TIMESTAMP}.sql"

    # Export database
    PGPASSWORD="$DB_PASSWORD" pg_dump \
        -h "$DB_HOST" \
        -p "$DB_PORT" \
        -U "$DB_USER" \
        -d "$DB_NAME" \
        --no-password \
        --format=custom \
        --compress=9 \
        --verbose \
        --file="$db_backup_file" \
        2>> "$LOG_FILE"

    if [ $? -eq 0 ]; then
        log "Database backup completed: $db_backup_file"
        echo "$db_backup_file"
    else
        error_exit "Database backup failed"
    fi
}

# File system backup function
backup_filesystem() {
    log "Starting filesystem backup..."

    local fs_backup_file="${BACKUP_DIR}/filesystem_${TIMESTAMP}.tar.gz"
    local source_dirs=(
        "/var/www/html/storage"
        "/var/www/html/bootstrap/cache"
        "/etc/nginx/ssl"
        "/etc/letsencrypt"
    )

    # Create tar archive
    tar -czf "$fs_backup_file" \
        --exclude='*.log' \
        --exclude='*.tmp' \
        --exclude='cache/*' \
        "${source_dirs[@]}" \
        2>> "$LOG_FILE"

    if [ $? -eq 0 ]; then
        log "Filesystem backup completed: $fs_backup_file"
        echo "$fs_backup_file"
    else
        error_exit "Filesystem backup failed"
    fi
}

# Configuration backup function
backup_configuration() {
    log "Starting configuration backup..."

    local config_backup_file="${BACKUP_DIR}/configuration_${TIMESTAMP}.tar.gz"
    local config_files=(
        "/var/www/html/.env"
        "/etc/nginx/nginx.conf"
        "/etc/nginx/sites-available"
        "/etc/php/8.1/fpm/php.ini"
        "/etc/postgresql/14/main/postgresql.conf"
        "/etc/redis/redis.conf"
    )

    # Create tar archive of configuration files
    tar -czf "$config_backup_file" \
        "${config_files[@]}" \
        2>> "$LOG_FILE"

    if [ $? -eq 0 ]; then
        log "Configuration backup completed: $config_backup_file"
        echo "$config_backup_file"
    else
        error_exit "Configuration backup failed"
    fi
}

# Redis backup function
backup_redis() {
    log "Starting Redis backup..."

    local redis_backup_file="${BACKUP_DIR}/redis_${TIMESTAMP}.rdb"

    # Trigger Redis save
    redis-cli -h redis -p 6379 SAVE

    # Copy Redis dump file
    docker cp flight-control-redis:/data/dump.rdb "$redis_backup_file" 2>> "$LOG_FILE"

    if [ $? -eq 0 ]; then
        log "Redis backup completed: $redis_backup_file"
        echo "$redis_backup_file"
    else
        log "WARNING: Redis backup failed, continuing..."
        echo ""
    fi
}

# Upload to S3 (optional)
upload_to_s3() {
    local backup_file="$1"
    local s3_key="backups/$(basename "$backup_file")"

    if [ -n "$AWS_ACCESS_KEY_ID" ] && [ -n "$AWS_SECRET_ACCESS_KEY" ]; then
        log "Uploading $backup_file to S3..."

        aws s3 cp "$backup_file" "s3://${S3_BUCKET}/${s3_key}" \
            --region "$S3_REGION" \
            --storage-class STANDARD_IA \
            2>> "$LOG_FILE"

        if [ $? -eq 0 ]; then
            log "S3 upload completed: s3://${S3_BUCKET}/${s3_key}"
        else
            log "WARNING: S3 upload failed for $backup_file"
        fi
    else
        log "S3 credentials not configured, skipping upload"
    fi
}

# Cleanup old backups
cleanup_old_backups() {
    log "Cleaning up old backups..."

    local retention_days="${BACKUP_RETENTION_DAYS:-30}"

    # Remove local backups older than retention period
    find "$BACKUP_ROOT" -name "20*.sql" -mtime +"$retention_days" -delete 2>> "$LOG_FILE"
    find "$BACKUP_ROOT" -name "20*.tar.gz" -mtime +"$retention_days" -delete 2>> "$LOG_FILE"
    find "$BACKUP_ROOT" -name "20*.rdb" -mtime +"$retention_days" -delete 2>> "$LOG_FILE"
    find "$BACKUP_ROOT" -name "backup_*.log" -mtime +"$retention_days" -delete 2>> "$LOG_FILE"

    # Remove empty directories
    find "$BACKUP_ROOT" -type d -empty -delete 2>> "$LOG_FILE"

    log "Cleanup completed (retention: ${retention_days} days)"
}

# Generate backup report
generate_report() {
    local report_file="${BACKUP_DIR}/backup_report_${TIMESTAMP}.txt"
    local total_size=0

    log "Generating backup report..."

    {
        echo "Flight Control System Backup Report"
        echo "===================================="
        echo "Backup Date: $(date)"
        echo "Backup Directory: $BACKUP_DIR"
        echo ""
        echo "Backup Files:"
        echo "-------------"

        for file in "$BACKUP_DIR"/*; do
            if [ -f "$file" ]; then
                local size=$(du -h "$file" | cut -f1)
                local filename=$(basename "$file")
                echo "  $filename: $size"
                # Add to total size (convert to bytes for calculation)
                local size_bytes=$(du -b "$file" | cut -f1)
                total_size=$((total_size + size_bytes))
            fi
        done

        echo ""
        echo "Total Backup Size: $(numfmt --to=iec-i --suffix=B $total_size)"
        echo ""
        echo "Database: $DB_NAME"
        echo "Retention Period: ${BACKUP_RETENTION_DAYS:-30} days"
        echo ""
        echo "Backup completed successfully"

    } > "$report_file"

    log "Backup report generated: $report_file"
}

# Health check after backup
health_check() {
    log "Performing post-backup health check..."

    # Check database connectivity
    if PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1;" > /dev/null 2>&1; then
        log "Database health check: PASSED"
    else
        log "WARNING: Database health check failed"
    fi

    # Check backup directory permissions
    if [ -w "$BACKUP_DIR" ]; then
        log "Backup directory permissions: OK"
    else
        log "WARNING: Backup directory permissions issue"
    fi
}

# Main backup function
main() {
    log "Starting Flight Control System backup process..."
    log "Backup timestamp: $TIMESTAMP"

    # Create backup directory
    create_backup_dir

    # Perform backups
    local db_backup=$(backup_database)
    local fs_backup=$(backup_filesystem)
    local config_backup=$(backup_configuration)
    local redis_backup=$(backup_redis)

    # Upload to S3 if configured
    [ -n "$db_backup" ] && upload_to_s3 "$db_backup"
    [ -n "$fs_backup" ] && upload_to_s3 "$fs_backup"
    [ -n "$config_backup" ] && upload_to_s3 "$config_backup"
    [ -n "$redis_backup" ] && upload_to_s3 "$redis_backup"

    # Generate report
    generate_report

    # Cleanup old backups
    cleanup_old_backups

    # Health check
    health_check

    log "Flight Control System backup process completed successfully"
    log "Backup location: $BACKUP_DIR"
    log "Log file: $LOG_FILE"
}

# Run main function
main "$@"
