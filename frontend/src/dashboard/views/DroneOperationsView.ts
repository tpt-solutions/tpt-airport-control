import { DashboardApiService } from '../services/DashboardApiService.js';
import type { User } from '../types.js';

interface DroneStats {
  active_drones: number;
  flights_today: number;
  pending_approvals: number;
  airspace_reservations: number;
  active_violations: number;
  maintenance_due: number;
  compliance_issues: number;
  recent_incidents: Array<{
    incident_id: string;
    drone_id: string;
    incident_type: string;
    severity_level: string;
    reported_at: string;
  }>;
  traffic_density: {
    low: number;
    medium: number;
    high: number;
    restricted: number;
  };
}

interface Drone {
  drone_id: string;
  registration_number: string;
  drone_type: string;
  owner_name: string;
  operational_status: string;
  total_flights: number;
  incident_count: number;
  active_violations: number;
  recent_flights: number;
}

interface FlightPlan {
  flight_plan_id: string;
  flight_plan_number: string;
  drone_id: string;
  registration_number: string;
  owner_name: string;
  planned_departure: string;
  planned_arrival: string;
  status: string;
  purpose: string;
  risk_score: number;
}

export class DroneOperationsView {
  private container: HTMLElement;
  private apiService: DashboardApiService;
  private stats: DroneStats | null = null;
  private drones: Drone[] = [];
  private flightPlans: FlightPlan[] = [];

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new DashboardApiService();
  }

  async render(user: User): Promise<string> {
    try {
      // Fetch drone operations data
      const [statsResponse, dronesResponse, plansResponse] = await Promise.all([
        fetch('/backend/api/drones/dashboard', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch('/backend/api/drones?drones&limit=20', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch('/backend/api/drones/flight-plans?limit=10', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        })
      ]);

      if (statsResponse.ok) {
        this.stats = await statsResponse.json();
      }

      if (dronesResponse.ok) {
        this.drones = await dronesResponse.json();
      }

      if (plansResponse.ok) {
        this.flightPlans = await plansResponse.json();
      }

      return `
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Drone Operations Control</h2>
            <div class="flex space-x-2">
              <button id="refresh-drone-data" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Refresh
              </button>
              <button id="view-drone-reports" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                View Reports
              </button>
              <button id="new-flight-plan" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700">
                New Flight Plan
              </button>
            </div>
          </div>

          <!-- System Status Alert -->
          ${this.renderSystemStatus()}

          <!-- Key Metrics -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            ${this.renderMetricCard('Active Drones', this.stats?.active_drones || 0, '🚁', 'blue')}
            ${this.renderMetricCard('Flights Today', this.stats?.flights_today || 0, '✈️', 'green')}
            ${this.renderMetricCard('Pending Approvals', this.stats?.pending_approvals || 0, '⏳', 'orange')}
            ${this.renderMetricCard('Active Violations', this.stats?.active_violations || 0, '⚠️', 'red')}
          </div>

          <!-- Traffic Density -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Airspace Traffic Density</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              ${this.renderTrafficDensityCard('Low', this.stats?.traffic_density.low || 0, 'green')}
              ${this.renderTrafficDensityCard('Medium', this.stats?.traffic_density.medium || 0, 'yellow')}
              ${this.renderTrafficDensityCard('High', this.stats?.traffic_density.high || 0, 'orange')}
              ${this.renderTrafficDensityCard('Restricted', this.stats?.traffic_density.restricted || 0, 'red')}
            </div>
          </div>

          <!-- Recent Incidents -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Recent Safety Incidents</h3>
            <div class="space-y-3">
              ${this.stats?.recent_incidents.map(incident => this.renderIncidentCard(incident)).join('') || '<p class="text-gray-500">No recent incidents</p>'}
            </div>
          </div>

          <!-- Active Drones -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Active Drone Fleet</h3>
            <div class="overflow-x-auto">
              <table class="min-w-full bg-white border">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Registration</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Flights</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Incidents</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  ${this.drones.map(drone => `
                    <tr>
                      <td class="px-4 py-2 text-sm font-medium text-gray-900">${drone.registration_number}</td>
                      <td class="px-4 py-2 text-sm text-gray-500">${drone.owner_name}</td>
                      <td class="px-4 py-2 text-sm text-gray-500 capitalize">${drone.drone_type}</td>
                      <td class="px-4 py-2 text-sm">
                        <span class="px-2 py-1 rounded-full text-xs font-medium ${
                          drone.operational_status === 'active' ? 'bg-green-100 text-green-800' :
                          drone.operational_status === 'maintenance' ? 'bg-yellow-100 text-yellow-800' :
                          'bg-red-100 text-red-800'
                        }">
                          ${drone.operational_status}
                        </span>
                      </td>
                      <td class="px-4 py-2 text-sm text-gray-500">${drone.total_flights}</td>
                      <td class="px-4 py-2 text-sm text-gray-500">${drone.incident_count}</td>
                      <td class="px-4 py-2 text-sm">
                        <button class="text-blue-600 hover:text-blue-800" onclick="viewDrone('${drone.drone_id}')">View</button>
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          </div>

          <!-- Pending Flight Plans -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Pending Flight Plan Approvals</h3>
            <div class="space-y-3">
              ${this.flightPlans.filter(plan => plan.status === 'planned').map(plan => this.renderFlightPlanCard(plan)).join('') || '<p class="text-gray-500">No pending approvals</p>'}
            </div>
          </div>

          <!-- Airspace Reservations -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Active Airspace Reservations</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div class="text-center p-4 border rounded">
                <div class="text-2xl font-bold text-blue-600">${this.stats?.airspace_reservations || 0}</div>
                <div class="text-sm text-gray-600">Active Reservations</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-2xl font-bold text-orange-600">${this.stats?.maintenance_due || 0}</div>
                <div class="text-sm text-gray-600">Maintenance Due</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-2xl font-bold text-red-600">${this.stats?.compliance_issues || 0}</div>
                <div class="text-sm text-gray-600">Compliance Issues</div>
              </div>
            </div>
          </div>
        </div>
      `;
    } catch (error) {
      console.error('Failed to load drone operations dashboard:', error);
      return `
        <div class="text-center text-red-500 p-8">
          <div class="text-6xl mb-4">🚁</div>
          <h3 class="text-xl font-semibold mb-2">Failed to Load Drone Operations Data</h3>
          <p class="text-gray-600">Please check your connection and try again.</p>
          <button id="retry-drone-data" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Retry
          </button>
        </div>
      `;
    }
  }

  private renderSystemStatus(): string {
    if (!this.stats) return '';

    const hasIssues = (this.stats.active_violations > 0) || (this.stats.pending_approvals > 0) || (this.stats.compliance_issues > 0);

    if (!hasIssues) {
      return `
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-green-800">All Systems Operational</h3>
              <div class="mt-2 text-sm text-green-700">
                <p>Drone operations are running smoothly with no active issues.</p>
              </div>
            </div>
          </div>
        </div>
      `;
    }

    return `
      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-yellow-800">System Attention Required</h3>
            <div class="mt-2 text-sm text-yellow-700">
              <p>
                ${this.stats.pending_approvals > 0 ? `${this.stats.pending_approvals} flight plans pending approval. ` : ''}
                ${this.stats.active_violations > 0 ? `${this.stats.active_violations} active violations. ` : ''}
                ${this.stats.compliance_issues > 0 ? `${this.stats.compliance_issues} compliance issues. ` : ''}
              </p>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  private renderMetricCard(title: string, value: number, icon: string, color: string): string {
    const colorClasses = {
      blue: 'bg-blue-500',
      green: 'bg-green-500',
      orange: 'bg-orange-500',
      red: 'bg-red-500'
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

  private renderTrafficDensityCard(level: string, count: number, color: string): string {
    return `
      <div class="text-center p-4 border rounded">
        <div class="text-2xl font-bold text-${color}-600">${count}</div>
        <div class="text-sm text-gray-600">${level}</div>
      </div>
    `;
  }

  private renderIncidentCard(incident: DroneStats['recent_incidents'][0]): string {
    const severityColors = {
      critical: 'red',
      high: 'orange',
      medium: 'yellow',
      low: 'green'
    };

    return `
      <div class="flex items-center justify-between p-3 border rounded">
        <div class="flex-1">
          <div class="flex items-center space-x-2 mb-1">
            <span class="text-sm font-medium">${incident.incident_type.replace('_', ' ')}</span>
            <span class="px-2 py-1 text-xs rounded-full bg-${severityColors[incident.severity_level as keyof typeof severityColors]}-100 text-${severityColors[incident.severity_level as keyof typeof severityColors]}-800">
              ${incident.severity_level}
            </span>
          </div>
          <p class="text-sm text-gray-600">Drone: ${incident.drone_id}</p>
          <p class="text-xs text-gray-500">${new Date(incident.reported_at).toLocaleString()}</p>
        </div>
      </div>
    `;
  }

  private renderFlightPlanCard(plan: FlightPlan): string {
    const riskColor = plan.risk_score >= 80 ? 'red' : plan.risk_score >= 60 ? 'yellow' : 'green';

    return `
      <div class="flex items-center justify-between p-4 border rounded">
        <div class="flex-1">
          <div class="flex items-center space-x-2 mb-2">
            <span class="text-sm font-medium">${plan.flight_plan_number}</span>
            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">${plan.status}</span>
            <span class="px-2 py-1 text-xs rounded-full bg-${riskColor}-100 text-${riskColor}-800">
              Risk: ${plan.risk_score}%
            </span>
          </div>
          <p class="text-sm text-gray-600">${plan.registration_number} - ${plan.owner_name}</p>
          <p class="text-xs text-gray-500">${plan.purpose} | ${new Date(plan.planned_departure).toLocaleString()}</p>
        </div>
        <div class="flex space-x-2">
          <button class="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700" onclick="approveFlight('${plan.flight_plan_id}')">
            Approve
          </button>
          <button class="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700" onclick="rejectFlight('${plan.flight_plan_id}')">
            Reject
          </button>
        </div>
      </div>
    `;
  }

  setupEventListeners(): void {
    const refreshBtn = document.getElementById('refresh-drone-data');
    const reportsBtn = document.getElementById('view-drone-reports');
    const newPlanBtn = document.getElementById('new-flight-plan');
    const retryBtn = document.getElementById('retry-drone-data');

    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshDroneData'));
      });
    }

    if (reportsBtn) {
      reportsBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('showDroneReports'));
      });
    }

    if (newPlanBtn) {
      newPlanBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('newFlightPlan'));
      });
    }

    if (retryBtn) {
      retryBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshDroneData'));
      });
    }
  }
}
