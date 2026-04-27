/**
 * Special Services Dashboard View
 *
 * Comprehensive dashboard for special assistance services coordination,
 * including real-time monitoring, service requests management, and accessibility compliance
 */

import { ApiService } from '../../services/ApiService';
import { NotificationManager } from '../../components/NotificationManager';
import { DataTable } from '../../components/DataTable';
import { Modal } from '../../components/Modal';
import { LoadingSpinner } from '../../components/LoadingSpinner';

interface SpecialServiceRequest {
  request_id: number;
  passenger_id: number;
  service_type: string;
  request_details: string;
  priority_level: string;
  status: string;
  created_at: string;
  assigned_staff_id?: number;
  completion_time?: string;
  passenger_name?: string;
  flight_number?: string;
}

interface ServiceStatistics {
  total_requests: number;
  completed_requests: number;
  in_progress_requests: number;
  pending_requests: number;
  unique_passengers_served: number;
  avg_resolution_time_hours: number;
}

interface EquipmentStatus {
  equipment_type: string;
  total_units: number;
  available_units: number;
  in_use_units: number;
  maintenance_units: number;
  availability_rate: number;
}

export class SpecialServicesView {
  private container: HTMLElement;
  private apiService: ApiService;
  private notificationManager: NotificationManager;
  private currentView: 'dashboard' | 'requests' | 'equipment' | 'reports' = 'dashboard';

  // Dashboard components
  private statsCards: HTMLElement[] = [];
  private requestsTable: DataTable | null = null;
  private equipmentTable: DataTable | null = null;
  private reportsContainer: HTMLElement | null = null;

  // Modal components
  private requestModal: Modal | null = null;
  private equipmentModal: Modal | null = null;

  // Data
  private currentRequests: SpecialServiceRequest[] = [];
  private serviceStats: ServiceStatistics | null = null;
  private equipmentStatus: EquipmentStatus[] = [];

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
      <div class="special-services-dashboard">
        <!-- Header -->
        <div class="dashboard-header">
          <h1>Special Services Coordination</h1>
          <div class="header-actions">
            <button id="refresh-btn" class="btn btn-secondary">
              <i class="icon-refresh"></i> Refresh
            </button>
            <button id="new-request-btn" class="btn btn-primary">
              <i class="icon-plus"></i> New Request
            </button>
          </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="dashboard-tabs">
          <button class="tab-btn active" data-view="dashboard">
            <i class="icon-dashboard"></i> Dashboard
          </button>
          <button class="tab-btn" data-view="requests">
            <i class="icon-list"></i> Service Requests
          </button>
          <button class="tab-btn" data-view="equipment">
            <i class="icon-tools"></i> Equipment
          </button>
          <button class="tab-btn" data-view="reports">
            <i class="icon-chart"></i> Reports
          </button>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
          <!-- Statistics Cards -->
          <div class="stats-grid" id="stats-grid">
            <div class="stat-card">
              <div class="stat-icon">
                <i class="icon-users"></i>
              </div>
              <div class="stat-content">
                <div class="stat-value" id="total-requests">0</div>
                <div class="stat-label">Total Requests</div>
                <div class="stat-trend" id="requests-trend"></div>
              </div>
            </div>

            <div class="stat-card">
              <div class="stat-icon">
                <i class="icon-check-circle"></i>
              </div>
              <div class="stat-content">
                <div class="stat-value" id="completed-requests">0</div>
                <div class="stat-label">Completed Today</div>
                <div class="stat-trend" id="completed-trend"></div>
              </div>
            </div>

            <div class="stat-card">
              <div class="stat-icon">
                <i class="icon-clock"></i>
              </div>
              <div class="stat-content">
                <div class="stat-value" id="pending-requests">0</div>
                <div class="stat-label">Pending Requests</div>
                <div class="stat-trend" id="pending-trend"></div>
              </div>
            </div>

            <div class="stat-card">
              <div class="stat-icon">
                <i class="icon-tools"></i>
              </div>
              <div class="stat-content">
                <div class="stat-value" id="equipment-availability">0%</div>
                <div class="stat-label">Equipment Available</div>
                <div class="stat-trend" id="equipment-trend"></div>
              </div>
            </div>
          </div>

