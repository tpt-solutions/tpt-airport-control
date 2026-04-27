import { AuthManager } from '../auth.js';
import { DashboardHeader } from './components/DashboardHeader.js';
import { DashboardSidebar } from './components/DashboardSidebar.js';
import { OverviewView } from './views/OverviewView.js';
import { FlightsManagementView } from './views/FlightsManagementView.js';
import { MyBookingsView } from './views/MyBookingsView.js';
import { InfrastructureManagementView } from './views/InfrastructureManagementView.js';
import { InfrastructureReportsView } from './views/InfrastructureReportsView.js';
import { DroneOperationsView } from './views/DroneOperationsView.js';
import { DroneReportsView } from './views/DroneReportsView.js';
import { CustomsBorderProtectionView } from './views/CustomsBorderProtectionView.js';
import { CustomsReportsView } from './views/CustomsReportsView.js';
import { AdvancedSecurityView } from './views/AdvancedSecurityView.js';
import { AdvancedSecurityReportsView } from './views/AdvancedSecurityReportsView.js';
import { AIConflictPredictionView } from './views/AIConflictPredictionView.js';
import { AIConflictReportsView } from './views/AIConflictReportsView.js';
import { VirtualAssistantView } from './views/VirtualAssistantView.js';
import { ModuleManagementView } from './views/ModuleManagementView.js';
import type { DashboardView, User } from './types.js';

export class DashboardManager {
  private auth: AuthManager;
  private container: HTMLElement;
  private currentView: DashboardView = 'overview';

  // Components
  private header: DashboardHeader;
  private sidebar: DashboardSidebar;

  // Views
  private overviewView: OverviewView;
  private flightsView: FlightsManagementView;
  private bookingsView: MyBookingsView;
  private infrastructureView: InfrastructureManagementView;
  private infrastructureReportsView: InfrastructureReportsView;
  private droneView: DroneOperationsView;
  private droneReportsView: DroneReportsView;
  private customsView: CustomsBorderProtectionView;
  private customsReportsView: CustomsReportsView;
  private advancedSecurityView: AdvancedSecurityView;
  private advancedSecurityReportsView: AdvancedSecurityReportsView;
  private aiConflictView: AIConflictPredictionView;
  private aiReportsView: AIConflictReportsView;
  private virtualAssistantView: VirtualAssistantView;
  private moduleManagementView: ModuleManagementView;

  constructor(container: HTMLElement) {
    this.auth = AuthManager.getInstance();
    this.container = container;

    // Initialize components
    this.header = new DashboardHeader(container);
    this.sidebar = new DashboardSidebar(container, this.currentView, this.handleViewChange.bind(this));

    // Initialize views
    this.overviewView = new OverviewView(container);
    this.flightsView = new FlightsManagementView(container);
    this.bookingsView = new MyBookingsView(container);
    this.infrastructureView = new InfrastructureManagementView(container);
    this.infrastructureReportsView = new InfrastructureReportsView(container);
    this.droneView = new DroneOperationsView(container);
    this.droneReportsView = new DroneReportsView(container);
    this.customsView = new CustomsBorderProtectionView(container);
    this.customsReportsView = new CustomsReportsView(container);
    this.advancedSecurityView = new AdvancedSecurityView(container);
    this.advancedSecurityReportsView = new AdvancedSecurityReportsView(container);
    this.aiConflictView = new AIConflictPredictionView(container);
    this.aiReportsView = new AIConflictReportsView(container);
    this.virtualAssistantView = new VirtualAssistantView(container);
    this.moduleManagementView = new ModuleManagementView(container);
  }

  async render() {
    const user = this.auth.getUser();
    if (!user) {
      this.container.innerHTML = '<div class="text-center text-red-500">Authentication required</div>';
      return;
    }

    this.container.innerHTML = `
      <div class="min-h-screen bg-gray-50">
        ${this.header.render(user)}
        <div class="flex">
          ${this.sidebar.render(user)}
          <main class="flex-1 p-6">
            <div id="dashboard-content">
              ${await this.renderContent(user)}
            </div>
          </main>
        </div>
      </div>
    `;

    this.setupEventListeners();
  }

