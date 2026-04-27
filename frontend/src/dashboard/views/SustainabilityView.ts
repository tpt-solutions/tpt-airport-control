import { DashboardApiService } from '../services/DashboardApiService.js';
import type { DashboardConfig } from '../../dashboard.js';

export class SustainabilityView {
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
      <div class="sustainability-view">
        <div class="mb-6">
          <h1 class="text-2xl font-bold text-gray-900">Environmental & Sustainability</h1>
          <p class="text-gray-600">Monitor emissions, noise levels, and energy consumption</p>
        </div>

        <!-- Dashboard Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" id="sustainability-stats">
          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.55 19.09l1.41 1.41 1.79-1.79-1.41-1.41-1.79 1.79zM11 23h2v-2.13c-1.75-.42-3.12-1.79-3.54-3.54H5.54c-.42 1.75-1.79 3.12-3.54 3.54V23h2c0-1.1.9-2 2-2s2 .9 2 2zM15.97 14.41c-.39-.39-1.02-.39-1.41 0l-1.06 1.06c-1.75-.42-3.12-1.79-3.54-3.54H9.54c-.42 1.75-1.79 3.12-3.54 3.54L4.94 15.47c-.39-.39-.39-1.02 0-1.41l1.06-1.06c.39-.39 1.02-.39 1.41 0l1.06 1.06c.39.39.39 1.02 0 1.41l-1.06 1.06c-.39.39-1.02.39-1.41 0L3.49 15H1v-2h2.49c.56 0 1.08-.23 1.46-.61l1.06-1.06c.78-.78 2.05-.78 2.83 0l1.06 1.06c.39.39 1.02.39 1.41 0l1.06-1.06c.78-.78 2.05-.78 2.83 0l1.06 1.06c.39.39 1.02.39 1.41 0l1.06-1.06c.78-.78 2.05-.78 2.83 0l1.06 1.06c.39.39 1.02.39 1.41 0L21 8.62V6h2v2.62l-1.79 1.79c-.78.78-2.05.78-2.83 0l-1.06-1.06c-.39-.39-1.02-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.02 0 1.41l1.06 1.06c.39.39 1.02.39 1.41 0l1.06-1.06c.78-.78 2.05-.78 2.83 0L23 12.38V14h-2.49c-.56 0-1.08.23-1.46.61l-1.06 1.06c-.78.78-2.05.78-2.83 0l-1.06-1.06c-.39-.39-1.02-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.02 0 1.41l1.06 1.06c.39.39 1.02.39 1.41 0l1.06-1.06c.78-.78 2.05-.78 2.83 0l1.79 1.79 1.41-1.41-1.79-1.79c-.78-.78-2.05-.78-2.83 0l-1.06 1.06c-.39.39-1.02.39-1.41 0l-1.06-1.06c-.78-.78-2.05-.78-2.83 0l-1.06-1.06z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">CO₂ Emissions (Today)</p>
                <p class="text-2xl font-semibold text-gray-900" id="co2-emissions">-</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Noise Level (dB)</p>
                <p class="text-2xl font-semibold text-gray-900" id="noise-level">-</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-yellow-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Energy Usage (kWh)</p>
                <p class="text-2xl font-semibold text-gray-900" id="energy-usage">-</p>
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
                <p class="text-sm font-medium text-gray-600">Active Alerts</p>
                <p class="text-2xl font-semibold text-gray-900" id="active-alerts">-</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-6">
          <div class="flex flex-wrap gap-3">
            <button id="view-emissions" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
              </svg>
              View Emissions
            </button>
            <button id="noise-monitoring" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
              </svg>
              Noise Monitoring
            </button>
            <button id="energy-dashboard" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
              </svg>
              Energy Dashboard
            </button>
            <button id="sustainability-reports" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              Sustainability Reports
            </button>
          </div>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Emissions Monitoring -->
          <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
              <h3 class="text-lg font-medium text-gray-900">Emissions Monitoring</h3>
            </div>
            <div class="p-6" id="emissions-monitoring">
              <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600 mx-auto"></div>
                <p class="mt-2 text-gray-600">Loading emissions data...</p>
              </div>
            </div>
          </div>

          <!-- Noise Monitoring -->
          <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
              <h3 class="text-lg font-medium text-gray-900">Noise Monitoring</h3>
            </div>
            <div class="p-6" id="noise-monitoring-section">
              <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="mt-2 text-gray-600">Loading noise data...</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Energy Consumption Section -->
        <div class="mt-6 bg-white rounded-lg shadow">
          <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Energy Consumption</h3>
          </div>
          <div class="p-6" id="energy-consumption-section">
            <div class="text-center py-8">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-yellow-600 mx-auto"></div>
              <p class="mt-2 text-gray-600">Loading energy data...</p>
            </div>
          </div>
        </div>

