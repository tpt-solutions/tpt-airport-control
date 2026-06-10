import { DashboardApiService } from '../services/DashboardApiService.js';
import { FlightMapView } from './FlightMapView.js';
import { ATC3DVisualization } from '../../atc-3d-visualization.js';
import { NotificationManager } from '../../components/NotificationManager.js';
import type { Flight, User } from '../types.js';

type ViewMode = 'list' | 'map' | '3d';

export class FlightsManagementView {
  private apiService: DashboardApiService;
  private notifications: NotificationManager;
  private currentViewMode: ViewMode = 'list';
  private mapView: FlightMapView | null = null;
  private atc3dView: ATC3DVisualization | null = null;
  private cachedFlights: Flight[] = [];
  private cachedUser: User | null = null;

  constructor(_container: HTMLElement) {
    this.apiService = new DashboardApiService();
    this.notifications = new NotificationManager(document.body);
  }

  async render(user: User): Promise<string> {
    try {
      this.cachedUser = user;
      const flights = await this.apiService.fetchFlights();
      this.cachedFlights = flights;

      const viewModeBtns = [
        { mode: 'list' as ViewMode, label: '📋 List', desc: 'List View' },
        { mode: 'map' as ViewMode, label: '🗺 Map', desc: 'Map View' },
        { mode: '3d' as ViewMode, label: '🎯 3D', desc: '3D ATC View' },
      ];

      return `
        <div class="space-y-6">
          <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="text-xl font-semibold text-slate-100">Flight Management</h2>
            <div class="flex items-center gap-2 flex-wrap">
              <input type="text" id="flight-search" placeholder="Search flights..."
                     class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
              <div class="flex rounded-lg overflow-hidden border border-slate-600">
                ${viewModeBtns.map(({ mode, label, desc }) => `
                  <button
                    class="view-mode-btn px-3 py-2 text-sm font-medium transition-colors ${
                      this.currentViewMode === mode
                        ? 'bg-blue-600 text-white'
                        : 'bg-slate-700 text-slate-300 hover:bg-slate-600'
                    }"
                    data-mode="${mode}"
                    title="${desc}">
                    ${label}
                  </button>
                `).join('')}
              </div>
              <button id="add-flight-btn" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-500 transition-colors">
                Add Flight
              </button>
            </div>
          </div>

          ${this.currentViewMode === 'list'
            ? this.renderListView(flights)
            : this.currentViewMode === 'map'
              ? this.renderMapView()
              : this.render3DView()}
        </div>
      `;
    } catch (error) {
      console.error('Failed to load flights:', error);
      return '<div class="text-center text-red-400 py-8">Failed to load flight data</div>';
    }
  }

  private sanitize(str: string): string {
    const el = document.createElement('div');
    el.textContent = str;
    return el.innerHTML;
  }

  private renderListView(flights: Flight[]): string {
    const statusColors: Record<string, string> = {
      scheduled: 'bg-yellow-900/30 text-yellow-300',
      boarding: 'bg-blue-900/30 text-blue-300',
      departed: 'bg-emerald-900/30 text-emerald-300',
      arrived: 'bg-slate-600/30 text-slate-300',
      cancelled: 'bg-red-900/30 text-red-300',
    };

    return `
      <div class="bg-slate-800 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
          <table id="flights-table" class="min-w-full divide-y divide-slate-700/60">
            <thead class="bg-slate-800/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Flight</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Route</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Departure</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Gate</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/60">
              ${flights.map(flight => `
                <tr class="hover:bg-slate-700/30 transition-colors">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-slate-200">${this.sanitize(flight.flight_number)}</div>
                    <div class="text-sm text-slate-400">${this.sanitize(flight.airline_name || '')}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                    ${this.sanitize(flight.origin)} → ${this.sanitize(flight.destination)}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                    ${new Date(flight.scheduled_departure).toLocaleString()}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusColors[flight.status] || 'bg-slate-600/30 text-slate-300'}">
                      ${this.sanitize(flight.status)}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                    ${this.sanitize(flight.gate || 'Not assigned')}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button class="text-blue-400 hover:text-blue-300 mr-3 transition-colors">Edit</button>
                    <button class="text-red-400 hover:text-red-300 transition-colors">Delete</button>
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  private renderMapView(): string {
    return `<div id="flight-map-container" class="h-150"></div>`;
  }

  private render3DView(): string {
    return `
      <div id="flight-3d-container" class="relative rounded-xl overflow-hidden border border-slate-700/60" style="height: 650px;">
        <div class="absolute top-3 left-3 z-10 flex gap-2">
          <button id="view-plan-btn" class="px-3 py-1.5 text-xs font-medium bg-slate-800/80 text-slate-200 rounded hover:bg-slate-700 backdrop-blur-sm border border-slate-600/50" title="Plan view (top-down)">🛰 Plan</button>
          <button id="view-side-btn" class="px-3 py-1.5 text-xs font-medium bg-slate-800/80 text-slate-200 rounded hover:bg-slate-700 backdrop-blur-sm border border-slate-600/50" title="Side view">📐 Side</button>
          <button id="view-3d-btn" class="px-3 py-1.5 text-xs font-medium bg-slate-800/80 text-slate-200 rounded hover:bg-slate-700 backdrop-blur-sm border border-slate-600/50" title="3D perspective">🎯 3D</button>
          <button id="view-reset-btn" class="px-3 py-1.5 text-xs font-medium bg-slate-800/80 text-slate-200 rounded hover:bg-slate-700 backdrop-blur-sm border border-slate-600/50" title="Reset camera">🔄 Reset</button>
        </div>
        <div class="absolute top-3 right-3 z-10 flex flex-col gap-1">
          <button id="toggle-weather-btn" class="px-2 py-1 text-xs font-medium bg-slate-800/80 text-slate-200 rounded hover:bg-slate-700 backdrop-blur-sm border border-slate-600/50" title="Toggle weather">⛅ Wx</button>
          <button id="toggle-terrain-btn" class="px-2 py-1 text-xs font-medium bg-slate-800/80 text-slate-200 rounded hover:bg-slate-700 backdrop-blur-sm border border-slate-600/50" title="Toggle terrain">🗻 Terrain</button>
          <button id="toggle-paths-btn" class="px-2 py-1 text-xs font-medium bg-slate-800/80 text-slate-200 rounded hover:bg-slate-700 backdrop-blur-sm border border-slate-600/50" title="Toggle flight paths">✈️ Paths</button>
        </div>
        <div class="absolute bottom-3 left-3 z-10 flex gap-3 text-xs text-slate-400 bg-slate-800/80 backdrop-blur-sm px-3 py-2 rounded-lg border border-slate-600/50">
          <span>Flights: <strong id="flight-count-3d" class="text-slate-100">${this.cachedFlights.length}</strong></span>
          <span class="text-slate-600">|</span>
          <span><kbd class="px-1 py-0.5 bg-slate-700 rounded text-xs">1-3</kbd> Views</span>
          <span><kbd class="px-1 py-0.5 bg-slate-700 rounded text-xs">R</kbd> Reset</span>
          <span><kbd class="px-1 py-0.5 bg-slate-700 rounded text-xs">F</kbd> Focus</span>
          <span><kbd class="px-1 py-0.5 bg-slate-700 rounded text-xs">Space</kbd> Pause</span>
        </div>
        <div id="radio-comms-overlay" class="absolute bottom-16 left-3 z-10 hidden max-w-md">
          <div class="bg-slate-900/90 backdrop-blur-sm border border-slate-600/60 rounded-lg px-4 py-3 text-sm">
            <div class="text-xs text-slate-500 mb-1">📡 RADIO COMMS</div>
            <div id="radio-comms-text" class="text-emerald-400 font-mono text-xs leading-relaxed"></div>
          </div>
        </div>
        <div id="atc-3d-render-target"></div>
      </div>
    `;
  }

  setupEventListeners(): void {
    // Flight search - filter the cached flights by flight number, airline, or route
    const flightSearch = document.getElementById('flight-search') as HTMLInputElement;
    if (flightSearch) {
      flightSearch.addEventListener('input', (e) => {
        const query = (e.target as HTMLInputElement).value.toLowerCase().trim();
        const tableBody = document.querySelector('#flights-table tbody');
        if (!tableBody) return;

        if (!query) {
          tableBody.querySelectorAll('tr').forEach(tr => tr.style.display = '');
          return;
        }

        tableBody.querySelectorAll('tr').forEach(tr => {
          const text = tr.textContent?.toLowerCase() || '';
          tr.style.display = text.includes(query) ? '' : 'none';
        });
      });
    }

    // Add flight button
    const addFlightBtn = document.getElementById('add-flight-btn');
    if (addFlightBtn) {
      addFlightBtn.addEventListener('click', this.openAddFlightModal.bind(this));
    }

    // View mode toggle buttons
    document.querySelectorAll('.view-mode-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const mode = (e.currentTarget as HTMLElement).dataset.mode as ViewMode;
        if (mode && mode !== this.currentViewMode) {
          this.currentViewMode = mode;
          this.reRenderView();
        }
      });
    });

    // Initialize map if in map mode
    if (this.currentViewMode === 'map') {
      this.initMapView();
    }

    // Initialize 3D if in 3D mode
    if (this.currentViewMode === '3d') {
      this.init3DView();
    }
  }

  private async reRenderView(): Promise<void> {
    if (!this.cachedUser) return;
    const content = document.getElementById('dashboard-content');
    if (!content) return;
    content.innerHTML = await this.render(this.cachedUser);
    this.setupEventListeners();
  }

  private initMapView(): void {
    const mapContainer = document.getElementById('flight-map-container');
    if (!mapContainer) return;

    if (!this.mapView) {
      this.mapView = new FlightMapView(mapContainer);
    }

    this.mapView.render().then(html => {
      mapContainer.innerHTML = html;
      this.mapView!.setupEventListeners();
      setTimeout(() => {
        this.mapView!.initMap(this.cachedFlights);
      }, 100);
    });
  }

  private init3DView(): void {
    const renderTarget = document.getElementById('atc-3d-render-target');
    if (!renderTarget) return;

    // Clean up previous instance if any
    if (this.atc3dView) {
      this.atc3dView.destroy();
      this.atc3dView = null;
    }

    const container = document.getElementById('flight-3d-container');
    if (!container) return;

    // Create the 3D visualization
    this.atc3dView = new ATC3DVisualization({
      container: renderTarget,
      bounds: {
        north: 45,
        south: 35,
        east: -70,
        west: -80,
        minAltitude: 0,
        maxAltitude: 50000,
      },
      showTerrain: true,
      showWeather: true,
      showAirports: true,
      showFlightPaths: true,
      updateInterval: 1000,
    });

    // Load flights from API data
    this.atc3dView.loadFlightsFromData(this.cachedFlights);

    // Attach radio comms callback
    this.atc3dView.onRadioComms = (callsign: string, message: string) => {
      this.showRadioComms(callsign, message);
    };

    // Attach flight count update
    this.atc3dView.onFlightCountChange = (count: number) => {
      const el = document.getElementById('flight-count-3d');
      if (el) el.textContent = String(count);
    };

    // Set up keyboard shortcuts
    this.setup3DKeyboardShortcuts();

    // Set up 3D control buttons
    this.setup3DControlButtons();

    // Start the pause/resume button state
    const pauseBtn = document.getElementById('toggle-pause-btn');
    if (pauseBtn) {
      pauseBtn.textContent = '⏸ Pause';
    }
  }

  private setup3DKeyboardShortcuts(): void {
    const handler = (e: KeyboardEvent) => {
      if (!this.atc3dView || this.currentViewMode !== '3d') return;

      // Don't trigger if user is typing in an input
      if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return;

      switch (e.key) {
        case '1':
          this.atc3dView.setViewMode('plan');
          e.preventDefault();
          break;
        case '2':
          this.atc3dView.setViewMode('side');
          e.preventDefault();
          break;
        case '3':
          this.atc3dView.setViewMode('3d');
          e.preventDefault();
          break;
        case 'r':
        case 'R':
          this.atc3dView.resetCamera();
          e.preventDefault();
          break;
        case 'f':
        case 'F':
          this.atc3dView.focusNextAircraft();
          e.preventDefault();
          break;
        case 'w':
        case 'W':
          this.atc3dView.toggleWeather();
          e.preventDefault();
          break;
        case 't':
        case 'T':
          this.atc3dView.toggleTerrain();
          e.preventDefault();
          break;
        case 'p':
        case 'P':
          this.atc3dView.toggleFlightPaths();
          e.preventDefault();
          break;
        case ' ':
          this.atc3dView.togglePause();
          e.preventDefault();
          break;
        case 'Escape':
          this.currentViewMode = 'list';
          this.reRenderView();
          e.preventDefault();
          break;
      }
    };

    // Store reference for cleanup
    (window as any).__atc3dKeyHandler = handler;
    window.addEventListener('keydown', handler);
  }

  private setup3DControlButtons(): void {
    const bind = (id: string, fn: () => void) => {
      document.getElementById(id)?.addEventListener('click', fn);
    };

    bind('view-plan-btn', () => this.atc3dView?.setViewMode('plan'));
    bind('view-side-btn', () => this.atc3dView?.setViewMode('side'));
    bind('view-3d-btn', () => this.atc3dView?.setViewMode('3d'));
    bind('view-reset-btn', () => this.atc3dView?.resetCamera());
    bind('toggle-weather-btn', () => this.atc3dView?.toggleWeather());
    bind('toggle-terrain-btn', () => this.atc3dView?.toggleTerrain());
    bind('toggle-paths-btn', () => this.atc3dView?.toggleFlightPaths());
  }

  private showRadioComms(callsign: string, message: string): void {
    const overlay = document.getElementById('radio-comms-overlay');
    const text = document.getElementById('radio-comms-text');
    if (!overlay || !text) return;

    text.textContent = `🛩️ ${callsign}: "${message}"`;
    overlay.classList.remove('hidden');

    // Auto-hide after 5 seconds
    clearTimeout((window as any).__radioCommsTimeout);
    (window as any).__radioCommsTimeout = window.setTimeout(() => {
      overlay.classList.add('hidden');
    }, 5000);
  }

  private openAddFlightModal() {
    const modalHtml = `
      <div id="add-flight-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-slate-800 border border-slate-700/60 p-6 rounded-xl shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
          <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-slate-100">Add New Flight</h3>
            <button id="close-add-flight-modal" class="text-slate-400 hover:text-white text-2xl transition-colors">&times;</button>
          </div>
          <form id="add-flight-form">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Flight Number</label>
                <input type="text" id="flight-number" required class="w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Airline</label>
                <select id="airline" required class="w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                  <option value="">Select Airline</option>
                  <option value="1">Delta Airlines</option>
                  <option value="2">American Airlines</option>
                  <option value="3">United Airlines</option>
                  <option value="4">Southwest Airlines</option>
                  <option value="5">Alaska Airlines</option>
                </select>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-300 mb-1">Origin</label>
                  <input type="text" id="origin" placeholder="e.g., LAX" required class="w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-300 mb-1">Destination</label>
                  <input type="text" id="destination" placeholder="e.g., JFK" required class="w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-300 mb-1">Departure</label>
                  <input type="datetime-local" id="departure" required class="w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-300 mb-1">Arrival</label>
                  <input type="datetime-local" id="arrival" required class="w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-300 mb-1">Gate</label>
                  <input type="text" id="gate" class="w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-300 mb-1">Terminal</label>
                  <input type="text" id="terminal" class="w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
              </div>
              <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-blue-600 text-white py-2.5 px-4 rounded-lg hover:bg-blue-500 font-medium transition-colors">
                  Create Flight
                </button>
                <button type="button" id="cancel-add-flight" class="flex-1 bg-slate-700 text-slate-300 py-2.5 px-4 rounded-lg hover:bg-slate-600 font-medium transition-colors">
                  Cancel
                </button>
              </div>
              <div id="add-flight-error" class="mt-2 p-2 bg-red-900/30 border border-red-700 text-red-300 rounded hidden">
                Error creating flight. Please try again.
              </div>
            </div>
          </form>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Close modal
    const closeBtn = document.getElementById('close-add-flight-modal');
    const cancelBtn = document.getElementById('cancel-add-flight');
    if (closeBtn) closeBtn.addEventListener('click', () => this.closeAddFlightModal());
    if (cancelBtn) cancelBtn.addEventListener('click', () => this.closeAddFlightModal());

    // Submit form
    const form = document.getElementById('add-flight-form') as HTMLFormElement;
    if (form) {
      form.addEventListener('submit', this.handleAddFlightSubmit.bind(this));
    }
  }

  private closeAddFlightModal() {
    const modal = document.getElementById('add-flight-modal');
    if (modal) {
      modal.remove();
    }
  }

  private async handleAddFlightSubmit(e: Event) {
    e.preventDefault();

    const formData = {
      flight_number: (document.getElementById('flight-number') as HTMLInputElement)?.value || '',
      airline_id: parseInt((document.getElementById('airline') as HTMLSelectElement)?.value || '0'),
      origin: (document.getElementById('origin') as HTMLInputElement)?.value || '',
      destination: (document.getElementById('destination') as HTMLInputElement)?.value || '',
      scheduled_departure: (document.getElementById('departure') as HTMLInputElement)?.value || '',
      scheduled_arrival: (document.getElementById('arrival') as HTMLInputElement)?.value || '',
      gate: (document.getElementById('gate') as HTMLInputElement)?.value || '',
      terminal: (document.getElementById('terminal') as HTMLInputElement)?.value || '',
      status: 'scheduled'
    };

    try {
      await this.apiService.createFlight(formData);
      this.closeAddFlightModal();
      // Refresh flights list
      window.dispatchEvent(new CustomEvent('refreshFlights'));
    } catch (error) {
      console.error('Failed to create flight:', error);
      this.notifications.error('Failed to create flight. Please check the form and try again.');
    }
  }
}