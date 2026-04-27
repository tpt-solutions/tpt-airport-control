import { DashboardApiService } from '../services/DashboardApiService.js';
import type { DashboardStats, User } from '../types.js';

export class OverviewView {
  private container: HTMLElement;
  private apiService: DashboardApiService;

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new DashboardApiService();
  }

  async render(user: User): Promise<string> {
    try {
      const stats = await this.apiService.fetchDashboardStats();

      return `
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Dashboard Overview</h2>
            <button id="refresh-stats" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
              Refresh
            </button>
          </div>

          <!-- Stats Cards -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            ${this.renderStatCard('Total Flights', stats.total_flights, '✈️', 'blue')}
            ${this.renderStatCard('Active Flights', stats.active_flights, '🛫', 'green')}
            ${this.renderStatCard('Total Passengers', stats.total_passengers, '👥', 'purple')}
            ${this.renderStatCard('Checked-in Passengers', stats.checked_in_passengers, '✅', 'indigo')}
          </div>

          <!-- System Health -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">System Health</h3>
            <div class="flex items-center space-x-4">
              <div class="flex items-center space-x-2">
                <div class="w-3 h-3 rounded-full ${
                  stats.system_health === 'healthy' ? 'bg-green-500' :
                  stats.system_health === 'warning' ? 'bg-yellow-500' : 'bg-red-500'
                }"></div>
                <span class="text-sm font-medium capitalize">${stats.system_health}</span>
              </div>
              <span class="text-sm text-gray-500">Last updated: ${new Date().toLocaleTimeString()}</span>
            </div>
          </div>

          <!-- Recent Activity -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Recent Activity</h3>
            <div id="recent-activity" class="space-y-3">
              <div class="animate-pulse text-gray-500">Loading recent activity...</div>
            </div>
          </div>
        </div>
      `;
    } catch (error) {
      console.error('Failed to load dashboard overview:', error);
      return '<div class="text-center text-red-500">Failed to load dashboard data</div>';
    }
  }

  private renderStatCard(title: string, value: number, icon: string, color: string): string {
    const colorClasses = {
      blue: 'bg-blue-500',
      green: 'bg-green-500',
      purple: 'bg-purple-500',
      indigo: 'bg-indigo-500'
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

  setupEventListeners(): void {
    const refreshBtn = document.getElementById('refresh-stats');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        // Trigger refresh - this would be handled by the parent component
        window.dispatchEvent(new CustomEvent('refreshOverview'));
      });
    }
  }
}
