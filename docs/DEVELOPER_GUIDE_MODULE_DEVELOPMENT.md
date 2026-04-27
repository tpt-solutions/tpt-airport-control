# Developer Guide - Module Development Standards

## 📋 Overview

This guide provides comprehensive standards and best practices for developing modules within the Flight Control System. The modular architecture allows for extensible functionality while maintaining system stability, security, and performance.

## 🎯 Development Principles

### Core Design Principles

#### 1. Modularity
- **Single Responsibility**: Each module handles one specific domain
- **Loose Coupling**: Minimal dependencies between modules
- **High Cohesion**: Related functionality grouped together
- **Interface Segregation**: Clean, well-defined APIs

#### 2. Scalability
- **Horizontal Scaling**: Support for multiple instances
- **Resource Efficiency**: Minimal memory and CPU footprint
- **Database Optimization**: Efficient query patterns
- **Caching Strategy**: Appropriate use of caching layers

#### 3. Security
- **Input Validation**: All inputs validated and sanitized
- **Access Control**: Role-based permissions enforced
- **Data Protection**: Sensitive data encrypted at rest and in transit
- **Audit Logging**: All operations logged for compliance

#### 4. Maintainability
- **Code Standards**: Consistent coding style and patterns
- **Documentation**: Comprehensive inline and external documentation
- **Testing**: Automated test coverage for all functionality
- **Version Control**: Proper branching and release management

## 🏗️ Module Architecture

### Standard Module Structure

```
modules/
├── [module-name]/
│   ├── backend/
│   │   ├── api/
│   │   │   └── [module-name].php
│   │   ├── models/
│   │   │   └── [ModuleName].php
│   │   ├── services/
│   │   │   └── [ModuleName]Service.php
│   │   ├── repositories/
│   │   │   └── [ModuleName]Repository.php
│   │   └── controllers/
│   │       └── [ModuleName]Controller.php
│   ├── frontend/
│   │   ├── views/
│   │   │   └── [ModuleName]View.ts
│   │   ├── components/
│   │   │   └── [ModuleName]Components.ts
│   │   └── services/
│   │       └── [ModuleName]ApiService.ts
│   ├── database/
│   │   ├── [module-name]_schema.sql
│   │   └── migrations/
│   │       └── [timestamp]_create_[module-name]_tables.sql
│   ├── tests/
│   │   ├── unit/
│   │   │   └── [ModuleName]Test.php
│   │   ├── integration/
│   │   │   └── [ModuleName]IntegrationTest.php
│   │   └── performance/
│   │       └── [ModuleName]PerformanceTest.php
│   ├── config/
│   │   └── [module-name].json
│   ├── docs/
│   │   ├── README.md
│   │   ├── API.md
│   │   └── USER_GUIDE.md
│   └── composer.json
```

### Module Metadata Structure

```json
{
  "module": {
    "name": "Infrastructure Management",
    "slug": "infrastructure",
    "version": "1.0.0",
    "description": "Facility monitoring and control module",
    "category": "infrastructure",
    "author": "Flight Control Team",
    "license": "Proprietary",
    "dependencies": {
      "core": ">=1.0.0",
      "required": [],
      "optional": ["emergency", "cargo"]
    },
    "conflicts": [],
    "permissions": {
      "admin": ["enable", "configure", "manage"],
      "operator": ["view", "monitor"],
      "maintenance": ["view", "update"]
    }
  }
}
```

## 🔧 Backend Development Standards

### API Design Patterns

#### RESTful API Structure
```php
<?php
/**
 * Infrastructure Management API
 * Handles facility monitoring and control operations
 */

class InfrastructureApi extends BaseApi
{
    /**
     * Get system status overview
     * GET /api/infrastructure/status
     */
    public function getStatus()
    {
        try {
            $this->validateAccess('infrastructure.view');

            $status = $this->infrastructureService->getSystemStatus();

            return $this->apiResponse->success([
                'status' => 'operational',
                'systems' => $status,
                'timestamp' => now()
            ]);

        } catch (Exception $e) {
            $this->logger->error('Infrastructure status retrieval failed', [
                'error' => $e->getMessage(),
                'user_id' => $this->auth->getUserId()
            ]);

            return $this->apiResponse->error('Failed to retrieve system status', 500);
        }
    }

    /**
     * Update sensor configuration
     * PUT /api/infrastructure/sensors/{id}
     */
    public function updateSensor($sensorId)
    {
        try {
            $this->validateAccess('infrastructure.manage');

            $data = $this->validateRequest([
                'threshold' => 'required|numeric|min:0|max:100',
                'enabled' => 'boolean',
                'calibration_offset' => 'numeric'
            ]);

            $sensor = $this->infrastructureService->updateSensor($sensorId, $data);

            $this->auditLog('sensor_updated', [
                'sensor_id' => $sensorId,
                'changes' => $data
            ]);

            return $this->apiResponse->success([
                'sensor' => $sensor,
                'message' => 'Sensor updated successfully'
            ]);

        } catch (ValidationException $e) {
            return $this->apiResponse->validationError($e->getErrors());
        } catch (Exception $e) {
            $this->logger->error('Sensor update failed', [
                'sensor_id' => $sensorId,
                'error' => $e->getMessage()
            ]);

            return $this->apiResponse->error('Failed to update sensor', 500);
        }
    }
}
```