  private async renderContent(user: User): Promise<string> {
    switch (this.currentView) {
      case 'overview':
        return await this.overviewView.render(user);
      case 'flights':
        return await this.flightsView.render(user);
      case 'my-bookings':
        return await this.bookingsView.render(user);
      case 'infrastructure':
        return await this.infrastructureView.render(user);
      case 'infrastructure-reports':
        return await this.infrastructureReportsView.render(user);
      case 'drones':
        return await this.droneView.render(user);
      case 'drone-reports':
        return await this.droneReportsView.render(user);
      case 'customs':
        return await this.customsView.render(user);
      case 'customs-reports':
        return await this.customsReportsView.render(user);
      case 'advanced-security':
        return await this.advancedSecurityView.render(user);
      case 'advanced-security-reports':
        return await this.advancedSecurityReportsView.render(user);
      case 'ai-conflict-prediction':
        return await this.aiConflictView.render(user);
      case 'ai-reports':
        return await this.aiReportsView.render(user);
      case 'virtual-assistant':
        return await this.virtualAssistantView.render(user);
      case 'module-management':
        return await this.moduleManagementView.render(user);
      case 'passengers':
        return '<div class="text-center text-gray-500 py-8">Passenger management interface coming soon...</div>';
      case 'maintenance':
        return '<div class="text-center text-gray-500 py-8">Maintenance management interface coming soon...</div>';
      case 'security':
        return '<div class="text-center text-gray-500 py-8">Security management interface coming soon...</div>';
      case 'my-baggage':
        return '<div class="text-center text-gray-500 py-8">Baggage tracking interface coming soon...</div>';
      default:
        return '<div class="text-center text-gray-500">Content not available</div>';
    }
  }

  private handleViewChange(view: DashboardView): void {
    this.currentView = view;
    this.sidebar.updateCurrentView(view);
    this.render();
  }

  private setupEventListeners(): void {
    // Header event listeners
    this.header.setupEventListeners();

    // Sidebar event listeners
    this.sidebar.setupEventListeners();

    // View-specific event listeners
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
      case 'infrastructure':
        this.infrastructureView.setupEventListeners();
        break;
      case 'infrastructure-reports':
        this.infrastructureReportsView.setupEventListeners();
        break;
      case 'drones':
        this.droneView.setupEventListeners();
        break;
      case 'drone-reports':
        this.droneReportsView.setupEventListeners();
        break;
      case 'customs':
        this.customsView.setupEventListeners();
        break;
      case 'customs-reports':
        this.customsReportsView.setupEventListeners();
        break;
      case 'advanced-security':
        this.advancedSecurityView.setupEventListeners();
        break;
      case 'advanced-security-reports':
        this.advancedSecurityReportsView.setupEventListeners();
        break;
      case 'ai-conflict-prediction':
        this.aiConflictView.setupEventListeners();
        break;
      case 'ai-reports':
        this.aiReportsView.setupEventListeners();
        break;
      case 'virtual-assistant':
        this.virtualAssistantView.setupEventListeners();
        break;
      case 'module-management':
        this.moduleManagementView.setupEventListeners();
        break;
    }

    // Global dashboard events
    window.addEventListener('refreshOverview', () => {
      if (this.currentView === 'overview') {
        this.render();
      }
    });

    window.addEventListener('refreshInfrastructure', () => {
      if (this.currentView === 'infrastructure') {
        this.render();
      }
    });

    window.addEventListener('showInfrastructureDashboard', () => {
      this.handleViewChange('infrastructure');
    });

    window.addEventListener('showInfrastructureReports', () => {
      this.handleViewChange('infrastructure-reports');
    });

    window.addEventListener('refreshDroneData', () => {
      if (this.currentView === 'drones') {
        this.render();
      }
    });

    window.addEventListener('showDroneDashboard', () => {
      this.handleViewChange('drones');
    });

    window.addEventListener('showDroneReports', () => {
      this.handleViewChange('drone-reports');
    });

    window.addEventListener('refreshBorderData', () => {
      if (this.currentView === 'customs') {
        this.render();
      }
    });

    window.addEventListener('showBorderReports', () => {
      this.handleViewChange('customs-reports');
    });

    window.addEventListener('refreshCustomsReports', () => {
      if (this.currentView === 'customs-reports') {
        this.render();
      }
    });

    window.addEventListener('refreshSecurityData', () => {
      if (this.currentView === 'advanced-security') {
        this.render();
      }
    });

    window.addEventListener('showSecurityReports', () => {
      this.handleViewChange('advanced-security-reports');
    });

    window.addEventListener('refreshSecurityReports', () => {
      if (this.currentView === 'advanced-security-reports') {
        this.render();
      }
    });
  }
}
