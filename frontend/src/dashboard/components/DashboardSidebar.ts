import type { DashboardView, MenuItem, User } from '../types.js';

export class DashboardSidebar {
  private container: HTMLElement;
  private currentView: DashboardView;
  private onViewChange: (view: DashboardView) => void;

  constructor(container: HTMLElement, currentView: DashboardView, onViewChange: (view: DashboardView) => void) {
    this.container = container;
    this.currentView = currentView;
    this.onViewChange = onViewChange;
  }

  render(user: User): string {
    const menuItems = this.getMenuItems(user.role_name);

    return `
      <nav class="bg-white w-64 min-h-screen shadow-sm">
        <div class="p-4">
          <ul class="space-y-2">
            ${menuItems.map(item => `
              <li>
                <button id="${item.id}-btn" class="w-full text-left px-4 py-2 text-sm font-medium rounded-md hover:bg-gray-100 transition-colors ${
                  this.currentView === item.id ? 'bg-blue-100 text-blue-700' : 'text-gray-700'
                }">
                  ${item.icon} ${item.label}
                </button>
              </li>
            `).join('')}
          </ul>
        </div>
      </nav>
    `;
  }

  private getMenuItems(role: string): MenuItem[] {
    const baseItems: MenuItem[] = [
      { id: 'overview', label: 'Overview', icon: '📊' }
    ];

    if (role === 'admin' || role === 'operator') {
      baseItems.push(
        { id: 'flights', label: 'Flight Management', icon: '✈️' },
        { id: 'passengers', label: 'Passenger Services', icon: '👥' },
        { id: 'infrastructure', label: 'Infrastructure', icon: '🏗️' },
        { id: 'drones', label: 'Drone Operations', icon: '🚁' },
        { id: 'customs', label: 'Customs & Border', icon: '🛂' },
        { id: 'advanced-security', label: 'Advanced Security', icon: '🔐' },
        { id: 'ai-conflict-prediction', label: 'AI Conflict Prediction', icon: '🤖' },
        { id: 'virtual-assistant', label: 'Virtual Assistant', icon: '🎙️' },
        { id: 'module-management', label: 'Module Management', icon: '⚙️' },
        { id: 'maintenance', label: 'Maintenance', icon: '🔧' },
        { id: 'security', label: 'Security', icon: '🔒' }
      );
    }

    if (role === 'passenger') {
      baseItems.push(
        { id: 'my-bookings', label: 'My Bookings', icon: '🎫' },
        { id: 'my-baggage', label: 'My Baggage', icon: '🧳' }
      );
    }

    return baseItems;
  }

  setupEventListeners(): void {
    const menuItems = ['overview', 'flights', 'passengers', 'infrastructure', 'drones', 'customs', 'advanced-security', 'ai-conflict-prediction', 'virtual-assistant', 'module-management', 'maintenance', 'security', 'my-bookings', 'my-baggage'];
    menuItems.forEach(item => {
      const btn = document.getElementById(`${item}-btn`);
      if (btn) {
        btn.addEventListener('click', () => {
          this.currentView = item as DashboardView;
          this.onViewChange(this.currentView);
        });
      }
    });
  }

  updateCurrentView(view: DashboardView): void {
    this.currentView = view;
  }
}
