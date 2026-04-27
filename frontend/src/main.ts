import './style.css'
import { AuthManager, AuthUI } from './auth.js'
import { DashboardManager } from './dashboard.js'

// PWA Service Worker Registration
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then(registration => {
        console.log('SW registered: ', registration);
      })
      .catch(registrationError => {
        console.log('SW registration failed: ', registrationError);
      });
  });
}



// Global error handling
window.addEventListener('error', (_event) => {
  console.error('Global error occurred');
  // Could send to monitoring service
});

window.addEventListener('unhandledrejection', (_event) => {
  console.error('Unhandled promise rejection occurred');
  // Could send to monitoring service
});



// ATC 3D Visualization Component
class ATC3DDashboard {
  private container: HTMLElement;
  private viz: any = null;

  constructor(container: HTMLElement) {
    this.container = container;
    this.init();
  }

  private init() {
    // Create 3D visualization container
    const vizContainer = document.createElement('div');
    vizContainer.id = 'atc-3d-container';
    vizContainer.style.width = '100%';
    vizContainer.style.height = '600px';
    vizContainer.style.border = '1px solid #ccc';

    // Create controls
    const controls = document.createElement('div');
    controls.innerHTML = `
      <div class="flex gap-4 p-4 bg-gray-100">
        <button id="plan-view" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Plan View</button>
        <button id="side-view" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Side View</button>
        <button id="3d-view" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">3D View</button>
        <button id="toggle-weather" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">Toggle Weather</button>
        <button id="toggle-trails" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">Toggle Trails</button>
        <button id="toggle-terrain" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">Toggle Terrain</button>
      </div>
    `;

    this.container.appendChild(controls);
    this.container.appendChild(vizContainer);

    // Initialize 3D visualization
    this.init3DVisualization(vizContainer);

    // Setup control event listeners
    this.setupControls();
  }

  private async init3DVisualization(container: HTMLElement) {
    try {
      // Dynamic import to avoid loading Three.js unless needed
      const { ATC3DVisualization } = await import('./atc-3d-visualization.js');

      this.viz = new ATC3DVisualization({
        container,
        bounds: {
          north: 45,
          south: 35,
          east: -70,
          west: -80,
          minAltitude: 0,
          maxAltitude: 50000
        },
        showTerrain: true,
        showWeather: true,
        showAirports: true,
        showFlightPaths: true,
        updateInterval: 1000
      });
    } catch (error) {
      console.error('Failed to initialize 3D visualization:', error);
      container.innerHTML = '<div class="flex items-center justify-center h-full text-red-500">3D Visualization failed to load. Please check console for errors.</div>';
    }
  }

  private setupControls() {
    const planViewBtn = document.getElementById('plan-view');
    const sideViewBtn = document.getElementById('side-view');
    const view3DBtn = document.getElementById('3d-view');
    const toggleWeatherBtn = document.getElementById('toggle-weather');
    const toggleTrailsBtn = document.getElementById('toggle-trails');
    const toggleTerrainBtn = document.getElementById('toggle-terrain');

    if (planViewBtn) {
      planViewBtn.addEventListener('click', () => {
        if (this.viz) this.viz.setViewMode('plan');
      });
    }

    if (sideViewBtn) {
      sideViewBtn.addEventListener('click', () => {
        if (this.viz) this.viz.setViewMode('side');
      });
    }

    if (view3DBtn) {
      view3DBtn.addEventListener('click', () => {
        if (this.viz) this.viz.setViewMode('3d');
      });
    }

    if (toggleWeatherBtn) {
      toggleWeatherBtn.addEventListener('click', () => {
        if (this.viz) {
          const current = toggleWeatherBtn.textContent?.includes('Hide');
          this.viz.toggleWeather(!current);
          toggleWeatherBtn.textContent = current ? 'Show Weather' : 'Hide Weather';
        }
      });
    }

    if (toggleTrailsBtn) {
      toggleTrailsBtn.addEventListener('click', () => {
        if (this.viz) {
          const current = toggleTrailsBtn.textContent?.includes('Hide');
          this.viz.toggleFlightPaths(!current);
          toggleTrailsBtn.textContent = current ? 'Show Trails' : 'Hide Trails';
        }
      });
    }

    if (toggleTerrainBtn) {
      toggleTerrainBtn.addEventListener('click', () => {
        if (this.viz) {
          const current = toggleTerrainBtn.textContent?.includes('Hide');
          this.viz.toggleTerrain(!current);
          toggleTerrainBtn.textContent = current ? 'Show Terrain' : 'Hide Terrain';
        }
      });
    }
  }

