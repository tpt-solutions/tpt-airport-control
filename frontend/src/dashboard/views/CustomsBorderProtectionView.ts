import { DashboardApiService } from '../services/DashboardApiService.js';
import type { User } from '../types.js';

interface BorderStats {
  today_entries: number;
  today_departures: number;
  pending_entries: number;
  denied_entries_today: number;
  active_watchlist_alerts: number;
  pending_visa_applications: number;
  customs_inspections_today: number;
  security_incidents_this_week: number;
  average_processing_time: number;
  biometric_verification_rate: number;
  recent_entries: Array<{
    entry_id: string;
    passport_number: string;
    holder_name: string;
    nationality: string;
    entry_timestamp: string;
    purpose_of_visit: string;
  }>;
}

interface BorderEntry {
  entry_id: string;
  passport_number: string;
  holder_name: string;
  holder_nationality: string;
  entry_type: string;
  entry_status: string;
  processing_time_minutes: number;
  purpose_of_visit: string;
  flight_number: string;
}

interface VisaApplication {
  application_id: string;
  application_number: string;
  applicant_name: string;
  applicant_nationality: string;
  visa_type: string;
  application_status: string;
  submission_date: string;
}

export class CustomsBorderProtectionView {
  private container: HTMLElement;
  private apiService: DashboardApiService;
  private stats: BorderStats | null = null;
  private recentEntries: BorderEntry[] = [];
  private pendingApplications: VisaApplication[] = [];

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new DashboardApiService();
  }

  async render(user: User): Promise<string> {
    try {
      // Fetch border control data
      const [statsResponse, entriesResponse, applicationsResponse] = await Promise.all([
        fetch('/backend/api/customs/dashboard', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch('/backend/api/customs/entries?limit=20', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch('/backend/api/customs/visa-applications?status=submitted&limit=10', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        })
      ]);

      if (statsResponse.ok) {
        this.stats = await statsResponse.json();
      }

      if (entriesResponse.ok) {
        this.recentEntries = await entriesResponse.json();
      }

      if (applicationsResponse.ok) {
        this.pendingApplications = await applicationsResponse.json();
      }

      return `
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Customs & Border Protection</h2>
            <div class="flex space-x-2">
              <button id="refresh-border-data" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Refresh
              </button>
              <button id="view-border-reports" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                View Reports
              </button>
              <button id="new-passport-check" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700">
                Passport Check
              </button>
            </div>
          </div>

          <!-- System Status Alert -->
          ${this.renderSystemStatus()}

          <!-- Key Metrics -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            ${this.renderMetricCard('Today\'s Entries', this.stats?.today_entries || 0, '✈️', 'blue')}
            ${this.renderMetricCard('Pending Entries', this.stats?.pending_entries || 0, '⏳', 'orange')}
            ${this.renderMetricCard('Denied Today', this.stats?.denied_entries_today || 0, '❌', 'red')}
            ${this.renderMetricCard('Watchlist Alerts', this.stats?.active_watchlist_alerts || 0, '🚨', 'yellow')}
          </div>

          <!-- Processing Metrics -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Processing Performance</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-blue-600">${this.stats?.average_processing_time || 0} min</div>
                <div class="text-sm text-gray-600">Avg Processing Time</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-green-600">${this.stats?.biometric_verification_rate || 0}%</div>
                <div class="text-sm text-gray-600">Biometric Success Rate</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-purple-600">${this.stats?.customs_inspections_today || 0}</div>
                <div class="text-sm text-gray-600">Inspections Today</div>
              </div>
            </div>
          </div>

          <!-- Recent Border Entries -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Recent Border Entries</h3>
            <div class="overflow-x-auto">
              <table class="min-w-full bg-white border">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Passport</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nationality</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Processing Time</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  ${this.recentEntries.map(entry => `
                    <tr>
                      <td class="px-4 py-2 text-sm font-medium text-gray-900">${entry.passport_number}</td>
                      <td class="px-4 py-2 text-sm text-gray-500">${entry.holder_name}</td>
                      <td class="px-4 py-2 text-sm text-gray-500">${entry.holder_nationality}</td>
                      <td class="px-4 py-2 text-sm text-gray-500 capitalize">${entry.entry_type}</td>
                      <td class="px-4 py-2 text-sm">
                        <span class="px-2 py-1 rounded-full text-xs font-medium ${
                          entry.entry_status === 'approved' ? 'bg-green-100 text-green-800' :
                          entry.entry_status === 'denied' ? 'bg-red-100 text-red-800' :
                          entry.entry_status === 'processing' ? 'bg-yellow-100 text-yellow-800' :
                          'bg-gray-100 text-gray-800'
                        }">
                          ${entry.entry_status}
                        </span>
                      </td>
                      <td class="px-4 py-2 text-sm text-gray-500">${entry.processing_time_minutes || 'N/A'} min</td>
                      <td class="px-4 py-2 text-sm">
                        <button class="text-blue-600 hover:text-blue-800" onclick="viewEntry('${entry.entry_id}')">View</button>
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          </div>

          <!-- Pending Visa Applications -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Pending Visa Applications</h3>
            <div class="space-y-3">
              ${this.pendingApplications.map(app => this.renderVisaApplicationCard(app)).join('') || '<p class="text-gray-500">No pending applications</p>'}
            </div>
          </div>

          <!-- Security & Compliance -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Security & Compliance</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-red-600">${this.stats?.security_incidents_this_week || 0}</div>
                <div class="text-sm text-gray-600">Security Incidents (Week)</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-orange-600">${this.stats?.pending_visa_applications || 0}</div>
                <div class="text-sm text-gray-600">Pending Visa Apps</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-blue-600">${this.stats?.today_departures || 0}</div>
                <div class="text-sm text-gray-600">Departures Today</div>
              </div>
            </div>
          </div>
        </div>
      `;
    } catch (error) {
      console.error('Failed to load border control dashboard:', error);
      return `
        <div class="text-center text-red-500 p-8">
          <div class="text-6xl mb-4">🛂</div>
          <h3 class="text-xl font-semibold mb-2">Failed to Load Border Control Data</h3>
          <p class="text-gray-600">Please check your connection and try again.</p>
          <button id="retry-border-data" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Retry
          </button>
        </div>
      `;
    }
  }

  private renderSystemStatus(): string {
    if (!this.stats) return '';

    const hasIssues = (this.stats.pending_entries > 0) || (this.stats.denied_entries_today > 0) || (this.stats.active_watchlist_alerts > 0);

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
              <h3 class="text-sm font-medium text-green-800">Border Operations Normal</h3>
              <div class="mt-2 text-sm text-green-700">
                <p>All border control systems are operating within normal parameters.</p>
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
            <h3 class="text-sm font-medium text-yellow-800">Border Control Attention Required</h3>
            <div class="mt-2 text-sm text-yellow-700">
              <p>
                ${this.stats.pending_entries > 0 ? `${this.stats.pending_entries} entries pending processing. ` : ''}
                ${this.stats.denied_entries_today > 0 ? `${this.stats.denied_entries_today} entries denied today. ` : ''}
                ${this.stats.active_watchlist_alerts > 0 ? `${this.stats.active_watchlist_alerts} active watchlist alerts. ` : ''}
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
      yellow: 'bg-yellow-500'
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

  private renderVisaApplicationCard(app: VisaApplication): string {
    return `
      <div class="flex items-center justify-between p-4 border rounded">
        <div class="flex-1">
          <div class="flex items-center space-x-2 mb-2">
            <span class="text-sm font-medium">${app.application_number}</span>
            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">${app.application_status}</span>
          </div>
          <p class="text-sm text-gray-600">${app.applicant_name} - ${app.applicant_nationality}</p>
          <p class="text-xs text-gray-500">${app.visa_type} visa | Submitted: ${new Date(app.submission_date).toLocaleDateString()}</p>
        </div>
        <div class="flex space-x-2">
          <button class="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700" onclick="approveVisa('${app.application_id}')">
            Approve
          </button>
          <button class="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700" onclick="rejectVisa('${app.application_id}')">
            Reject
          </button>
        </div>
      </div>
    `;
  }

  setupEventListeners(): void {
    const refreshBtn = document.getElementById('refresh-border-data');
    const reportsBtn = document.getElementById('view-border-reports');
    const passportBtn = document.getElementById('new-passport-check');
    const retryBtn = document.getElementById('retry-border-data');

    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshBorderData'));
      });
    }

    if (reportsBtn) {
      reportsBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('showBorderReports'));
      });
    }

    if (passportBtn) {
      passportBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('newPassportCheck'));
      });
    }

    if (retryBtn) {
      retryBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshBorderData'));
      });
    }
  }
}
