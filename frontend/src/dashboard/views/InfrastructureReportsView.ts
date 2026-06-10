import { DashboardApiService } from '../services/DashboardApiService.js';
import { AuthManager } from '../../auth.js';
import type { User } from '../types.js';

interface UtilizationReport {
  zone_id: string;
  zone_name: string;
  zone_type: string;
  building_name: string;
  total_area_sqm: number;
  occupied_area_sqm: number;
  available_area_sqm: number;
  utilization_percentage: number;
  peak_occupancy_time: string;
  average_occupancy: number;
  occupancy_trend: string;
  measurement_date: string;
}

interface MaintenanceReport {
  work_order_id: string;
  work_order_number: string;
  work_order_type: string;
  priority_level: string;
  system_id: string;
  system_name: string;
  zone_name: string;
  building_name: string;
  description: string;
  reported_at: string;
  scheduled_start: string;
  scheduled_end: string;
  actual_start: string;
  actual_end: string;
  work_order_status: string;
  estimated_duration_hours: number;
  actual_duration_hours: number;
  labor_cost: number;
  parts_cost: number;
  total_cost: number;
}

interface PerformanceReport {
  period: {
    start_date: string;
    end_date: string;
  };
  system_performance: {
    overall_health_score: number;
    operational_uptime: number;
    maintenance_compliance: number;
  };
  sensor_performance: {
    active_sensors: number;
    sensor_uptime: number;
    alerts_generated: number;
  };
  maintenance_metrics: {
    work_orders_completed: number;
    average_completion_time: number;
    preventive_maintenance_ratio: number;
  };
  energy_efficiency: {
    total_energy_consumption: number;
    average_efficiency_rating: number;
    energy_savings_achieved: number;
    renewable_energy_percentage: number;
  };
  environmental_compliance: {
    compliant_readings: number;
    warning_readings: number;
    non_compliant_readings: number;
    compliance_rate: number;
  };
}

