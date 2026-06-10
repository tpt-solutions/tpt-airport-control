import { DashboardApiService } from '../services/DashboardApiService.js';
import { AuthManager } from '../../auth.js';
import type { User } from '../types.js';

interface OperationsReport {
  period: {
    start_date: string;
    end_date: string;
  };
  flight_operations: {
    total_flights: number;
    by_purpose: Array<{
      purpose: string;
      count: number;
      avg_duration: number;
    }>;
    completion_rate: number;
  };
  safety_metrics: {
    total_incidents: number;
    incident_rate: number;
    violations_count: number;
  };
  compliance_status: {
    compliant_registrations: number;
    expired_registrations: number;
    maintenance_compliance: number;
  };
  airspace_utilization: {
    reservations_approved: number;
    peak_traffic_hours: Array<{
      hour: number;
      avg_drones: number;
    }>;
  };
}

interface FlightOperation {
  operation_id: string;
  flight_plan_id: string;
  drone_id: string;
  registration_number: string;
  owner_name: string;
  actual_departure: string;
  actual_arrival: string;
  actual_duration_minutes: number;
  status: string;
  purpose: string;
  incidents_reported: any[];
}

interface AirspaceReservation {
  reservation_id: string;
  reservation_number: string;
  zone_id: string;
  zone_name: string;
  zone_type: string;
  drone_id: string;
  registration_number: string;
  owner_name: string;
  reservation_start: string;
  reservation_end: string;
  status: string;
  activity_type: string;
}

export class DroneReportsView {
  private container: HTMLElement;
  private apiService: DashboardApiService;
  private currentReport: string = 'operations';
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
          <h2 class="text-2xl font-bold text-gray-900">Drone Operations Reports</h2>
          <div class="flex items-center space-x-4">
            <button id="back-to-drone-dashboard" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
              ← Back to Dashboard
            </button>
            <button id="export-drone-report" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
              Export Report
            </button>
          </div>
        </div>

        <!-- Report Controls -->
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex flex-wrap items-center gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
              <select id="drone-report-type" class="border rounded px-3 py-2">
                <option value="operations">Operations Report</option>
                <option value="airspace">Airspace Utilization</option>
                <option value="safety">Safety & Compliance</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
              <input type="date" id="drone-start-date" value="${this.dateRange.start}" class="border rounded px-3 py-2">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
              <input type="date" id="drone-end-date" value="${this.dateRange.end}" class="border rounded px-3 py-2">
            </div>

            <div class="flex items-end">
              <button id="generate-drone-report" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Generate Report
              </button>
            </div>
          </div>
        </div>

        <!-- Report Content -->
        <div id="drone-report-content" class="bg-white rounded-lg shadow p-6">
          <div class="text-center text-gray-500">
            <div class="text-4xl mb-4">🚁</div>
            <p>Select report type and date range, then click "Generate Report"</p>
          </div>
        </div>
      </div>
    `;
  }

  async generateReport(reportType: string, startDate: string, endDate: string): Promise<string> {
    try {
      const reportContent = document.getElementById('drone-report-content');
      if (reportContent) {
        reportContent.innerHTML = '<div class="text-center"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div><p class="mt-4 text-gray-600">Generating report...</p></div>';
      }

      switch (reportType) {
        case 'operations':
          return await this.generateOperationsReport(startDate, endDate);
        case 'airspace':
          return await this.generateAirspaceReport(startDate, endDate);
        case 'safety':
          return await this.generateSafetyReport(startDate, endDate);
        default:
          throw new Error('Invalid report type');
      }
    } catch (error) {
      console.error('Failed to generate drone report:', error);
      return `
        <div class="text-center text-red-500 p-8">
          <div class="text-6xl mb-4">❌</div>
          <h3 class="text-xl font-semibold mb-2">Failed to Generate Report</h3>
          <p class="text-gray-600">Please check your parameters and try again.</p>
        </div>
      `;
    }
  }

  private async generateOperationsReport(startDate: string, endDate: string): Promise<string> {
    // Fetch operations data from API
    const response = await fetch(`/backend/api/drones/reports/operations?start_date=${startDate}&end_date=${endDate}`, {
      headers: {
        'Authorization': `Bearer ${AuthManager.getInstance().getToken() ?? ''}`,
        'Content-Type': 'application/json'
      }
    });

    if (!response.ok) {
      throw new Error('Failed to fetch operations data');
    }

    const data: OperationsReport = await response.json();

    return `
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="text-xl font-semibold">Drone Operations Report</h3>
          <span class="text-sm text-gray-500">${data.period.start_date} to ${data.period.end_date}</span>
        </div>

