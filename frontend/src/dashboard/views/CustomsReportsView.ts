import { DashboardApiService } from '../services/DashboardApiService.js';
import type { User } from '../types.js';

interface BorderReport {
  period: {
    start_date: string;
    end_date: string;
  };
  border_activity: {
    total_entries: number;
    total_departures: number;
    denied_entries: number;
    by_nationality: Array<{
      nationality: string;
      entries: number;
      percentage: number;
    }>;
  };
  security_metrics: {
    watchlist_intercepts: number;
    security_incidents: number;
    document_fraud_cases: number;
  };
  customs_performance: {
    declarations_processed: number;
    inspections_conducted: number;
    violations_detected: number;
    revenue_collected: number;
  };
  immigration_status: {
    visa_applications_processed: number;
    overstayed_cases: number;
    deportation_orders: number;
  };
  processing_efficiency: {
    average_processing_time: number;
    biometric_success_rate: number;
    peak_hour_performance: Array<{
      hour: number;
      entries: number;
      avg_processing_time: number;
    }>;
  };
}

interface CustomsInspection {
  inspection_id: string;
  inspection_number: string;
  declaration_number: string;
  passport_number: string;
  holder_name: string;
  inspection_type: string;
  inspection_start: string;
  violation_type: string | null;
  fine_amount: number | null;
  penalty_assessed: boolean;
}

interface SecurityIncident {
  incident_id: string;
  incident_number: string;
  incident_type: string;
  severity_level: string;
  incident_date: string;
  involved_passports: string[];
  arrest_made: boolean;
  investigation_required: boolean;
}

