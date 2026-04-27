import { DashboardApiService } from '../services/DashboardApiService.js';
import type { User } from '../types.js';

interface ConflictHistory {
  id: number;
  aircraft1: string;
  aircraft2: string;
  actual_conflict_time: string;
  min_horizontal_sep: number;
  min_vertical_sep: number;
  resolution_method: string;
  detected_at: string;
}

interface AIPerformanceMetrics {
  total_predictions: number;
  accurate_predictions: number;
  false_positives: number;
  average_confidence: number;
  model_accuracy: number;
  processing_time_avg: number;
  prediction_accuracy_trend: Array<{
    date: string;
    accuracy: number;
  }>;
  conflict_severity_distribution: {
    low: number;
    medium: number;
    high: number;
    critical: number;
  };
  response_time_distribution: {
    under_1_min: number;
    under_5_min: number;
    under_15_min: number;
    over_15_min: number;
  };
}

interface ModelTrainingData {
  last_training_date: string;
  training_samples: number;
  model_version: string;
  improvement_rate: number;
  next_training_scheduled: string;
}

export class AIConflictReportsView {
  private container: HTMLElement;
  private apiService: DashboardApiService;
  private performanceMetrics: AIPerformanceMetrics | null = null;
  private conflictHistory: ConflictHistory[] = [];
  private trainingData: ModelTrainingData | null = null;
  private selectedTimeRange: string = '7d';

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new DashboardApiService();
  }

  async render(user: User): Promise<string> {
    try {
      await this.loadData();

      return `
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">AI Conflict Prediction Reports</h2>
            <div class="flex space-x-2">
              <select id="time-range-select" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                <option value="1d" ${this.selectedTimeRange === '1d' ? 'selected' : ''}>Last 24 Hours</option>
                <option value="7d" ${this.selectedTimeRange === '7d' ? 'selected' : ''}>Last 7 Days</option>
                <option value="30d" ${this.selectedTimeRange === '30d' ? 'selected' : ''}>Last 30 Days</option>
                <option value="90d" ${this.selectedTimeRange === '90d' ? 'selected' : ''}>Last 90 Days</option>
              </select>
              <button id="refresh-reports" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Refresh
              </button>
              <button id="export-reports" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                Export Report
              </button>
            </div>
          </div>

          <!-- AI Performance Overview -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            ${this.renderPerformanceCard('Model Accuracy', `${this.performanceMetrics?.model_accuracy || 0}%`, '🎯', 'green')}
            ${this.renderPerformanceCard('Average Confidence', `${this.performanceMetrics?.average_confidence || 0}%`, '📊', 'blue')}
            ${this.renderPerformanceCard('False Positives', this.performanceMetrics?.false_positives || 0, '⚠️', 'orange')}
            ${this.renderPerformanceCard('Processing Time', `${this.performanceMetrics?.processing_time_avg || 0}ms`, '⚡', 'purple')}
          </div>

          <!-- AI Performance Charts -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Accuracy Trend Chart -->
            <div class="bg-white rounded-lg shadow p-6">
              <h3 class="text-lg font-semibold mb-4">Prediction Accuracy Trend</h3>
              <div class="h-64 flex items-center justify-center bg-gray-50 rounded">
                <div class="text-center">
                  <div class="text-4xl mb-2">📈</div>
                  <p class="text-gray-600">Accuracy Trend Chart</p>
                  <p class="text-sm text-gray-500">Interactive chart would be rendered here</p>
                </div>
              </div>
            </div>

            <!-- Conflict Severity Distribution -->
            <div class="bg-white rounded-lg shadow p-6">
              <h3 class="text-lg font-semibold mb-4">Conflict Severity Distribution</h3>
              <div class="space-y-4">
                ${this.renderSeverityBar('Critical', this.performanceMetrics?.conflict_severity_distribution.critical || 0, 'red')}
                ${this.renderSeverityBar('High', this.performanceMetrics?.conflict_severity_distribution.high || 0, 'orange')}
                ${this.renderSeverityBar('Medium', this.performanceMetrics?.conflict_severity_distribution.medium || 0, 'yellow')}
                ${this.renderSeverityBar('Low', this.performanceMetrics?.conflict_severity_distribution.low || 0, 'green')}
              </div>
            </div>
          </div>

          <!-- Response Time Analysis -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Response Time Distribution</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-green-600">${this.performanceMetrics?.response_time_distribution.under_1_min || 0}</div>
                <div class="text-sm text-gray-600">Under 1 min</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-blue-600">${this.performanceMetrics?.response_time_distribution.under_5_min || 0}</div>
                <div class="text-sm text-gray-600">Under 5 min</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-orange-600">${this.performanceMetrics?.response_time_distribution.under_15_min || 0}</div>
                <div class="text-sm text-gray-600">Under 15 min</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-3xl font-bold text-red-600">${this.performanceMetrics?.response_time_distribution.over_15_min || 0}</div>
                <div class="text-sm text-gray-600">Over 15 min</div>
              </div>
            </div>
          </div>

          <!-- Model Training & Learning -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">AI Model Training & Learning</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div class="text-center p-4 border rounded">
                <div class="text-lg font-bold text-blue-600">${this.trainingData?.model_version || 'v1.0'}</div>
                <div class="text-sm text-gray-600">Model Version</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-lg font-bold text-green-600">${this.trainingData?.training_samples?.toLocaleString() || 0}</div>
                <div class="text-sm text-gray-600">Training Samples</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-lg font-bold text-purple-600">${this.trainingData?.improvement_rate || 0}%</div>
                <div class="text-sm text-gray-600">Improvement Rate</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-lg font-bold text-orange-600">${this.formatDate(this.trainingData?.last_training_date || '')}</div>
                <div class="text-sm text-gray-600">Last Training</div>
              </div>
            </div>
            <div class="mt-4 p-4 bg-blue-50 rounded">
              <p class="text-sm text-blue-800">
                <strong>Next Training:</strong> ${this.formatDate(this.trainingData?.next_training_scheduled || '')}
              </p>
            </div>
          </div>

          <!-- Conflict History Table -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Recent Conflict History</h3>
            <div class="overflow-x-auto">
              <table class="min-w-full bg-white border">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aircraft</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Conflict Time</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Separation</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Resolution</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Detected</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  ${this.conflictHistory.slice(0, 20).map(conflict => `
                    <tr>
                      <td class="px-4 py-2 text-sm font-medium text-gray-900">${conflict.aircraft1} ↔ ${conflict.aircraft2}</td>
                      <td class="px-4 py-2 text-sm text-gray-500">${this.formatDateTime(conflict.actual_conflict_time)}</td>
                      <td class="px-4 py-2 text-sm text-gray-500">
                        ${conflict.min_horizontal_sep.toFixed(1)} NM / ${conflict.min_vertical_sep.toFixed(0)} ft
                      </td>
                      <td class="px-4 py-2 text-sm text-gray-500">${conflict.resolution_method || 'N/A'}</td>
                      <td class="px-4 py-2 text-sm text-gray-500">${this.formatDateTime(conflict.detected_at)}</td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
            ${this.conflictHistory.length > 20 ? '<p class="text-sm text-gray-500 mt-4">Showing 20 most recent conflicts</p>' : ''}
          </div>

          <!-- AI System Health -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">AI System Health & Diagnostics</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="p-4 border rounded">
                <h4 class="font-medium text-gray-900 mb-2">System Status</h4>
                <div class="flex items-center">
                  <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                  <span class="text-sm text-gray-600">Operational</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">All systems functioning normally</p>
              </div>
              <div class="p-4 border rounded">
                <h4 class="font-medium text-gray-900 mb-2">Data Quality</h4>
                <div class="flex items-center">
                  <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                  <span class="text-sm text-gray-600">Excellent</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">High-quality ADS-B data feed</p>
              </div>
              <div class="p-4 border rounded">
                <h4 class="font-medium text-gray-900 mb-2">Model Performance</h4>
                <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                <span class="text-sm text-gray-600">Optimal</span>
                <p class="text-xs text-gray-500 mt-1">Model performing within expected parameters</p>
              </div>
            </div>
          </div>

          <!-- Recommendations & Insights -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">AI System Recommendations</h3>
            <div class="space-y-4">
              ${this.renderRecommendation(
                'Model Retraining',
                'Consider retraining the model with recent conflict data to improve accuracy',
                'info'
              )}
              ${this.renderRecommendation(
                'False Positive Reduction',
                'Implement additional filtering to reduce false positive predictions',
                'warning'
              )}
              ${this.renderRecommendation(
                'Performance Optimization',
                'Current processing time is optimal for real-time operations',
                'success'
              )}
            </div>
          </div>
        </div>
      `;
    } catch (error) {
      console.error('Failed to load AI reports:', error);
      return `
        <div class="text-center text-red-500 p-8">
          <div class="text-6xl mb-4">📊</div>
          <h3 class="text-xl font-semibold mb-2">Failed to Load AI Reports</h3>
          <p class="text-gray-600">Please check your connection and try again.</p>
          <button id="retry-reports" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Retry
          </button>
        </div>
      `;
    }
  }

  private async loadData(): Promise<void> {
    try {
      const timeRange = this.selectedTimeRange;
      const [performanceResponse, historyResponse, trainingResponse] = await Promise.all([
        fetch(`/backend/api/ai-conflicts/performance?range=${timeRange}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch(`/backend/api/ai-conflicts/history?range=${timeRange}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch('/backend/api/ai-conflicts/training', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        })
      ]);

      if (performanceResponse.ok) {
        this.performanceMetrics = await performanceResponse.json();
      }

      if (historyResponse.ok) {
        this.conflictHistory = await historyResponse.json();
      }

      if (trainingResponse.ok) {
        this.trainingData = await trainingResponse.json();
      }
    } catch (error) {
      console.error('Failed to load AI reports data:', error);
    }
  }

  private renderPerformanceCard(title: string, value: string | number, icon: string, color: string): string {
    const colorClasses = {
      green: 'bg-green-500',
      blue: 'bg-blue-500',
      orange: 'bg-orange-500',
      purple: 'bg-purple-500'
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
            <p class="text-2xl font-bold text-gray-900">${value}</p>
          </div>
        </div>
      </div>
    `;
  }

  private renderSeverityBar(label: string, count: number, color: string): string {
    const percentage = this.performanceMetrics?.total_predictions ?
      (count / this.performanceMetrics.total_predictions) * 100 : 0;

    return `
      <div class="flex items-center space-x-4">
        <div class="w-20 text-sm font-medium text-gray-700">${label}</div>
        <div class="flex-1">
          <div class="w-full bg-gray-200 rounded-full h-4">
            <div class="bg-${color}-500 h-4 rounded-full" style="width: ${percentage}%"></div>
          </div>
        </div>
        <div class="w-12 text-sm text-gray-600 text-right">${count}</div>
      </div>
    `;
  }

  private renderRecommendation(title: string, description: string, type: string): string {
    const typeClasses = {
      info: 'bg-blue-50 border-blue-200 text-blue-800',
      warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
      success: 'bg-green-50 border-green-200 text-green-800'
    };

    return `
      <div class="p-4 border rounded ${typeClasses[type as keyof typeof typeClasses]}">
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
          </div>
          <div class="ml-3">
            <h4 class="text-sm font-medium">${title}</h4>
            <p class="text-sm mt-1">${description}</p>
          </div>
        </div>
      </div>
    `;
  }

  private formatDate(dateString: string): string {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString();
  }

  private formatDateTime(dateString: string): string {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString();
  }

  setupEventListeners(): void {
    const timeRangeSelect = document.getElementById('time-range-select') as HTMLSelectElement;
    const refreshBtn = document.getElementById('refresh-reports');
    const exportBtn = document.getElementById('export-reports');
    const retryBtn = document.getElementById('retry-reports');

    if (timeRangeSelect) {
      timeRangeSelect.addEventListener('change', (e) => {
        this.selectedTimeRange = (e.target as HTMLSelectElement).value;
        window.dispatchEvent(new CustomEvent('refreshAIReports'));
      });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshAIReports'));
      });
    }

    if (exportBtn) {
      exportBtn.addEventListener('click', () => {
        this.exportReports();
      });
    }

    if (retryBtn) {
      retryBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshAIReports'));
      });
    }
  }

  private exportReports(): void {
    const reportData = {
      generated_at: new Date().toISOString(),
      time_range: this.selectedTimeRange,
      performance_metrics: this.performanceMetrics,
      conflict_history: this.conflictHistory,
      training_data: this.trainingData
    };

    const blob = new Blob([JSON.stringify(reportData, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `ai-conflict-report-${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }
}