        <!-- Flight Operations Summary -->
        <div class="bg-white rounded-lg shadow p-6">
          <h4 class="text-lg font-semibold mb-4">Flight Operations Summary</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-blue-600">${data.flight_operations.total_flights}</div>
              <div class="text-sm text-gray-600">Total Flights</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-green-600">${data.flight_operations.completion_rate}%</div>
              <div class="text-sm text-gray-600">Completion Rate</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-purple-600">${data.flight_operations.by_purpose.length}</div>
              <div class="text-sm text-gray-600">Flight Purposes</div>
            </div>
          </div>
        </div>

        <!-- Flights by Purpose -->
        <div class="bg-white rounded-lg shadow p-6">
          <h4 class="text-lg font-semibold mb-4">Flights by Purpose</h4>
          <div class="space-y-3">
            ${data.flight_operations.by_purpose.map(purpose => `
              <div class="flex items-center justify-between p-3 border rounded">
                <div>
                  <h5 class="font-medium capitalize">${purpose.purpose.replace('_', ' ')}</h5>
                  <p class="text-sm text-gray-600">${purpose.count} flights</p>
                </div>
                <div class="text-right">
                  <div class="text-lg font-bold text-blue-600">${purpose.avg_duration.toFixed(1)} min</div>
                  <div class="text-sm text-gray-500">Avg Duration</div>
                </div>
              </div>
            `).join('')}
          </div>
        </div>

        <!-- Safety Metrics -->
        <div class="bg-white rounded-lg shadow p-6">
          <h4 class="text-lg font-semibold mb-4">Safety Metrics</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-red-600">${data.safety_metrics.total_incidents}</div>
              <div class="text-sm text-gray-600">Total Incidents</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-orange-600">${data.safety_metrics.incident_rate.toFixed(2)}%</div>
              <div class="text-sm text-gray-600">Incident Rate</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-yellow-600">${data.safety_metrics.violations_count}</div>
              <div class="text-sm text-gray-600">Violations</div>
            </div>
          </div>
        </div>

        <!-- Compliance Status -->
        <div class="bg-white rounded-lg shadow p-6">
          <h4 class="text-lg font-semibold mb-4">Compliance Status</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-green-600">${data.compliance_status.compliant_registrations}</div>
              <div class="text-sm text-gray-600">Compliant Registrations</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-red-600">${data.compliance_status.expired_registrations}</div>
              <div class="text-sm text-gray-600">Expired Registrations</div>
            </div>
            <div class="text-center p-4 border rounded">
              <div class="text-3xl font-bold text-blue-600">${data.compliance_status.maintenance_compliance}%</div>
              <div class="text-sm text-gray-600">Maintenance Compliance</div>
            </div>
          </div>
        </div>

        <!-- Peak Traffic Hours -->
        <div class="bg-white rounded-lg shadow p-6">
          <h4 class="text-lg font-semibold mb-4">Peak Traffic Hours</h4>
          <div class="space-y-2">
            ${data.airspace_utilization.peak_traffic_hours.map(hour => `
              <div class="flex items-center justify-between p-2 border rounded">
                <span class="font-medium">${hour.hour}:00</span>
                <span class="text-blue-600 font-bold">${hour.avg_drones.toFixed(1)} avg drones</span>
              </div>
            `).join('')}
          </div>
        </div>
      </div>
    `;
  }

  private async generateAirspaceReport(startDate: string, endDate: string): Promise<string> {
    // Fetch airspace utilization data
    const response = await fetch(`/backend/api/drones/reports/airspace?start_date=${startDate}&end_date=${endDate}`, {
      headers: {
        'Authorization': `Bearer ${AuthManager.getInstance().getToken() ?? ''}`,
        'Content-Type': 'application/json'
      }
    });

    if (!response.ok) {
      throw new Error('Failed to fetch airspace data');
    }

    const data: AirspaceReservation[] = await response.json();

    const totalReservations = data.length;
    const approvedReservations = data.filter(r => r.status === 'approved').length;
    const activeReservations = data.filter(r => r.status === 'active').length;
    const zonesUsed = [...new Set(data.map(r => r.zone_name))].length;

    return `
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="text-xl font-semibold">Airspace Utilization Report</h3>
          <span class="text-sm text-gray-500">${startDate} to ${endDate}</span>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="bg-blue-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-blue-600">${totalReservations}</div>
            <div class="text-sm text-blue-800">Total Reservations</div>
          </div>
          <div class="bg-green-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-green-600">${approvedReservations}</div>
            <div class="text-sm text-green-800">Approved</div>
          </div>
          <div class="bg-purple-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-purple-600">${activeReservations}</div>
            <div class="text-sm text-purple-800">Active</div>
          </div>
          <div class="bg-orange-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-orange-600">${zonesUsed}</div>
            <div class="text-sm text-orange-800">Zones Used</div>
          </div>
        </div>