#### Service Layer Pattern
```php
<?php
/**
 * Infrastructure Management Service
 * Business logic for facility operations
 */

class InfrastructureService
{
    private $repository;
    private $cache;
    private $logger;

    public function __construct(
        InfrastructureRepository $repository,
        CacheManager $cache,
        Logger $logger
    ) {
        $this->repository = $repository;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Get comprehensive system status
     */
    public function getSystemStatus()
    {
        // Check cache first
        $cacheKey = 'infrastructure.system_status';
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        try {
            $status = [
                'overall_health' => $this->calculateOverallHealth(),
                'systems' => $this->getAllSystemStatuses(),
                'alerts' => $this->getActiveAlerts(),
                'maintenance' => $this->getUpcomingMaintenance()
            ];

            // Cache for 5 minutes
            $this->cache->put($cacheKey, $status, 300);

            return $status;

        } catch (Exception $e) {
            $this->logger->error('Failed to get system status', [
                'error' => $e->getMessage()
            ]);

            throw new InfrastructureException('Unable to retrieve system status');
        }
    }

    /**
     * Calculate overall system health score
     */
    private function calculateOverallHealth()
    {
        $systems = $this->repository->getAllSystems();
        $totalScore = 0;
        $systemCount = count($systems);

        foreach ($systems as $system) {
            $totalScore += $this->calculateSystemHealth($system);
        }

        return $systemCount > 0 ? round($totalScore / $systemCount, 2) : 0;
    }

    /**
     * Calculate individual system health
     */
    private function calculateSystemHealth($system)
    {
        $weights = [
            'operational_status' => 0.4,
            'sensor_readings' => 0.3,
            'maintenance_status' => 0.2,
            'alert_count' => 0.1
        ];

        $score = 0;

        // Operational status (0-100)
        $score += ($system['operational'] ? 100 : 0) * $weights['operational_status'];

        // Sensor readings quality (0-100)
        $sensorScore = $this->calculateSensorScore($system['sensors']);
        $score += $sensorScore * $weights['sensor_readings'];

        // Maintenance status (0-100)
        $maintenanceScore = $this->calculateMaintenanceScore($system['maintenance']);
        $score += $maintenanceScore * $weights['maintenance_status'];

        // Alert impact (inverse relationship)
        $alertScore = max(0, 100 - ($system['alert_count'] * 10));
        $score += $alertScore * $weights['alert_count'];

        return round($score);
    }
}
```

### Repository Pattern Implementation
```php
<?php
/**
 * Infrastructure Repository
 * Data access layer for infrastructure entities
 */

class InfrastructureRepository
{
    private $db;
    private $cache;

    public function __construct(Database $db, CacheManager $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * Get system by ID with caching
     */
    public function getSystemById($systemId)
    {
        $cacheKey = "infrastructure.system.{$systemId}";

        return $this->cache->remember($cacheKey, 3600, function () use ($systemId) {
            return $this->db->table('infrastructure_systems')
                ->where('system_id', $systemId)
                ->where('deleted_at', null)
                ->first();
        });
    }

    /**
     * Get systems with filtering and pagination
     */
    public function getSystems($filters = [], $pagination = null)
    {
        $query = $this->db->table('infrastructure_systems')
            ->where('deleted_at', null);

        // Apply filters
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['status'])) {
            $query->where('operational_status', $filters['status']);
        }

        if (!empty($filters['health_min'])) {
            $query->where('health_score', '>=', $filters['health_min']);
        }

        // Apply pagination
        if ($pagination) {
            return $query->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);
        }

        return $query->get();
    }

    /**
     * Update system with optimistic locking
     */
    public function updateSystem($systemId, $data, $expectedVersion = null)
    {
        $this->db->beginTransaction();

        try {
            $query = $this->db->table('infrastructure_systems')
                ->where('system_id', $systemId)
                ->where('deleted_at', null);

            // Optimistic locking
            if ($expectedVersion !== null) {
                $query->where('version', $expectedVersion);
            }

            $updated = $query->update(array_merge($data, [
                'updated_at' => now(),
                'version' => $this->db->raw('version + 1')
            ]));

            if ($updated === 0) {
                throw new ConcurrencyException('System was modified by another process');
            }

            // Clear related caches
            $this->clearSystemCache($systemId);

            $this->db->commit();

            return $this->getSystemById($systemId);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Clear system-related cache entries
     */
    private function clearSystemCache($systemId)
    {
        $patterns = [
            "infrastructure.system.{$systemId}",
            "infrastructure.systems.*",
            "infrastructure.status"
        ];

        foreach ($patterns as $pattern) {
            $this->cache->deleteMultiple($this->cache->keys($pattern));
        }
    }
}
```