          <!-- Dashboard View -->
          <div class="view-container" id="dashboard-view">
            <div class="dashboard-grid">
              <!-- Active Requests -->
              <div class="dashboard-card">
                <div class="card-header">
                  <h3>Active Service Requests</h3>
                  <button class="btn btn-link" id="view-all-requests">View All</button>
                </div>
                <div class="card-content">
                  <div id="active-requests-list" class="requests-list">
                    <div class="loading-spinner">
                      <i class="icon-spinner"></i> Loading requests...
                    </div>
                  </div>
                </div>
              </div>

              <!-- Equipment Status -->
              <div class="dashboard-card">
                <div class="card-header">
                  <h3>Equipment Status</h3>
                  <button class="btn btn-link" id="view-all-equipment">View All</button>
                </div>
                <div class="card-content">
                  <div id="equipment-status-list" class="equipment-list">
                    <div class="loading-spinner">
                      <i class="icon-spinner"></i> Loading equipment...
                    </div>
                  </div>
                </div>
              </div>

              <!-- Service Type Distribution -->
              <div class="dashboard-card">
                <div class="card-header">
                  <h3>Service Distribution</h3>
                </div>
                <div class="card-content">
                  <canvas id="service-distribution-chart" width="300" height="200"></canvas>
                </div>
              </div>

              <!-- Quick Actions -->
              <div class="dashboard-card">
                <div class="card-header">
                  <h3>Quick Actions</h3>
                </div>
                <div class="card-content">
                  <div class="quick-actions">
                    <button class="action-btn" id="assign-staff-btn">
                      <i class="icon-user-plus"></i>
                      <span>Assign Staff</span>
                    </button>
                    <button class="action-btn" id="update-equipment-btn">
                      <i class="icon-tools"></i>
                      <span>Update Equipment</span>
                    </button>
                    <button class="action-btn" id="generate-report-btn">
                      <i class="icon-chart"></i>
                      <span>Generate Report</span>
                    </button>
                    <button class="action-btn" id="emergency-mode-btn">
                      <i class="icon-alert-triangle"></i>
                      <span>Emergency Mode</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Requests View -->
          <div class="view-container hidden" id="requests-view">
            <div class="requests-management">
              <div class="requests-header">
                <div class="filters">
                  <select id="status-filter">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="assigned">Assigned</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                  </select>
                  <select id="service-filter">
                    <option value="">All Services</option>
                    <option value="wheelchair">Wheelchair</option>
                    <option value="medical_assistance">Medical Assistance</option>
                    <option value="visual_impairment">Visual Impairment</option>
                    <option value="hearing_impairment">Hearing Impairment</option>
                    <option value="mobility_aid">Mobility Aid</option>
                    <option value="oxygen_support">Oxygen Support</option>
                    <option value="language_assistance">Language Assistance</option>
                    <option value="unaccompanied_minor">Unaccompanied Minor</option>
                  </select>
                  <input type="text" id="search-input" placeholder="Search requests...">
                </div>
                <button class="btn btn-primary" id="export-requests-btn">
                  <i class="icon-download"></i> Export
                </button>
              </div>
              <div id="requests-table-container"></div>
            </div>
          </div>

          <!-- Equipment View -->
          <div class="view-container hidden" id="equipment-view">
            <div class="equipment-management">
              <div class="equipment-header">
                <div class="equipment-stats">
                  <div class="stat-item">
                    <span class="stat-label">Total Equipment:</span>
                    <span class="stat-value" id="total-equipment">0</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Available:</span>
                    <span class="stat-value" id="available-equipment">0</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">In Use:</span>
                    <span class="stat-value" id="in-use-equipment">0</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Maintenance:</span>
                    <span class="stat-value" id="maintenance-equipment">0</span>
                  </div>
                </div>
                <button class="btn btn-primary" id="add-equipment-btn">
                  <i class="icon-plus"></i> Add Equipment
                </button>
              </div>
              <div id="equipment-table-container"></div>
            </div>
          </div>

          <!-- Reports View -->
          <div class="view-container hidden" id="reports-view">
            <div class="reports-section">
              <div class="reports-header">
                <h3>Special Services Reports</h3>
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
                    <h4>Service Utilization</h4>
                    <p>Comprehensive analysis of service request patterns and completion rates</p>
                    <button class="btn btn-secondary" id="utilization-report-btn">
                      Generate Report
                    </button>
                  </div>
                </div>

