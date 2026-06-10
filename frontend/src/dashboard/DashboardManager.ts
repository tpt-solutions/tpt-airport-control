import { AuthManager } from '../auth.js';
import { DashboardHeader } from './components/DashboardHeader.js';
import { DashboardSidebar } from './components/DashboardSidebar.js';
import { OverviewView } from './views/OverviewView.js';
import { FlightsManagementView } from './views/FlightsManagementView.js';
import { MyBookingsView } from './views/MyBookingsView.js';
import { PassengersManagementView } from './views/PassengersManagementView.js';
import { MaintenanceView } from './views/MaintenanceView.js';
import { SecurityManagementView } from './views/SecurityManagementView.js';
import { MyBaggageView } from './views/MyBaggageView.js';
import type { DashboardView, User } from './types.js';

// Views that require elevated roles; anything not listed allows any authenticated user.
const VIEW_PERMISSIONS: Partial<Record<DashboardView, string[]>> = {
  flights:          ['operator', 'admin', 'super_admin'],
  passengers:       ['operator', 'admin', 'super_admin'],
  maintenance:      ['operator', 'admin', 'super_admin'],
  security:         ['operator', 'admin', 'super_admin'],
  'module-management': ['admin', 'super_admin'],
};

export class DashboardManager {
  private auth: AuthManager;
  private container: HTMLElement;
  private currentView: DashboardView = 'overview';
  private abortController: AbortController | null = null;

  // Components
  private header: DashboardHeader;
  private sidebar: DashboardSidebar;

  // Views
  private overviewView: OverviewView;
  private flightsView: FlightsManagementView;
  private bookingsView: MyBookingsView;
  private passengersView: PassengersManagementView;
  private maintenanceView: MaintenanceView;
  private securityView: SecurityManagementView;
  private baggageView: MyBaggageView;

  constructor(container: HTMLElement) {
    this.auth = AuthManager.getInstance();
    this.container = container;

    // Initialize components
    this.header = new DashboardHeader(container);
    this.sidebar = new DashboardSidebar(this.currentView, this.handleViewChange.bind(this));

    // Initialize views
    this.overviewView = new OverviewView(container);
    this.flightsView = new FlightsManagementView(container);
    this.bookingsView = new MyBookingsView(container);
    this.passengersView = new PassengersManagementView(container);
    this.maintenanceView = new MaintenanceView(container);
    this.securityView = new SecurityManagementView(container);
    this.baggageView = new MyBaggageView(container);
  }

  async render() {
    try {
      const user = this.auth.getUser();

      if (!user || typeof user.role_name === 'undefined') {
        this.container.innerHTML = '<div class="min-h-screen bg-slate-950 flex items-center justify-center text-red-400">Authentication required</div>';
        return;
      }

      this.container.innerHTML = `
        <div class="min-h-screen bg-slate-950 flex flex-col">
          ${this.header.render(user)}
          <div class="flex flex-1 overflow-hidden">
            ${this.sidebar.render(user)}
            <main class="flex-1 overflow-y-auto p-6 bg-slate-950">
              <div id="dashboard-content">
                ${await this.renderContent(user)}
              </div>
            </main>
          </div>
        </div>
      `;

      this.setupEventListeners();
    } catch (error) {
      console.error('[DashboardManager] render() error:', error);
      this.container.innerHTML = `<div class="min-h-screen bg-slate-950 flex items-center justify-center text-red-400 p-8">
        <div>
          <h2 class="text-xl font-bold mb-2">Dashboard Error</h2>
          <pre class="text-sm whitespace-pre-wrap"></pre>
        </div>
      </div>`;
      const errorPre = this.container.querySelector('pre');
      if (errorPre) {
        errorPre.textContent = error instanceof Error ? error.message : String(error);
      }
    }
  }

  private async renderContent(user: User): Promise<string> {
    try {
      switch (this.currentView) {
        case 'overview':
          return await this.overviewView.render(user);
        case 'flights':
          return await this.flightsView.render(user);
        case 'my-bookings':
          return await this.bookingsView.render(user);
        case 'passengers':
          return await this.passengersView.render(user);
        case 'infrastructure':
        case 'infrastructure-reports':
        case 'drones':
        case 'drone-reports':
        case 'customs':
        case 'customs-reports':
        case 'advanced-security':
        case 'advanced-security-reports':
        case 'ai-conflict-prediction':
        case 'ai-reports':
        case 'virtual-assistant':
        case 'module-management':
          return '<div class="text-center text-gray-500 py-8">Coming soon...</div>';
        case 'maintenance':
          return await this.maintenanceView.render(user);
        case 'security':
          return await this.securityView.render(user);
        case 'my-baggage':
          return await this.baggageView.render(user);
        default:
          return '<div class="text-center text-gray-500">Content not available</div>';
      }
    } catch (error) {
      console.error('[DashboardManager] renderContent() error for view', this.currentView, ':', error);
      return `<div class="text-center text-red-400 py-8">
        <div class="font-bold mb-2">Error loading view: ${this.currentView}</div>
        <div id="error-message-text" class="text-sm"></div>
      </div>`;
    }
  }

  navigateTo(view: DashboardView): void {
    this.handleViewChange(view);
  }

  private canAccessView(view: DashboardView, user: User): boolean {
    const allowed = VIEW_PERMISSIONS[view];
    if (!allowed) return true;
    return allowed.includes(user.role_name);
  }

  private handleViewChange(view: DashboardView): void {
    const user = this.auth.getUser();
    if (user && !this.canAccessView(view, user)) {
      const content = document.getElementById('dashboard-content');
      if (content) {
        const msg = document.createElement('div');
        msg.className = 'text-center text-red-400 py-16';
        msg.textContent = 'Access denied. You do not have permission to view this section.';
        content.replaceChildren(msg);
      }
      return;
    }
    this.currentView = view;
    this.sidebar.updateCurrentView(view);
    this.render();
  }

  private setupEventListeners(): void {
    // Clean up previous listeners before adding new ones
    if (this.abortController) {
      this.abortController.abort();
    }
    this.abortController = new AbortController();
    const signal = this.abortController.signal;

    // Header event listeners
    this.header.setupEventListeners();

    // Sidebar event listeners
    this.sidebar.setupEventListeners();

    // View-specific event listeners - register all available views
    switch (this.currentView) {
      case 'overview':
        this.overviewView.setupEventListeners();
        break;
      case 'flights':
        this.flightsView.setupEventListeners();
        break;
      case 'my-bookings':
        this.bookingsView.setupEventListeners();
        break;
      case 'passengers':
        this.passengersView.setupEventListeners();
        break;
      case 'maintenance':
        this.maintenanceView.setupEventListeners();
        break;
      case 'security':
        this.securityView.setupEventListeners();
        break;
      case 'my-baggage':
        this.baggageView.setupEventListeners();
        break;
    }

    // Global flight management events
    window.addEventListener('refreshFlights', () => {
      if (this.currentView === 'flights') {
        this.render();
      }
    }, { signal });

    // Switch back to flights list from map view
    window.addEventListener('showFlightsList', () => {
      this.handleViewChange('flights');
    }, { signal });

    // Navigate event from quick actions in OverviewView
    window.addEventListener('navigate', ((e: CustomEvent) => {
      const view = e.detail?.view as DashboardView;
      if (view && view !== this.currentView) {
        this.handleViewChange(view);
      }
    }) as EventListener, { signal });

    // Refresh overview stats
    window.addEventListener('refreshOverview', () => {
      if (this.currentView === 'overview') {
        this.render();
      }
    }, { signal });
  }
}