export class InfrastructureReportsView {
  private container: HTMLElement;
  private apiService: DashboardApiService;
  private currentReport: string = 'utilization';
  private dateRange: { start: string; end: string } = {
    start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    end: new Date().toISOString().split('T')[0]
  };

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new DashboardApiService();
  }

  async render(user: User): Promise<string> {
    return `
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-2xl font-bold text-gray-900">Infrastructure Reports</h2>
          <div class="flex items-center space-x-4">
            <button id="back-to-dashboard" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
              ← Back to Dashboard
            </button>
            <button id="export-report" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
              Export Report
            </button>
          </div>
        </div>

        <!-- Report Controls -->
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex flex-wrap items-center gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
              <select id="report-type" class="border rounded px-3 py-2">
                <option value="utilization">Facility Utilization</option>
                <option value="maintenance">Maintenance Scheduling</option>
                <option value="performance">Performance Report</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
              <input type="date" id="start-date" value="${this.dateRange.start}" class="border rounded px-3 py-2">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
              <input type="date" id="end-date" value="${this.dateRange.end}" class="border rounded px-3 py-2">
            </div>

            <div class="flex items-end">
              <button id="generate-report" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Generate Report
              </button>
            </div>
          </div>
        </div>

        <!-- Report Content -->
        <div id="report-content" class="bg-white rounded-lg shadow p-6">
          <div class="text-center text-gray-500">
            <div class="text-4xl mb-4">📊</div>
            <p>Select report type and date range, then click "Generate Report"</p>
          </div>
        </div>
      </div>
    `;
  }

  async generateReport(reportType: string, startDate: string, endDate: string): Promise<string> {
    try {
      const reportContent = document.getElementById('report-content');
      if (reportContent) {
        reportContent.innerHTML = '<div class="text-center"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div><p class="mt-4 text-gray-600">Generating report...</p></div>';
      }

      switch (reportType) {
        case 'utilization':
          return await this.generateUtilizationReport(startDate, endDate);
        case 'maintenance':
          return await this.generateMaintenanceReport(startDate, endDate);
        case 'performance':
          return await this.generatePerformanceReport(startDate, endDate);
        default:
          throw new Error('Invalid report type');
      }
    } catch (error) {
      console.error('Failed to generate report:', error);
      return `
        <div class="text-center text-red-500 p-8">
          <div class="text-6xl mb-4">❌</div>
          <h3 class="text-xl font-semibold mb-2">Failed to Generate Report</h3>
          <p class="text-gray-600">Please check your parameters and try again.</p>
        </div>
      `;
    }
  }

  private async generateUtilizationReport(startDate: string, endDate: string): Promise<string> {
    // Fetch utilization data from API
    const response = await fetch(`/backend/api/infrastructure/reports?type=utilization&start_date=${startDate}&end_date=${endDate}`, {
      headers: {
        'Authorization': `Bearer ${AuthManager.getInstance().getToken() ?? ''}`,
        'Content-Type': 'application/json'
      }
    });

    if (!response.ok) {
      throw new Error('Failed to fetch utilization data');
    }

    const data: UtilizationReport[] = await response.json();

    const totalZones = data.length;
    const avgUtilization = data.reduce((sum, zone) => sum + zone.utilization_percentage, 0) / totalZones;
    const highUtilizationZones = data.filter(zone => zone.utilization_percentage >= 80).length;
    const lowUtilizationZones = data.filter(zone => zone.utilization_percentage < 30).length;

    return `
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="text-xl font-semibold">Facility Utilization Report</h3>
          <span class="text-sm text-gray-500">${startDate} to ${endDate}</span>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="bg-blue-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-blue-600">${totalZones}</div>
            <div class="text-sm text-blue-800">Total Zones</div>
          </div>
          <div class="bg-green-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-green-600">${avgUtilization.toFixed(1)}%</div>
            <div class="text-sm text-green-800">Average Utilization</div>
          </div>
          <div class="bg-red-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-red-600">${highUtilizationZones}</div>
            <div class="text-sm text-red-800">High Utilization (≥80%)</div>
          </div>
          <div class="bg-yellow-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-yellow-600">${lowUtilizationZones}</div>
            <div class="text-sm text-yellow-800">Low Utilization (<30%)</div>
          </div>
        </div>

        <!-- Utilization Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full bg-white border">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Zone</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Building</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Area (sqm)</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Utilization</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Trend</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              ${data.map(zone => `
                <tr>
                  <td class="px-4 py-2 text-sm font-medium text-gray-900">${zone.zone_name}</td>
                  <td class="px-4 py-2 text-sm text-gray-500 capitalize">${zone.zone_type.replace('_', ' ')}</td>
                  <td class="px-4 py-2 text-sm text-gray-500">${zone.building_name}</td>
                  <td class="px-4 py-2 text-sm text-gray-500">${zone.total_area_sqm}</td>
                  <td class="px-4 py-2 text-sm">
                    <span class="px-2 py-1 rounded-full text-xs font-medium ${
                      zone.utilization_percentage >= 80 ? 'bg-red-100 text-red-800' :
                      zone.utilization_percentage >= 60 ? 'bg-yellow-100 text-yellow-800' :
                      'bg-green-100 text-green-800'
                    }">
                      ${zone.utilization_percentage}%
                    </span>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-500 capitalize">${zone.occupancy_trend}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  private async generateMaintenanceReport(startDate: string, endDate: string): Promise<string> {
    // Fetch maintenance data from API
    const response = await fetch(`/backend/api/infrastructure/reports?type=maintenance&start_date=${startDate}&end_date=${endDate}`, {
      headers: {
        'Authorization': `Bearer ${AuthManager.getInstance().getToken() ?? ''}`,
        'Content-Type': 'application/json'
      }
    });

    if (!response.ok) {
      throw new Error('Failed to fetch maintenance data');
    }

    const data: MaintenanceReport[] = await response.json();

    const totalWorkOrders = data.length;
    const completedWorkOrders = data.filter(wo => wo.work_order_status === 'completed').length;
    const pendingWorkOrders = data.filter(wo => ['open', 'assigned', 'in_progress'].includes(wo.work_order_status)).length;
    const criticalWorkOrders = data.filter(wo => wo.priority_level === 'critical').length;
    const totalCost = data.reduce((sum, wo) => sum + (wo.total_cost || 0), 0);

    return `
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="text-xl font-semibold">Maintenance Scheduling Report</h3>
          <span class="text-sm text-gray-500">${startDate} to ${endDate}</span>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div class="bg-blue-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-blue-600">${totalWorkOrders}</div>
            <div class="text-sm text-blue-800">Total Work Orders</div>
          </div>
          <div class="bg-green-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-green-600">${completedWorkOrders}</div>
            <div class="text-sm text-green-800">Completed</div>
          </div>
          <div class="bg-yellow-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-yellow-600">${pendingWorkOrders}</div>
            <div class="text-sm text-yellow-800">Pending</div>
          </div>
          <div class="bg-red-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-red-600">${criticalWorkOrders}</div>
            <div class="text-sm text-red-800">Critical Priority</div>
          </div>
          <div class="bg-purple-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-purple-600">$${totalCost.toLocaleString()}</div>
            <div class="text-sm text-purple-800">Total Cost</div>
          </div>
        </div>

        <!-- Maintenance Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full bg-white border">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Work Order</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">System</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Scheduled</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cost</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              ${data.map(wo => `
                <tr>
                  <td class="px-4 py-2 text-sm font-medium text-gray-900">${wo.work_order_number}</td>
                  <td class="px-4 py-2 text-sm text-gray-500 capitalize">${wo.work_order_type}</td>
                  <td class="px-4 py-2 text-sm">
                    <span class="px-2 py-1 rounded-full text-xs font-medium ${
                      wo.priority_level === 'critical' ? 'bg-red-100 text-red-800' :
                      wo.priority_level === 'high' ? 'bg-orange-100 text-orange-800' :
                      wo.priority_level === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-green-100 text-green-800'
                    }">
                      ${wo.priority_level}
                    </span>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-500">${wo.system_name}</td>
                  <td class="px-4 py-2 text-sm">
                    <span class="px-2 py-1 rounded-full text-xs font-medium ${
                      wo.work_order_status === 'completed' ? 'bg-green-100 text-green-800' :
                      wo.work_order_status === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                      wo.work_order_status === 'assigned' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-gray-100 text-gray-800'
                    }">
                      ${wo.work_order_status.replace('_', ' ')}
                    </span>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-500">
                    ${wo.scheduled_start ? new Date(wo.scheduled_start).toLocaleDateString() : 'Not scheduled'}
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-500">
                    ${wo.total_cost ? '$' + wo.total_cost.toLocaleString() : 'N/A'}
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  private async generatePerformanceReport(startDate: string, endDate: string): Promise<string> {
    // Fetch performance data from API
    const response = await fetch(`/backend/api/infrastructure/reports?type=performance&start_date=${startDate}&end_date=${endDate}`, {
      headers: {
        'Authorization': `Bearer ${AuthManager.getInstance().getToken() ?? ''}`,
        'Content-Type': 'application/json'
      }
    });

    if (!response.ok) {
      throw new Error('Failed to fetch performance data');
    }

    const data: PerformanceReport = await response.json();

    return `
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="text-xl font-semibold">Infrastructure Performance Report</h3>
          <span class="text-sm text-gray-500">${data.period.start_date} to ${data.period.end_date}</span>
        </div>

        <!-- System Performance -->
        <div class="bg-white rounded-lg shadow p-6">
          <h4 class="text-lg font-semibold mb-4">System Performance</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-blue-600">${data.system_performance.overall_health_score}%</div>
              <div class="text-sm text-gray-600">Overall Health Score</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-green-600">${data.system_performance.operational_uptime}%</div>
              <div class="text-sm text-gray-600">Operational Uptime</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-purple-600">${data.system_performance.maintenance_compliance}%</div>
              <div class="text-sm text-gray-600">Maintenance Compliance</div>
            </div>
          </div>
        </div>

        <!-- Sensor Performance -->
        <div class="bg-white rounded-lg shadow p-6">
          <h4 class="text-lg font-semibold mb-4">Sensor Performance</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-blue-600">${data.sensor_performance.active_sensors}</div>
              <div class="text-sm text-gray-600">Active Sensors</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-green-600">${data.sensor_performance.sensor_uptime}%</div>
              <div class="text-sm text-gray-600">Sensor Uptime</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-red-600">${data.sensor_performance.alerts_generated}</div>
              <div class="text-sm text-gray-600">Alerts Generated</div>
            </div>
          </div>
        </div>

        <!-- Maintenance Metrics -->
        <div class="bg-white rounded-lg shadow p-6">
          <h4 class="text-lg font-semibold mb-4">Maintenance Metrics</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-blue-600">${data.maintenance_metrics.work_orders_completed}</div>
              <div class="text-sm text-gray-600">Work Orders Completed</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-green-600">${data.maintenance_metrics.average_completion_time.toFixed(1)}h</div>
              <div class="text-sm text-gray-600">Avg Completion Time</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-purple-600">${data.maintenance_metrics.preventive_maintenance_ratio}%</div>
              <div class="text-sm text-gray-600">Preventive Maintenance Ratio</div>
            </div>
          </div>
        </div>

        <!-- Energy Efficiency -->
        <div class="bg-white rounded-lg shadow p-6">
          <h4 class="text-lg font-semibold mb-4">Energy Efficiency</h4>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-blue-600">${data.energy_efficiency.total_energy_consumption.toLocaleString()}</div>
              <div class="text-sm text-gray-600">Total Energy (kWh)</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-green-600">${data.energy_efficiency.average_efficiency_rating}%</div>
              <div class="text-sm text-gray-600">Avg Efficiency Rating</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-purple-600">${data.energy_efficiency.energy_savings_achieved.toLocaleString()}</div>
              <div class="text-sm text-gray-600">Energy Savings</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-yellow-600">${data.energy_efficiency.renewable_energy_percentage}%</div>
              <div class="text-sm text-gray-600">Renewable Energy</div>
            </div>
          </div>
        </div>

        <!-- Environmental Compliance -->
        <div class="bg-white rounded-lg shadow p-6">
          <h4 class="text-lg font-semibold mb-4">Environmental Compliance</h4>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-green-600">${data.environmental_compliance.compliant_readings}</div>
              <div class="text-sm text-gray-600">Compliant</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-yellow-600">${data.environmental_compliance.warning_readings}</div>
              <div class="text-sm text-gray-600">Warning</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-red-600">${data.environmental_compliance.non_compliant_readings}</div>
              <div class="text-sm text-gray-600">Non-Compliant</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-blue-600">${data.environmental_compliance.compliance_rate}%</div>
              <div class="text-sm text-gray-600">Compliance Rate</div>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  setupEventListeners(): void {
    const backBtn = document.getElementById('back-to-dashboard');
    const exportBtn = document.getElementById('export-report');
    const generateBtn = document.getElementById('generate-report');
    const reportTypeSelect = document.getElementById('report-type') as HTMLSelectElement;
    const startDateInput = document.getElementById('start-date') as HTMLInputElement;
    const endDateInput = document.getElementById('end-date') as HTMLInputElement;

    if (backBtn) {
      backBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('showInfrastructureDashboard'));
      });
    }

    if (exportBtn) {
      exportBtn.addEventListener('click', () => {
        this.exportReport();
      });
    }

    if (generateBtn) {
      generateBtn.addEventListener('click', async () => {
        const reportType = reportTypeSelect?.value || 'utilization';
        const startDate = startDateInput?.value || this.dateRange.start;
        const endDate = endDateInput?.value || this.dateRange.end;

        this.dateRange = { start: startDate, end: endDate };
        this.currentReport = reportType;

        const reportContent = await this.generateReport(reportType, startDate, endDate);
        const contentElement = document.getElementById('report-content');
        if (contentElement) {
          contentElement.innerHTML = reportContent;
        }
      });
    }
  }

  private exportReport(): void {
    const reportContent = document.getElementById('report-content');
    if (!reportContent) return;

    // Create a simple text export (in a real app, you'd use a proper export library)
    const reportText = `
Infrastructure ${this.currentReport.charAt(0).toUpperCase() + this.currentReport.slice(1)} Report
Generated on: ${new Date().toLocaleString()}
Period: ${this.dateRange.start} to ${this.dateRange.end}

${reportContent.textContent}
    `;

    const blob = new Blob([reportText], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `infrastructure-${this.currentReport}-report-${new Date().toISOString().split('T')[0]}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }
}