                <div class="report-card">
                  <div class="report-icon">
                    <i class="icon-shield"></i>
                  </div>
                  <div class="report-content">
                    <h4>Accessibility Compliance</h4>
                    <p>Compliance monitoring and accessibility service performance</p>
                    <button class="btn btn-secondary" id="compliance-report-btn">
                      Generate Report
                    </button>
                  </div>
                </div>

                <div class="report-card">
                  <div class="report-icon">
                    <i class="icon-users"></i>
                  </div>
                  <div class="report-content">
                    <h4>Staff Performance</h4>
                    <p>Staff workload analysis and service quality metrics</p>
                    <button class="btn btn-secondary" id="staff-report-btn">
                      Generate Report
                    </button>
                  </div>
                </div>

                <div class="report-card">
                  <div class="report-icon">
                    <i class="icon-trending-up"></i>
                  </div>
                  <div class="report-content">
                    <h4>Service Trends</h4>
                    <p>Historical trends and predictive analytics</p>
                    <button class="btn btn-secondary" id="trends-report-btn">
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
      .special-services-dashboard {
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
      }

      .tab-btn {
        flex: 1;
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

      .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
      }

      .stat-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: transform 0.2s ease;
      }

      .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.15);
      }

      .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
      }

      .stat-icon i {
        color: white;
      }

      .stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
      .stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
      .stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
      .stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

      .stat-content {
        flex: 1;
      }

      .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 5px;
      }

      .stat-label {
        color: #6c757d;
        font-size: 0.9rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .stat-trend {
        font-size: 0.8rem;
        margin-top: 5px;
      }

      .stat-trend.positive { color: #28a745; }
      .stat-trend.negative { color: #dc3545; }
      .stat-trend.neutral { color: #6c757d; }

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

      .requests-list {
        max-height: 300px;
        overflow-y: auto;
      }

      .request-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid #f8f9fa;
      }

      .request-item:last-child {
        border-bottom: none;
      }

      .request-info {
        flex: 1;
      }

      .request-type {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 5px;
      }

      .request-details {
        color: #6c757d;
        font-size: 0.9rem;
      }

      .request-status {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        text-transform: uppercase;
      }

      .status-pending { background: #fff3cd; color: #856404; }
      .status-assigned { background: #cce5ff; color: #004085; }
      .status-in_progress { background: #d1ecf1; color: #0c5460; }
      .status-completed { background: #d4edda; color: #155724; }
      .status-cancelled { background: #f8d7da; color: #721c24; }

      .equipment-list {
        display: grid;
        gap: 15px;
      }

      .equipment-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
      }

      .equipment-info {
        flex: 1;
      }

      .equipment-name {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 5px;
      }

      .equipment-details {
        color: #6c757d;
        font-size: 0.9rem;
      }

      .equipment-status {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
      }

      .equipment-available { background: #d4edda; color: #155724; }
      .equipment-in_use { background: #cce5ff; color: #004085; }
      .equipment-maintenance { background: #fff3cd; color: #856404; }
      .equipment-out_of_service { background: #f8d7da; color: #721c24; }

      .quick-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
      }

      .action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        padding: 20px;
        background: #f8f9fa;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        color: #495057;
      }

      .action-btn:hover {
        background: #007bff;
        border-color: #007bff;
        color: white;
        transform: translateY(-2px);
      }

      .action-btn i {
        font-size: 24px;
      }

      .action-btn span {
        font-size: 0.9rem;
        font-weight: 500;
      }

      .view-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        overflow: hidden;
      }

      .view-container.hidden {
        display: none;
      }

      .requests-management,
      .equipment-management {
        padding: 25px;
      }

      .requests-header,
      .equipment-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e9ecef;
      }

      .filters {
        display: flex;
        gap: 15px;
        align-items: center;
      }

      .filters select,
      .filters input {
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        font-size: 0.9rem;
      }

      .equipment-stats {
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
        .special-services-dashboard {
          padding: 15px;
        }

        .dashboard-header {
          flex-direction: column;
          gap: 15px;
          text-align: center;
        }

        .stats-grid {
          grid-template-columns: 1fr;
        }

        .dashboard-grid {
          grid-template-columns: 1fr;
        }

        .quick-actions {
          grid-template-columns: 1fr;
        }

        .reports-grid {
          grid-template-columns: 1fr;
        }

        .requests-header,
        .equipment-header {
          flex-direction: column;
          gap: 15px;
        }

        .filters {
          flex-wrap: wrap;
        }

        .equipment-stats {
          flex-wrap: wrap;
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
        const view = (e.currentTarget as HTMLElement).dataset.view as typeof this.currentView;
        this.switchView(view);
      });
    });

    // Refresh button
    const refreshBtn = this.container.querySelector('#refresh-btn') as HTMLButtonElement;
    refreshBtn?.addEventListener('click', () => this.loadDashboardData());

    // New request button
    const newRequestBtn = this.container.querySelector('#new-request-btn') as HTMLButtonElement;
    newRequestBtn?.addEventListener('click', () => this.showNewRequestModal());

    // View all buttons
    const viewAllRequestsBtn = this.container.querySelector('#view-all-requests') as HTMLButtonElement;
    viewAllRequestsBtn?.addEventListener('click', () => this.switchView('requests'));

    const viewAllEquipmentBtn = this.container.querySelector('#view-all-equipment') as HTMLButtonElement;
    viewAllEquipmentBtn?.addEventListener('click', () => this.switchView('equipment'));

    // Quick action buttons
    const assignStaffBtn = this.container.querySelector('#assign-staff-btn') as HTMLButtonElement;
    assignStaffBtn?.addEventListener('click', () => this.showAssignStaffModal());

    const updateEquipmentBtn = this.container.querySelector('#update-equipment-btn') as HTMLButtonElement;
    updateEquipmentBtn?.addEventListener('click', () => this.showUpdateEquipmentModal());

    const generateReportBtn = this.container.querySelector('#generate-report-btn') as HTMLButtonElement;
    generateReportBtn?.addEventListener('click', () => this.switchView('reports'));

    const emergencyModeBtn = this.container.querySelector('#emergency-mode-btn') as HTMLButtonElement;
    emergencyModeBtn?.addEventListener('click', () => this.toggleEmergencyMode());

    // Report generation buttons
    const utilizationReportBtn = this.container.querySelector('#utilization-report-btn') as HTMLButtonElement;
    utilizationReportBtn?.addEventListener('click', () => this.generateUtilizationReport());

    const complianceReportBtn = this.container.querySelector('#compliance-report-btn') as HTMLButtonElement;
    complianceReportBtn?.addEventListener('click', () => this.generateComplianceReport());

    const staffReportBtn = this.container.querySelector('#staff-report-btn') as HTMLButtonElement;
    staffReportBtn?.addEventListener('click', () => this.generateStaffReport());

    const trendsReportBtn = this.container.querySelector('#trends-report-btn') as HTMLButtonElement;
    trendsReportBtn?.addEventListener('click', () => this.generateTrendsReport());

    // Export buttons
    const exportRequestsBtn = this.container.querySelector('#export-requests-btn') as HTMLButtonElement;
    exportRequestsBtn?.addEventListener('click', () => this.exportRequests());

    const exportReportBtn = this.container.querySelector('#export-report-btn') as HTMLButtonElement;
    exportReportBtn?.addEventListener('click', () => this.exportReport());

    const printReportBtn = this.container.querySelector('#print-report-btn') as HTMLButtonElement;
    printReportBtn?.addEventListener('click', () => this.printReport());

    // Filters
    const statusFilter = this.container.querySelector('#status-filter') as HTMLSelectElement;
    const serviceFilter = this.container.querySelector('#service-filter') as HTMLSelectElement;
    const searchInput = this.container.querySelector('#search-input') as HTMLInputElement;

    [statusFilter, serviceFilter, searchInput].forEach(element => {
      element?.addEventListener('input', () => this.filterRequests());
    });

    // Add equipment button
    const addEquipmentBtn = this.container.querySelector('#add-equipment-btn') as HTMLButtonElement;
    addEquipmentBtn?.addEventListener('click', () => this.showAddEquipmentModal());
  }

  private async loadDashboardData(): Promise<void> {
    try {
      // Load statistics
      await this.loadServiceStatistics();

      // Load active requests
      await this.loadActiveRequests();

      // Load equipment status
      await this.loadEquipmentStatus();

      // Update UI
      this.updateStatisticsDisplay();
      this.updateActiveRequestsDisplay();
      this.updateEquipmentStatusDisplay();

    } catch (error) {
      console.error('Error loading dashboard data:', error);
      this.notificationManager.show('Error loading dashboard data', 'error');
    }
  }

  private async loadServiceStatistics(): Promise<void> {
    try {
      const response = await this.apiService.get('/api/special-services/stats');
      this.serviceStats = response;
    } catch (error) {
      console.error('Error loading service statistics:', error);
    }
  }

  private async loadActiveRequests(): Promise<void> {
    try {
      const response = await this.apiService.get('/api/special-services?status=pending&status=assigned&status=in_progress&limit=10');
      this.currentRequests = response;
    } catch (error) {
      console.error('Error loading active requests:', error);
    }
  }

  private async loadEquipmentStatus(): Promise<void> {
    try {
      const response = await this.apiService.get('/api/special-services/equipment');
      this.equipmentStatus = response;
    } catch (error) {
      console.error('Error loading equipment status:', error);
    }
  }

  private updateStatisticsDisplay(): void {
    if (!this.serviceStats) return;

    const totalRequestsEl = this.container.querySelector('#total-requests') as HTMLElement;
    const completedRequestsEl = this.container.querySelector('#completed-requests') as HTMLElement;
    const pendingRequestsEl = this.container.querySelector('#pending-requests') as HTMLElement;
    const equipmentAvailabilityEl = this.container.querySelector('#equipment-availability') as HTMLElement;

    if (totalRequestsEl) totalRequestsEl.textContent = this.serviceStats.total_requests.toString();
    if (completedRequestsEl) completedRequestsEl.textContent = this.serviceStats.completed_requests.toString();
    if (pendingRequestsEl) pendingRequestsEl.textContent = this.serviceStats.pending_requests.toString();

    // Calculate equipment availability
    if (this.equipmentStatus.length > 0) {
      const totalEquipment = this.equipmentStatus.reduce((sum, eq) => sum + eq.total_units, 0);
      const availableEquipment = this.equipmentStatus.reduce((sum, eq) => sum + eq.available_units, 0);
      const availabilityRate = totalEquipment > 0 ? Math.round((availableEquipment / totalEquipment) * 100) : 0;

      if (equipmentAvailabilityEl) equipmentAvailabilityEl.textContent = `${availabilityRate}%`;
    }
  }

  private updateActiveRequestsDisplay(): void {
    const container = this.container.querySelector('#active-requests-list') as HTMLElement;
    if (!container) return;

    if (this.currentRequests.length === 0) {
      container.innerHTML = '<div class="no-data">No active requests</div>';
      return;
    }

    const requestsHtml = this.currentRequests.map(request => `
      <div class="request-item">
        <div class="request-info">
          <div class="request-type">${this.formatServiceType(request.service_type)}</div>
          <div class="request-details">
            ${request.passenger_name || 'Passenger'} • ${request.flight_number || 'No flight'}
          </div>
        </div>
        <span class="request-status status-${request.status}">${this.formatStatus(request.status)}</span>
      </div>
    `).join('');

    container.innerHTML = requestsHtml;
  }

  private updateEquipmentStatusDisplay(): void {
    const container = this.container.querySelector('#equipment-status-list') as HTMLElement;
    if (!container) return;

    if (this.equipmentStatus.length === 0) {
      container.innerHTML = '<div class="no-data">No equipment data available</div>';
      return;
    }

    const equipmentHtml = this.equipmentStatus.slice(0, 5).map(equipment => `
      <div class="equipment-item">
        <div class="equipment-info">
          <div class="equipment-name">${this.formatEquipmentType(equipment.equipment_type)}</div>
          <div class="equipment-details">
            ${equipment.available_units}/${equipment.total_units} available • ${equipment.availability_rate}% utilization
          </div>
        </div>
        <span class="equipment-status equipment-${equipment.available_units > 0 ? 'available' : 'in_use'}">
          ${equipment.available_units > 0 ? 'Available' : 'In Use'}
        </span>
      </div>
    `).join('');

    container.innerHTML = equipmentHtml;

    // Update equipment stats
    const totalEquipment = this.equipmentStatus.reduce((sum, eq) => sum + eq.total_units, 0);
    const availableEquipment = this.equipmentStatus.reduce((sum, eq) => sum + eq.available_units, 0);
    const inUseEquipment = this.equipmentStatus.reduce((sum, eq) => sum + eq.in_use_units, 0);
    const maintenanceEquipment = this.equipmentStatus.reduce((sum, eq) => sum + eq.maintenance_units, 0);

    const totalEl = this.container.querySelector('#total-equipment') as HTMLElement;
    const availableEl = this.container.querySelector('#available-equipment') as HTMLElement;
    const inUseEl = this.container.querySelector('#in-use-equipment') as HTMLElement;
    const maintenanceEl = this.container.querySelector('#maintenance-equipment') as HTMLElement;

    if (totalEl) totalEl.textContent = totalEquipment.toString();
    if (availableEl) availableEl.textContent = availableEquipment.toString();
    if (inUseEl) inUseEl.textContent = inUseEquipment.toString();
    if (maintenanceEl) maintenanceEl.textContent = maintenanceEquipment.toString();
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
    if (view === 'requests') {
      this.loadRequestsView();
    } else if (view === 'equipment') {
      this.loadEquipmentView();
    }
  }

  private async loadRequestsView(): Promise<void> {
    const container = this.container.querySelector('#requests-table-container') as HTMLElement;
    if (!container) return;

    // Show loading
    container.innerHTML = '<div class="loading-spinner"><i class="icon-spinner"></i> Loading requests...</div>';

    try {
      const response = await this.apiService.get('/api/special-services?limit=100');

      const columns = [
        { key: 'request_id', label: 'ID', sortable: true },
        { key: 'service_type', label: 'Service Type', sortable: true, formatter: (value: string) => this.formatServiceType(value) },
        { key: 'passenger_name', label: 'Passenger', sortable: true },
        { key: 'flight_number', label: 'Flight', sortable: true },
        { key: 'status', label: 'Status', sortable: true, formatter: (value: string) => this.formatStatus(value) },
        { key: 'created_at', label: 'Created', sortable: true, formatter: (value: string) => new Date(value).toLocaleDateString() },
        { key: 'actions', label: 'Actions', formatter: () => '<button class="btn btn-secondary btn-sm">View</button>' }
      ];

      this.requestsTable = new DataTable(container, {
        data: response,
        columns: columns,
        searchable: true,
        sortable: true,
        pagination: true,
        pageSize: 25
      });

    } catch (error) {
      console.error('Error loading requests view:', error);
      container.innerHTML = '<div class="error">Error loading requests</div>';
    }
  }

  private async loadEquipmentView(): Promise<void> {
    const container = this.container.querySelector('#equipment-table-container') as HTMLElement;
    if (!container) return;

    // Show loading
    container.innerHTML = '<div class="loading-spinner"><i class="icon-spinner"></i> Loading equipment...</div>';

    try {
      const response = await this.apiService.get('/api/special-services/equipment');

      const columns = [
        { key: 'equipment_type', label: 'Type', sortable: true, formatter: (value: string) => this.formatEquipmentType(value) },
        { key: 'location', label: 'Location', sortable: true },
        { key: 'status', label: 'Status', sortable: true, formatter: (value: string) => this.formatEquipmentStatus(value) },
        { key: 'serial_number', label: 'Serial Number', sortable: true },
        { key: 'last_maintenance_date', label: 'Last Maintenance', sortable: true, formatter: (value: string) => value ? new Date(value).toLocaleDateString() : 'N/A' },
        { key: 'actions', label: 'Actions', formatter: () => '<button class="btn btn-secondary btn-sm">Update</button>' }
      ];

      this.equipmentTable = new DataTable(container, {
        data: response,
        columns: columns,
        searchable: true,
        sortable: true,
        pagination: true,
        pageSize: 25
      });

    } catch (error) {
      console.error('Error loading equipment view:', error);
      container.innerHTML = '<div class="error">Error loading equipment</div>';
    }
  }

  private async generateUtilizationReport(): Promise<void> {
    const startDate = (this.container.querySelector('#report-start-date') as HTMLInputElement)?.value || '';
    const endDate = (this.container.querySelector('#report-end-date') as HTMLInputElement)?.value || '';

    if (!startDate || !endDate) {
      this.notificationManager.show('Please select date range', 'warning');
      return;
    }

    try {
      const response = await this.apiService.get(`/api/special-services/reports/utilization?start_date=${startDate}&end_date=${endDate}`);
      this.displayReportResults('Service Utilization Report', response);
    } catch (error) {
      console.error('Error generating utilization report:', error);
      this.notificationManager.show('Error generating report', 'error');
    }
  }

  private async generateComplianceReport(): Promise<void> {
    const startDate = (this.container.querySelector('#report-start-date') as HTMLInputElement)?.value || '';
    const endDate = (this.container.querySelector('#report-end-date') as HTMLInputElement)?.value || '';

    if (!startDate || !endDate) {
      this.notificationManager.show('Please select date range', 'warning');
      return;
    }

    try {
      const response = await this.apiService.get(`/api/special-services/reports/accessibility?start_date=${startDate}&end_date=${endDate}`);
      this.displayReportResults('Accessibility Compliance Report', response);
    } catch (error) {
      console.error('Error generating compliance report:', error);
      this.notificationManager.show('Error generating report', 'error');
    }
  }

  private async generateStaffReport(): Promise<void> {
    // Staff performance report would require additional API endpoint
    this.notificationManager.show('Staff performance report coming soon', 'info');
  }

  private async generateTrendsReport(): Promise<void> {
    // Service trends report would require additional API endpoint
    this.notificationManager.show('Service trends report coming soon', 'info');
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

  private startAutoRefresh(): void {
    // Auto-refresh data every 30 seconds
    setInterval(() => {
      if (this.currentView === 'dashboard') {
        this.loadDashboardData();
      }
    }, 30000);
  }

  private showNewRequestModal(): void {
    // Implementation for new request modal
    this.notificationManager.show('New request modal coming soon', 'info');
  }

  private showAssignStaffModal(): void {
    // Implementation for assign staff modal
    this.notificationManager.show('Assign staff modal coming soon', 'info');
  }

  private showUpdateEquipmentModal(): void {
    // Implementation for update equipment modal
    this.notificationManager.show('Update equipment modal coming soon', 'info');
  }

  private showAddEquipmentModal(): void {
    // Implementation for add equipment modal
    this.notificationManager.show('Add equipment modal coming soon', 'info');
  }

  private toggleEmergencyMode(): void {
    // Implementation for emergency mode toggle
    this.notificationManager.show('Emergency mode activated', 'warning');
  }

  private filterRequests(): void {
    // Implementation for filtering requests
    if (this.requestsTable) {
      // Apply filters to the table
    }
  }

  private exportRequests(): void {
    // Implementation for exporting requests
    this.notificationManager.show('Export functionality coming soon', 'info');
  }

  private exportReport(): void {
    // Implementation for exporting report
    this.notificationManager.show('Report export coming soon', 'info');
  }

  private printReport(): void {
    // Implementation for printing report
    window.print();
  }

  private formatServiceType(serviceType: string): string {
    const serviceTypes: { [key: string]: string } = {
      'wheelchair': 'Wheelchair Assistance',
      'medical_assistance': 'Medical Assistance',
      'visual_impairment': 'Visual Impairment',
      'hearing_impairment': 'Hearing Impairment',
      'mobility_aid': 'Mobility Aid',
      'oxygen_support': 'Oxygen Support',
      'language_assistance': 'Language Assistance',
      'unaccompanied_minor': 'Unaccompanied Minor'
    };
    return serviceTypes[serviceType] || serviceType;
  }

  private formatStatus(status: string): string {
    const statuses: { [key: string]: string } = {
      'pending': 'Pending',
      'assigned': 'Assigned',
      'in_progress': 'In Progress',
      'completed': 'Completed',
      'cancelled': 'Cancelled'
    };
    return statuses[status] || status;
  }

  private formatEquipmentType(equipmentType: string): string {
    const equipmentTypes: { [key: string]: string } = {
      'wheelchair_manual': 'Manual Wheelchair',
      'wheelchair_electric': 'Electric Wheelchair',
      'walker': 'Walker',
      'cane': 'Cane',
      'hearing_aid': 'Hearing Aid',
      'oxygen_tank': 'Oxygen Tank',
      'stretcher': 'Stretcher',
      'crt': 'CRT Device'
    };
    return equipmentTypes[equipmentType] || equipmentType;
  }

  private formatEquipmentStatus(status: string): string {
    const statuses: { [key: string]: string } = {
      'available': 'Available',
      'in_use': 'In Use',
      'maintenance': 'Maintenance',
      'out_of_service': 'Out of Service'
    };
    return statuses[status] || status;
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
