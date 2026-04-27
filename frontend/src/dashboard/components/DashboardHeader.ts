import { AuthManager } from '../../auth.js';
import type { User } from '../types.js';

export class DashboardHeader {
  private container: HTMLElement;
  private auth: AuthManager;

  constructor(container: HTMLElement) {
    this.container = container;
    this.auth = AuthManager.getInstance();
  }

  render(user: User): string {
    return `
      <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex justify-between items-center py-4">
            <div class="flex items-center">
              <h1 class="text-2xl font-bold text-gray-900">Flight Control Dashboard</h1>
              <span class="ml-4 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                ${user.role_name}
              </span>
            </div>
            <div class="flex items-center space-x-4">
              <span class="text-sm text-gray-700">Welcome, ${user.first_name} ${user.last_name}</span>
              <button id="logout-btn" class="text-sm text-red-600 hover:text-red-800">Logout</button>
            </div>
          </div>
        </div>
      </header>
    `;
  }

  setupEventListeners(): void {
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', () => {
        this.auth.logout();
      });
    }
  }
}
