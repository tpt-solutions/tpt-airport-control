import { DashboardApiService } from '../services/DashboardApiService.js';
import type { User } from '../types.js';

interface InfrastructureStats {
  building_systems_status: Array<{
    system_type: string;
    operational_count: number;
    maintenance_count: number;
    failed_count: number;
    avg_health_score: number;
  }>;
  sensor_status: Array<{
    sensor_type: string;
    active_count: number;
    failed_count: number;
    alerts_today: number;
  }>;
  facility_zones_utilization: Array<{
    zone_name: string;
    zone_type: string;
    utilization_rate: number;
    operational_status: string;
  }>;
  active_alerts: number;
  maintenance_due: number;
  energy_consumption_today: number;
  environmental_compliance: {
    compliant_zones: number;
    warning_zones: number;
    non_compliant_zones: number;
  };
  recent_work_orders: Array<{
    work_order_number: string;
    work_order_type: string;
    priority_level: string;
    status: string;
    description: string;
  }>;
}

export class InfrastructureManagementView {
  private container: HTMLElement;
  private apiService: DashboardApiService;
  private stats: InfrastructureStats | null = null;

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new DashboardApiService();
  }

  async render(user: User): Promise<string> {
    try {
      // Fetch infrastructure dashboard data
      const response = await fetch('/backend/api/infrastructure/dashboard', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json'
        }
      });

      if (!response.ok) {
        throw new Error('Failed to fetch infrastructure data');
      }

      this.stats = await response.json();

      return `
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Infrastructure Management</h2>
            <div class="flex space-x-2">
              <button id="refresh-infrastructure" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Refresh
              </button>
              <button id="view-reports" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                View Reports
              </button>
            </div>
          </div>

          <!-- Alert Summary -->
          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold">System Status</h3>
              <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                  <div class="w-3 h-3 rounded-full ${this.stats.active_alerts > 0 ? 'bg-red-500' : 'bg-green-500'}"></div>
                  <span class="text-sm font-medium">
                    ${this.stats.active_alerts > 0 ? `${this.stats.active_alerts} Active Alerts` : 'All Systems Normal'}
                  </span>
                </div>
                <span class="text-sm text-gray-500">Last updated: ${new Date().toLocaleTimeString()}</span>
              </div>
            </div>
          </div>

          <!-- Key Metrics -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            ${this.renderMetricCard('Active Alerts', this.stats!.active_alerts, '🚨', 'red')}
            ${this.renderMetricCard('Maintenance Due', this.stats!.maintenance_due, '🔧', 'orange')}
            ${this.renderMetricCard('Energy Today (kWh)', this.stats!.energy_consumption_today || 0, '⚡', 'blue')}
            ${this.renderMetricCard('Compliant Zones', this.stats!.environmental_compliance.compliant_zones, '✅', 'green')}
          </div>

          <!-- Building Systems Status -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Building Systems Status</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              ${this.stats!.building_systems_status.map(system => this.renderSystemCard(system)).join('')}
            </div>
          </div>

          <!-- IoT Sensors Status -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">IoT Sensors Status</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              ${this.stats!.sensor_status.map(sensor => this.renderSensorCard(sensor)).join('')}
            </div>
          </div>

          <!-- Facility Zones Utilization -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Facility Zones Utilization</h3>
            <div class="space-y-3">
              ${this.stats!.facility_zones_utilization.map(zone => this.renderZoneCard(zone)).join('')}
            </div>
          </div>

          <!-- Recent Work Orders -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Recent Maintenance Work Orders</h3>
            <div class="space-y-3">
              ${this.stats!.recent_work_orders.map(order => this.renderWorkOrderCard(order)).join('')}
            </div>
          </div>

          <!-- Environmental Compliance -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Environmental Compliance</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              ${this.renderComplianceCard('Compliant', this.stats!.environmental_compliance.compliant_zones, 'green')}
              ${this.renderComplianceCard('Warning', this.stats!.environmental_compliance.warning_zones, 'yellow')}
              ${this.renderComplianceCard('Non-Compliant', this.stats!.environmental_compliance.non_compliant_zones, 'red')}
            </div>
          </div>
        </div>
      `;
    } catch (error) {
      console.error('Failed to load infrastructure dashboard:', error);
      return `
        <div class="text-center text-red-500 p-8">
          <div class="text-6xl mb-4">⚠️</div>
          <h3 class="text-xl font-semibold mb-2">Failed to Load Infrastructure Data</h3>
          <p class="text-gray-600">Please check your connection and try again.</p>
          <button id="retry-infrastructure" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Retry
          </button>
        </div>
      `;
    }
  }

  private renderMetricCard(title: string, value: number, icon: string, color: string): string {
    const colorClasses = {
      red: 'bg-red-500',
      orange: 'bg-orange-500',
      blue: 'bg-blue-500',
      green: 'bg-green-500'
    };

    return `
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <div class="w-8 h-8 ${colorClasses[color as keyof typeof colorClasses]} rounded-full flex items-center justify-center text-white text-sm">
              ${icon}
            </div>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">${title}</p>
            <p class="text-2xl font-bold text-gray-900">${value.toLocaleString()}</p>
          </div>
        </div>
      </div>
    `;
  }

  private renderSystemCard(system: InfrastructureStats['building_systems_status'][0]): string {
    const healthColor = system.avg_health_score >= 80 ? 'green' : system.avg_health_score >= 60 ? 'yellow' : 'red';

    return `
      <div class="border rounded-lg p-4">
        <div class="flex items-center justify-between mb-2">
          <h4 class="font-medium capitalize">${system.system_type.replace('_', ' ')}</h4>
          <span class="text-sm text-${healthColor}-600 font-medium">${system.avg_health_score}%</span>
        </div>
        <div class="space-y-1 text-sm text-gray-600">
          <div class="flex justify-between">
            <span>Operational:</span>
            <span class="text-green-600">${system.operational_count}</span>
          </div>
          <div class="flex justify-between">
            <span>Maintenance:</span>
            <span class="text-yellow-600">${system.maintenance_count}</span>
          </div>
          <div class="flex justify-between">
            <span>Failed:</span>
            <span class="text-red-600">${system.failed_count}</span>
          </div>
        </div>
      </div>
    `;
  }

  private renderSensorCard(sensor: InfrastructureStats['sensor_status'][0]): string {
    return `
      <div class="border rounded-lg p-4">
        <div class="flex items-center justify-between mb-2">
          <h4 class="font-medium capitalize">${sensor.sensor_type}</h4>
          <span class="text-sm ${sensor.alerts_today > 0 ? 'text-red-600' : 'text-green-600'} font-medium">
            ${sensor.alerts_today > 0 ? `${sensor.alerts_today} alerts` : 'OK'}
          </span>
        </div>
        <div class="space-y-1 text-sm text-gray-600">
          <div class="flex justify-between">
            <span>Active:</span>
            <span class="text-green-600">${sensor.active_count}</span>
          </div>
          <div class="flex justify-between">
            <span>Failed:</span>
            <span class="text-red-600">${sensor.failed_count}</span>
          </div>
        </div>
      </div>
    `;
  }

  private renderZoneCard(zone: InfrastructureStats['facility_zones_utilization'][0]): string {
    const utilizationColor = zone.utilization_rate >= 80 ? 'red' : zone.utilization_rate >= 60 ? 'yellow' : 'green';

    return `
      <div class="flex items-center justify-between p-3 border rounded">
        <div>
          <h4 class="font-medium">${zone.zone_name}</h4>
          <p class="text-sm text-gray-600 capitalize">${zone.zone_type.replace('_', ' ')}</p>
        </div>
        <div class="text-right">
          <div class="text-lg font-bold text-${utilizationColor}-600">${zone.utilization_rate}%</div>
          <div class="text-sm text-gray-500 capitalize">${zone.operational_status}</div>
        </div>
      </div>
    `;
  }

  private renderWorkOrderCard(order: InfrastructureStats['recent_work_orders'][0]): string {
    const priorityColors = {
      critical: 'red',
      high: 'orange',
      medium: 'yellow',
      low: 'green'
    };

    return `
      <div class="flex items-center justify-between p-3 border rounded">
        <div class="flex-1">
          <div class="flex items-center space-x-2 mb-1">
            <span class="text-sm font-medium">${order.work_order_number}</span>
            <span class="px-2 py-1 text-xs rounded-full bg-${priorityColors[order.priority_level as keyof typeof priorityColors]}-100 text-${priorityColors[order.priority_level as keyof typeof priorityColors]}-800">
              ${order.priority_level}
            </span>
            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800 capitalize">
              ${order.status.replace('_', ' ')}
            </span>
          </div>
          <p class="text-sm text-gray-600">${order.description}</p>
          <p class="text-xs text-gray-500 capitalize">${order.work_order_type} maintenance</p>
        </div>
      </div>
    `;
  }

  private renderComplianceCard(status: string, count: number, color: string): string {
    return `
      <div class="text-center p-4 border rounded">
        <div class="text-2xl font-bold text-${color}-600">${count}</div>
        <div class="text-sm text-gray-600">${status}</div>
      </div>
    `;
  }

  setupEventListeners(): void {
    const refreshBtn = document.getElementById('refresh-infrastructure');
    const viewReportsBtn = document.getElementById('view-reports');
    const retryBtn = document.getElementById('retry-infrastructure');

    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshInfrastructure'));
      });
    }

    if (viewReportsBtn) {
      viewReportsBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('showInfrastructureReports'));
      });
    }

    if (retryBtn) {
      retryBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshInfrastructure'));
      });
    }
  }
}
