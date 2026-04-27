import { DashboardApiService } from '../services/DashboardApiService.js';
import type { DashboardConfig } from '../../dashboard.js';

export class PassengerAlertsView {
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
      <div class="passenger-alerts-view">
        <div class="mb-6">
          <h1 class="text-2xl font-bold text-gray-900">Passenger Alerts System</h1>
          <p class="text-gray-600">Manage notifications, travel reminders, and passenger communications</p>
        </div>

        <!-- Dashboard Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" id="alerts-stats">
          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4.868 12.683A17.925 17.925 0 0112 21c7.962 0 12-1.21 12-2.683m-12 2.683l.01-.01M12 21c-7.962 0-12-1.21-12-2.683m12 2.683l-.01-.01M12 21v-2.5M12 7.5V3m0 4.5l4 4m-4-4l-4 4"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Active Alerts</p>
                <p class="text-2xl font-semibold text-gray-900" id="active-alerts">0</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Sent Today</p>
                <p class="text-2xl font-semibold text-gray-900" id="sent-today">0</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Push Subscribers</p>
                <p class="text-2xl font-semibold text-gray-900" id="push-subscribers">0</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-yellow-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Delivery Rate</p>
                <p class="text-2xl font-semibold text-gray-900" id="delivery-rate">0%</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-6">
          <div class="flex flex-wrap gap-3">
            <button id="create-alert" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
              </svg>
              Create Alert
            </button>
            <button id="manage-templates" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              Manage Templates
            </button>
            <button id="bulk-send" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
              </svg>
              Bulk Send
            </button>
            <button id="alerts-analytics" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
              </svg>
              Analytics
            </button>
          </div>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Recent Alerts -->
          <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
              <h3 class="text-lg font-medium text-gray-900">Recent Alerts</h3>
            </div>
            <div class="p-6" id="recent-alerts">
              <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="mt-2 text-gray-600">Loading alerts...</p>
              </div>
            </div>
          </div>

          <!-- Alert Templates -->
          <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
              <h3 class="text-lg font-medium text-gray-900">Alert Templates</h3>
            </div>
            <div class="p-6" id="alert-templates">
              <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="mt-2 text-gray-600">Loading templates...</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Active Travel Reminders -->
        <div class="mt-6 bg-white rounded-lg shadow">
          <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Active Travel Reminders</h3>
          </div>
          <div class="p-6" id="travel-reminders">
            <div class="text-center py-8">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600 mx-auto"></div>
              <p class="mt-2 text-gray-600">Loading reminders...</p>
            </div>
          </div>
        </div>

        <!-- Notification Channels -->
        <div class="mt-6 bg-white rounded-lg shadow">
          <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Notification Channels</h3>
          </div>
          <div class="p-6" id="notification-channels">
            <div class="text-center py-8">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600 mx-auto"></div>
              <p class="mt-2 text-gray-600">Loading channels...</p>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  async init(): Promise<void> {
    this.container = document.querySelector('.passenger-alerts-view') as HTMLElement;

    if (!this.container) {
      throw new Error('Passenger alerts view container not found');
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

    // Create alert button
    const createBtn = this.container.querySelector('#create-alert') as HTMLButtonElement;
    if (createBtn) {
      createBtn.addEventListener('click', () => this.showCreateAlertModal());
    }

    // Manage templates button
    const templatesBtn = this.container.querySelector('#manage-templates') as HTMLButtonElement;
    if (templatesBtn) {
      templatesBtn.addEventListener('click', () => this.showManageTemplatesView());
    }

    // Bulk send button
    const bulkBtn = this.container.querySelector('#bulk-send') as HTMLButtonElement;
    if (bulkBtn) {
      bulkBtn.addEventListener('click', () => this.showBulkSendView());
    }

    // Analytics button
    const analyticsBtn = this.container.querySelector('#alerts-analytics') as HTMLButtonElement;
    if (analyticsBtn) {
      analyticsBtn.addEventListener('click', () => this.showAnalyticsView());
    }
  }

  private async loadDashboardData(): Promise<void> {
    try {
      // Load alerts dashboard data
      const dashboardData = await this.apiService.callApi('/api/passenger-alerts', 'GET');

      // Update stats
      this.updateStats(dashboardData);

      // Load recent alerts
      await this.loadRecentAlerts();

      // Load alert templates
      await this.loadAlertTemplates();

      // Load travel reminders
      await this.loadTravelReminders();

      // Load notification channels
      await this.loadNotificationChannels();

    } catch (error) {
      console.error('Error loading alerts dashboard data:', error);
      this.showError('Failed to load dashboard data');
    }
  }

  private updateStats(data: any): void {
    const activeEl = this.container?.querySelector('#active-alerts');
    const sentEl = this.container?.querySelector('#sent-today');
    const subscribersEl = this.container?.querySelector('#push-subscribers');
    const deliveryEl = this.container?.querySelector('#delivery-rate');

    if (activeEl) activeEl.textContent = data.active_alerts || '0';
    if (sentEl) sentEl.textContent = data.sent_today || '0';
    if (subscribersEl) subscribersEl.textContent = data.push_subscribers || '0';
    if (deliveryEl) deliveryEl.textContent = `${data.delivery_rate || 0}%`;
  }

  private async loadRecentAlerts(): Promise<void> {
    try {
      const alertsData = await this.apiService.callApi('/api/passenger-alerts/alerts', 'GET');

      const container = this.container?.querySelector('#recent-alerts');
      if (!container) return;

      if (!alertsData || alertsData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No recent alerts</p>';
        return;
      }

      const alertsHTML = alertsData.slice(0, 5).map((alert: any) => `
        <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-b-0">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                ${this.getPriorityColor(alert.priority)}">
                ${alert.priority || 'normal'}
              </span>
            </div>
            <div class="ml-4">
              <p class="font-medium text-gray-900">${alert.alert_title}</p>
              <p class="text-sm text-gray-600">${alert.alert_type} - ${alert.target_audience}</p>
            </div>
          </div>
          <div class="text-right">
            <p class="text-sm font-medium text-gray-900">${alert.delivery_channels?.join(', ')}</p>
            <p class="text-xs text-gray-500">${new Date(alert.created_at).toLocaleString()}</p>
          </div>
        </div>
      `).join('');

      container.innerHTML = alertsHTML;

    } catch (error) {
      console.error('Error loading recent alerts:', error);
      const container = this.container?.querySelector('#recent-alerts');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load alerts</p>';
      }
    }
  }

