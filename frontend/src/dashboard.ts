import { DashboardApiService } from './dashboard/services/DashboardApiService.js';
import { DashboardHeader } from './dashboard/components/DashboardHeader.js';
import { DashboardSidebar } from './dashboard/components/DashboardSidebar.js';
import { OverviewView } from './dashboard/views/OverviewView.js';
import { FlightsManagementView } from './dashboard/views/FlightsManagementView.js';
import { MyBookingsView } from './dashboard/views/MyBookingsView.js';
import { CargoOperationsView } from './dashboard/views/CargoOperationsView.js';
import { SustainabilityView } from './dashboard/views/SustainabilityView.js';
import { CommercialOperationsView } from './dashboard/views/CommercialOperationsView.js';
import { EmergencyManagementView } from './dashboard/views/EmergencyManagementView.js';
import { PassengerAlertsView } from './dashboard/views/PassengerAlertsView.js';

export interface DashboardConfig {
  user: {
    id: string;
    name: string;
    role: string;
    permissions: string[];
  };
  modules: {
    cargo: boolean;
    sustainability: boolean;
    commercial: boolean;
    emergency: boolean;
    passengerAlerts: boolean;
  };
}

export class DashboardManager {
  private container: HTMLElement;
  private apiService: DashboardApiService;
  private header: DashboardHeader;
  private sidebar: DashboardSidebar;
  private currentView: any = null;
  private config: DashboardConfig;

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new DashboardApiService();
    this.config = this.getDashboardConfig();
    this.header = new DashboardHeader(this.config);
    this.sidebar = new DashboardSidebar(this.config);
    this.init();
  }

  private init() {
    this.setupEventListeners();
  }

  private setupEventListeners() {
    // Listen for navigation events
    document.addEventListener('navigate', (event: any) => {
      this.navigateToView(event.detail.view);
    });

    // Listen for module toggle events
    document.addEventListener('moduleToggle', (event: any) => {
      this.handleModuleToggle(event.detail);
    });
  }

  async render() {
    this.container.innerHTML = '';

    // Create main dashboard structure
    const dashboardHTML = `
      <div class="dashboard-container min-h-screen bg-gray-50">
        ${this.header.render()}
        <div class="dashboard-content flex">
          ${this.sidebar.render()}
          <main class="flex-1 p-6" id="main-content">
            <div class="loading-spinner text-center py-12">
              <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
              <p class="mt-4 text-gray-600">Loading dashboard...</p>
            </div>
          </main>
        </div>
      </div>
    `;

    this.container.innerHTML = dashboardHTML;

    // Initialize header and sidebar
    this.header.init();
    this.sidebar.init();

    // Load default view
    await this.navigateToView('overview');
  }

  async navigateToView(viewName: string) {
    const mainContent = document.getElementById('main-content');
    if (!mainContent) return;

    // Clean up current view
    if (this.currentView && typeof this.currentView.destroy === 'function') {
      this.currentView.destroy();
    }

    // Show loading
    mainContent.innerHTML = `
      <div class="loading-spinner text-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
        <p class="mt-4 text-gray-600">Loading ${viewName}...</p>
      </div>
    `;

    try {
      // Create and render new view
      switch (viewName) {
        case 'overview':
          this.currentView = new OverviewView(this.apiService, this.config);
          break;
        case 'flights':
          this.currentView = new FlightsManagementView(this.apiService, this.config);
          break;
        case 'bookings':
          this.currentView = new MyBookingsView(this.apiService, this.config);
          break;
        case 'cargo':
          if (this.config.modules.cargo) {
            this.currentView = new CargoOperationsView(this.apiService, this.config);
          } else {
            throw new Error('Cargo Operations module is not enabled');
          }
          break;
        case 'sustainability':
          if (this.config.modules.sustainability) {
            this.currentView = new SustainabilityView(this.apiService, this.config);
          } else {
            throw new Error('Sustainability module is not enabled');
          }
          break;
        case 'commercial':
          if (this.config.modules.commercial) {
            this.currentView = new CommercialOperationsView(this.apiService, this.config);
          } else {
            throw new Error('Commercial Operations module is not enabled');
          }
          break;
        case 'emergency':
          if (this.config.modules.emergency) {
            this.currentView = new EmergencyManagementView(this.apiService, this.config);
          } else {
            throw new Error('Emergency Management module is not enabled');
          }
          break;
        case 'alerts':
          if (this.config.modules.passengerAlerts) {
            this.currentView = new PassengerAlertsView(this.apiService, this.config);
          } else {
            throw new Error('Passenger Alerts module is not enabled');
          }
          break;
        default:
          throw new Error(`Unknown view: ${viewName}`);
      }

      const viewHTML = await this.currentView.render();
      mainContent.innerHTML = viewHTML;
      await this.currentView.init();

    } catch (error) {
      console.error('Error loading view:', error);
      mainContent.innerHTML = `
        <div class="error-message bg-red-50 border border-red-200 rounded-lg p-6">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800">Error Loading View</h3>
              <p class="mt-1 text-sm text-red-700">${error.message}</p>
            </div>
          </div>
        </div>
      `;
    }
  }

  private handleModuleToggle(data: { module: string; enabled: boolean }) {
    this.config.modules[data.module as keyof typeof this.config.modules] = data.enabled;
    // Update sidebar to reflect changes
    this.sidebar.updateModules(this.config.modules);
  }

  private getDashboardConfig(): DashboardConfig {
    // Get user info from localStorage or API
    const user = JSON.parse(localStorage.getItem('user') || '{}');

    // Get module configuration from API or localStorage
    const modules = {
      cargo: true, // Enable by default for demonstration
      sustainability: true,
      commercial: true,
      emergency: true,
      passengerAlerts: true
    };

    return {
      user: {
        id: user.id || '1',
        name: user.name || 'Demo User',
        role: user.role || 'admin',
        permissions: user.permissions || ['read', 'write', 'admin']
      },
      modules
    };
  }

  destroy() {
    if (this.currentView && typeof this.currentView.destroy === 'function') {
      this.currentView.destroy();
    }
    this.header.destroy();
    this.sidebar.destroy();
  }
}

// Export types and interfaces
export type { DashboardConfig };
