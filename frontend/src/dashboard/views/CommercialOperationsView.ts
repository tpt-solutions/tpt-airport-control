import { DashboardApiService } from '../services/DashboardApiService.js';
import type { DashboardConfig } from '../../dashboard.js';

export class CommercialOperationsView {
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
      <div class="commercial-operations-view">
        <div class="mb-6">
          <h1 class="text-2xl font-bold text-gray-900">Commercial Operations</h1>
          <p class="text-gray-600">Manage retail sales, advertising, and VIP services</p>
        </div>

        <!-- Dashboard Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" id="commercial-stats">
          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Today's Sales</p>
                <p class="text-2xl font-semibold text-gray-900" id="today-sales">$0</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Active Outlets</p>
                <p class="text-2xl font-semibold text-gray-900" id="active-outlets">0</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">VIP Guests</p>
                <p class="text-2xl font-semibold text-gray-900" id="vip-guests">0</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-yellow-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Ad Revenue</p>
                <p class="text-2xl font-semibold text-gray-900" id="ad-revenue">$0</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-6">
          <div class="flex flex-wrap gap-3">
            <button id="manage-outlets" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
              </svg>
              Manage Outlets
            </button>
            <button id="advertising" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
              </svg>
              Advertising
            </button>
            <button id="vip-services" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
              </svg>
              VIP Services
            </button>
            <button id="sales-reports" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              Sales Reports
            </button>
          </div>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Recent Sales -->
          <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
              <h3 class="text-lg font-medium text-gray-900">Recent Sales</h3>
            </div>
            <div class="p-6" id="recent-sales">
              <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="mt-2 text-gray-600">Loading sales data...</p>
              </div>
            </div>
          </div>

          <!-- Active Promotions -->
          <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
              <h3 class="text-lg font-medium text-gray-900">Active Promotions</h3>
            </div>
            <div class="p-6" id="active-promotions">
              <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="mt-2 text-gray-600">Loading promotions...</p>
              </div>
            </div>
          </div>
        </div>

        <!-- VIP Lounge Status -->
        <div class="mt-6 bg-white rounded-lg shadow">
          <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">VIP Lounge Status</h3>
          </div>
          <div class="p-6" id="vip-lounge-status">
            <div class="text-center py-8">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600 mx-auto"></div>
              <p class="mt-2 text-gray-600">Loading VIP lounge data...</p>
            </div>
          </div>
        </div>

        <!-- Advertising Performance -->
        <div class="mt-6 bg-white rounded-lg shadow">
          <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Advertising Performance</h3>
          </div>
          <div class="p-6" id="advertising-performance">
            <div class="text-center py-8">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600 mx-auto"></div>
              <p class="mt-2 text-gray-600">Loading advertising data...</p>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  async init(): Promise<void> {
    this.container = document.querySelector('.commercial-operations-view') as HTMLElement;

    if (!this.container) {
      throw new Error('Commercial operations view container not found');
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

    // Manage outlets button
    const outletsBtn = this.container.querySelector('#manage-outlets') as HTMLButtonElement;
    if (outletsBtn) {
      outletsBtn.addEventListener('click', () => this.showManageOutletsView());
    }

    // Advertising button
    const advertisingBtn = this.container.querySelector('#advertising') as HTMLButtonElement;
    if (advertisingBtn) {
      advertisingBtn.addEventListener('click', () => this.showAdvertisingView());
    }

    // VIP services button
    const vipBtn = this.container.querySelector('#vip-services') as HTMLButtonElement;
    if (vipBtn) {
      vipBtn.addEventListener('click', () => this.showVipServicesView());
    }

    // Sales reports button
    const reportsBtn = this.container.querySelector('#sales-reports') as HTMLButtonElement;
    if (reportsBtn) {
      reportsBtn.addEventListener('click', () => this.showSalesReportsView());
    }
  }

  private async loadDashboardData(): Promise<void> {
    try {
      // Load commercial dashboard data
      const dashboardData = await this.apiService.callApi('/api/commercial', 'GET');

      // Update stats
      this.updateStats(dashboardData);

      // Load recent sales
      await this.loadRecentSales();

      // Load active promotions
      await this.loadActivePromotions();

      // Load VIP lounge status
      await this.loadVipLoungeStatus();

      // Load advertising performance
      await this.loadAdvertisingPerformance();

    } catch (error) {
      console.error('Error loading commercial dashboard data:', error);
      this.showError('Failed to load dashboard data');
    }
  }

  private updateStats(data: any): void {
    const salesEl = this.container?.querySelector('#today-sales');
    const outletsEl = this.container?.querySelector('#active-outlets');
    const vipEl = this.container?.querySelector('#vip-guests');
    const adEl = this.container?.querySelector('#ad-revenue');

    if (salesEl) salesEl.textContent = `$${data.today_sales || 0}`;
    if (outletsEl) outletsEl.textContent = data.active_outlets || '0';
    if (vipEl) vipEl.textContent = data.vip_guests || '0';
    if (adEl) adEl.textContent = `$${data.ad_revenue_today || 0}`;
  }

  private async loadRecentSales(): Promise<void> {
    try {
      const salesData = await this.apiService.callApi('/api/commercial/sales', 'GET');

      const container = this.container?.querySelector('#recent-sales');
      if (!container) return;

      if (!salesData || salesData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No recent sales</p>';
        return;
      }

      const salesHTML = salesData.slice(0, 5).map((sale: any) => `
        <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-b-0">
          <div>
            <p class="font-medium text-gray-900">${sale.outlet_name}</p>
            <p class="text-sm text-gray-600">${sale.transaction_id}</p>
          </div>
          <div class="text-right">
            <p class="font-medium text-gray-900">$${sale.amount}</p>
            <p class="text-sm text-gray-500">${new Date(sale.transaction_time).toLocaleTimeString()}</p>
          </div>
        </div>
      `).join('');

      container.innerHTML = salesHTML;

    } catch (error) {
      console.error('Error loading recent sales:', error);
      const container = this.container?.querySelector('#recent-sales');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load sales data</p>';
      }
    }
  }

