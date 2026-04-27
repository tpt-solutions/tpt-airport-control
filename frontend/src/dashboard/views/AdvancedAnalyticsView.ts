/**
 * Advanced Analytics Dashboard View
 *
 * Comprehensive dashboard for AI-powered predictive analytics, machine learning insights,
 * and automated decision support with real-time monitoring and forecasting capabilities
 */

import { ApiService } from '../../services/ApiService';
import { NotificationManager } from '../../components/NotificationManager';
import { DataTable } from '../../components/DataTable';
import { Modal } from '../../components/Modal';
import { LoadingSpinner } from '../../components/LoadingSpinner';

interface AnalyticsModel {
  model_id: string;
  model_name: string;
  model_type: string;
  model_accuracy: number;
  is_active: boolean;
  created_date: string;
  last_trained: string;
  performance_metrics: {
    accuracy: number;
    precision: number;
    recall: number;
    f1_score: number;
    last_updated: string;
  };
  last_prediction: string;
  usage_stats: {
    total_predictions: number;
    predictions_today: number;
    avg_response_time: number;
    error_rate: number;
  };
}

interface PredictionData {
  prediction_id: string;
  model_id: string;
  predicted_value: number;
  prediction_confidence: number;
  actual_value?: number;
  prediction_error?: number;
  timestamp: string;
  confidence_level: number;
  accuracy_status: string;
}

interface ForecastData {
  forecast_id: string;
  forecast_type: string;
  forecasted_demand: number;
  confidence_interval_lower: number;
  confidence_interval_upper: number;
  forecast_accuracy: number;
  forecast_period_start: string;
  forecast_period_end: string;
  trend_analysis: {
    direction: string;
    magnitude: number;
    confidence: number;
  };
  seasonal_factors: {
    daily_pattern: number[];
    weekly_pattern: number[];
    monthly_pattern: number[];
  };
  risk_assessment: {
    risk_level: string;
    risk_factors: string[];
    mitigation_strategies: string[];
  };
}

interface OperationalInsight {
  insight_id: string;
  insight_type: string;
  insight_title: string;
  insight_description: string;
  confidence_level: number;
  impact_level: string;
  affected_areas: string[];
  recommended_actions: string[];
  insight_date: string;
  is_reviewed: boolean;
}

interface AnomalyData {
  anomaly_id: string;
  anomaly_type: string;
  severity_level: string;
  affected_system: string;
  anomaly_description: string;
  detection_date: string;
  confidence_score: number;
  investigation_status: string;
  recommended_actions: {
    immediate_actions: string[];
    preventive_measures: string[];
    follow_up: string[];
  };
}

interface DashboardMetrics {
  predictive_insights: {
    active_models: number;
    predictions_today: number;
    forecast_accuracy: number;
  };
  demand_forecasting: {
    next_24h_demand: number;
    forecast_confidence: number;
    capacity_utilization: number;
  };
  maintenance_predictions: {
    critical_predictions: number;
    preventive_maintenance_savings: number;
    equipment_health_score: number;
  };
  anomaly_detection: {
    anomalies_today: number;
    unresolved_anomalies: number;
    false_positive_rate: number;
  };
  performance_metrics: {
    operational_efficiency: number;
    customer_satisfaction: number;
    revenue_optimization: number;
  };
  automated_insights: Array<{
    type: string;
    title: string;
    impact: string;
    confidence: number;
  }>;
  alerts: Array<{
    type: string;
    severity: string;
    message: string;
    status: string;
  }>;
}

export class AdvancedAnalyticsView {
  private container: HTMLElement;
  private apiService: ApiService;
  private notificationManager: NotificationManager;
  private currentView: 'dashboard' | 'models' | 'predictions' | 'forecasts' | 'insights' | 'anomalies' | 'reports' = 'dashboard';

  // Dashboard components
  private modelsTable: DataTable | null = null;
  private predictionsTable: DataTable | null = null;
  private forecastsTable: DataTable | null = null;
  private insightsTable: DataTable | null = null;
  private anomaliesTable: DataTable | null = null;

  // Modal components
  private modelModal: Modal | null = null;
  private predictionModal: Modal | null = null;
  private forecastModal: Modal | null = null;

  // Data
  private analyticsModels: AnalyticsModel[] = [];
  private predictions: PredictionData[] = [];
  private forecasts: ForecastData[] = [];
  private operationalInsights: OperationalInsight[] = [];
  private anomalies: AnomalyData[] = [];
  private dashboardMetrics: DashboardMetrics | null = null;