        <!-- Reservations Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full bg-white border">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reservation</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Drone</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Zone</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Start Time</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">End Time</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Activity</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              ${data.map(reservation => `
                <tr>
                  <td class="px-4 py-2 text-sm font-medium text-gray-900">${reservation.reservation_number}</td>
                  <td class="px-4 py-2 text-sm text-gray-500">
                    ${reservation.registration_number}<br>
                    <span class="text-xs text-gray-400">${reservation.owner_name}</span>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-500">
                    ${reservation.zone_name}<br>
                    <span class="text-xs text-gray-400 capitalize">${reservation.zone_type.replace('_', ' ')}</span>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-500">${new Date(reservation.reservation_start).toLocaleString()}</td>
                  <td class="px-4 py-2 text-sm text-gray-500">${new Date(reservation.reservation_end).toLocaleString()}</td>
                  <td class="px-4 py-2 text-sm">
                    <span class="px-2 py-1 rounded-full text-xs font-medium ${
                      reservation.status === 'approved' ? 'bg-green-100 text-green-800' :
                      reservation.status === 'active' ? 'bg-blue-100 text-blue-800' :
                      reservation.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-gray-100 text-gray-800'
                    }">
                      ${reservation.status}
                    </span>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-500 capitalize">${reservation.activity_type?.replace('_', ' ') || 'N/A'}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  private async generateSafetyReport(startDate: string, endDate: string): Promise<string> {
    // Fetch safety and compliance data
    const [incidentsResponse, violationsResponse] = await Promise.all([
      fetch(`/backend/api/drones/incidents?start_date=${startDate}&end_date=${endDate}`, {
        headers: {
          'Authorization': `Bearer ${AuthManager.getInstance().getToken() ?? ''}`,
          'Content-Type': 'application/json'
        }
      }),
      fetch(`/backend/api/drones/violations?start_date=${startDate}&end_date=${endDate}`, {
        headers: {
          'Authorization': `Bearer ${AuthManager.getInstance().getToken() ?? ''}`,
          'Content-Type': 'application/json'
        }
      })
    ]);

    const incidents = incidentsResponse.ok ? await incidentsResponse.json() : [];
    const violations = violationsResponse.ok ? await violationsResponse.json() : [];

    const criticalIncidents = incidents.filter((i: any) => i.severity_level === 'critical').length;
    const highSeverityIncidents = incidents.filter((i: any) => i.severity_level === 'high').length;
    const totalFines = violations.reduce((sum: number, v: any) => sum + (v.fine_amount || 0), 0);

    return `
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="text-xl font-semibold">Safety & Compliance Report</h3>
          <span class="text-sm text-gray-500">${startDate} to ${endDate}</span>
        </div>

        <!-- Safety Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="bg-red-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-red-600">${incidents.length}</div>
            <div class="text-sm text-red-800">Total Incidents</div>
          </div>
          <div class="bg-orange-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-orange-600">${criticalIncidents}</div>
            <div class="text-sm text-orange-800">Critical Incidents</div>
          </div>
          <div class="bg-yellow-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-yellow-600">${violations.length}</div>
            <div class="text-sm text-yellow-800">Violations</div>
          </div>
          <div class="bg-purple-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-purple-600">$${totalFines.toLocaleString()}</div>
            <div class="text-sm text-purple-800">Total Fines</div>
          </div>
        </div>

        <!-- Incidents Table -->
        <div class="bg-white rounded-lg shadow p-6">
          <h4 class="text-lg font-semibold mb-4">Safety Incidents</h4>
          <div class="overflow-x-auto">
            <table class="min-w-full bg-white border">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Incident</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Severity</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Drone</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                ${incidents.map((incident: any) => `
                  <tr>
                    <td class="px-4 py-2 text-sm font-medium text-gray-900">${incident.incident_number}</td>
                    <td class="px-4 py-2 text-sm text-gray-500 capitalize">${incident.incident_type.replace('_', ' ')}</td>
                    <td class="px-4 py-2 text-sm">
                      <span class="px-2 py-1 rounded-full text-xs font-medium ${
                        incident.severity_level === 'critical' ? 'bg-red-100 text-red-800' :
                        incident.severity_level === 'high' ? 'bg-orange-100 text-orange-800' :
                        incident.severity_level === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-green-100 text-green-800'
                      }">
                        ${incident.severity_level}
                      </span>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-500">${incident.drone_id || 'N/A'}</td>
                    <td class="px-4 py-2 text-sm text-gray-500">${new Date(incident.reported_at).toLocaleDateString()}</td>
                    <td class="px-4 py-2 text-sm text-gray-500">${incident.incident_description.substring(0, 50)}...</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>

        <!-- Violations Table -->
        <div class="bg-white rounded-lg shadow p-6">
          <h4 class="text-lg font-semibold mb-4">Airspace Violations</h4>
          <div class="overflow-x-auto">
            <table class="min-w-full bg-white border">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Violation Type</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Severity</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Drone</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fine</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                ${violations.map((violation: any) => `
                  <tr>
                    <td class="px-4 py-2 text-sm font-medium text-gray-900 capitalize">${violation.violation_type.replace('_', ' ')}</td>
                    <td class="px-4 py-2 text-sm">
                      <span class="px-2 py-1 rounded-full text-xs font-medium ${
                        violation.severity_level === 'critical' ? 'bg-red-100 text-red-800' :
                        violation.severity_level === 'high' ? 'bg-orange-100 text-orange-800' :
                        violation.severity_level === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-green-100 text-green-800'
                      }">
                        ${violation.severity_level}
                      </span>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-500">${violation.drone_id || 'N/A'}</td>
                    <td class="px-4 py-2 text-sm text-gray-500">${new Date(violation.violation_timestamp).toLocaleDateString()}</td>
                    <td class="px-4 py-2 text-sm text-gray-500">${violation.fine_amount ? '$' + violation.fine_amount : 'N/A'}</td>
                    <td class="px-4 py-2 text-sm">
                      <span class="px-2 py-1 rounded-full text-xs font-medium ${
                        violation.status === 'resolved' ? 'bg-green-100 text-green-800' :
                        violation.status === 'open' ? 'bg-red-100 text-red-800' :
                        'bg-yellow-100 text-yellow-800'
                      }">
                        ${violation.status}
                      </span>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    `;
  }

  setupEventListeners(): void {
    const backBtn = document.getElementById('back-to-drone-dashboard');
    const exportBtn = document.getElementById('export-drone-report');
    const generateBtn = document.getElementById('generate-drone-report');
    const reportTypeSelect = document.getElementById('drone-report-type') as HTMLSelectElement;
    const startDateInput = document.getElementById('drone-start-date') as HTMLInputElement;
    const endDateInput = document.getElementById('drone-end-date') as HTMLInputElement;

    if (backBtn) {
      backBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('showDroneDashboard'));
      });
    }

    if (exportBtn) {
      exportBtn.addEventListener('click', () => {
        this.exportReport();
      });
    }

    if (generateBtn) {
      generateBtn.addEventListener('click', async () => {
        const reportType = reportTypeSelect?.value || 'operations';
        const startDate = startDateInput?.value || this.dateRange.start;
        const endDate = endDateInput?.value || this.dateRange.end;

        this.dateRange = { start: startDate, end: endDate };
        this.currentReport = reportType;

        const reportContent = await this.generateReport(reportType, startDate, endDate);
        const contentElement = document.getElementById('drone-report-content');
        if (contentElement) {
          contentElement.innerHTML = reportContent;
        }
      });
    }
  }

  private exportReport(): void {
    const reportContent = document.getElementById('drone-report-content');
    if (!reportContent) return;

    // Create a simple text export (in a real app, you'd use a proper export library)
    const reportText = `
Drone ${this.currentReport.charAt(0).toUpperCase() + this.currentReport.slice(1)} Report
Generated on: ${new Date().toLocaleString()}
Period: ${this.dateRange.start} to ${this.dateRange.end}

${reportContent.textContent}
    `;

    const blob = new Blob([reportText], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `drone-${this.currentReport}-report-${new Date().toISOString().split('T')[0]}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }
}