  private async loadAlertTemplates(): Promise<void> {
    try {
      const templatesData = await this.apiService.callApi('/api/passenger-alerts/templates', 'GET');

      const container = this.container?.querySelector('#alert-templates');
      if (!container) return;

      if (!templatesData || templatesData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No templates available</p>';
        return;
      }

      const templatesHTML = templatesData.slice(0, 4).map((template: any) => `
        <div class="p-4 bg-gray-50 rounded-lg mb-3">
          <div class="flex items-center justify-between mb-2">
            <h4 class="font-medium text-gray-900">${template.template_name}</h4>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
              ${template.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
              ${template.status}
            </span>
          </div>
          <p class="text-sm text-gray-600 mb-2">${template.template_type}</p>
          <p class="text-xs text-gray-500">${template.description}</p>
        </div>
      `).join('');

      container.innerHTML = templatesHTML;

    } catch (error) {
      console.error('Error loading alert templates:', error);
      const container = this.container?.querySelector('#alert-templates');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load templates</p>';
      }
    }
  }

  private async loadTravelReminders(): Promise<void> {
    try {
      const remindersData = await this.apiService.callApi('/api/passenger-alerts/reminders', 'GET');

      const container = this.container?.querySelector('#travel-reminders');
      if (!container) return;

      if (!remindersData || remindersData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No active travel reminders</p>';
        return;
      }

      const remindersHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          ${remindersData.slice(0, 6).map((reminder: any) => `
            <div class="p-4 bg-gray-50 rounded-lg">
              <div class="flex items-center justify-between mb-2">
                <h4 class="font-medium text-gray-900">${reminder.reminder_type}</h4>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                  ${reminder.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                  ${reminder.status}
                </span>
              </div>
              <p class="text-sm text-gray-600 mb-2">${reminder.flight_number}</p>
              <p class="text-xs text-gray-500">${new Date(reminder.scheduled_time).toLocaleString()}</p>
            </div>
          `).join('')}
        </div>
      `;

      container.innerHTML = remindersHTML;

    } catch (error) {
      console.error('Error loading travel reminders:', error);
      const container = this.container?.querySelector('#travel-reminders');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load reminders</p>';
      }
    }
  }

  private async loadNotificationChannels(): Promise<void> {
    try {
      const channelsData = await this.apiService.callApi('/api/passenger-alerts/channels', 'GET');

      const container = this.container?.querySelector('#notification-channels');
      if (!container) return;

      if (!channelsData || channelsData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No notification channels configured</p>';
        return;
      }

      const channelsHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          ${channelsData.map((channel: any) => `
            <div class="p-4 bg-gray-50 rounded-lg">
              <div class="flex items-center justify-between mb-2">
                <h4 class="font-medium text-gray-900">${channel.channel_name}</h4>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                  ${channel.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                  ${channel.status}
                </span>
              </div>
              <p class="text-sm text-gray-600 mb-2">${channel.channel_type}</p>
              <p class="text-xs text-gray-500">Success: ${channel.delivery_rate || 0}%</p>
            </div>
          `).join('')}
        </div>
      `;

      container.innerHTML = channelsHTML;

    } catch (error) {
      console.error('Error loading notification channels:', error);
      const container = this.container?.querySelector('#notification-channels');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load channels</p>';
      }
    }
  }

  private getPriorityColor(priority: string): string {
    const colors: { [key: string]: string } = {
      'critical': 'bg-red-100 text-red-800',
      'high': 'bg-orange-100 text-orange-800',
      'normal': 'bg-blue-100 text-blue-800',
      'low': 'bg-gray-100 text-gray-800'
    };
    return colors[priority] || 'bg-gray-100 text-gray-800';
  }

  private showCreateAlertModal(): void {
    // Implementation for create alert modal
    console.log('Show create alert modal');
  }

  private showManageTemplatesView(): void {
    // Implementation for manage templates view
    console.log('Show manage templates view');
  }

  private showBulkSendView(): void {
    // Implementation for bulk send view
    console.log('Show bulk send view');
  }

  private showAnalyticsView(): void {
    // Implementation for analytics view
    console.log('Show analytics view');
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