  // Charts
  private accuracyChart: any = null;
  private predictionsChart: any = null;
  private forecastChart: any = null;

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new ApiService();
    this.notificationManager = new NotificationManager();
    this.initialize();
  }

  private async initialize(): Promise<void> {
    this.render();
    await this.loadDashboardData();
    this.setupEventListeners();
    this.startAutoRefresh();
  }

  private render(): void {
    this.container.innerHTML = `
      <div class="advanced-analytics-dashboard">
        <!-- Header -->
        <div class="dashboard-header">
          <h1>Advanced Analytics & AI Insights</h1>
          <div class="header-actions">
            <button id="refresh-btn" class="btn btn-secondary">
              <i class="icon-refresh"></i> Refresh
            </button>
            <button id="train-models-btn" class="btn btn-primary">
              <i class="icon-brain"></i> Train Models
            </button>
            <button id="generate-insights-btn" class="btn btn-success">
              <i class="icon-lightbulb"></i> Generate Insights
            </button>
          </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="dashboard-tabs">
          <button class="tab-btn active" data-view="dashboard">
            <i class="icon-dashboard"></i> Dashboard
          </button>
          <button class="tab-btn" data-view="models">
            <i class="icon-brain"></i> AI Models
          </button>
          <button class="tab-btn" data-view="predictions">
            <i class="icon-trending-up"></i> Predictions
          </button>
          <button class="tab-btn" data-view="forecasts">
            <i class="icon-calendar"></i> Forecasts
          </button>
          <button class="tab-btn" data-view="insights">
            <i class="icon-lightbulb"></i> Insights
          </button>
          <button class="tab-btn" data-view="anomalies">
            <i class="icon-alert-triangle"></i> Anomalies
          </button>
          <button class="tab-btn" data-view="reports">
            <i class="icon-file-text"></i> Reports
          </button>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
          <!-- Dashboard View -->
          <div class="view-container" id="dashboard-view">
            <div class="dashboard-grid">
              <!-- Key Metrics -->
              <div class="dashboard-card metrics-overview">
                <div class="card-header">
                  <h3>AI Performance Overview</h3>
                </div>
                <div class="card-content">
                  <div class="metrics-grid">
                    <div class="metric-item">
                      <div class="metric-icon">
                        <i class="icon-brain"></i>
                      </div>
                      <div class="metric-content">
                        <div class="metric-value" id="active-models">0</div>
                        <div class="metric-label">Active Models</div>
                      </div>
                    </div>
                    <div class="metric-item">
                      <div class="metric-icon">
                        <i class="icon-trending-up"></i>
                      </div>
                      <div class="metric-content">
                        <div class="metric-value" id="predictions-today">0</div>
                        <div class="metric-label">Predictions Today</div>
                      </div>
                    </div>
                    <div class="metric-item">
                      <div class="metric-icon">
                        <i class="icon-target"></i>
                      </div>
                      <div class="metric-content">
                        <div class="metric-value" id="forecast-accuracy">0%</div>
                        <div class="metric-label">Forecast Accuracy</div>
                      </div>
                    </div>
                    <div class="metric-item">
                      <div class="metric-icon">
                        <i class="icon-alert-triangle"></i>
                      </div>
                      <div class="metric-content">
                        <div class="metric-value" id="anomalies-today">0</div>
                        <div class="metric-label">Anomalies Detected</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Demand Forecasting -->
              <div class="dashboard-card">
                <div class="card-header">
                  <h3>Demand Forecasting</h3>
                  <div class="card-actions">
                    <button class="btn btn-link" id="view-forecasts">View All</button>
                  </div>
                </div>
                <div class="card-content">
                  <div class="forecast-summary">
                    <div class="forecast-item">
                      <span class="forecast-label">Next 24h Demand:</span>
                      <span class="forecast-value" id="next-24h-demand">0</span>
                    </div>
                    <div class="forecast-item">
                      <span class="forecast-label">Confidence:</span>
                      <span class="forecast-value" id="forecast-confidence">0%</span>
                    </div>
                    <div class="forecast-item">
                      <span class="forecast-label">Capacity Utilization:</span>
                      <span class="forecast-value" id="capacity-utilization">0%</span>
                    </div>
                  </div>
                  <canvas id="forecast-chart" width="300" height="150"></canvas>
                </div>
              </div>

              <!-- Maintenance Predictions -->
              <div class="dashboard-card">
                <div class="card-header">
                  <h3>Predictive Maintenance</h3>
                  <div class="card-actions">
                    <button class="btn btn-link" id="view-maintenance">View Details</button>
                  </div>
                </div>
                <div class="card-content">
                  <div class="maintenance-summary">
                    <div class="maintenance-item critical">
                      <span class="maintenance-label">Critical Predictions:</span>
                      <span class="maintenance-value" id="critical-predictions">0</span>
                    </div>
                    <div class="maintenance-item">
                      <span class="maintenance-label">Potential Savings:</span>
                      <span class="maintenance-value" id="maintenance-savings">$0</span>
                    </div>
                    <div class="maintenance-item">
                      <span class="maintenance-label">Equipment Health:</span>
                      <span class="maintenance-value" id="equipment-health">0%</span>
                    </div>
                  </div>
                  <div class="health-indicator">
                    <div class="health-bar">
                      <div class="health-fill" id="health-fill" style="width: 0%"></div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Automated Insights -->
              <div class="dashboard-card">
                <div class="card-header">
                  <h3>Automated Insights</h3>
                  <div class="card-actions">
                    <button class="btn btn-link" id="view-insights">View All</button>
                  </div>
                </div>
                <div class="card-content">
                  <div id="insights-list" class="insights-list">
                    <div class="loading-spinner">
                      <i class="icon-spinner"></i> Loading insights...
                    </div>
                  </div>
                </div>
              </div>

              <!-- Model Performance Chart -->
              <div class="dashboard-card">
                <div class="card-header">
                  <h3>Model Performance</h3>
                </div>
                <div class="card-content">
                  <canvas id="performance-chart" width="300" height="200"></canvas>
                </div>
              </div>

              <!-- Recent Alerts -->
              <div class="dashboard-card">
                <div class="card-header">
                  <h3>System Alerts</h3>
                  <div class="card-actions">
                    <button class="btn btn-link" id="view-alerts">View All</button>
                  </div>
                </div>
                <div class="card-content">
                  <div id="alerts-list" class="alerts-list">
                    <div class="loading-spinner">
                      <i class="icon-spinner"></i> Loading alerts...
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Models View -->
          <div class="view-container hidden" id="models-view">
            <div class="models-management">
              <div class="models-header">
                <div class="models-stats">
                  <div class="stat-item">
                    <span class="stat-label">Total Models:</span>
                    <span class="stat-value" id="total-models">0</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Active Models:</span>
                    <span class="stat-value" id="active-models-count">0</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Avg Accuracy:</span>
                    <span class="stat-value" id="avg-accuracy">0%</span>
                  </div>
                </div>
                <div class="models-actions">
                  <button class="btn btn-primary" id="create-model-btn">
                    <i class="icon-plus"></i> Create Model
                  </button>
                  <button class="btn btn-secondary" id="retrain-models-btn">
                    <i class="icon-refresh"></i> Retrain All
                  </button>
                </div>
              </div>
              <div id="models-table-container"></div>
            </div>
          </div>

          <!-- Predictions View -->
          <div class="view-container hidden" id="predictions-view">
            <div class="predictions-management">
              <div class="predictions-header">
                <div class="filters">
                  <select id="model-filter">
                    <option value="">All Models</option>
                  </select>
                  <select id="confidence-filter">
                    <option value="">All Confidence</option>
                    <option value="high">High (>80%)</option>
                    <option value="medium">Medium (60-80%)</option>
                    <option value="low">Low (<60%)</option>
                  </select>
                  <input type="date" id="predictions-date-from">
                  <input type="date" id="predictions-date-to">
                  <button class="btn btn-secondary" id="filter-predictions-btn">
                    <i class="icon-filter"></i> Filter
                  </button>
                </div>
                <button class="btn btn-primary" id="export-predictions-btn">
                  <i class="icon-download"></i> Export
                </button>
              </div>
              <div id="predictions-table-container"></div>
            </div>
          </div>

          <!-- Forecasts View -->
          <div class="view-container hidden" id="forecasts-view">
            <div class="forecasts-management">
              <div class="forecasts-header">
                <div class="forecast-filters">
                  <select id="forecast-type-filter">
                    <option value="">All Types</option>
                    <option value="passenger_demand">Passenger Demand</option>
                    <option value="cargo_demand">Cargo Demand</option>
                    <option value="service_demand">Service Demand</option>
                  </select>
                  <input type="date" id="forecast-date-from">
                  <input type="date" id="forecast-date-to">
                  <button class="btn btn-secondary" id="filter-forecasts-btn">
                    <i class="icon-filter"></i> Filter
                  </button>
                </div>
                <div class="forecast-actions">
                  <button class="btn btn-primary" id="generate-forecast-btn">
                    <i class="icon-plus"></i> Generate Forecast
                  </button>
                  <button class="btn btn-secondary" id="export-forecasts-btn">
                    <i class="icon-download"></i> Export
                  </button>
                </div>
              </div>
              <div id="forecasts-table-container"></div>
            </div>
          </div>

          <!-- Insights View -->
          <div class="view-container hidden" id="insights-view">
            <div class="insights-management">
              <div class="insights-header">
                <div class="insights-filters">
                  <select id="insight-type-filter">
                    <option value="">All Types</option>
                    <option value="operational">Operational</option>
                    <option value="performance">Performance</option>
                    <option value="predictive">Predictive</option>
                    <option value="anomaly">Anomaly</option>
                  </select>
                  <select id="impact-filter">
                    <option value="">All Impact</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                  </select>
                  <label>
                    <input type="checkbox" id="unreviewed-only"> Unreviewed Only
                  </label>
                </div>
                <button class="btn btn-primary" id="generate-insight-btn">
                  <i class="icon-plus"></i> Generate Insight
                </button>
              </div>
              <div id="insights-table-container"></div>
            </div>
          </div>

          <!-- Anomalies View -->
          <div class="view-container hidden" id="anomalies-view">
            <div class="anomalies-management">
              <div class="anomalies-header">
                <div class="anomalies-stats">
                  <div class="stat-item">
                    <span class="stat-label">Today:</span>
                    <span class="stat-value" id="anomalies-today-count">0</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Unresolved:</span>
                    <span class="stat-value" id="unresolved-anomalies">0</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">False Positive Rate:</span>
                    <span class="stat-value" id="false-positive-rate">0%</span>
                  </div>
                </div>
                <button class="btn btn-secondary" id="export-anomalies-btn">
                  <i class="icon-download"></i> Export
                </button>
              </div>
              <div id="anomalies-table-container"></div>
            </div>
          </div>

          <!-- Reports View -->
          <div class="view-container hidden" id="reports-view">
            <div class="reports-section">
              <div class="reports-header">
                <h3>Analytics Reports</h3>
                <div class="report-filters">
                  <div class="date-range">
                    <label>From:</label>
                    <input type="date" id="report-start-date">
                    <label>To:</label>
                    <input type="date" id="report-end-date">
                  </div>
                </div>
              </div>

              <div class="reports-grid">
                <div class="report-card">
                  <div class="report-icon">
                    <i class="icon-bar-chart"></i>
                  </div>
                  <div class="report-content">
                    <h4>Predictive Analytics Report</h4>
                    <p>Comprehensive analysis of AI model performance and prediction accuracy</p>
                    <button class="btn btn-secondary" id="predictive-report-btn">
                      Generate Report
                    </button>
                  </div>
                </div>

                <div class="report-card">
                  <div class="report-icon">
                    <i class="icon-trending-up"></i>
                  </div>
                  <div class="report-content">
                    <h4>Demand Forecasting Report</h4>
                    <p>Analysis of demand patterns, forecasting accuracy, and capacity planning</p>
                    <button class="btn btn-secondary" id="forecasting-report-btn">
                      Generate Report
                    </button>
                  </div>
                </div>

                <div class="report-card">
                  <div class="report-icon">
                    <i class="icon-lightbulb"></i>
                  </div>
                  <div class="report-content">
                    <h4>Operational Insights Report</h4>
                    <p>Automated insights, anomaly detection, and optimization recommendations</p>
                    <button class="btn btn-secondary" id="insights-report-btn">
                      Generate Report
                    </button>
                  </div>
                </div>

                <div class="report-card">
                  <div class="report-icon">
                    <i class="icon-cog"></i>
                  </div>
                  <div class="report-content">
                    <h4>Model Performance Report</h4>
                    <p>Detailed analysis of AI model accuracy, training performance, and optimization</p>
                    <button class="btn btn-secondary" id="performance-report-btn">
                      Generate Report
                    </button>
                  </div>
                </div>
              </div>

              <div id="report-results" class="report-results hidden">
                <div class="report-header">
                  <h4 id="report-title">Report Results</h4>
                  <div class="report-actions">
                    <button class="btn btn-secondary" id="export-report-btn">
                      <i class="icon-download"></i> Export PDF
                    </button>
                    <button class="btn btn-secondary" id="print-report-btn">
                      <i class="icon-printer"></i> Print
                    </button>
                  </div>
                </div>
                <div id="report-content" class="report-content"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;

    this.applyStyles();
  }

  private applyStyles(): void {
    const style = document.createElement('style');
    style.textContent = `
      .advanced-analytics-dashboard {
        padding: 20px;
        background: #f8f9fa;
        min-height: 100vh;
      }

      .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #e9ecef;
      }

      .dashboard-header h1 {
        color: #2c3e50;
        margin: 0;
        font-size: 2rem;
        font-weight: 600;
      }

      .header-actions {
        display: flex;
        gap: 10px;
      }

      .dashboard-tabs {
        display: flex;
        margin-bottom: 30px;
        background: white;
        border-radius: 8px;
        padding: 5px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        overflow-x: auto;
      }

      .tab-btn {
        flex: 1;
        min-width: 120px;
        padding: 12px 20px;
        border: none;
        background: transparent;
        color: #6c757d;
        font-weight: 500;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
      }

      .tab-btn.active {
        background: #007bff;
        color: white;
        box-shadow: 0 2px 8px rgba(0,123,255,0.3);
      }

      .tab-btn:hover:not(.active) {
        background: #e9ecef;
        color: #495057;
      }

      .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 25px;
      }

      .dashboard-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        overflow: hidden;
      }

      .card-header {
        padding: 20px 25px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }

      .card-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.2rem;
        font-weight: 600;
      }

      .card-content {
        padding: 25px;
      }

      .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
      }

      .metric-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 12px;
        transition: transform 0.2s ease;
      }

      .metric-item:hover {
        transform: translateY(-2px);
      }

      .metric-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
      }

      .metric-item:nth-child(1) .metric-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
      .metric-item:nth-child(2) .metric-icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
      .metric-item:nth-child(3) .metric-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
      .metric-item:nth-child(4) .metric-icon { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

      .metric-content {
        flex: 1;
      }

      .metric-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 5px;
      }

      .metric-label {
        color: #6c757d;
        font-size: 0.9rem;
        font-weight: 500;
      }

      .forecast-summary,
      .maintenance-summary {
        margin-bottom: 20px;
      }

      .forecast-item,
      .maintenance-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #f8f9fa;
      }

      .forecast-item:last-child,
      .maintenance-item:last-child {
        border-bottom: none;
      }

      .forecast-label,
      .maintenance-label {
        color: #6c757d;
        font-weight: 500;
      }

      .forecast-value,
      .maintenance-value {
        font-weight: 600;
        color: #2c3e50;
      }

      .maintenance-item.critical .maintenance-value {
        color: #dc3545;
        font-weight: 700;
      }

      .health-indicator {
        margin-top: 20px;
      }

      .health-bar {
        width: 100%;
        height: 20px;
        background: #e9ecef;
        border-radius: 10px;
        overflow: hidden;
      }

      .health-fill {
        height: 100%;
        background: linear-gradient(90deg, #dc3545 0%, #ffc107 50%, #28a745 100%);
        transition: width 0.5s ease;
      }

      .insights-list,
      .alerts-list {
        max-height: 250px;
        overflow-y: auto;
      }

      .insight-item,
      .alert-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 15px;
        margin-bottom: 10px;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid #007bff;
      }

      .insight-item:last-child,
      .alert-item:last-child {
        margin-bottom: 0;
      }

      .insight-content,
      .alert-content {
        flex: 1;
      }

      .insight-title,
      .alert-title {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 5px;
      }

      .insight-description,
      .alert-description {
        color: #6c757d;
        font-size: 0.9rem;
        margin-bottom: 8px;
      }

      .insight-meta,
      .alert-meta {
        display: flex;
        gap: 15px;
        font-size: 0.8rem;
        color: #6c757d;
      }

      .insight-confidence,
      .alert-severity {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
      }

      .confidence-high { background: #d4edda; color: #155724; }
      .confidence-medium { background: #fff3cd; color: #856404; }
      .confidence-low { background: #f8d7da; color: #721c24; }

      .severity-critical { background: #f8d7da; color: #721c24; }
      .severity-high { background: #f8d7da; color: #721c24; }
      .severity-medium { background: #fff3cd; color: #856404; }
      .severity-low { background: #d1ecf1; color: #0c5460; }

      .view-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        overflow: hidden;
      }

      .view-container.hidden {
        display: none;
      }

      .models-management,
      .predictions-management,
      .forecasts-management,
      .insights-management,
      .anomalies-management {
        padding: 25px;
      }

      .models-header,
      .predictions-header,
      .forecasts-header,
      .insights-header,
      .anomalies-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e9ecef;
      }

      .models-stats,
      .anomalies-stats {
        display: flex;
        gap: 25px;
      }

      .stat-item {
        text-align: center;
      }

      .stat-item .stat-label {
        color: #6c757d;
        font-size: 0.8rem;
        margin-bottom: 5px;
      }

      .stat-item .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2c3e50;
      }

      .models-actions,
      .forecast-actions {
        display: flex;
        gap: 10px;
      }

      .filters,
      .forecast-filters,
      .insights-filters {
        display: flex;
        gap: 15px;
        align-items: center;
        flex-wrap: wrap;
      }

      .filters select,
      .filters input,
      .forecast-filters select,
      .forecast-filters input,
      .insights-filters select,
      .insights-filters input {
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        font-size: 0.9rem;
      }

      .insights-filters label {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.9rem;
        color: #495057;
      }

      .reports-section {
        padding: 25px;
      }

      .reports-header {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e9ecef;
      }

      .reports-header h3 {
        margin: 0 0 15px 0;
        color: #2c3e50;
        font-size: 1.5rem;
      }

      .report-filters {
        display: flex;
        gap: 15px;
        align-items: center;
      }

      .date-range {
        display: flex;
        align-items: center;
        gap: 10px;
      }

      .date-range label {
        font-weight: 500;
        color: #495057;
      }

      .date-range input {
        padding: 6px 10px;
        border: 1px solid #ced4da;
        border-radius: 4px;
      }

      .reports-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
      }

      .report-card {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        padding: 25px;
        display: flex;
        gap: 20px;
        transition: all 0.3s ease;
        cursor: pointer;
      }

      .report-card:hover {
        border-color: #007bff;
        box-shadow: 0 4px 12px rgba(0,123,255,0.15);
        transform: translateY(-2px);
      }

      .report-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
      }

      .report-content {
        flex: 1;
      }

      .report-content h4 {
        margin: 0 0 10px 0;
        color: #2c3e50;
        font-size: 1.1rem;
      }

      .report-content p {
        margin: 0 0 15px 0;
        color: #6c757d;
        font-size: 0.9rem;
        line-height: 1.4;
      }

      .report-results {
        background: white;
        border-radius: 12px;
        padding: 25px;
        border: 1px solid #e9ecef;
      }

      .report-results.hidden {
        display: none;
      }

      .report-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e9ecef;
      }

      .report-header h4 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.3rem;
      }

      .report-actions {
        display: flex;
        gap: 10px;
      }

      .report-content {
        max-height: 600px;
        overflow-y: auto;
      }

      .loading-spinner {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        color: #6c757d;
        padding: 40px;
      }

      .loading-spinner i {
        animation: spin 1s linear infinite;
      }

      @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
      }

      .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
      }

      .btn-primary {
        background: #007bff;
        color: white;
      }

      .btn-primary:hover {
        background: #0056b3;
      }

      .btn-secondary {
        background: #6c757d;
        color: white;
      }

      .btn-secondary:hover {
        background: #545b62;
      }

      .btn-success {
        background: #28a745;
        color: white;
      }

      .btn-success:hover {
        background: #1e7e34;
      }

      .btn-link {
        background: transparent;
        color: #007bff;
        padding: 4px 8px;
      }

      .btn-link:hover {
        background: #f8f9fa;
      }

      .hidden {
        display: none !important;
      }

      @media (max-width: 768px) {
        .advanced-analytics-dashboard {
          padding: 15px;
        }

        .dashboard-header {
          flex-direction: column;
          gap: 15px;
          text-align: center;
        }

        .dashboard-tabs {
          flex-wrap: wrap;
        }

        .tab-btn {
          min-width: 100px;
          padding: 8px 12px;
        }

        .metrics-grid {
          grid-template-columns: 1fr;
        }

        .dashboard-grid {
          grid-template-columns: 1fr;
        }

        .models-header,
        .predictions-header,
        .forecasts-header,
        .insights-header,
        .anomalies-header {
          flex-direction: column;
          gap: 15px;
        }

        .models-stats,
        .anomalies-stats {
          flex-wrap: wrap;
        }

        .filters,
        .forecast-filters,
        .insights-filters {
          flex-direction: column;
          align-items: stretch;
        }

        .reports-grid {
          grid-template-columns: 1fr;
        }
      }
    `;
    document.head.appendChild(style);
  }

  private setupEventListeners(): void {
    // Tab navigation
    const tabButtons = this.container.querySelectorAll('.tab-btn');
    tabButtons.forEach(button => {
      button.addEventListener('click', (e) => {
        const target = e.currentTarget as HTMLElement;
        const view = target.dataset.view as typeof this.currentView;
        this.switchView(view);
      });
    });

    // Header actions
    const refreshBtn = this.container.querySelector('#refresh-btn') as HTMLButtonElement;
    refreshBtn?.addEventListener('click', () => this.loadDashboardData());

    const trainModelsBtn = this.container.querySelector('#train-models-btn') as HTMLButtonElement;
    trainModelsBtn?.addEventListener('click', () => this.trainAllModels());

    const generateInsightsBtn = this.container.querySelector('#generate-insights-btn') as HTMLButtonElement;
    generateInsightsBtn?.addEventListener('click', () => this.generateInsights());

    // Dashboard actions
    const viewForecastsBtn = this.container.querySelector('#view-forecasts') as HTMLButtonElement;
    viewForecastsBtn?.addEventListener('click', () => this.switchView('forecasts'));

    const viewMaintenanceBtn = this.container.querySelector('#view-maintenance') as HTMLButtonElement;
    viewMaintenanceBtn?.addEventListener('click', () => this.switchView('insights'));

    const viewInsightsBtn = this.container.querySelector('#view-insights') as HTMLButtonElement;
    viewInsightsBtn?.addEventListener('click', () => this.switchView('insights'));

    const viewAlertsBtn = this.container.querySelector('#view-alerts') as HTMLButtonElement;
    viewAlertsBtn?.addEventListener('click', () => this.switchView('anomalies'));

    // Model management
    const createModelBtn = this.container.querySelector('#create-model-btn') as HTMLButtonElement;
    createModelBtn?.addEventListener('click', () => this.showCreateModelModal());

    const retrainModelsBtn = this.container.querySelector('#retrain-models-btn') as HTMLButtonElement;
    retrainModelsBtn?.addEventListener('click', () => this.retrainAllModels());

    // Filter buttons
    const filterPredictionsBtn = this.container.querySelector('#filter-predictions-btn') as HTMLButtonElement;
    filterPredictionsBtn?.addEventListener('click', () => this.filterPredictions());

    const filterForecastsBtn = this.container.querySelector('#filter-forecasts-btn') as HTMLButtonElement;
    filterForecastsBtn?.addEventListener('click', () => this.filterForecasts());

    // Generate buttons
    const generateForecastBtn = this.container.querySelector('#generate-forecast-btn') as HTMLButtonElement;
    generateForecastBtn?.addEventListener('click', () => this.showGenerateForecastModal());

    const generateInsightBtn = this.container.querySelector('#generate-insight-btn') as HTMLButtonElement;
    generateInsightBtn?.addEventListener('click', () => this.showGenerateInsightModal());

    // Export buttons
    const exportPredictionsBtn = this.container.querySelector('#export-predictions-btn') as HTMLButtonElement;
    exportPredictionsBtn?.addEventListener('click', () => this.exportPredictions());

    const exportForecastsBtn = this.container.querySelector('#export-forecasts-btn') as HTMLButtonElement;
    exportForecastsBtn?.addEventListener('click', () => this.exportForecasts());

    const exportAnomaliesBtn = this.container.querySelector('#export-anomalies-btn') as HTMLButtonElement;
    exportAnomaliesBtn?.addEventListener('click', () => this.exportAnomalies());

    // Report generation buttons
    const predictiveReportBtn = this.container.querySelector('#predictive-report-btn') as HTMLButtonElement;
    predictiveReportBtn?.addEventListener('click', () => this.generatePredictiveReport());

    const forecastingReportBtn = this.container.querySelector('#forecasting-report-btn') as HTMLButtonElement;
    forecastingReportBtn?.addEventListener('click', () => this.generateForecastingReport());

    const insightsReportBtn = this.container.querySelector('#insights-report-btn') as HTMLButtonElement;
    insightsReportBtn?.addEventListener('click', () => this.generateInsightsReport());

    const performanceReportBtn = this.container.querySelector('#performance-report-btn') as HTMLButtonElement;
    performanceReportBtn?.addEventListener('click', () => this.generatePerformanceReport());

    // Export and print buttons
    const exportReportBtn = this.container.querySelector('#export-report-btn') as HTMLButtonElement;
    exportReportBtn?.addEventListener('click', () => this.exportReport());

    const printReportBtn = this.container.querySelector('#print-report-btn') as HTMLButtonElement;
    printReportBtn?.addEventListener('click', () => this.printReport());
  }

  private async loadDashboardData(): Promise<void> {
    try {
      // Load dashboard metrics
      await this.loadDashboardMetrics();

      // Load analytics models
      await this.loadAnalyticsModels();

      // Load recent predictions
      await this.loadRecentPredictions();

      // Load forecasts
      await this.loadForecasts();

      // Load operational insights
      await this.loadOperationalInsights();

      // Load anomalies
      await this.loadAnomalies();

      // Update UI
      this.updateDashboardDisplay();
      this.updateInsightsDisplay();
      this.updateAlertsDisplay();

    } catch (error) {
      console.error('Error loading dashboard data:', error);
      this.notificationManager.show('Error loading dashboard data', 'error');
    }
  }

  private async loadDashboardMetrics(): Promise<void> {
    try {
      const response = await this.apiService.get('/api/advanced-analytics/dashboard');
      this.dashboardMetrics = response as unknown as DashboardMetrics;
    } catch (error) {
      console.error('Error loading dashboard metrics:', error);
    }
  }

  private async loadAnalyticsModels(): Promise<void> {
    try {
      const response = await this.apiService.get('/api/advanced-analytics/models');
      this.analyticsModels = response as unknown as AnalyticsModel[];
    } catch (error) {
      console.error('Error loading analytics models:', error);
    }
  }

  private async loadRecentPredictions(): Promise<void> {
    try {
      const response = await this.apiService.get('/api/advanced-analytics/predictions?limit=50');
      this.predictions = response as unknown as PredictionData[];
    } catch (error) {
      console.error('Error loading predictions:', error);
    }
  }

  private async loadForecasts(): Promise<void> {
    try {
      const response = await this.apiService.get('/api/advanced-analytics/forecasts?limit=20');
      this.forecasts = response as unknown as ForecastData[];
    } catch (error) {
      console.error('Error loading forecasts:', error);
    }
  }

  private async loadOperationalInsights(): Promise<void> {
    try {
      const response = await this.apiService.get('/api/advanced-analytics/insights?limit=10');
      this.operationalInsights = response as unknown as OperationalInsight[];
    } catch (error) {
      console.error('Error loading insights:', error);
    }
  }

  private async loadAnomalies(): Promise<void> {
    try {
      const response = await this.apiService.get('/api/advanced-analytics/anomalies?limit=20');
      this.anomalies = response as unknown as AnomalyData[];
    } catch (error) {
      console.error('Error loading anomalies:', error);
    }
  }

  private updateDashboardDisplay(): void {
    if (!this.dashboardMetrics) return;

    // Update metrics
    const activeModelsEl = this.container.querySelector('#active-models') as HTMLElement;
    const predictionsTodayEl = this.container.querySelector('#predictions-today') as HTMLElement;
    const forecastAccuracyEl = this.container.querySelector('#forecast-accuracy') as HTMLElement;
    const anomaliesTodayEl = this.container.querySelector('#anomalies-today') as HTMLElement;

    if (activeModelsEl) activeModelsEl.textContent = this.dashboardMetrics.predictive_insights.active_models.toString();
    if (predictionsTodayEl) predictionsTodayEl.textContent = this.dashboardMetrics.predictive_insights.predictions_today.toString();
    if (forecastAccuracyEl) forecastAccuracyEl.textContent = `${Math.round(this.dashboardMetrics.predictive_insights.forecast_accuracy * 100)}%`;
    if (anomaliesTodayEl) anomaliesTodayEl.textContent = this.dashboardMetrics.anomaly_detection.anomalies_today.toString();

    // Update forecast details
    const next24hDemandEl = this.container.querySelector('#next-24h-demand') as HTMLElement;
    const forecastConfidenceEl = this.container.querySelector('#forecast-confidence') as HTMLElement;
    const capacityUtilizationEl = this.container.querySelector('#capacity-utilization') as HTMLElement;

    if (next24hDemandEl) next24hDemandEl.textContent = this.dashboardMetrics.demand_forecasting.next_24h_demand.toString();
    if (forecastConfidenceEl) forecastConfidenceEl.textContent = `${Math.round(this.dashboardMetrics.demand_forecasting.forecast_confidence * 100)}%`;
    if (capacityUtilizationEl) capacityUtilizationEl.textContent = `${Math.round(this.dashboardMetrics.demand_forecasting.capacity_utilization)}%`;

    // Update maintenance details
    const criticalPredictionsEl = this.container.querySelector('#critical-predictions') as HTMLElement;
    const maintenanceSavingsEl = this.container.querySelector('#maintenance-savings') as HTMLElement;
    const equipmentHealthEl = this.container.querySelector('#equipment-health') as HTMLElement;
    const healthFillEl = this.container.querySelector('#health-fill') as HTMLElement;

    if (criticalPredictionsEl) criticalPredictionsEl.textContent = this.dashboardMetrics.maintenance_predictions.critical_predictions.toString();
    if (maintenanceSavingsEl) maintenanceSavingsEl.textContent = `$${this.dashboardMetrics.maintenance_predictions.preventive_maintenance_savings.toLocaleString()}`;
    if (equipmentHealthEl) equipmentHealthEl.textContent = `${Math.round(this.dashboardMetrics.maintenance_predictions.equipment_health_score)}%`;
    if (healthFillEl) healthFillEl.style.width = `${this.dashboardMetrics.maintenance_predictions.equipment_health_score}%`;
  }

  private updateInsightsDisplay(): void {
    const container = this.container.querySelector('#insights-list') as HTMLElement;
    if (!container || !this.dashboardMetrics?.automated_insights) return;

    if (this.dashboardMetrics.automated_insights.length === 0) {
      container.innerHTML = '<div class="no-data">No insights available</div>';
      return;
    }

    const insightsHtml = this.dashboardMetrics.automated_insights.map(insight => `
      <div class="insight-item">
        <div class="insight-content">
          <div class="insight-title">${insight.title}</div>
          <div class="insight-description">${insight.impact} impact insight</div>
          <div class="insight-meta">
            <span class="insight-confidence confidence-${this.getConfidenceLevel(insight.confidence)}">
              ${Math.round(insight.confidence * 100)}% confidence
            </span>
          </div>
        </div>
      </div>
    `).join('');

    container.innerHTML = insightsHtml;
  }

  private updateAlertsDisplay(): void {
    const container = this.container.querySelector('#alerts-list') as HTMLElement;
    if (!container || !this.dashboardMetrics?.alerts) return;

    if (this.dashboardMetrics.alerts.length === 0) {
      container.innerHTML = '<div class="no-data">No alerts</div>';
      return;
    }

    const alertsHtml = this.dashboardMetrics.alerts.map(alert => `
      <div class="alert-item">
        <div class="alert-content">
          <div class="alert-title">${alert.message}</div>
          <div class="alert-description">${alert.type} alert</div>
          <div class="alert-meta">
            <span class="alert-severity severity-${alert.severity.toLowerCase()}">
              ${alert.severity}
            </span>
          </div>
        </div>
      </div>
    `).join('');

    container.innerHTML = alertsHtml;
  }

  private switchView(view: typeof this.currentView): void {
    this.currentView = view;

    // Update tab buttons
    const tabButtons = this.container.querySelectorAll('.tab-btn');
    tabButtons.forEach(button => {
      if (button.dataset.view === view) {
        button.classList.add('active');
      } else {
        button.classList.remove('active');
      }
    });

    // Update view containers
    const viewContainers = this.container.querySelectorAll('.view-container');
    viewContainers.forEach(container => {
      if (container.id === `${view}-view`) {
        container.classList.remove('hidden');
      } else {
        container.classList.add('hidden');
      }
    });

    // Load view-specific data
    if (view === 'models') {
      this.loadModelsView();
    } else if (view === 'predictions') {
      this.loadPredictionsView();
    } else if (view === 'forecasts') {
      this.loadForecastsView();
    } else if (view === 'insights') {
      this.loadInsightsView();
    } else if (view === 'anomalies') {
      this.loadAnomaliesView();
    }
  }

  private startAutoRefresh(): void {
    // Auto-refresh data every 60 seconds for analytics
    setInterval(() => {
      if (this.currentView === 'dashboard') {
        this.loadDashboardData();
      }
    }, 60000);
  }

  private async loadModelsView(): Promise<void> {
    const container = this.container.querySelector('#models-table-container') as HTMLElement;
    if (!container) return;

    // Show loading
    container.innerHTML = '<div class="loading-spinner"><i class="icon-spinner"></i> Loading models...</div>';

    try {
      const response = await this.apiService.get('/api/advanced-analytics/models');

      const columns = [
        { key: 'model_name', label: 'Model Name', sortable: true },
        { key: 'model_type', label: 'Type', sortable: true, formatter: (value: string) => this.formatModelType(value) },
        { key: 'model_accuracy', label: 'Accuracy', sortable: true, formatter: (value: number) => `${Math.round(value * 100)}%` },
        { key: 'is_active', label: 'Status', sortable: true, formatter: (value: boolean) => value ? 'Active' : 'Inactive' },
        { key: 'last_trained', label: 'Last Trained', sortable: true, formatter: (value: string) => new Date(value).toLocaleDateString() },
        { key: 'actions', label: 'Actions', formatter: () => '<button class="btn btn-secondary btn-sm">View</button>' }
      ];

      this.modelsTable = new DataTable(container, {
        data: response,
        columns: columns,
        searchable: true,
        sortable: true,
        pagination: true,
        pageSize: 25
      });

      // Update stats
      this.updateModelsStats(response);

    } catch (error) {
      console.error('Error loading models view:', error);
      container.innerHTML = '<div class="error">Error loading models</div>';
    }
  }

  private async loadPredictionsView(): Promise<void> {
    const container = this.container.querySelector('#predictions-table-container') as HTMLElement;
    if (!container) return;

    // Show loading
    container.innerHTML = '<div class="loading-spinner"><i class="icon-spinner"></i> Loading predictions...</div>';

    try {
      const response = await this.apiService.get('/api/advanced-analytics/predictions?limit=100');

      const columns = [
        { key: 'model_id', label: 'Model', sortable: true },
        { key: 'predicted_value', label: 'Prediction', sortable: true },
        { key: 'prediction_confidence', label: 'Confidence', sortable: true, formatter: (value: number) => `${Math.round(value * 100)}%` },
        { key: 'actual_value', label: 'Actual', sortable: true },
        { key: 'accuracy_status', label: 'Accuracy', sortable: true, formatter: (value: string) => this.formatAccuracyStatus(value) },
        { key: 'timestamp', label: 'Timestamp', sortable: true, formatter: (value: string) => new Date(value).toLocaleString() }
      ];

      this.predictionsTable = new DataTable(container, {
        data: response,
        columns: columns,
        searchable: true,
        sortable: true,
        pagination: true,
        pageSize: 25
      });

    } catch (error) {
      console.error('Error loading predictions view:', error);
      container.innerHTML = '<div class="error">Error loading predictions</div>';
    }
  }

  private async loadForecastsView(): Promise<void> {
    const container = this.container.querySelector('#forecasts-table-container') as HTMLElement;
    if (!container) return;

    // Show loading
    container.innerHTML = '<div class="loading-spinner"><i class="icon-spinner"></i> Loading forecasts...</div>';

    try {
      const response = await this.apiService.get('/api/advanced-analytics/forecasts?limit=50');

      const columns = [
        { key: 'forecast_type', label: 'Type', sortable: true },
        { key: 'forecasted_demand', label: 'Forecast', sortable: true },
        { key: 'forecast_accuracy', label: 'Accuracy', sortable: true, formatter: (value: number) => `${Math.round(value * 100)}%` },
        { key: 'forecast_period_start', label: 'Start Date', sortable: true, formatter: (value: string) => new Date(value).toLocaleDateString() },
        { key: 'forecast_period_end', label: 'End Date', sortable: true, formatter: (value: string) => new Date(value).toLocaleDateString() },
        { key: 'trend_analysis', label: 'Trend', formatter: (value: any) => value?.direction || 'N/A' }
      ];

      this.forecastsTable = new DataTable(container, {
        data: response,
        columns: columns,
        searchable: true,
        sortable: true,
        pagination: true,
        pageSize: 25
      });

    } catch (error) {
      console.error('Error loading forecasts view:', error);
      container.innerHTML = '<div class="error">Error loading forecasts</div>';
    }
  }

  private async loadInsightsView(): Promise<void> {
    const container = this.container.querySelector('#insights-table-container') as HTMLElement;
    if (!container) return;

    // Show loading
    container.innerHTML = '<div class="loading-spinner"><i class="icon-spinner"></i> Loading insights...</div>';

    try {
      const response = await this.apiService.get('/api/advanced-analytics/insights?limit=50');

      const columns = [
        { key: 'insight_type', label: 'Type', sortable: true },
        { key: 'insight_title', label: 'Title', sortable: true },
        { key: 'impact_level', label: 'Impact', sortable: true },
        { key: 'confidence_level', label: 'Confidence', sortable: true, formatter: (value: number) => `${Math.round(value * 100)}%` },
        { key: 'is_reviewed', label: 'Reviewed', sortable: true, formatter: (value: boolean) => value ? 'Yes' : 'No' },
        { key: 'insight_date', label: 'Date', sortable: true, formatter: (value: string) => new Date(value).toLocaleDateString() }
      ];

      this.insightsTable = new DataTable(container, {
        data: response,
        columns: columns,
        searchable: true,
        sortable: true,
        pagination: true,
        pageSize: 25
      });

    } catch (error) {
      console.error('Error loading insights view:', error);
      container.innerHTML = '<div class="error">Error loading insights</div>';
    }
  }

  private async loadAnomaliesView(): Promise<void> {
    const container = this.container.querySelector('#anomalies-table-container') as HTMLElement;
    if (!container) return;

    // Show loading
    container.innerHTML = '<div class="loading-spinner"><i class="icon-spinner"></i> Loading anomalies...</div>';

    try {
      const response = await this.apiService.get('/api/advanced-analytics/anomalies?limit=50');

      const columns = [
        { key: 'anomaly_type', label: 'Type', sortable: true },
        { key: 'severity_level', label: 'Severity', sortable: true },
        { key: 'affected_system', label: 'System', sortable: true },
        { key: 'anomaly_description', label: 'Description', sortable: true },
        { key: 'detection_date', label: 'Detected', sortable: true, formatter: (value: string) => new Date(value).toLocaleString() },
        { key: 'investigation_status', label: 'Status', sortable: true }
      ];

      this.anomaliesTable = new DataTable(container, {
        data: response,
        columns: columns,
        searchable: true,
        sortable: true,
        pagination: true,
        pageSize: 25
      });

      // Update anomalies stats
      this.updateAnomaliesStats(response);

    } catch (error) {
      console.error('Error loading anomalies view:', error);
      container.innerHTML = '<div class="error">Error loading anomalies</div>';
    }
  }

  private updateModelsStats(models: AnalyticsModel[]): void {
    const totalModels = models.length;
    const activeModels = models.filter(m => m.is_active).length;
    const avgAccuracy = models.length > 0 ?
      models.reduce((sum, m) => sum + m.model_accuracy, 0) / models.length : 0;

    const totalEl = this.container.querySelector('#total-models') as HTMLElement;
    const activeEl = this.container.querySelector('#active-models-count') as HTMLElement;
    const avgEl = this.container.querySelector('#avg-accuracy') as HTMLElement;

    if (totalEl) totalEl.textContent = totalModels.toString();
    if (activeEl) activeEl.textContent = activeModels.toString();
    if (avgEl) avgEl.textContent = `${Math.round(avgAccuracy * 100)}%`;
  }

  private updateAnomaliesStats(anomalies: AnomalyData[]): void {
    const todayAnomalies = anomalies.filter(a =>
      new Date(a.detection_date).toDateString() === new Date().toDateString()
    ).length;
    const unresolvedAnomalies = anomalies.filter(a => a.investigation_status !== 'resolved').length;
    const falsePositiveRate = 0.05; // This would come from actual data

    const todayEl = this.container.querySelector('#anomalies-today-count') as HTMLElement;
    const unresolvedEl = this.container.querySelector('#unresolved-anomalies') as HTMLElement;
    const falsePositiveEl = this.container.querySelector('#false-positive-rate') as HTMLElement;

    if (todayEl) todayEl.textContent = todayAnomalies.toString();
    if (unresolvedEl) unresolvedEl.textContent = unresolvedAnomalies.toString();
    if (falsePositiveEl) falsePositiveEl.textContent = `${Math.round(falsePositiveRate * 100)}%`;
  }

  private async trainAllModels(): Promise<void> {
    try {
      this.notificationManager.show('Training all models...', 'info');

      // This would call the backend to train all models
      const response = await this.apiService.post('/api/advanced-analytics/train-all', {});
      this.notificationManager.show('Model training started successfully', 'success');

    } catch (error) {
      console.error('Error training models:', error);
      this.notificationManager.show('Error training models', 'error');
    }
  }

  private async generateInsights(): Promise<void> {
    try {
      this.notificationManager.show('Generating insights...', 'info');

      // This would call the backend to generate insights
      const response = await this.apiService.post('/api/advanced-analytics/generate-insights', {});
      this.notificationManager.show('Insights generated successfully', 'success');

    } catch (error) {
      console.error('Error generating insights:', error);
      this.notificationManager.show('Error generating insights', 'error');
    }
  }

  private showCreateModelModal(): void {
    // Implementation for create model modal
    this.notificationManager.show('Create model modal coming soon', 'info');
  }

  private async retrainAllModels(): Promise<void> {
    try {
      this.notificationManager.show('Retraining all models...', 'info');

      // This would call the backend to retrain all models
      const response = await this.apiService.post('/api/advanced-analytics/retrain-all', {});
      this.notificationManager.show('Model retraining started successfully', 'success');

    } catch (error) {
      console.error('Error retraining models:', error);
      this.notificationManager.show('Error retraining models', 'error');
    }
  }

  private filterPredictions(): void {
    // Implementation for filtering predictions
    if (this.predictionsTable) {
      // Apply filters to the table
    }
  }

  private filterForecasts(): void {
    // Implementation for filtering forecasts
    if (this.forecastsTable) {
      // Apply filters to the table
    }
  }

  private showGenerateForecastModal(): void {
    // Implementation for generate forecast modal
    this.notificationManager.show('Generate forecast modal coming soon', 'info');
  }

  private showGenerateInsightModal(): void {
    // Implementation for generate insight modal
    this.notificationManager.show('Generate insight modal coming soon', 'info');
  }

  private exportPredictions(): void {
    // Implementation for exporting predictions
    this.notificationManager.show('Export functionality coming soon', 'info');
  }

  private exportForecasts(): void {
    // Implementation for exporting forecasts
    this.notificationManager.show('Export functionality coming soon', 'info');
  }

  private exportAnomalies(): void {
    // Implementation for exporting anomalies
    this.notificationManager.show('Export functionality coming soon', 'info');
  }

  private async generatePredictiveReport(): Promise<void> {
    const startDate = (this.container.querySelector('#report-start-date') as HTMLInputElement)?.value || '';
    const endDate = (this.container.querySelector('#report-end-date') as HTMLInputElement)?.value || '';

    if (!startDate || !endDate) {
      this.notificationManager.show('Please select date range', 'warning');
      return;
    }

    try {
      const response = await this.apiService.get(`/api/advanced-analytics/reports/predictive?start_date=${startDate}&end_date=${endDate}`);
      this.displayReportResults('Predictive Analytics Report', response);
    } catch (error) {
      console.error('Error generating predictive report:', error);
      this.notificationManager.show('Error generating report', 'error');
    }
  }

  private async generateForecastingReport(): Promise<void> {
    const startDate = (this.container.querySelector('#report-start-date') as HTMLInputElement)?.value || '';
    const endDate = (this.container.querySelector('#report-end-date') as HTMLInputElement)?.value || '';

    if (!startDate || !endDate) {
      this.notificationManager.show('Please select date range', 'warning');
      return;
    }

    try {
      const response = await this.apiService.get(`/api/advanced-analytics/reports/forecasting?start_date=${startDate}&end_date=${endDate}`);
      this.displayReportResults('Demand Forecasting Report', response);
    } catch (error) {
      console.error('Error generating forecasting report:', error);
      this.notificationManager.show('Error generating report', 'error');
    }
  }

  private async generateInsightsReport(): Promise<void> {
    const startDate = (this.container.querySelector('#report-start-date') as HTMLInputElement)?.value || '';
    const endDate = (this.container.querySelector('#report-end-date') as HTMLInputElement)?.value || '';

    if (!startDate || !endDate) {
      this.notificationManager.show('Please select date range', 'warning');
      return;
    }

    try {
      const response = await this.apiService.get(`/api/advanced-analytics/reports/insights?start_date=${startDate}&end_date=${endDate}`);
      this.displayReportResults('Operational Insights Report', response);
    } catch (error) {
      console.error('Error generating insights report:', error);
      this.notificationManager.show('Error generating report', 'error');
    }
  }

  private async generatePerformanceReport(): Promise<void> {
    const startDate = (this.container.querySelector('#report-start-date') as HTMLInputElement)?.value || '';
    const endDate = (this.container.querySelector('#report-end-date') as HTMLInputElement)?.value || '';

    if (!startDate || !endDate) {
      this.notificationManager.show('Please select date range', 'warning');
      return;
    }

    try {
      const response = await this.apiService.get(`/api/advanced-analytics/reports/performance?start_date=${startDate}&end_date=${endDate}`);
      this.displayReportResults('Model Performance Report', response);
    } catch (error) {
      console.error('Error generating performance report:', error);
      this.notificationManager.show('Error generating report', 'error');
    }
  }

  private displayReportResults(title: string, data: any): void {
    const reportResults = this.container.querySelector('#report-results') as HTMLElement;
    const reportTitle = this.container.querySelector('#report-title') as HTMLElement;
    const reportContent = this.container.querySelector('#report-content') as HTMLElement;

    if (reportTitle) reportTitle.textContent = title;
    if (reportContent) {
      reportContent.innerHTML = this.formatReportData(data);
    }
    if (reportResults) reportResults.classList.remove('hidden');
  }

  private exportReport(): void {
    // Implementation for exporting report
    this.notificationManager.show('Report export coming soon', 'info');
  }

  private printReport(): void {
    // Implementation for printing report
    window.print();
  }

  private formatModelType(type: string): string {
    const types: { [key: string]: string } = {
      'demand_forecasting': 'Demand Forecasting',
      'regression': 'Regression',
      'classification': 'Classification',
      'clustering': 'Clustering',
      'time_series': 'Time Series',
      'anomaly_detection': 'Anomaly Detection'
    };
    return types[type] || type;
  }

  private formatAccuracyStatus(status: string): string {
    const statuses: { [key: string]: string } = {
      'high': 'High',
      'medium': 'Medium',
      'low': 'Low'
    };
    return statuses[status] || status;
  }

  private getConfidenceLevel(confidence: number): string {
    if (confidence >= 0.8) return 'high';
    if (confidence >= 0.6) return 'medium';
    return 'low';
  }

  private formatReportData(data: any): string {
    if (!data) return '<div class="no-data">No data available</div>';

    let html = '<div class="report-data">';

    // Format based on data structure
    if (Array.isArray(data)) {
      html += '<table class="report-table">';
      html += '<thead><tr><th>Field</th><th>Value</th></tr></thead>';
      html += '<tbody>';
      data.forEach((item, index) => {
        html += `<tr><td>${index + 1}</td><td>${JSON.stringify(item)}</td></tr>`;
      });
      html += '</tbody></table>';
    } else if (typeof data === 'object') {
      html += '<div class="report-summary">';
      Object.keys(data).forEach(key => {
        html += `<div class="report-item"><strong>${key}:</strong> ${data[key]}</div>`;
      });
      html += '</div>';
    } else {
      html += `<div class="report-text">${data}</div>`;
    }

    html += '</div>';
    return html;
  }
}