  private async loadActivePromotions(): Promise<void> {
    try {
      const promotionsData = await this.apiService.callApi('/api/commercial/promotions', 'GET');

      const container = this.container?.querySelector('#active-promotions');
      if (!container) return;

      if (!promotionsData || promotionsData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No active promotions</p>';
        return;
      }

      const promotionsHTML = promotionsData.slice(0, 3).map((promo: any) => `
        <div class="p-4 bg-gray-50 rounded-lg mb-3">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="font-medium text-gray-900">${promo.promotion_name}</h4>
              <p class="text-sm text-gray-600">${promo.description}</p>
            </div>
            <div class="text-right">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                ${promo.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                ${promo.status}
              </span>
              <p class="text-xs text-gray-500 mt-1">${promo.discount_percentage}% off</p>
            </div>
          </div>
        </div>
      `).join('');

      container.innerHTML = promotionsHTML;

    } catch (error) {
      console.error('Error loading active promotions:', error);
      const container = this.container?.querySelector('#active-promotions');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load promotions</p>';
      }
    }
  }

  private async loadVipLoungeStatus(): Promise<void> {
    try {
      const vipData = await this.apiService.callApi('/api/commercial/vip', 'GET');

      const container = this.container?.querySelector('#vip-lounge-status');
      if (!container) return;

      if (!vipData) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No VIP lounge data available</p>';
        return;
      }

      const vipHTML = `
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div class="bg-purple-50 p-4 rounded-lg">
            <h4 class="font-medium text-purple-900">Current Guests</h4>
            <p class="text-2xl font-bold text-purple-600">${vipData.current_guests || 0}</p>
          </div>
          <div class="bg-blue-50 p-4 rounded-lg">
            <h4 class="font-medium text-blue-900">Available Rooms</h4>
            <p class="text-2xl font-bold text-blue-600">${vipData.available_rooms || 0}</p>
          </div>
          <div class="bg-green-50 p-4 rounded-lg">
            <h4 class="font-medium text-green-900">Today's Revenue</h4>
            <p class="text-2xl font-bold text-green-600">$${vipData.today_revenue || 0}</p>
          </div>
        </div>
        <div class="space-y-2">
          ${vipData.guests?.slice(0, 3).map((guest: any) => `
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div>
                <p class="font-medium text-gray-900">${guest.guest_name}</p>
                <p class="text-sm text-gray-600">Room ${guest.room_number}</p>
              </div>
              <div class="text-right">
                <p class="font-medium text-gray-900">${guest.service_level}</p>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                  ${guest.status === 'checked_in' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                  ${guest.status}
                </span>
              </div>
            </div>
          `).join('') || '<p class="text-gray-500 text-center py-4">No current guests</p>'}
        </div>
      `;

      container.innerHTML = vipHTML;

    } catch (error) {
      console.error('Error loading VIP lounge status:', error);
      const container = this.container?.querySelector('#vip-lounge-status');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load VIP lounge data</p>';
      }
    }
  }

  private async loadAdvertisingPerformance(): Promise<void> {
    try {
      const adData = await this.apiService.callApi('/api/commercial/advertising', 'GET');

      const container = this.container?.querySelector('#advertising-performance');
      if (!container) return;

      if (!adData || adData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No advertising data available</p>';
        return;
      }

      const adHTML = `
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div class="bg-green-50 p-4 rounded-lg">
            <h4 class="font-medium text-green-900">Today's Revenue</h4>
            <p class="text-2xl font-bold text-green-600">$${adData.today_revenue || 0}</p>
          </div>
          <div class="bg-blue-50 p-4 rounded-lg">
            <h4 class="font-medium text-blue-900">Active Campaigns</h4>
            <p class="text-2xl font-bold text-blue-600">${adData.active_campaigns || 0}</p>
          </div>
          <div class="bg-purple-50 p-4 rounded-lg">
            <h4 class="font-medium text-purple-900">Impressions</h4>
            <p class="text-2xl font-bold text-purple-600">${adData.total_impressions || 0}</p>
          </div>
        </div>
        <div class="space-y-2">
          ${adData.campaigns?.slice(0, 3).map((campaign: any) => `
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div>
                <p class="font-medium text-gray-900">${campaign.campaign_name}</p>
                <p class="text-sm text-gray-600">${campaign.location}</p>
              </div>
              <div class="text-right">
                <p class="font-medium text-gray-900">$${campaign.revenue}</p>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                  ${campaign.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                  ${campaign.status}
                </span>
              </div>
            </div>
          `).join('') || '<p class="text-gray-500 text-center py-4">No active campaigns</p>'}
        </div>
      `;

      container.innerHTML = adHTML;

    } catch (error) {
      console.error('Error loading advertising performance:', error);
      const container = this.container?.querySelector('#advertising-performance');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load advertising data</p>';
      }
    }
  }

  private showManageOutletsView(): void {
    // Implementation for manage outlets view
    console.log('Show manage outlets view');
  }

  private showAdvertisingView(): void {
    // Implementation for advertising view
    console.log('Show advertising view');
  }

  private showVipServicesView(): void {
    // Implementation for VIP services view
    console.log('Show VIP services view');
  }

  private showSalesReportsView(): void {
    // Implementation for sales reports view
    console.log('Show sales reports view');
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
