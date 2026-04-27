import { DashboardApiService } from '../services/DashboardApiService.js';
import type { DashboardConfig } from '../../dashboard.js';

export class CargoOperationsView {
  private apiService: DashboardApiService;
  private config: DashboardConfig;
  private container: HTMLElement | null = null;
  private refreshInterval: number | null = null;

  constructor(apiService: DashboardApiService, config: DashboardConfig) {
    this.apiService = apiService;
    this.config = config;
  }

  async render(): Promise<string> {
    return `
      <div class="cargo-operations-view">
        <div class="mb-6">
          <h1 class="text-2xl font-bold text-gray-900">Cargo Operations</h1>
          <p class="text-gray-600">Manage cargo shipments, terminals, and customs clearance</p>
        </div>

        <!-- Dashboard Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" id="cargo-stats">
          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Active Shipments</p>
                <p class="text-2xl font-semibold text-gray-900" id="active-shipments">-</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Delivered Today</p>
                <p class="text-2xl font-semibold text-gray-900" id="delivered-today">-</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-yellow-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Pending Customs</p>
                <p class="text-2xl font-semibold text-gray-900" id="pending-customs">-</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-red-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Temperature Alerts</p>
                <p class="text-2xl font-semibold text-gray-900" id="temp-alerts">-</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-6">
          <div class="flex flex-wrap gap-3">
            <button id="create-shipment" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
              </svg>
              Create Shipment
            </button>
            <button id="view-terminals" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
              </svg>
              View Terminals
            </button>
            <button id="customs-clearance" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
              </svg>
              Customs Clearance
            </button>
            <button id="temperature-monitoring" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
              </svg>
              Temperature Monitoring
            </button>
          </div>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Recent Shipments -->
          <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
              <h3 class="text-lg font-medium text-gray-900">Recent Shipments</h3>
            </div>
            <div class="p-6" id="recent-shipments">
              <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="mt-2 text-gray-600">Loading shipments...</p>
              </div>
            </div>
          </div>

          <!-- Cargo Terminals -->
          <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
              <h3 class="text-lg font-medium text-gray-900">Cargo Terminals</h3>
            </div>
            <div class="p-6" id="cargo-terminals">
              <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="mt-2 text-gray-600">Loading terminals...</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Temperature Monitoring Section -->
        <div class="mt-6 bg-white rounded-lg shadow">
          <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Temperature Monitoring</h3>
          </div>
          <div class="p-6" id="temperature-monitoring-section">
            <div class="text-center py-8">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
              <p class="mt-2 text-gray-600">Loading temperature data...</p>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  async init(): Promise<void> {
    this.container = document.querySelector('.cargo-operations-view') as HTMLElement;

    if (!this.container) {
      throw new Error('Cargo operations view container not found');
    }

    // Setup event listeners
    this.setupEventListeners();

    // Load initial data
    await this.loadDashboardData();

    // Set up auto-refresh
    this.refreshInterval = window.setInterval(() => {
      this.loadDashboardData();
    }, 30000); // Refresh every 30 seconds
  }

  private setupEventListeners(): void {
    if (!this.container) return;

    // Create shipment button
    const createShipmentBtn = this.container.querySelector('#create-shipment') as HTMLButtonElement;
    if (createShipmentBtn) {
      createShipmentBtn.addEventListener('click', () => this.showCreateShipmentModal());
    }

    // View terminals button
    const viewTerminalsBtn = this.container.querySelector('#view-terminals') as HTMLButtonElement;
    if (viewTerminalsBtn) {
      viewTerminalsBtn.addEventListener('click', () => this.showTerminalsView());
    }

    // Customs clearance button
    const customsBtn = this.container.querySelector('#customs-clearance') as HTMLButtonElement;
    if (customsBtn) {
      customsBtn.addEventListener('click', () => this.showCustomsClearanceView());
    }

    // Temperature monitoring button
    const tempBtn = this.container.querySelector('#temperature-monitoring') as HTMLButtonElement;
    if (tempBtn) {
      tempBtn.addEventListener('click', () => this.showTemperatureMonitoringView());
    }
  }

  private async loadDashboardData(): Promise<void> {
    try {
      // Load cargo dashboard data
      const dashboardData = await this.apiService.callApi('/api/cargo', 'GET');

      // Update stats
      this.updateStats(dashboardData);

      // Load recent shipments
      await this.loadRecentShipments();

      // Load terminals
      await this.loadTerminals();

      // Load temperature data
      await this.loadTemperatureData();

    } catch (error) {
      console.error('Error loading cargo dashboard data:', error);
      this.showError('Failed to load dashboard data');
    }
  }

  private updateStats(data: any): void {
    const activeShipmentsEl = this.container?.querySelector('#active-shipments');
    const deliveredTodayEl = this.container?.querySelector('#delivered-today');
    const pendingCustomsEl = this.container?.querySelector('#pending-customs');
    const tempAlertsEl = this.container?.querySelector('#temp-alerts');

    if (activeShipmentsEl) activeShipmentsEl.textContent = data.active_shipments || '0';
    if (deliveredTodayEl) deliveredTodayEl.textContent = data.shipments_today || '0';
    if (pendingCustomsEl) pendingCustomsEl.textContent = data.pending_customs || '0';
    if (tempAlertsEl) tempAlertsEl.textContent = data.temperature_alerts || '0';
  }

  private async loadRecentShipments(): Promise<void> {
    try {
      const shipments = await this.apiService.callApi('/api/cargo/shipments', 'GET');

      const container = this.container?.querySelector('#recent-shipments');
      if (!container) return;

      if (!shipments || shipments.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No recent shipments</p>';
        return;
      }

      const shipmentsHTML = shipments.slice(0, 5).map((shipment: any) => `
        <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-b-0">
          <div>
            <p class="font-medium text-gray-900">${shipment.shipment_number || shipment.shipment_id}</p>
            <p class="text-sm text-gray-600">${shipment.origin_airport} → ${shipment.destination_airport}</p>
          </div>
          <div class="text-right">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
              ${this.getStatusColor(shipment.shipment_status)}">
              ${shipment.shipment_status || 'Unknown'}
            </span>
          </div>
        </div>
      `).join('');

      container.innerHTML = shipmentsHTML;

    } catch (error) {
      console.error('Error loading recent shipments:', error);
      const container = this.container?.querySelector('#recent-shipments');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load shipments</p>';
      }
    }
  }

  private async loadTerminals(): Promise<void> {
    try {
      const terminals = await this.apiService.callApi('/api/cargo/terminals', 'GET');

      const container = this.container?.querySelector('#cargo-terminals');
      if (!container) return;

      if (!terminals || terminals.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No terminals found</p>';
        return;
      }

      const terminalsHTML = terminals.slice(0, 5).map((terminal: any) => `
        <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-b-0">
          <div>
            <p class="font-medium text-gray-900">${terminal.terminal_name}</p>
            <p class="text-sm text-gray-600">${terminal.airport_code}</p>
          </div>
          <div class="text-right">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
              ${terminal.status === 'operational' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
              ${terminal.status || 'Unknown'}
            </span>
            <p class="text-xs text-gray-500 mt-1">${terminal.utilization_rate || 0}% utilized</p>
          </div>
        </div>
      `).join('');

      container.innerHTML = terminalsHTML;

    } catch (error) {
      console.error('Error loading terminals:', error);
      const container = this.container?.querySelector('#cargo-terminals');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load terminals</p>';
      }
    }
  }

  private async loadTemperatureData(): Promise<void> {
    try {
      const tempData = await this.apiService.callApi('/api/cargo/temperature/monitoring', 'GET');

      const container = this.container?.querySelector('#temperature-monitoring-section');
      if (!container) return;

      if (!tempData || tempData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No temperature data available</p>';
        return;
      }

      const tempHTML = `
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div class="bg-blue-50 p-4 rounded-lg">
            <h4 class="font-medium text-blue-900">Active Sensors</h4>
            <p class="text-2xl font-bold text-blue-600">${tempData.total_sensors || 0}</p>
          </div>
          <div class="bg-green-50 p-4 rounded-lg">
            <h4 class="font-medium text-green-900">Normal Readings</h4>
            <p class="text-2xl font-bold text-green-600">${tempData.monitoring_results?.filter((r: any) => r.status === 'normal').length || 0}</p>
          </div>
          <div class="bg-red-50 p-4 rounded-lg">
            <h4 class="font-medium text-red-900">Alerts</h4>
            <p class="text-2xl font-bold text-red-600">${tempData.alerts?.length || 0}</p>
          </div>
        </div>
        <div class="space-y-2">
          ${tempData.monitoring_results?.slice(0, 3).map((sensor: any) => `
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div>
                <p class="font-medium text-gray-900">Sensor ${sensor.sensor_id}</p>
                <p class="text-sm text-gray-600">${sensor.location}</p>
              </div>
              <div class="text-right">
                <p class="font-medium text-gray-900">${sensor.current_temp || 'N/A'}°C</p>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                  ${sensor.status === 'normal' ? 'bg-green-100 text-green-800' :
                    sensor.status === 'high' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'}">
                  ${sensor.status || 'Unknown'}
                </span>
              </div>
            </div>
          `).join('') || '<p class="text-gray-500 text-center py-4">No sensor data</p>'}
        </div>
      `;

      container.innerHTML = tempHTML;

    } catch (error) {
      console.error('Error loading temperature data:', error);
      const container = this.container?.querySelector('#temperature-monitoring-section');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load temperature data</p>';
      }
    }
  }

  private getStatusColor(status: string): string {
    const colors: { [key: string]: string } = {
      'created': 'bg-blue-100 text-blue-800',
      'received': 'bg-yellow-100 text-yellow-800',
      'processed': 'bg-purple-100 text-purple-800',
      'loaded': 'bg-indigo-100 text-indigo-800',
      'in_transit': 'bg-blue-100 text-blue-800',
      'arrived': 'bg-cyan-100 text-cyan-800',
      'cleared': 'bg-green-100 text-green-800',
      'delivered': 'bg-green-100 text-green-800',
      'cancelled': 'bg-red-100 text-red-800'
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
  }

  private showCreateShipmentModal(): void {
    // Implementation for create shipment modal
    console.log('Show create shipment modal');
  }

  private showTerminalsView(): void {
    // Implementation for terminals view
    console.log('Show terminals view');
  }

  private showCustomsClearanceView(): void {
    // Implementation for customs clearance view
    console.log('Show customs clearance view');
  }

  private showTemperatureMonitoringView(): void {
    // Implementation for temperature monitoring view
    console.log('Show temperature monitoring view');
  }

  private showError(message: string): void {
    const errorHTML = `
      <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-red-800">Error</h3>
            <p class="mt-1 text-sm text-red-700">${message}</p>
          </div>
        </div>
      </div>
    `;

    const mainContent = document.getElementById('main-content');
    if (mainContent) {
      mainContent.innerHTML = errorHTML;
    }
  }

  destroy(): void {
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }

    // Clean up event listeners
    if (this.container) {
      const buttons = this.container.querySelectorAll('button');
      buttons.forEach(button => {
        button.removeEventListener('click', () => {});
      });
    }
  }
}