## 🎨 Frontend Development Standards

### Component Architecture

#### Base Component Structure
```typescript
/**
 * Infrastructure Dashboard Component
 * Main dashboard for infrastructure monitoring
 */

import { AuthManager } from '../../auth/AuthManager';
import { ApiService } from '../../services/ApiService';
import { NotificationManager } from '../../components/NotificationManager';

export class InfrastructureDashboard {
    private container: HTMLElement;
    private auth: AuthManager;
    private api: ApiService;
    private notifications: NotificationManager;

    // Component state
    private systems: System[] = [];
    private alerts: Alert[] = [];
    private isLoading: boolean = false;

    constructor(container: HTMLElement) {
        this.container = container;
        this.auth = AuthManager.getInstance();
        this.api = new ApiService();
        this.notifications = new NotificationManager();

        this.initialize();
    }

    /**
     * Initialize component
     */
    private async initialize(): Promise<void> {
        try {
            await this.checkPermissions();
            await this.loadData();
            this.setupEventListeners();
            this.render();
        } catch (error) {
            this.handleError(error);
        }
    }

    /**
     * Check user permissions
     */
    private async checkPermissions(): Promise<void> {
        const user = this.auth.getUser();
        if (!user || !user.permissions.includes('infrastructure.view')) {
            throw new Error('Insufficient permissions');
        }
    }

    /**
     * Load dashboard data
     */
    private async loadData(): Promise<void> {
        this.isLoading = true;
        this.render();

        try {
            const [systemsResponse, alertsResponse] = await Promise.all([
                this.api.get('/api/infrastructure/systems'),
                this.api.get('/api/infrastructure/alerts')
            ]);

            this.systems = systemsResponse.data;
            this.alerts = alertsResponse.data;

        } catch (error) {
            this.notifications.error('Failed to load dashboard data');
            throw error;
        } finally {
            this.isLoading = false;
            this.render();
        }
    }

    /**
     * Setup event listeners
     */
    private setupEventListeners(): void {
        // Refresh button
        const refreshBtn = this.container.querySelector('#refresh-dashboard');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.refresh());
        }

        // System status updates (WebSocket)
        this.setupWebSocketListeners();
    }

    /**
     * Setup WebSocket listeners for real-time updates
     */
    private setupWebSocketListeners(): void {
        const ws = new WebSocket('ws://localhost:8080');

        ws.onmessage = (event) => {
            const data = JSON.parse(event.data);

            if (data.type === 'infrastructure_update') {
                this.handleRealTimeUpdate(data);
            }
        };

        ws.onerror = (error) => {
            console.error('WebSocket error:', error);
            this.notifications.warning('Real-time updates unavailable');
        };
    }

    /**
     * Handle real-time updates
     */
    private handleRealTimeUpdate(data: any): void {
        switch (data.action) {
            case 'system_status_changed':
                this.updateSystemStatus(data.systemId, data.status);
                break;
            case 'alert_created':
                this.addAlert(data.alert);
                break;
            case 'alert_resolved':
                this.removeAlert(data.alertId);
                break;
        }

        this.render();
    }

    /**
     * Render component
     */
    public render(): void {
        this.container.innerHTML = `
            <div class="infrastructure-dashboard">
                ${this.renderHeader()}
                ${this.renderLoadingSpinner()}
                ${this.renderContent()}
                ${this.renderFooter()}
            </div>
        `;
    }

    /**
     * Render dashboard header
     */
    private renderHeader(): string {
        const activeAlerts = this.alerts.filter(a => a.status === 'active').length;

        return `
            <header class="dashboard-header">
                <h1>Infrastructure Management</h1>
                <div class="header-actions">
                    <button id="refresh-dashboard" class="btn btn-primary">
                        <i class="icon-refresh"></i> Refresh
                    </button>
                    <span class="alert-count ${activeAlerts > 0 ? 'has-alerts' : ''}">
                        ${activeAlerts} Active Alerts
                    </span>
                </div>
            </header>
        `;
    }

    /**
     * Render loading spinner
     */
    private renderLoadingSpinner(): string {
        return this.isLoading ? `
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>Loading infrastructure data...</p>
            </div>
        ` : '';
    }

    /**
     * Render main content
     */
    private renderContent(): string {
        if (this.isLoading) return '';

        return `
            <main class="dashboard-content">
                ${this.renderSystemOverview()}
                ${this.renderAlertsPanel()}
                ${this.renderMaintenanceSchedule()}
            </main>
        `;
    }

    /**
     * Render system overview
     */
    private renderSystemOverview(): string {
        const healthySystems = this.systems.filter(s => s.health_score >= 80).length;
        const totalSystems = this.systems.length;
        const healthPercentage = totalSystems > 0 ? (healthySystems / totalSystems) * 100 : 0;

        return `
            <section class="system-overview">
                <h2>System Overview</h2>
                <div class="health-summary">
                    <div class="health-score">
                        <div class="score-circle" style="--percentage: ${healthPercentage}">
                            <span class="score-text">${Math.round(healthPercentage)}%</span>
                        </div>
                        <p>Overall Health</p>
                    </div>
                    <div class="system-stats">
                        <div class="stat">
                            <span class="stat-value">${totalSystems}</span>
                            <span class="stat-label">Total Systems</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value">${healthySystems}</span>
                            <span class="stat-label">Healthy</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value">${totalSystems - healthySystems}</span>
                            <span class="stat-label">Needs Attention</span>
                        </div>
                    </div>
                </div>
                <div class="systems-grid">
                    ${this.systems.map(system => this.renderSystemCard(system)).join('')}
                </div>
            </section>
        `;
    }

    /**
     * Render individual system card
     */
    private renderSystemCard(system: System): string {
        const healthClass = system.health_score >= 80 ? 'healthy' :
                           system.health_score >= 60 ? 'warning' : 'critical';

        return `
            <div class="system-card ${healthClass}" data-system-id="${system.id}">
                <div class="system-header">
                    <h3>${system.name}</h3>
                    <span class="system-status">${system.operational_status}</span>
                </div>
                <div class="system-metrics">
                    <div class="metric">
                        <span class="metric-value">${system.health_score}%</span>
                        <span class="metric-label">Health</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value">${system.temperature}°C</span>
                        <span class="metric-label">Temperature</span>
                    </div>
                    <div class="metric">
                        <span class="metric-value">${system.humidity}%</span>
                        <span class="metric-label">Humidity</span>
                    </div>
                </div>
                <div class="system-actions">
                    <button class="btn btn-sm" onclick="viewSystemDetails('${system.id}')">
                        View Details
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="configureSystem('${system.id}')">
                        Configure
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Render alerts panel
     */
    private renderAlertsPanel(): string {
        const activeAlerts = this.alerts.filter(a => a.status === 'active');

        return `
            <section class="alerts-panel">
                <h2>Active Alerts</h2>
                ${activeAlerts.length === 0 ?
                    '<p class="no-alerts">No active alerts</p>' :
                    `<div class="alerts-list">
                        ${activeAlerts.map(alert => this.renderAlert(alert)).join('')}
                    </div>`
                }
            </section>
        `;
    }

    /**
     * Render individual alert
     */
    private renderAlert(alert: Alert): string {
        const severityClass = `severity-${alert.severity}`;

        return `
            <div class="alert-item ${severityClass}" data-alert-id="${alert.id}">
                <div class="alert-header">
                    <span class="alert-severity">${alert.severity}</span>
                    <span class="alert-timestamp">${this.formatTimestamp(alert.created_at)}</span>
                </div>
                <div class="alert-content">
                    <h4>${alert.title}</h4>
                    <p>${alert.description}</p>
                </div>
                <div class="alert-actions">
                    <button class="btn btn-sm" onclick="acknowledgeAlert('${alert.id}')">
                        Acknowledge
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="viewAlertDetails('${alert.id}')">
                        Details
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Render maintenance schedule
     */
    private renderMaintenanceSchedule(): string {
        // Implementation for maintenance schedule rendering
        return `
            <section class="maintenance-schedule">
                <h2>Upcoming Maintenance</h2>
                <div class="maintenance-list">
                    <!-- Maintenance items would be rendered here -->
                    <p>Maintenance schedule loading...</p>
                </div>
            </section>
        `;
    }

    /**
     * Render footer
     */
    private renderFooter(): string {
        return `
            <footer class="dashboard-footer">
                <p>Last updated: ${new Date().toLocaleString()}</p>
                <div class="footer-actions">
                    <button class="btn btn-link" onclick="exportDashboard()">
                        Export Report
                    </button>
                    <button class="btn btn-link" onclick="configureDashboard()">
                        Dashboard Settings
                    </button>
                </div>
            </footer>
        `;
    }

    /**
     * Refresh dashboard data
     */
    public async refresh(): Promise<void> {
        try {
            await this.loadData();
            this.notifications.success('Dashboard refreshed successfully');
        } catch (error) {
            this.notifications.error('Failed to refresh dashboard');
        }
    }

    /**
     * Handle errors
     */
    private handleError(error: any): void {
        console.error('Infrastructure Dashboard Error:', error);

        this.container.innerHTML = `
            <div class="error-state">
                <h2>Error Loading Dashboard</h2>
                <p>${error.message || 'An unexpected error occurred'}</p>
                <button class="btn btn-primary" onclick="window.location.reload()">
                    Reload Page
                </button>
            </div>
        `;

        this.notifications.error('Dashboard failed to load');
    }

    /**
     * Utility methods
     */
    private formatTimestamp(timestamp: string): string {
        return new Date(timestamp).toLocaleString();
    }

    private updateSystemStatus(systemId: string, status: any): void {
        const system = this.systems.find(s => s.id === systemId);
        if (system) {
            Object.assign(system, status);
        }
    }

    private addAlert(alert: Alert): void {
        this.alerts.unshift(alert);
    }

    private removeAlert(alertId: string): void {
        this.alerts = this.alerts.filter(a => a.id !== alertId);
    }
}

// Type definitions
interface System {
    id: string;
    name: string;
    operational_status: string;
    health_score: number;
    temperature: number;
    humidity: number;
}

interface Alert {
    id: string;
    title: string;
    description: string;
    severity: 'low' | 'medium' | 'high' | 'critical';
    status: 'active' | 'acknowledged' | 'resolved';
    created_at: string;
}
```

### API Service Pattern
```typescript
/**
 * Infrastructure API Service
 * Handles all API communications for infrastructure module
 */

export class InfrastructureApiService {
    private baseUrl: string = '/api/infrastructure';
    private apiService: ApiService;

    constructor() {
        this.apiService = new ApiService();
    }

    /**
     * Get system status overview
     */
    async getSystemStatus(): Promise<ApiResponse<SystemStatus>> {
        return this.apiService.get(`${this.baseUrl}/status`);
    }

    /**
     * Get all systems with filtering
     */
    async getSystems(filters?: SystemFilters): Promise<ApiResponse<System[]>> {
        const params = new URLSearchParams();

        if (filters?.category) params.append('category', filters.category);
        if (filters?.status) params.append('status', filters.status);
        if (filters?.healthMin) params.append('health_min', filters.healthMin.toString());

        return this.apiService.get(`${this.baseUrl}/systems?${params}`);
    }

    /**
     * Get system by ID
     */
    async getSystem(systemId: string): Promise<ApiResponse<System>> {
        return this.apiService.get(`${this.baseUrl}/systems/${systemId}`);
    }

    /**
     * Update system configuration
     */
    async updateSystem(systemId: string, data: Partial<System>): Promise<ApiResponse<System>> {
        return this.apiService.put(`${this.baseUrl}/systems/${systemId}`, data);
    }

    /**
     * Get active alerts
     */
    async getAlerts(filters?: AlertFilters): Promise<ApiResponse<Alert[]>> {
        const params = new URLSearchParams();

        if (filters?.severity) params.append('severity', filters.severity);
        if (filters?.status) params.append('status', filters.status);

        return this.apiService.get(`${this.baseUrl}/alerts?${params}`);
    }

    /**
     * Acknowledge alert
     */
    async acknowledgeAlert(alertId: string, notes?: string): Promise<ApiResponse<Alert>> {
        return this.apiService.post(`${this.baseUrl}/alerts/${alertId}/acknowledge`, { notes });
    }

    /**
     * Get maintenance schedule
     */
    async getMaintenanceSchedule(dateRange?: DateRange): Promise<ApiResponse<MaintenanceItem[]>> {
        const params = new URLSearchParams();

        if (dateRange?.start) params.append('start_date', dateRange.start.toISOString());
        if (dateRange?.end) params.append('end_date', dateRange.end.toISOString());

        return this.apiService.get(`${this.baseUrl}/maintenance?${params}`);
    }

    /**
     * Create maintenance work order
     */
    async createWorkOrder(workOrder: NewWorkOrder): Promise<ApiResponse<WorkOrder>> {
        return this.apiService.post(`${this.baseUrl}/maintenance/work-orders`, workOrder);
    }

    /**
     * Get sensor readings
     */
    async getSensorReadings(sensorId: string, timeRange?: TimeRange): Promise<ApiResponse<SensorReading[]>> {
        const params = new URLSearchParams();

        if (timeRange?.start) params.append('start_time', timeRange.start.toISOString());
        if (timeRange?.end) params.append('end_time', timeRange.end.toISOString());

        return this.apiService.get(`${this.baseUrl}/sensors/${sensorId}/readings?${params}`);
    }

    /**
     * Update sensor configuration
     */
    async updateSensor(sensorId: string, config: SensorConfig): Promise<ApiResponse<Sensor>> {
        return this.apiService.put(`${this.baseUrl}/sensors/${sensorId}`, config);
    }

    /**
     * Get energy consumption data
     */
    async getEnergyConsumption(timeRange?: TimeRange): Promise<ApiResponse<EnergyData>> {
        const params = new URLSearchParams();

        if (timeRange?.start) params.append('start_date', timeRange.start.toISOString());
        if (timeRange?.end) params.append('end_date', timeRange.end.toISOString());

        return this.apiService.get(`${this.baseUrl}/energy/consumption?${params}`);
    }

    /**
     * Export dashboard data
     */
    async exportDashboard(format: 'pdf' | 'excel' | 'csv' = 'pdf'): Promise<Blob> {
        return this.apiService.download(`${this.baseUrl}/export?format=${format}`);
    }
}

// Type definitions
interface ApiResponse<T> {
    success: boolean;
    data: T;
    message?: string;
    errors?: string[];
}

interface SystemStatus {
    overall_health: number;
    systems_count: number;
    alerts_count: number;
    maintenance_count: number;
}

interface SystemFilters {
    category?: string;
    status?: string;
    healthMin?: number;
}

interface AlertFilters {
    severity?: 'low' | 'medium' | 'high' | 'critical';
    status?: 'active' | 'acknowledged' | 'resolved';
}

interface DateRange {
    start: Date;
    end: Date;
}

interface TimeRange {
    start: Date;
    end: Date;
}

interface NewWorkOrder {
    system_id: string;
    title: string;
    description: string;
    priority: 'low' | 'medium' | 'high' | 'critical';
    assigned_to?: string;
    due_date?: Date;
}

interface SensorConfig {
    enabled: boolean;
    threshold_warning: number;
    threshold_critical: number;
    update_interval: number;
    calibration_offset?: number;
}

interface EnergyData {
    total_consumption: number;
    by_system: Record<string, number>;
    cost_estimate: number;
    efficiency_score: number;
}
```

## 🧪 Testing Standards

### Unit Testing Pattern
```php
<?php
/**
 * Infrastructure Service Unit Tests
 */

use PHPUnit\Framework\TestCase;
use App\Modules\Infrastructure\Services\InfrastructureService;
use App\Modules\Infrastructure\Repositories\InfrastructureRepository;

class InfrastructureServiceTest extends TestCase
{
    private $service;
    private $repository;
    private $cache;
    private $logger;

    protected function setUp(): void
    {
        // Create mocks
        $this->repository = $this->createMock(InfrastructureRepository::class);
        $this->cache = $this->createMock(CacheManager::class);
        $this->logger = $this->createMock(Logger::class);

        // Create service instance
        $this->service = new InfrastructureService(
            $this->repository,
            $this->cache,
            $this->logger
        );
    }

    /**
     * Test successful system status retrieval
     */
    public function testGetSystemStatusSuccess()
    {
        // Arrange
        $expectedStatus = [
            'overall_health' => 85,
            'systems' => [
                ['id' => '1', 'name' => 'HVAC System', 'health_score' => 90]
            ],
            'alerts' => [],
            'maintenance' => []
        ];

        $this->cache->expects($this->once())
            ->method('get')
            ->with('infrastructure.system_status')
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('getAllSystems')
            ->willReturn($expectedStatus['systems']);

        $this->cache->expects($this->once())
            ->method('put')
            ->with('infrastructure.system_status', $this->anything(), 300);

        // Act
        $result = $this->service->getSystemStatus();

        // Assert
        $this->assertEquals($expectedStatus['overall_health'], $result['overall_health']);
        $this->assertCount(1, $result['systems']);
    }

    /**
     * Test system status caching
     */
    public function testGetSystemStatusCaching()
    {
        // Arrange
        $cachedStatus = ['overall_health' => 90, 'cached' => true];

        $this->cache->expects($this->once())
            ->method('get')
            ->with('infrastructure.system_status')
            ->willReturn($cachedStatus);

        $this->repository->expects($this->never())
            ->method('getAllSystems');

        // Act
        $result = $this->service->getSystemStatus();

        // Assert
        $this->assertEquals($cachedStatus, $result);
    }

    /**
     * Test system status error handling
     */
    public function testGetSystemStatusErrorHandling()
    {
        // Arrange
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('getAllSystems')
            ->willThrowException(new Exception('Database connection failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to get system status', $this->anything());

        // Assert exception
        $this->expectException(InfrastructureException::class);
        $this->expectExceptionMessage('Unable to retrieve system status');

        // Act
        $this->service->getSystemStatus();
    }

    /**
     * Test health score calculation
     */
    public function testCalculateSystemHealth()
    {
        // Test cases for different health scenarios
        $testCases = [
            [
                'system' => [
                    'operational' => true,
                    'sensor_readings' => 95,
                    'maintenance_status' => 90,
                    'alert_count' => 0
                ],
                'expected' => 95
            ],
            [
                'system' => [
                    'operational' => false,
                    'sensor_readings' => 80,
                    'maintenance_status' => 70,
                    'alert_count' => 5
                ],
                'expected' => 32
            ]
        ];

        foreach ($testCases as $testCase) {
            $result = $this->invokePrivateMethod(
                $this->service,
                'calculateSystemHealth',
                [$testCase['system']]
            );

            $this->assertEquals($testCase['expected'], $result);
        }
    }

    /**
     * Helper method to invoke private methods
     */
    private function invokePrivateMethod($object, $methodName, $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
```

### Integration Testing Pattern
```php
<?php
/**
 * Infrastructure Module Integration Tests
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfrastructureIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test complete infrastructure workflow
     */
    public function testCompleteInfrastructureWorkflow()
    {
        // Create test data
        $system = factory(InfrastructureSystem::class)->create([
            'name' => 'Test HVAC System',
            'category' => 'hvac',
            'operational_status' => 'active'
        ]);

        $sensor = factory(InfrastructureSensor::class)->create([
            'system_id' => $system->id,
            'type' => 'temperature',
            'threshold_warning' => 25,
            'threshold_critical' => 30
        ]);

        // Test API endpoints
        $response = $this->getJson('/api/infrastructure/status');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'overall_health',
                        'systems_count',
                        'alerts_count'
                    ]
                ]);

        // Test system retrieval
        $response = $this->getJson("/api/infrastructure/systems/{$system->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $system->id,
                        'name' => 'Test HVAC System'
                    ]
                ]);

        // Test sensor update
        $updateData = [
            'threshold_warning' => 26,
            'enabled' => true
        ];

        $response = $this->putJson("/api/infrastructure/sensors/{$sensor->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'threshold_warning' => 26,
                        'enabled' => true
                    ]
                ]);

        // Verify database state
        $this->assertDatabaseHas('infrastructure_sensors', [
            'id' => $sensor->id,
            'threshold_warning' => 26
        ]);

        // Test alert creation
        $alertData = [
            'system_id' => $system->id,
            'title' => 'High Temperature Alert',
            'description' => 'Temperature exceeded threshold',
            'severity' => 'high'
        ];

        $response = $this->postJson('/api/infrastructure/alerts', $alertData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'title' => 'High Temperature Alert',
                        'severity' => 'high'
                    ]
                ]);

        // Verify alert was created
        $this->assertDatabaseHas('infrastructure_alerts', [
            'system_id' => $system->id,
            'title' => 'High Temperature Alert'
        ]);
    }

    /**
     * Test module permission integration
     */
    public function testModulePermissionIntegration()
    {
        $admin = factory(User::class)->create();
        $admin->assignRole('admin');

        $operator = factory(User::class)->create();
        $operator->assignRole('operator');

        // Test admin access
        $this->actingAs($admin)
             ->getJson('/api/infrastructure/admin/config')
             ->assertStatus(200);

        // Test operator access (should be denied)
        $this->actingAs($operator)
             ->getJson('/api/infrastructure/admin/config')
             ->assertStatus(403);

        // Test operator allowed access
        $this->actingAs($operator)
             ->getJson('/api/infrastructure/status')
             ->assertStatus(200);
    }

    /**
     * Test cross-module data sharing
     */
    public function testCrossModuleDataSharing()
    {
        // Create infrastructure system
        $system = factory(InfrastructureSystem::class)->create([
            'operational_status' => 'maintenance'
        ]);

        // Simulate emergency module requesting infrastructure status
        $response = $this->getJson('/api/emergency/infrastructure-status');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'systems_under_maintenance' => 1
                    ]
                ]);
    }

    /**
     * Test module configuration validation
     */
    public function testModuleConfigurationValidation()
    {
        $admin = factory(User::class)->create();
        $admin->assignRole('admin');

        $this->actingAs($admin);

        // Test valid configuration
        $validConfig = [
            'alert_thresholds' => [
                'temperature' => ['warning' => 25, 'critical' => 30]
            ],
            'maintenance_schedule' => 'weekly'
        ];

        $response = $this->postJson('/api/infrastructure/config/validate', $validConfig);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'valid' => true
                ]);

        // Test invalid configuration
        $invalidConfig = [
            'alert_thresholds' => 'invalid_string_instead_of_object'
        ];

        $response = $this->postJson('/api/infrastructure/config/validate', $invalidConfig);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'valid' => false
                ]);
    }

    /**
     * Test concurrent access handling
     */
    public function testConcurrentAccessHandling()
    {
        $system = factory(InfrastructureSystem::class)->create();

        // Simulate concurrent updates
        $promises = [];

        for ($i = 0; $i < 5; $i++) {
            $promises[] = $this->putJsonAsync("/api/infrastructure/systems/{$system->id}", [
                'notes' => "Update {$i}",
                'version' => $system->version
            ]);
        }

        // Wait for all requests to complete
        $responses = Promise\wait(Promise\all($promises));

        // Count successful updates (should be 1 due to optimistic locking)
        $successfulUpdates = array_filter($responses, function($response) {
            return $response->getStatusCode() === 200;
        });

        $this->assertCount(1, $successfulUpdates);

        // Check for concurrency errors
        $concurrencyErrors = array_filter($responses, function($response) {
            return $response->getStatusCode() === 409; // Conflict
        });

        $this->assertGreaterThan(0, count($concurrencyErrors));
    }
}
```

## 📋 Code Quality Standards

### PHP Code Standards
```php
<?php
/**
 * Code Quality Standards for Module Development
 */

// 1. Naming Conventions
class InfrastructureManagementService  // PascalCase for classes
{
    private $systemRepository;         // camelCase for properties
    private $cacheManager;

    public function getSystemStatus()  // camelCase for methods
    {
        // Implementation
    }

    private function calculateHealthScore()  // camelCase for private methods
    {
        // Implementation
    }
}

// 2. Method Length and Complexity
public function processSystemUpdate(System $system, array $updates): System
{
    // Keep methods focused and under 30 lines when possible
    $this->validateUpdates($updates);
    $this->checkPermissions($system);

    DB::transaction(function () use ($system, $updates) {
        $this->updateSystemRecord($system, $updates);
        $this->logSystemChange($system, $updates);
        $this->notifySubscribers($system);
    });

    return $this->refreshSystem($system);
}

// 3. Error Handling
public function updateSensorConfiguration(int $sensorId, array $config): Sensor
{
    try {
        $sensor = $this->sensorRepository->findOrFail($sensorId);

        // Validate configuration
        $this->validateSensorConfig($config);

        // Update with optimistic locking
        $updated = $this->sensorRepository->updateWithLock(
            $sensorId,
            $config,
            $sensor->version
        );

        $this->cache->invalidate("sensor.{$sensorId}");

        return $updated;

    } catch (ValidationException $e) {
        $this->logger->warning('Sensor configuration validation failed', [
            'sensor_id' => $sensorId,
            'errors' => $e->getErrors()
        ]);
        throw $e;

    } catch (ConcurrencyException $e) {
        $this->logger->info('Sensor update conflict detected', [
            'sensor_id' => $sensorId,
            'version' => $sensor->version
        ]);
        throw new ConflictException('Sensor was modified by another process');

    } catch (Exception $e) {
        $this->logger->error('Unexpected error updating sensor', [
            'sensor_id' => $sensorId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw new InfrastructureException('Failed to update sensor configuration');
    }
}

// 4. Documentation Standards
/**
 * Calculate the overall health score for an infrastructure system.
 *
 * This method aggregates various health indicators including:
 * - Operational status (40% weight)
 * - Sensor readings quality (30% weight)
 * - Maintenance status (20% weight)
 * - Active alerts count (10% weight)
 *
 * @param array $system System data including operational status,
 *                      sensor readings, maintenance info, and alerts
 * @return int Health score between 0-100
 * @throws InvalidArgumentException If system data is malformed
 *
 * @example
 * $system = [
 *     'operational' => true,
 *     'sensor_readings' => 95,
 *     'maintenance_status' => 90,
 *     'alert_count' => 0
 * ];
 * $score = $this->calculateSystemHealth($system); // Returns 95
 */
public function calculateSystemHealth(array $system): int
{
    // Implementation with detailed comments
}
```

### TypeScript/JavaScript Standards
```typescript
/**
 * TypeScript/JavaScript Code Quality Standards
 */

// 1. Type Definitions
interface SystemStatus {
    readonly id: string;
    name: string;
    operational: boolean;
    healthScore: number;
    lastUpdated: Date;
    sensors: Sensor[];
}

interface Sensor {
    readonly id: string;
    type: 'temperature' | 'humidity' | 'pressure';
    value: number;
    unit: string;
    threshold: {
        warning: number;
        critical: number;
    };
    status: 'normal' | 'warning' | 'critical';
}

// 2. Class Implementation
export class InfrastructureMonitor {
    private readonly apiService: ApiService;
    private readonly eventEmitter: EventEmitter;
    private systems: Map<string, SystemStatus> = new Map();

    constructor(
        apiService: ApiService,
        eventEmitter: EventEmitter
    ) {
        this.apiService = apiService;
        this.eventEmitter = eventEmitter;
        this.initialize();
    }

    private async initialize(): Promise<void> {
        try {
            await this.loadSystems();
            this.setupEventListeners();
            this.startMonitoring();
        } catch (error) {
            this.handleInitializationError(error);
        }
    }

    // 3. Async/Await Patterns
    public async getSystemStatus(systemId: string): Promise<SystemStatus> {
        const cached = this.systems.get(systemId);

        if (cached && this.isCacheValid(cached)) {
            return cached;
        }

        try {
            const response = await this.apiService.get<SystemStatus>(
                `/api/infrastructure/systems/${systemId}`
            );

            this.systems.set(systemId, response.data);
            return response.data;

        } catch (error) {
            this.logger.error('Failed to get system status', {
                system
