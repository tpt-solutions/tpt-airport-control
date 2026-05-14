import { DashboardApiService } from '../services/DashboardApiService.js';
import type { User } from '../types.js';

interface ConflictPrediction {
  aircraft1: string;
  aircraft2: string;
  time_to_conflict: number;
  min_horizontal_sep: number;
  min_vertical_sep: number;
  severity: number;
  confidence: number;
  predicted_conflict_point: {
    lat: number;
    lon: number;
    alt: number;
  };
  recommended_actions: Array<{
    type: string;
    target_aircraft: string;
    action: string;
    magnitude: number;
    reason: string;
  }>;
  detected_at: number;
}

interface AIPerformance {
  total_predictions: number;
  accurate_predictions: number;
  false_positives: number;
  average_confidence: number;
  model_accuracy: number;
  processing_time_avg: number;
}

interface ActiveAircraft {
  icao24: string;
  callsign: string;
  latitude: number;
  longitude: number;
  baro_altitude: number;
  velocity: number;
  true_track: number;
  vertical_rate: number;
  last_update: number;
}

export class AIConflictPredictionView {
  private container: HTMLElement;
  private apiService: DashboardApiService;
  private predictions: ConflictPrediction[] = [];
  private activeAircraft: ActiveAircraft[] = [];
  private performance: AIPerformance | null = null;
  private updateInterval: number | null = null;

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new DashboardApiService();
  }

  async render(user: User): Promise<string> {
    try {
      // Fetch initial data
      await this.loadData();

      return `
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">AI Conflict Prediction System</h2>
            <div class="flex space-x-2">
              <button id="refresh-ai-data" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Refresh
              </button>
              <button id="view-ai-reports" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                View Reports
              </button>
              <button id="run-prediction" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700">
                Run Prediction
              </button>
            </div>
          </div>

          <!-- AI System Status -->
          ${this.renderSystemStatus()}

          <!-- Key AI Metrics -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            ${this.renderMetricCard('Active Aircraft', this.activeAircraft.length, '✈️', 'blue')}
            ${this.renderMetricCard('Active Predictions', this.predictions.length, '⚠️', 'orange')}
            ${this.renderMetricCard('High Severity Conflicts', this.predictions.filter(p => p.severity > 70).length, '🚨', 'red')}
            ${this.renderMetricCard('AI Confidence', Math.round(this.performance?.average_confidence || 0), '%', 'green')}
          </div>

          <!-- AI Performance Metrics -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">AI Performance</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-blue-600">${this.performance?.total_predictions || 0}</div>
                <div class="text-sm text-gray-600">Total Predictions</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-green-600">${this.performance?.accurate_predictions || 0}</div>
                <div class="text-sm text-gray-600">Accurate Predictions</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-red-600">${this.performance?.false_positives || 0}</div>
                <div class="text-sm text-gray-600">False Positives</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-purple-600">${this.performance?.model_accuracy || 0}%</div>
                <div class="text-sm text-gray-600">Model Accuracy</div>
              </div>
            </div>
          </div>

          <!-- Active Conflict Predictions -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Active Conflict Predictions</h3>
            <div class="space-y-4">
              ${this.predictions.map(prediction => this.renderConflictPrediction(prediction)).join('') || '<p class="text-gray-500">No active conflict predictions</p>'}
            </div>
          </div>

          <!-- Aircraft Tracking -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Active Aircraft Tracking</h3>
            <div class="overflow-x-auto">
              <table class="min-w-full bg-white border">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Callsign</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Altitude</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Speed</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Heading</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Last Update</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  ${this.activeAircraft.slice(0, 10).map(aircraft => `
                    <tr>
                      <td class="px-4 py-2 text-sm font-medium text-gray-900">${aircraft.callsign || aircraft.icao24}</td>
                      <td class="px-4 py-2 text-sm text-gray-500">${aircraft.baro_altitude?.toLocaleString() || 'N/A'} ft</td>
                      <td class="px-4 py-2 text-sm text-gray-500">${Math.round((aircraft.velocity || 0) * 1.94384)} kts</td>
                      <td class="px-4 py-2 text-sm text-gray-500">${Math.round(aircraft.true_track || 0)}°</td>
                      <td class="px-4 py-2 text-sm text-gray-500">${this.formatTimeAgo(aircraft.last_update)}</td>
                      <td class="px-4 py-2 text-sm">
                        <button class="text-blue-600 hover:text-blue-800" onclick="viewAircraft('${aircraft.icao24}')">View</button>
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
            ${this.activeAircraft.length > 10 ? '<p class="text-sm text-gray-500 mt-4">Showing 10 of ' + this.activeAircraft.length + ' aircraft</p>' : ''}
          </div>

          <!-- AI Learning Status -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">AI Learning & Adaptation</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-blue-600">${this.performance?.processing_time_avg || 0}ms</div>
                <div class="text-sm text-gray-600">Avg Processing Time</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-green-600">Active</div>
                <div class="text-sm text-gray-600">Learning Status</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-purple-600">ML</div>
                <div class="text-sm text-gray-600">Model Type</div>
              </div>
            </div>
          </div>
        </div>
      `;
    } catch (error) {
      console.error('Failed to load AI conflict prediction dashboard:', error);
      return `
        <div class="text-center text-red-500 p-8">
          <div class="text-6xl mb-4">🤖</div>
          <h3 class="text-xl font-semibold mb-2">Failed to Load AI System</h3>
          <p class="text-gray-600">Please check your connection and try again.</p>
          <button id="retry-ai-data" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Retry
          </button>
        </div>
      `;
    }
  }

  private async loadData(): Promise<void> {
    try {
      const [predictionsResponse, aircraftResponse, performanceResponse] = await Promise.all([
        fetch('/backend/api/ai-conflicts/predictions', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch('/backend/api/adsb/aircraft', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch('/backend/api/ai-conflicts/performance', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        })
      ]);

      if (predictionsResponse.ok) {
        this.predictions = await predictionsResponse.json();
      }

      if (aircraftResponse.ok) {
        this.activeAircraft = await aircraftResponse.json();
      }

      if (performanceResponse.ok) {
        this.performance = await performanceResponse.json();
      }
    } catch (error) {
      console.error('Failed to load AI data:', error);
    }
  }

  private renderSystemStatus(): string {
    const hasCriticalConflicts = this.predictions.some(p => p.severity > 80 && p.time_to_conflict < 300);
    const systemHealthy = !hasCriticalConflicts && this.activeAircraft.length > 0;

    if (systemHealthy) {
      return `
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
          <div class="flex items-center">
            <div class="shrink-0">
              <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-green-800">AI Conflict Prediction System Active</h3>
              <div class="mt-2 text-sm text-green-700">
                <p>Monitoring ${this.activeAircraft.length} aircraft with ${this.predictions.length} active predictions.</p>
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
            <h3 class="text-sm font-medium text-yellow-800">AI System Monitoring Required</h3>
            <div class="mt-2 text-sm text-yellow-700">
              <p>
                ${hasCriticalConflicts ? 'Critical conflicts detected. ' : ''}
                ${this.activeAircraft.length === 0 ? 'No aircraft data available. ' : ''}
                System requires attention.
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

  private renderConflictPrediction(prediction: ConflictPrediction): string {
    const severityColor = prediction.severity > 80 ? 'red' : prediction.severity > 60 ? 'orange' : 'yellow';
    const timeToConflict = Math.floor(prediction.time_to_conflict / 60);

    return `
      <div class="flex items-center justify-between p-4 border rounded ${
        prediction.severity > 80 ? 'bg-red-50 border-red-200' : 'bg-white'
      }">
        <div class="flex-1">
          <div class="flex items-center space-x-2 mb-2">
            <span class="text-sm font-medium">${prediction.aircraft1} ↔ ${prediction.aircraft2}</span>
            <span class="px-2 py-1 text-xs rounded-full bg-${severityColor}-100 text-${severityColor}-800">
              Severity: ${prediction.severity}%
            </span>
            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
              ${prediction.confidence}% confidence
            </span>
          </div>
          <p class="text-sm text-gray-600">
            Time to conflict: ${timeToConflict} min |
            Min separation: ${prediction.min_horizontal_sep.toFixed(1)} NM horizontal, ${prediction.min_vertical_sep.toFixed(0)} ft vertical
          </p>
          <p class="text-xs text-gray-500">
            Conflict point: ${prediction.predicted_conflict_point.lat.toFixed(4)}, ${prediction.predicted_conflict_point.lon.toFixed(4)}
          </p>
        </div>
        <div class="flex space-x-2">
          <button class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700" onclick="viewConflictDetails('${prediction.aircraft1}', '${prediction.aircraft2}')">
            View Details
          </button>
          ${prediction.recommended_actions.length > 0 ? '<button class="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700" onclick="applyResolution(\'' + prediction.aircraft1 + '\', \'' + prediction.aircraft2 + '\')">Apply Resolution</button>' : ''}
        </div>
      </div>
    `;
  }

  private formatTimeAgo(timestamp: number): string {
    const now = Date.now() / 1000;
    const diff = now - timestamp;

    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)} min ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} hr ago`;
    return `${Math.floor(diff / 86400)} days ago`;
  }

  setupEventListeners(): void {
    const refreshBtn = document.getElementById('refresh-ai-data');
    const reportsBtn = document.getElementById('view-ai-reports');
    const runBtn = document.getElementById('run-prediction');
    const retryBtn = document.getElementById('retry-ai-data');

    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshAIData'));
      });
    }

    if (reportsBtn) {
      reportsBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('showAIReports'));
      });
    }

    if (runBtn) {
      runBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('runAIPrediction'));
      });
    }

    if (retryBtn) {
      retryBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshAIData'));
      });
    }

    // Auto-refresh every 30 seconds
    this.updateInterval = window.setInterval(() => {
      this.loadData().then(() => {
        // Update the UI if needed
        const event = new CustomEvent('aiDataUpdated', { detail: { predictions: this.predictions, aircraft: this.activeAircraft } });
        window.dispatchEvent(event);
      });
    }, 30000);
  }

  destroy(): void {
    if (this.updateInterval) {
      clearInterval(this.updateInterval);
      this.updateInterval = null;
    }
  }
}