        <!-- Environmental Alerts -->
        <div class="mt-6 bg-white rounded-lg shadow">
          <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Environmental Alerts</h3>
          </div>
          <div class="p-6" id="environmental-alerts">
            <div class="text-center py-8">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-red-600 mx-auto"></div>
              <p class="mt-2 text-gray-600">Loading alerts...</p>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  async init(): Promise<void> {
    this.container = document.querySelector('.sustainability-view') as HTMLElement;

    if (!this.container) {
      throw new Error('Sustainability view container not found');
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

    // View emissions button
    const emissionsBtn = this.container.querySelector('#view-emissions') as HTMLButtonElement;
    if (emissionsBtn) {
      emissionsBtn.addEventListener('click', () => this.showEmissionsView());
    }

    // Noise monitoring button
    const noiseBtn = this.container.querySelector('#noise-monitoring') as HTMLButtonElement;
    if (noiseBtn) {
      noiseBtn.addEventListener('click', () => this.showNoiseMonitoringView());
    }

    // Energy dashboard button
    const energyBtn = this.container.querySelector('#energy-dashboard') as HTMLButtonElement;
    if (energyBtn) {
      energyBtn.addEventListener('click', () => this.showEnergyDashboardView());
    }

    // Sustainability reports button
    const reportsBtn = this.container.querySelector('#sustainability-reports') as HTMLButtonElement;
    if (reportsBtn) {
      reportsBtn.addEventListener('click', () => this.showSustainabilityReportsView());
    }
  }

  private async loadDashboardData(): Promise<void> {
    try {
      // Load sustainability dashboard data
      const dashboardData = await this.apiService.callApi('/api/sustainability', 'GET');

      // Update stats
      this.updateStats(dashboardData);

      // Load emissions data
      await this.loadEmissionsData();

      // Load noise data
      await this.loadNoiseData();

      // Load energy data
      await this.loadEnergyData();

      // Load alerts
      await this.loadAlerts();

    } catch (error) {
      console.error('Error loading sustainability dashboard data:', error);
      this.showError('Failed to load dashboard data');
    }
  }

  private updateStats(data: any): void {
    const co2El = this.container?.querySelector('#co2-emissions');
    const noiseEl = this.container?.querySelector('#noise-level');
    const energyEl = this.container?.querySelector('#energy-usage');
    const alertsEl = this.container?.querySelector('#active-alerts');

    if (co2El) co2El.textContent = data.co2_emissions_today ? `${data.co2_emissions_today} kg` : '0 kg';
    if (noiseEl) noiseEl.textContent = data.current_noise_level ? `${data.current_noise_level} dB` : 'N/A';
    if (energyEl) energyEl.textContent = data.energy_usage_today ? `${data.energy_usage_today} kWh` : '0 kWh';
    if (alertsEl) alertsEl.textContent = data.active_alerts || '0';
  }

  private async loadEmissionsData(): Promise<void> {
    try {
      const emissionsData = await this.apiService.callApi('/api/sustainability/emissions', 'GET');

      const container = this.container?.querySelector('#emissions-monitoring');
      if (!container) return;

      if (!emissionsData || emissionsData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No emissions data available</p>';
        return;
      }

      const emissionsHTML = `
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="bg-green-50 p-4 rounded-lg">
              <h4 class="font-medium text-green-900">Today's CO₂</h4>
              <p class="text-2xl font-bold text-green-600">${emissionsData.today_co2 || 0} kg</p>
            </div>
            <div class="bg-blue-50 p-4 rounded-lg">
              <h4 class="font-medium text-blue-900">Monthly Average</h4>
              <p class="text-2xl font-bold text-blue-600">${emissionsData.monthly_avg || 0} kg</p>
            </div>
          </div>
          <div class="space-y-2">
            ${emissionsData.sources?.slice(0, 3).map((source: any) => `
              <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div>
                  <p class="font-medium text-gray-900">${source.source_name}</p>
                  <p class="text-sm text-gray-600">${source.location}</p>
                </div>
                <div class="text-right">
                  <p class="font-medium text-gray-900">${source.emission_rate || 0} kg/h</p>
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                    ${source.status === 'normal' ? 'bg-green-100 text-green-800' :
                      source.status === 'high' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'}">
                    ${source.status || 'Unknown'}
                  </span>
                </div>
              </div>
            `).join('') || '<p class="text-gray-500 text-center py-4">No emission sources</p>'}
          </div>
        </div>
      `;

      container.innerHTML = emissionsHTML;

    } catch (error) {
      console.error('Error loading emissions data:', error);
      const container = this.container?.querySelector('#emissions-monitoring');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load emissions data</p>';
      }
    }
  }

  private async loadNoiseData(): Promise<void> {
    try {
      const noiseData = await this.apiService.callApi('/api/sustainability/noise', 'GET');

      const container = this.container?.querySelector('#noise-monitoring-section');
      if (!container) return;

      if (!noiseData || noiseData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No noise data available</p>';
        return;
      }

      const noiseHTML = `
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="bg-blue-50 p-4 rounded-lg">
              <h4 class="font-medium text-blue-900">Current Level</h4>
              <p class="text-2xl font-bold text-blue-600">${noiseData.current_level || 0} dB</p>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg">
              <h4 class="font-medium text-purple-900">Peak Today</h4>
              <p class="text-2xl font-bold text-purple-600">${noiseData.peak_today || 0} dB</p>
            </div>
          </div>
          <div class="space-y-2">
            ${noiseData.monitors?.slice(0, 3).map((monitor: any) => `
              <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div>
                  <p class="font-medium text-gray-900">${monitor.monitor_name}</p>
                  <p class="text-sm text-gray-600">${monitor.location}</p>
                </div>
                <div class="text-right">
                  <p class="font-medium text-gray-900">${monitor.current_level || 0} dB</p>
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                    ${monitor.status === 'normal' ? 'bg-green-100 text-green-800' :
                      monitor.status === 'high' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'}">
                    ${monitor.status || 'Unknown'}
                  </span>
                </div>
              </div>
            `).join('') || '<p class="text-gray-500 text-center py-4">No noise monitors</p>'}
          </div>
        </div>
      `;

      container.innerHTML = noiseHTML;

    } catch (error) {
      console.error('Error loading noise data:', error);
      const container = this.container?.querySelector('#noise-monitoring-section');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load noise data</p>';
      }
    }
  }

  private async loadEnergyData(): Promise<void> {
    try {
      const energyData = await this.apiService.callApi('/api/sustainability/energy', 'GET');

      const container = this.container?.querySelector('#energy-consumption-section');
      if (!container) return;

      if (!energyData || energyData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No energy data available</p>';
        return;
      }

      const energyHTML = `
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div class="bg-yellow-50 p-4 rounded-lg">
            <h4 class="font-medium text-yellow-900">Today's Usage</h4>
            <p class="text-2xl font-bold text-yellow-600">${energyData.today_usage || 0} kWh</p>
          </div>
          <div class="bg-orange-50 p-4 rounded-lg">
            <h4 class="font-medium text-orange-900">Monthly Total</h4>
            <p class="text-2xl font-bold text-orange-600">${energyData.monthly_total || 0} kWh</p>
          </div>
          <div class="bg-green-50 p-4 rounded-lg">
            <h4 class="font-medium text-green-900">Renewable %</h4>
            <p class="text-2xl font-bold text-green-600">${energyData.renewable_percentage || 0}%</p>
          </div>
        </div>
        <div class="space-y-2">
          ${energyData.consumers?.slice(0, 3).map((consumer: any) => `
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div>
                <p class="font-medium text-gray-900">${consumer.consumer_name}</p>
                <p class="text-sm text-gray-600">${consumer.location}</p>
              </div>
              <div class="text-right">
                <p class="font-medium text-gray-900">${consumer.current_usage || 0} kW</p>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                  ${consumer.efficiency === 'high' ? 'bg-green-100 text-green-800' :
                    consumer.efficiency === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'}">
                  ${consumer.efficiency || 'Unknown'} efficiency
                </span>
              </div>
            </div>
          `).join('') || '<p class="text-gray-500 text-center py-4">No energy consumers</p>'}
        </div>
      `;

      container.innerHTML = energyHTML;

    } catch (error) {
      console.error('Error loading energy data:', error);
      const container = this.container?.querySelector('#energy-consumption-section');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load energy data</p>';
      }
    }
  }

  private async loadAlerts(): Promise<void> {
    try {
      const alertsData = await this.apiService.callApi('/api/sustainability/alerts', 'GET');

      const container = this.container?.querySelector('#environmental-alerts');
      if (!container) return;

      if (!alertsData || alertsData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No active alerts</p>';
        return;
      }

      const alertsHTML = alertsData.slice(0, 5).map((alert: any) => `
        <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-b-0">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                ${alert.severity === 'critical' ? 'bg-red-100 text-red-800' :
                  alert.severity === 'high' ? 'bg-orange-100 text-orange-800' :
                  alert.severity === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'}">
                ${alert.severity || 'low'}
              </span>
            </div>
            <div class="ml-4">
              <p class="font-medium text-gray-900">${alert.alert_title}</p>
              <p class="text-sm text-gray-600">${alert.description}</p>
            </div>
          </div>
          <div class="text-right">
            <p class="text-sm text-gray-500">${new Date(alert.created_at).toLocaleString()}</p>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
              ${alert.status === 'active' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}">
              ${alert.status || 'resolved'}
            </span>
          </div>
        </div>
      `).join('');

      container.innerHTML = alertsHTML;

    } catch (error) {
      console.error('Error loading alerts:', error);
      const container = this.container?.querySelector('#environmental-alerts');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load alerts</p>';
      }
    }
  }

  private showEmissionsView(): void {
    // Implementation for emissions view
    console.log('Show emissions view');
  }

  private showNoiseMonitoringView(): void {
    // Implementation for noise monitoring view
    console.log('Show noise monitoring view');
  }

  private showEnergyDashboardView(): void {
    // Implementation for energy dashboard view
    console.log('Show energy dashboard view');
  }

  private showSustainabilityReportsView(): void {
    // Implementation for sustainability reports view
    console.log('Show sustainability reports view');
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
