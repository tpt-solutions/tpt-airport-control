import { DashboardApiService } from '../services/DashboardApiService.js';
import type { User } from '../types.js';

interface SecurityStats {
  active_cameras: number;
  online_cameras: number;
  facial_recognition_matches_today: number;
  behavioral_anomalies_detected: number;
  active_threats: number;
  security_incidents_today: number;
  access_denials_today: number;
  crowd_density_alerts: number;
  system_health_score: number;
  ai_model_accuracy: number;
}

interface SecurityAlert {
  alert_id: string;
  alert_type: string;
  severity_level: string;
  alert_title: string;
  alert_description: string;
  location_zone: string;
  alert_timestamp: string;
  acknowledged: boolean;
}

interface ThreatEvent {
  event_id: string;
  event_type: string;
  severity_level: string;
  confidence_score: number;
  location_zone: string;
  camera_name: string;
  detection_timestamp: string;
  response_required: boolean;
}

interface CameraStatus {
  camera_id: string;
  camera_name: string;
  location_zone: string;
  status: string;
  last_ping: string;
  facial_recognition_enabled: boolean;
  behavioral_analytics_enabled: boolean;
}

export class AdvancedSecurityView {
  private container: HTMLElement;
  private apiService: DashboardApiService;
  private stats: SecurityStats | null = null;
  private activeAlerts: SecurityAlert[] = [];
  private recentThreats: ThreatEvent[] = [];
  private cameraStatuses: CameraStatus[] = [];

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new DashboardApiService();
  }

  async render(user: User): Promise<string> {
    try {
      // Fetch security data
      const [statsResponse, alertsResponse, threatsResponse, camerasResponse] = await Promise.all([
        fetch('/backend/api/advanced-security/dashboard', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch('/backend/api/advanced-security/alerts?status=unresolved', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch('/backend/api/advanced-security/threats?limit=10', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch('/backend/api/advanced-security/cameras', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        })
      ]);

      if (statsResponse.ok) {
        this.stats = await statsResponse.json();
      }

      if (alertsResponse.ok) {
        this.activeAlerts = await alertsResponse.json();
      }

      if (threatsResponse.ok) {
        this.recentThreats = await threatsResponse.json();
      }

      if (camerasResponse.ok) {
        this.cameraStatuses = await camerasResponse.json();
      }

      return `
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Advanced Security Monitoring</h2>
            <div class="flex space-x-2">
              <button id="refresh-security-data" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Refresh
              </button>
              <button id="view-security-reports" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                View Reports
              </button>
              <button id="security-system-test" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700">
                System Test
              </button>
            </div>
          </div>

          <!-- System Health Alert -->
          ${this.renderSystemHealth()}

          <!-- Key Security Metrics -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            ${this.renderMetricCard('Active Cameras', this.stats?.active_cameras || 0, '📹', 'blue')}
            ${this.renderMetricCard('Online Cameras', this.stats?.online_cameras || 0, '🟢', 'green')}
            ${this.renderMetricCard('Facial Matches Today', this.stats?.facial_recognition_matches_today || 0, '👤', 'purple')}
            ${this.renderMetricCard('Active Threats', this.stats?.active_threats || 0, '🚨', 'red')}
          </div>

          <!-- AI Performance Metrics -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">AI Performance</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-blue-600">${this.stats?.system_health_score || 0}%</div>
                <div class="text-sm text-gray-600">System Health Score</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-green-600">${this.stats?.ai_model_accuracy || 0}%</div>
                <div class="text-sm text-gray-600">AI Model Accuracy</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-orange-600">${this.stats?.behavioral_anomalies_detected || 0}</div>
                <div class="text-sm text-gray-600">Behavioral Anomalies</div>
              </div>
            </div>
          </div>

          <!-- Active Security Alerts -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Active Security Alerts</h3>
            <div class="space-y-3">
              ${this.activeAlerts.map(alert => this.renderSecurityAlert(alert)).join('') || '<p class="text-gray-500">No active alerts</p>'}
            </div>
          </div>

          <!-- Recent Threat Events -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Recent Threat Events</h3>
            <div class="overflow-x-auto">
              <table class="min-w-full bg-white border">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Event Type</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Severity</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Confidence</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Camera</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  ${this.recentThreats.map(threat => `
                    <tr>
                      <td class="px-4 py-2 text-sm font-medium text-gray-900">${threat.event_type}</td>
                      <td class="px-4 py-2 text-sm">
                        <span class="px-2 py-1 rounded-full text-xs font-medium ${
                          threat.severity_level === 'high' ? 'bg-red-100 text-red-800' :
                          threat.severity_level === 'medium' ? 'bg-orange-100 text-orange-800' :
                          'bg-yellow-100 text-yellow-800'
                        }">
                          ${threat.severity_level}
                        </span>
                      </td>
                      <td class="px-4 py-2 text-sm text-gray-500">${threat.confidence_score}%</td>
                      <td class="px-4 py-2 text-sm text-gray-500">${threat.location_zone}</td>
                      <td class="px-4 py-2 text-sm text-gray-500">${threat.camera_name}</td>
                      <td class="px-4 py-2 text-sm text-gray-500">${new Date(threat.detection_timestamp).toLocaleTimeString()}</td>
                      <td class="px-4 py-2 text-sm">
                        <button class="text-blue-600 hover:text-blue-800" onclick="viewThreat('${threat.event_id}')">View</button>
                        ${threat.response_required ? '<button class="ml-2 text-red-600 hover:text-red-800" onclick="respondToThreat(\'' + threat.event_id + '\')">Respond</button>' : ''}
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          </div>

          <!-- Camera Status Overview -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Camera Status Overview</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              ${this.cameraStatuses.slice(0, 6).map(camera => this.renderCameraStatus(camera)).join('')}
            </div>
            ${this.cameraStatuses.length > 6 ? '<p class="text-sm text-gray-500 mt-4">Showing 6 of ' + this.cameraStatuses.length + ' cameras</p>' : ''}
          </div>

          <!-- Security Operations Summary -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Security Operations Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-red-600">${this.stats?.security_incidents_today || 0}</div>
                <div class="text-sm text-gray-600">Incidents Today</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-orange-600">${this.stats?.access_denials_today || 0}</div>
                <div class="text-sm text-gray-600">Access Denials</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-yellow-600">${this.stats?.crowd_density_alerts || 0}</div>
                <div class="text-sm text-gray-600">Crowd Alerts</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-blue-600">${this.activeAlerts.length}</div>
                <div class="text-sm text-gray-600">Active Alerts</div>
              </div>
            </div>
          </div>
        </div>
      `;
    } catch (error) {
      console.error('Failed to load advanced security dashboard:', error);
      return `
        <div class="text-center text-red-500 p-8">
          <div class="text-6xl mb-4">🔒</div>
          <h3 class="text-xl font-semibold mb-2">Failed to Load Security Dashboard</h3>
          <p class="text-gray-600">Please check your connection and try again.</p>
          <button id="retry-security-data" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Retry
          </button>
        </div>
      `;
    }
  }

  private renderSystemHealth(): string {
    if (!this.stats) return '';

    const healthScore = this.stats.system_health_score;
    const hasIssues = healthScore < 90 || this.stats.active_threats > 0 || this.activeAlerts.length > 0;

    if (!hasIssues) {
      return `
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
          <div class="flex items-center">
            <div class="shrink-0">
              <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-green-800">Security Systems Operational</h3>
              <div class="mt-2 text-sm text-green-700">
                <p>All security systems are functioning normally with ${healthScore}% health score.</p>
              </div>
            </div>
          </div>
        </div>
      `;
    }

    return `
      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-center">
          <div class="shrink-0">
            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-yellow-800">Security Attention Required</h3>
            <div class="mt-2 text-sm text-yellow-700">
              <p>
                System health: ${healthScore}% |
                ${this.stats.active_threats} active threats |
                ${this.activeAlerts.length} unresolved alerts
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
      red: 'bg-red-500',
      yellow: 'bg-yellow-500',
      purple: 'bg-purple-500'
    };

    return `
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="shrink-0">
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

  private renderSecurityAlert(alert: SecurityAlert): string {
    return `
      <div class="flex items-center justify-between p-4 border rounded ${
        alert.acknowledged ? 'bg-gray-50' : 'bg-red-50 border-red-200'
      }">
        <div class="flex-1">
          <div class="flex items-center space-x-2 mb-2">
            <span class="text-sm font-medium">${alert.alert_title}</span>
            <span class="px-2 py-1 text-xs rounded-full ${
              alert.severity_level === 'high' ? 'bg-red-100 text-red-800' :
              alert.severity_level === 'medium' ? 'bg-orange-100 text-orange-800' :
              'bg-yellow-100 text-yellow-800'
            }">${alert.severity_level}</span>
            ${alert.acknowledged ? '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Acknowledged</span>' : ''}
          </div>
          <p class="text-sm text-gray-600">${alert.alert_description}</p>
          <p class="text-xs text-gray-500">Zone: ${alert.location_zone} | ${new Date(alert.alert_timestamp).toLocaleString()}</p>
        </div>
        <div class="flex space-x-2">
          ${!alert.acknowledged ? '<button class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700" onclick="acknowledgeAlert(\'' + alert.alert_id + '\')">Acknowledge</button>' : ''}
          <button class="px-3 py-1 text-sm bg-gray-600 text-white rounded hover:bg-gray-700" onclick="viewAlert('${alert.alert_id}')">
            View Details
          </button>
        </div>
      </div>
    `;
  }

  private renderCameraStatus(camera: CameraStatus): string {
    const isOnline = camera.status === 'online';
    const lastPing = new Date(camera.last_ping);
    const minutesAgo = Math.floor((Date.now() - lastPing.getTime()) / (1000 * 60));

    return `
      <div class="p-4 border rounded">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium">${camera.camera_name}</span>
          <span class="px-2 py-1 text-xs rounded-full ${
            isOnline ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
          }">
            ${camera.status}
          </span>
        </div>
        <p class="text-xs text-gray-600 mb-2">${camera.location_zone}</p>
        <div class="flex items-center space-x-2 text-xs text-gray-500">
          <span>Last ping: ${minutesAgo} min ago</span>
          ${camera.facial_recognition_enabled ? '<span class="text-blue-600">👤 FR</span>' : ''}
          ${camera.behavioral_analytics_enabled ? '<span class="text-purple-600">🧠 BA</span>' : ''}
        </div>
      </div>
    `;
  }

  setupEventListeners(): void {
    const refreshBtn = document.getElementById('refresh-security-data');
    const reportsBtn = document.getElementById('view-security-reports');
    const testBtn = document.getElementById('security-system-test');
    const retryBtn = document.getElementById('retry-security-data');

    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshSecurityData'));
      });
    }

    if (reportsBtn) {
      reportsBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('showSecurityReports'));
      });
    }

    if (testBtn) {
      testBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('runSecurityTest'));
      });
    }

    if (retryBtn) {
      retryBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshSecurityData'));
      });
    }
  }
}