  public destroy() {
    if (this.viz) {
      this.viz.destroy();
    }
  }
}

// App initialization
class FlightControlApp {
  private app: HTMLElement;
  private auth: AuthManager;
  private authUI: AuthUI;
  private dashboard: DashboardManager;
  private currentView: 'status' | 'atc-3d' = 'status';
  private atcDashboard: ATC3DDashboard | null = null;

  constructor() {
    this.app = document.querySelector<HTMLDivElement>('#app')!;
    this.auth = AuthManager.getInstance();
    this.authUI = new AuthUI(this.app);
    this.dashboard = new DashboardManager(this.app);
    this.init();
  }

  private async init() {
    // Check if user is already authenticated
    if (this.auth.isAuthenticated()) {
      await this.showDashboard();
    } else {
      this.showLogin();
    }
  }

  private async showDashboard() {
    await this.dashboard.render();
  }

  private showLogin() {
    this.authUI.renderLoginForm();
  }



  private renderStatusView() {
    return `
      <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4 text-center">System Status</h2>
        <div id="status" class="text-center">
          <div class="animate-pulse text-gray-500">Loading...</div>
        </div>
        <div class="mt-6">
          <button id="checkHealth" class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition-colors">
            Check System Health
          </button>
        </div>
      </div>
    `;
  }

  private renderATC3DView() {
    return `
      <div id="atc-3d-container" class="w-full">
        <div class="text-center py-8">
          <div class="animate-pulse text-gray-500">Loading 3D ATC Visualization...</div>
        </div>
      </div>
    `;
  }



  private switchView(view: 'status' | 'atc-3d') {
    if (this.currentView === view) return;

    this.currentView = view;
    const contentArea = document.getElementById('content-area');

    if (!contentArea) return;

    // Clean up current view
    if (this.atcDashboard) {
      this.atcDashboard.destroy();
      this.atcDashboard = null;
    }

    // Render new view
    if (view === 'status') {
      contentArea.innerHTML = this.renderStatusView();
      this.checkHealth();
    } else if (view === 'atc-3d') {
      contentArea.innerHTML = this.renderATC3DView();
      // Initialize 3D visualization after a short delay to ensure DOM is ready
      setTimeout(() => {
        const container = document.getElementById('atc-3d-container');
        if (container) {
          this.atcDashboard = new ATC3DDashboard(container);
        }
      }, 100);
    }
  }

  private async checkHealth() {
    const statusDiv = document.getElementById('status');
    if (!statusDiv) return;

    try {
      statusDiv.innerHTML = '<div class="text-blue-600">Checking system health...</div>';

      const response = await fetch('/api/health.php');
      const data = await response.json();

      if (data.status === 'healthy') {
        statusDiv.innerHTML = `
          <div class="text-green-600 font-semibold">✓ System Healthy</div>
          <div class="text-sm text-gray-600 mt-2">
            Database: ${data.checks.database}<br>
            Last checked: ${new Date(data.timestamp * 1000).toLocaleString()}
          </div>
        `;
      } else {
        statusDiv.innerHTML = `
          <div class="text-red-600 font-semibold">⚠ System Issues Detected</div>
          <div class="text-sm text-gray-600 mt-2">
            Please check system logs and contact administrator.
          </div>
        `;
      }
    } catch (error) {
      console.error('Health check failed:', error);
      statusDiv.innerHTML = `
        <div class="text-red-600 font-semibold">✗ Connection Failed</div>
        <div class="text-sm text-gray-600 mt-2">
          Unable to connect to backend. Check network and server status.
        </div>
      `;
    }
  }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  new FlightControlApp();
});
