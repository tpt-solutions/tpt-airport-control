import { DashboardApiService } from '../services/DashboardApiService.js';
import { AuthManager } from '../../auth.js';
import type { User } from '../types.js';

interface SecurityReport {
  period: {
    start_date: string;
    end_date: string;
  };
  threat_analysis: {
    total_threats: number;
    high_severity_threats: number;
    medium_severity_threats: number;
    low_severity_threats: number;
    threats_by_type: Array<{
      type: string;
      count: number;
      percentage: number;
    }>;
    threats_by_zone: Array<{
      zone: string;
      count: number;
      percentage: number;
    }>;
  };
  facial_recognition: {
    total_matches: number;
    unique_persons: number;
    match_accuracy: number;
    false_positives: number;
    processing_time_avg: number;
  };
  behavioral_analytics: {
    total_sessions: number;
    anomalies_detected: number;
    anomaly_rate: number;
    risk_assessments: {
      high_risk: number;
      medium_risk: number;
      low_risk: number;
    };
  };
  camera_performance: {
    total_cameras: number;
    online_cameras: number;
    offline_cameras: number;
    average_uptime: number;
    maintenance_required: number;
  };
  access_control: {
    total_events: number;
    granted_access: number;
    denied_access: number;
    denial_rate: number;
    peak_hours: Array<{
      hour: number;
      access_count: number;
    }>;
  };
  incidents_response: {
    total_incidents: number;
    resolved_incidents: number;
    average_response_time: number;
    escalation_rate: number;
  };
}

interface SecurityIncident {
  incident_id: string;
  incident_number: string;
  incident_type: string;
  severity_level: string;
  location_zone: string;
  incident_timestamp: string;
  resolved: boolean;
  response_time_minutes: number;
}

interface PolicyViolation {
  violation_id: string;
  violation_number: string;
  policy_name: string;
  violation_type: string;
  severity_level: string;
  location_zone: string;
  violation_timestamp: string;
  corrective_action: string;
}