export class CustomsReportsView {
  private container: HTMLElement;
  private apiService: DashboardApiService;
  private reportData: BorderReport | null = null;
  private inspections: CustomsInspection[] = [];
  private incidents: SecurityIncident[] = [];
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
      // Fetch report data
      const [reportResponse, inspectionsResponse, incidentsResponse] = await Promise.all([
        fetch(`/backend/api/customs/report?start_date=${this.startDate}&end_date=${this.endDate}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch(`/backend/api/customs/inspections?start_date=${this.startDate}&end_date=${this.endDate}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch(`/backend/api/customs/incidents?start_date=${this.startDate}&end_date=${this.endDate}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        })
      ]);

      if (reportResponse.ok) {
        this.reportData = await reportResponse.json();
      }

      if (inspectionsResponse.ok) {
        this.inspections = await inspectionsResponse.json();
      }

      if (incidentsResponse.ok) {
        this.incidents = await incidentsResponse.json();
      }

      return `
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Customs & Border Protection Reports</h2>
            <div class="flex space-x-2">
              <input type="date" id="report-start-date" value="${this.startDate}" class="px-3 py-2 border rounded">
              <input type="date" id="report-end-date" value="${this.endDate}" class="px-3 py-2 border rounded">
              <button id="generate-report" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Generate Report
              </button>
              <button id="export-report" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                Export
              </button>
            </div>
          </div>

          ${this.reportData ? this.renderReportSummary() : '<p class="text-gray-500">No report data available</p>'}

          <!-- Border Activity Analysis -->
          ${this.renderBorderActivitySection()}

          <!-- Security Metrics -->
          ${this.renderSecurityMetricsSection()}

          <!-- Customs Performance -->
          ${this.renderCustomsPerformanceSection()}

          <!-- Immigration Status -->
          ${this.renderImmigrationStatusSection()}

          <!-- Processing Efficiency -->
          ${this.renderProcessingEfficiencySection()}

          <!-- Recent Inspections -->
          ${this.renderInspectionsSection()}

          <!-- Security Incidents -->
          ${this.renderIncidentsSection()}
        </div>
      `;
    } catch (error) {
      console.error('Failed to load customs reports:', error);
      return `
        <div class="text-center text-red-500 p-8">
          <div class="text-6xl mb-4">📊</div>
          <h3 class="text-xl font-semibold mb-2">Failed to Load Customs Reports</h3>
          <p class="text-gray-600">Please check your connection and try again.</p>
          <button id="retry-reports" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
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
        <h3 class="text-lg font-semibold mb-4">Report Summary</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-blue-600">${this.reportData.border_activity.total_entries.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Total Entries</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-green-600">${this.reportData.border_activity.total_departures.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Total Departures</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-red-600">${this.reportData.border_activity.denied_entries.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Denied Entries</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-purple-600">$${this.reportData.customs_performance.revenue_collected?.toLocaleString() || 0}</div>
            <div class="text-sm text-gray-600">Revenue Collected</div>
          </div>
        </div>
      </div>
    `;
  }

  private renderBorderActivitySection(): string {
    if (!this.reportData) return '';

    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Border Activity Analysis</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <h4 class="text-md font-medium mb-3">Entry Statistics</h4>
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Total Entries:</span>
                <span class="text-sm font-medium">${this.reportData.border_activity.total_entries.toLocaleString()}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Total Departures:</span>
                <span class="text-sm font-medium">${this.reportData.border_activity.total_departures.toLocaleString()}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Denied Entries:</span>
                <span class="text-sm font-medium text-red-600">${this.reportData.border_activity.denied_entries.toLocaleString()}</span>
              </div>
            </div>
          </div>
          <div>
            <h4 class="text-md font-medium mb-3">Top Nationalities</h4>
            <div class="space-y-2">
              ${this.reportData.border_activity.by_nationality.slice(0, 5).map(nationality => `
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">${nationality.nationality}:</span>
                  <span class="text-sm font-medium">${nationality.entries.toLocaleString()} (${nationality.percentage}%)</span>
                </div>
              `).join('')}
            </div>
          </div>
        </div>
      </div>
    `;
  }

  private renderSecurityMetricsSection(): string {
    if (!this.reportData) return '';

    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Security Metrics</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="text-center p-4 border rounded">
            <div class="text-3xl font-bold text-red-600">${this.reportData.security_metrics.watchlist_intercepts}</div>
            <div class="text-sm text-gray-600">Watchlist Intercepts</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-3xl font-bold text-orange-600">${this.reportData.security_metrics.security_incidents}</div>
            <div class="text-sm text-gray-600">Security Incidents</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-3xl font-bold text-yellow-600">${this.reportData.security_metrics.document_fraud_cases}</div>
            <div class="text-sm text-gray-600">Document Fraud Cases</div>
          </div>
        </div>
      </div>
    `;
  }

  private renderCustomsPerformanceSection(): string {
    if (!this.reportData) return '';

    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Customs Performance</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-blue-600">${this.reportData.customs_performance.declarations_processed.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Declarations Processed</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-green-600">${this.reportData.customs_performance.inspections_conducted.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Inspections Conducted</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-red-600">${this.reportData.customs_performance.violations_detected.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Violations Detected</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-purple-600">$${this.reportData.customs_performance.revenue_collected?.toLocaleString() || 0}</div>
            <div class="text-sm text-gray-600">Revenue Collected</div>
          </div>
        </div>
      </div>
    `;
  }

  private renderImmigrationStatusSection(): string {
    if (!this.reportData) return '';

    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Immigration Status</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-blue-600">${this.reportData.immigration_status.visa_applications_processed.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Visa Applications Processed</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-orange-600">${this.reportData.immigration_status.overstayed_cases.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Overstayed Cases</div>
          </div>
          <div class="text-center p-4 border rounded">
            <div class="text-2xl font-bold text-red-600">${this.reportData.immigration_status.deportation_orders.toLocaleString()}</div>
            <div class="text-sm text-gray-600">Deportation Orders</div>
          </div>
        </div>
      </div>
    `;
  }

  private renderProcessingEfficiencySection(): string {
    if (!this.reportData) return '';

    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Processing Efficiency</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <h4 class="text-md font-medium mb-3">Overall Performance</h4>
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Average Processing Time:</span>
                <span class="text-sm font-medium">${this.reportData.processing_efficiency.average_processing_time} min</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">Biometric Success Rate:</span>
                <span class="text-sm font-medium">${this.reportData.processing_efficiency.biometric_success_rate}%</span>
              </div>
            </div>
          </div>
          <div>
            <h4 class="text-md font-medium mb-3">Peak Hour Performance</h4>
            <div class="space-y-1 max-h-32 overflow-y-auto">
              ${this.reportData.processing_efficiency.peak_hour_performance.map(hour => `
                <div class="flex justify-between text-xs">
                  <span>${hour.hour}:00:</span>
                  <span>${hour.entries} entries (${hour.avg_processing_time} min avg)</span>
                </div>
              `).join('')}
            </div>
          </div>
        </div>
      </div>
    `;
  }

  private renderInspectionsSection(): string {
    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Recent Customs Inspections</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full bg-white border">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Inspection #</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Passport</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Violation</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fine</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              ${this.inspections.slice(0, 10).map(inspection => `
                <tr>
                  <td class="px-4 py-2 text-sm font-medium text-gray-900">${inspection.inspection_number}</td>
                  <td class="px-4 py-2 text-sm text-gray-500">${inspection.passport_number}</td>
                  <td class="px-4 py-2 text-sm text-gray-500">${inspection.holder_name}</td>
                  <td class="px-4 py-2 text-sm text-gray-500 capitalize">${inspection.inspection_type}</td>
                  <td class="px-4 py-2 text-sm">
                    <span class="px-2 py-1 rounded-full text-xs font-medium ${
                      inspection.violation_type ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
                    }">
                      ${inspection.violation_type || 'None'}
                    </span>
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-500">${inspection.fine_amount ? '$' + inspection.fine_amount : 'N/A'}</td>
                  <td class="px-4 py-2 text-sm text-gray-500">${new Date(inspection.inspection_start).toLocaleDateString()}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  private renderIncidentsSection(): string {
    return `
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Security Incidents</h3>
        <div class="space-y-3">
          ${this.incidents.slice(0, 5).map(incident => `
            <div class="flex items-center justify-between p-4 border rounded">
              <div class="flex-1">
                <div class="flex items-center space-x-2 mb-2">
                  <span class="text-sm font-medium">${incident.incident_number}</span>
                  <span class="px-2 py-1 text-xs rounded-full ${
                    incident.severity_level === 'high' ? 'bg-red-100 text-red-800' :
                    incident.severity_level === 'medium' ? 'bg-orange-100 text-orange-800' :
                    'bg-yellow-100 text-yellow-800'
                  }">${incident.severity_level}</span>
                  ${incident.arrest_made ? '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Arrest Made</span>' : ''}
                </div>
                <p class="text-sm text-gray-600">${incident.incident_type}</p>
                <p class="text-xs text-gray-500">Date: ${new Date(incident.incident_date).toLocaleDateString()} | Passports: ${incident.involved_passports.length}</p>
              </div>
              <div class="flex space-x-2">
                <button class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700" onclick="viewIncident('${incident.incident_id}')">
                  View Details
                </button>
              </div>
            </div>
          `).join('') || '<p class="text-gray-500">No security incidents in the selected period</p>'}
        </div>
      </div>
    `;
  }

  setupEventListeners(): void {
    const generateBtn = document.getElementById('generate-report');
    const exportBtn = document.getElementById('export-report');
    const startDateInput = document.getElementById('report-start-date') as HTMLInputElement;
    const endDateInput = document.getElementById('report-end-date') as HTMLInputElement;
    const retryBtn = document.getElementById('retry-reports');

    if (generateBtn) {
      generateBtn.addEventListener('click', () => {
        if (startDateInput && endDateInput) {
          this.startDate = startDateInput.value;
          this.endDate = endDateInput.value;
          window.dispatchEvent(new CustomEvent('refreshCustomsReports'));
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
        window.dispatchEvent(new CustomEvent('refreshCustomsReports'));
      });
    }
  }

  private exportReport(): void {
    if (!this.reportData) return;

    const reportText = `
Customs & Border Protection Report
Period: ${this.reportData.period.start_date} to ${this.reportData.period.end_date}

BORDER ACTIVITY:
- Total Entries: ${this.reportData.border_activity.total_entries}
- Total Departures: ${this.reportData.border_activity.total_departures}
- Denied Entries: ${this.reportData.border_activity.denied_entries}

SECURITY METRICS:
- Watchlist Intercepts: ${this.reportData.security_metrics.watchlist_intercepts}
- Security Incidents: ${this.reportData.security_metrics.security_incidents}
- Document Fraud Cases: ${this.reportData.security_metrics.document_fraud_cases}

CUSTOMS PERFORMANCE:
- Declarations Processed: ${this.reportData.customs_performance.declarations_processed}
- Inspections Conducted: ${this.reportData.customs_performance.inspections_conducted}
- Violations Detected: ${this.reportData.customs_performance.violations_detected}
- Revenue Collected: $${this.reportData.customs_performance.revenue_collected}

IMMIGRATION STATUS:
- Visa Applications Processed: ${this.reportData.immigration_status.visa_applications_processed}
- Overstayed Cases: ${this.reportData.immigration_status.overstayed_cases}
- Deportation Orders: ${this.reportData.immigration_status.deportation_orders}

PROCESSING EFFICIENCY:
- Average Processing Time: ${this.reportData.processing_efficiency.average_processing_time} minutes
- Biometric Success Rate: ${this.reportData.processing_efficiency.biometric_success_rate}%

Generated on: ${new Date().toLocaleString()}
    `;

    const blob = new Blob([reportText], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `customs-report-${this.startDate}-to-${this.endDate}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }
}