export class AdvancedSecurityReportsView {
  private container: HTMLElement;
  private apiService: DashboardApiService;
  private reportData: SecurityReport | null = null;
  private incidents: SecurityIncident[] = [];
  private violations: PolicyViolation[] = [];
  private startDate: string;
  private endDate: string;

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new DashboardApiService();
    this.startDate = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    this.endDate = new Date().toISOString().split('T')[0];
  }

  async render(user: User): Promise<string> {
    try {
      // Fetch security report data
      const [reportResponse, incidentsResponse, violationsResponse] = await Promise.all([
        fetch(`/backend/api/advanced-security/report?start_date=${this.startDate}&end_date=${this.endDate}`, {
          headers: {
            'Authorization': `Bearer ${AuthManager.getInstance().getToken() ?? ''}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch(`/backend/api/advanced-security/incidents?start_date=${this.startDate}&end_date=${this.endDate}`, {
          headers: {
            'Authorization': `Bearer ${AuthManager.getInstance().getToken() ?? ''}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch(`/backend/api/advanced-security/violations?start_date=${this.startDate}&end_date=${this.endDate}`, {
          headers: {
            'Authorization': `Bearer ${AuthManager.getInstance().getToken() ?? ''}`,
            'Content-Type': 'application/json'
          }
        })
      ]);

      if (reportResponse.ok) {
        this.reportData = await reportResponse.json();
      }

      if (incidentsResponse.ok) {
        this.incidents = await incidentsResponse.json();
      }

      if (violationsResponse.ok) {
        this.violations = await violationsResponse.json();
      }

      return `
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Advanced Security Reports</h2>
            <div class="flex space-x-2">
              <input type="date" id="security-report-start-date" value="${this.startDate}" class="px-3 py-2 border rounded">
              <input type="date" id="security-report-end-date" value="${this.endDate}" class="px-3 py-2 border rounded">
              <button id="generate-security-report" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Generate Report
              </button>
              <button id="export-security-report" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                Export
              </button>
            </div>
          </div>

          ${this.reportData ? this.renderReportSummary() : '<p class="text-gray-500">No report data available</p>'}

          <!-- Threat Analysis -->
          ${this.renderThreatAnalysisSection()}

          <!-- Facial Recognition Performance -->
          ${this.renderFacialRecognitionSection()}

          <!-- Behavioral Analytics -->
          ${this.renderBehavioralAnalyticsSection()}

          <!-- Camera Performance -->
          ${this.renderCameraPerformanceSection()}

          <!-- Access Control Metrics -->
          ${this.renderAccessControlSection()}

          <!-- Incident Response -->
          ${this.renderIncidentResponseSection()}

          <!-- Security Incidents -->
          ${this.renderIncidentsSection()}

          <!-- Policy Violations -->
          ${this.renderViolationsSection()}
        </div>
      `;
    } catch (error) {
      console.error('Failed to load security reports:', error);
      return `
        <div class="text-center text-red-500 p-8">
          <div class="text-6xl mb-4">📊</div>
          <h3 class="text-xl font-semibold mb-2">Failed to Load Security Reports</h3>
          <p class="text-gray-600">Please check your connection and try again.</p>
          <button id="retry-security-reports" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Retry
          </button>
        </div>
      `;
    }
  }

  private renderReportSummary(): string {
    if (!this.reportData) return '';

    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Security Report Summary</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-red-600">${this.reportData.threat_analysis.total_threats.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Total Threats</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-blue-600">${this.reportData.facial_recognition.total_matches.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Facial Matches</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-green-600">${this.reportData.camera_performance.online_cameras}/${this.reportData.camera_performance.total_cameras}</div>
            <div class="text-sm text-gray-600">Cameras Online</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-purple-600">${this.reportData.incidents_response.total_incidents.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Security Incidents</div>
          </div>
        </div>
      </div>
    `;
  }

  private renderThreatAnalysisSection(): string {
    if (!this.reportData) return '';

    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Threat Analysis</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <h4 class="text-md font-medium mb-3">Threat Severity Distribution</h4>
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">High Severity:</span>
                <span class="text-sm font-medium text-red-600">${this.reportData.threat_analysis.high_severity_threats}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Medium Severity:</span>
                <span class="text-sm font-medium text-orange-600">${this.reportData.threat_analysis.medium_severity_threats}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Low Severity:</span>
                <span class="text-sm font-medium text-yellow-600">${this.reportData.threat_analysis.low_severity_threats}</span>
              </div>
            </div>
          </div>
          <div>
            <h4 class="text-md font-medium mb-3">Threats by Type</h4>
            <div class="space-y-2">
              ${this.reportData.threat_analysis.threats_by_type.slice(0, 5).map(threat => `
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">${threat.type}:</span>
                  <span class="text-sm font-medium">${threat.count} (${threat.percentage}%)</span>
                </div>
              `).join('')}
            </div>
          </div>
        </div>
      </div>
    `;
  }

  private renderFacialRecognitionSection(): string {
    if (!this.reportData) return '';

    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Facial Recognition Performance</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-blue-600">${this.reportData.facial_recognition.total_matches.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Total Matches</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-green-600">${this.reportData.facial_recognition.unique_persons.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Unique Persons</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-purple-600">${this.reportData.facial_recognition.match_accuracy}%</div>
            <div class="text-sm text-gray-600">Match Accuracy</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-red-600">${this.reportData.facial_recognition.false_positives}</div>
            <div class="text-sm text-gray-600">False Positives</div>
          </div>
        </div>
        <div class="mt-4 text-center">
          <p class="text-sm text-gray-600">Average Processing Time: ${this.reportData.facial_recognition.processing_time_avg}ms</p>
        </div>
      </div>
    `;
  }

  private renderBehavioralAnalyticsSection(): string {
    if (!this.reportData) return '';

    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Behavioral Analytics</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <h4 class="text-md font-medium mb-3">Session Analysis</h4>
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Total Sessions:</span>
                <span class="text-sm font-medium">${this.reportData.behavioral_analytics.total_sessions.toLocaleString()}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Anomalies Detected:</span>
                <span class="text-sm font-medium text-red-600">${this.reportData.behavioral_analytics.anomalies_detected}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Anomaly Rate:</span>
                <span class="text-sm font-medium">${this.reportData.behavioral_analytics.anomaly_rate}%</span>
              </div>
            </div>
          </div>
          <div>
            <h4 class="text-md font-medium mb-3">Risk Assessment Distribution</h4>
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">High Risk:</span>
                <span class="text-sm font-medium text-red-600">${this.reportData.behavioral_analytics.risk_assessments.high_risk}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Medium Risk:</span>
                <span class="text-sm font-medium text-orange-600">${this.reportData.behavioral_analytics.risk_assessments.medium_risk}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Low Risk:</span>
                <span class="text-sm font-medium text-green-600">${this.reportData.behavioral_analytics.risk_assessments.low_risk}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  private renderCameraPerformanceSection(): string {
    if (!this.reportData) return '';

    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Camera Performance</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-blue-600">${this.reportData.camera_performance.total_cameras}</div>
            <div class="text-sm text-gray-600">Total Cameras</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-green-600">${this.reportData.camera_performance.online_cameras}</div>
            <div class="text-sm text-gray-600">Online Cameras</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-red-600">${this.reportData.camera_performance.offline_cameras}</div>
            <div class="text-sm text-gray-600">Offline Cameras</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-purple-600">${this.reportData.camera_performance.average_uptime}%</div>
            <div class="text-sm text-gray-600">Average Uptime</div>
          </div>
        </div>
        <div class="mt-4 text-center">
          <p class="text-sm text-gray-600">Cameras requiring maintenance: ${this.reportData.camera_performance.maintenance_required}</p>
        </div>
      </div>
    `;
  }

  private renderAccessControlSection(): string {
    if (!this.reportData) return '';

    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Access Control Metrics</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <h4 class="text-md font-medium mb-3">Access Statistics</h4>
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Total Events:</span>
                <span class="text-sm font-medium">${this.reportData.access_control.total_events.toLocaleString()}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Granted Access:</span>
                <span class="text-sm font-medium text-green-600">${this.reportData.access_control.granted_access.toLocaleString()}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Denied Access:</span>
                <span class="text-sm font-medium text-red-600">${this.reportData.access_control.denied_access.toLocaleString()}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Denial Rate:</span>
                <span class="text-sm font-medium">${this.reportData.access_control.denial_rate}%</span>
              </div>
            </div>
          </div>
          <div>
            <h4 class="text-md font-medium mb-3">Peak Access Hours</h4>
            <div class="space-y-1 max-h-32 overflow-y-auto">
              ${this.reportData.access_control.peak_hours.map(hour => `
                <div class="flex justify-between text-xs">
                  <span>${hour.hour}:00:</span>
                  <span>${hour.access_count} accesses</span>
                </div>
              `).join('')}
            </div>
          </div>
        </div>
      </div>
    `;
  }

  private renderIncidentResponseSection(): string {
    if (!this.reportData) return '';

    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Incident Response Performance</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-blue-600">${this.reportData.incidents_response.total_incidents.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Total Incidents</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-green-600">${this.reportData.incidents_response.resolved_incidents.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Resolved Incidents</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-orange-600">${this.reportData.incidents_response.average_response_time} min</div>
            <div class="text-sm text-gray-600">Avg Response Time</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-red-600">${this.reportData.incidents_response.escalation_rate}%</div>
            <div class="text-sm text-gray-600">Escalation Rate</div>
          </div>
        </div>
      </div>
    `;
  }

  private renderIncidentsSection(): string {
    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Security Incidents</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full bg-white border">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Incident #</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Severity</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Response Time</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              ${this.incidents.slice(0, 10).map(incident => `
                <tr>
                  <td class="px-4 py-2 text-sm font-medium text-gray-900">${incident.incident_number}</td>
                  <td class="px-4 py-2 text-sm text-gray-500">${incident.incident_type}</td>
                  <td class="px-4 py-2 text-sm">
                    <span class="px-2 py-1 rounded-full text-xs font-medium ${
                      incident.severity_level === 'high' ? 'bg-red-100 text-red-800' :
                      incident.severity_level === 'medium' ? 'bg-orange-100 text-orange-800' :
                      'bg-yellow-100 text-yellow-800'
                    }">
                      ${incident.severity_level}
                    </span>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-500">${incident.location_zone}</td>
                  <td class="px-4 py-2 text-sm">
                    <span class="px-2 py-1 rounded-full text-xs font-medium ${
                      incident.resolved ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                    }">
                      ${incident.resolved ? 'Resolved' : 'Active'}
                    </span>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-500">${incident.response_time_minutes || 'N/A'} min</td>
                  <td class="px-4 py-2 text-sm text-gray-500">${new Date(incident.incident_timestamp).toLocaleDateString()}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  private renderViolationsSection(): string {
    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Policy Violations</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full bg-white border">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Violation #</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Policy</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Severity</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              ${this.violations.slice(0, 10).map(violation => `
                <tr>
                  <td class="px-4 py-2 text-sm font-medium text-gray-900">${violation.violation_number}</td>
                  <td class="px-4 py-2 text-sm text-gray-500">${violation.policy_name}</td>
                  <td class="px-4 py-2 text-sm text-gray-500">${violation.violation_type}</td>
                  <td class="px-4 py-2 text-sm">
                    <span class="px-2 py-1 rounded-full text-xs font-medium ${
                      violation.severity_level === 'high' ? 'bg-red-100 text-red-800' :
                      violation.severity_level === 'medium' ? 'bg-orange-100 text-orange-800' :
                      'bg-yellow-100 text-yellow-800'
                    }">
                      ${violation.severity_level}
                    </span>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-500">${violation.location_zone}</td>
                  <td class="px-4 py-2 text-sm text-gray-500">${violation.corrective_action}</td>
                  <td class="px-4 py-2 text-sm text-gray-500">${new Date(violation.violation_timestamp).toLocaleDateString()}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  setupEventListeners(): void {
    const generateBtn = document.getElementById('generate-security-report');
    const exportBtn = document.getElementById('export-security-report');
    const startDateInput = document.getElementById('security-report-start-date') as HTMLInputElement;
    const endDateInput = document.getElementById('security-report-end-date') as HTMLInputElement;
    const retryBtn = document.getElementById('retry-security-reports');

    if (generateBtn) {
      generateBtn.addEventListener('click', () => {
        if (startDateInput && endDateInput) {
          this.startDate = startDateInput.value;
          this.endDate = endDateInput.value;
          window.dispatchEvent(new CustomEvent('refreshSecurityReports'));
        }
      });
    }

    if (exportBtn) {
      exportBtn.addEventListener('click', () => {
        this.exportReport();
      });
    }

    if (retryBtn) {
      retryBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshSecurityReports'));
      });
    }
  }

  private exportReport(): void {
    if (!this.reportData) return;

    const reportText = `
Advanced Security Report
Period: ${this.reportData.period.start_date} to ${this.reportData.period.end_date}

THREAT ANALYSIS:
- Total Threats: ${this.reportData.threat_analysis.total_threats}
- High Severity: ${this.reportData.threat_analysis.high_severity_threats}
- Medium Severity: ${this.reportData.threat_analysis.medium_severity_threats}
- Low Severity: ${this.reportData.threat_analysis.low_severity_threats}

FACIAL RECOGNITION:
- Total Matches: ${this.reportData.facial_recognition.total_matches}
- Unique Persons: ${this.reportData.facial_recognition.unique_persons}
- Match Accuracy: ${this.reportData.facial_recognition.match_accuracy}%
- False Positives: ${this.reportData.facial_recognition.false_positives}
- Avg Processing Time: ${this.reportData.facial_recognition.processing_time_avg}ms

BEHAVIORAL ANALYTICS:
- Total Sessions: ${this.reportData.behavioral_analytics.total_sessions}
- Anomalies Detected: ${this.reportData.behavioral_analytics.anomalies_detected}
- Anomaly Rate: ${this.reportData.behavioral_analytics.anomaly_rate}%
- High Risk Assessments: ${this.reportData.behavioral_analytics.risk_assessments.high_risk}
- Medium Risk Assessments: ${this.reportData.behavioral_analytics.risk_assessments.medium_risk}
- Low Risk Assessments: ${this.reportData.behavioral_analytics.risk_assessments.low_risk}

CAMERA PERFORMANCE:
- Total Cameras: ${this.reportData.camera_performance.total_cameras}
- Online Cameras: ${this.reportData.camera_performance.online_cameras}
- Offline Cameras: ${this.reportData.camera_performance.offline_cameras}
- Average Uptime: ${this.reportData.camera_performance.average_uptime}%
- Maintenance Required: ${this.reportData.camera_performance.maintenance_required}

ACCESS CONTROL:
- Total Events: ${this.reportData.access_control.total_events}
- Granted Access: ${this.reportData.access_control.granted_access}
- Denied Access: ${this.reportData.access_control.denied_access}
- Denial Rate: ${this.reportData.access_control.denial_rate}%

INCIDENT RESPONSE:
- Total Incidents: ${this.reportData.incidents_response.total_incidents}
- Resolved Incidents: ${this.reportData.incidents_response.resolved_incidents}
- Average Response Time: ${this.reportData.incidents_response.average_response_time} minutes
- Escalation Rate: ${this.reportData.incidents_response.escalation_rate}%

Generated on: ${new Date().toLocaleString()}
    `;

    const blob = new Blob([reportText], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `advanced-security-report-${this.startDate}-to-${this.endDate}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }
}
