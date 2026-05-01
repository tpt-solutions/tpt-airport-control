import { AuthManager } from '../../auth.js';
import { ThemeManager, THEMES } from '../../services/ThemeManager.js';
import type { User } from '../types.js';

// Default fallback user when no user is authenticated
const defaultUser: User = {
  id: 0,
  username: 'guest',
  email: '',
  first_name: 'Guest',
  last_name: 'User',
  role_name: 'guest'
};

export class DashboardHeader {
  private container: HTMLElement;
  private auth: AuthManager;
  private themeManager: ThemeManager;

  constructor(container: HTMLElement) {
    this.container = container;
    this.auth = AuthManager.getInstance();
    this.themeManager = ThemeManager.getInstance();
  }

  render(user?: User | null): string {
    // Use default user if user is undefined/null to prevent crashes
    const currentUser = (!user || typeof user.role_name === 'undefined') ? defaultUser : user;
    
    const roleLabels: Record<string, string> = {
      admin: 'Admin', operator: 'Operator', passenger: 'Passenger', guest: 'Guest'
    };
    const current = this.themeManager.getTheme();

    const themeDots = THEMES.map(t => `
      <button
        class="fc-theme-dot ${t.dotClass} ${current === t.id ? 'fc-dot-active' : ''}"
        data-theme-id="${t.id}"
        title="${t.label}"
        aria-label="Switch to ${t.label} theme">
      </button>
    `).join('');

    return `
      <header class="fc-header px-5 py-3 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center shrink-0">
            <span class="text-white text-xs font-bold tracking-tight">FC</span>
          </div>
          <div class="hidden sm:flex flex-col leading-tight">
            <span class="text-sm font-semibold fc-text-primary tracking-tight">Flight Control</span>
            <span class="text-xs fc-text-muted">Operations Dashboard</span>
          </div>
        </div>

        <div class="flex items-center gap-4">
          <!-- Theme switcher -->
          <div class="flex items-center gap-1.5" title="Switch theme">
            ${themeDots}
          </div>

          <div class="w-px h-4 fc-divider border-l"></div>

          <!-- Role badge -->
          <span class="fc-accent-bg text-xs font-semibold px-2.5 py-1 uppercase tracking-wider">
            ${roleLabels[currentUser.role_name] ?? currentUser.role_name}
          </span>

          <!-- User avatar + name -->
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-slate-600 to-slate-700 border fc-divider flex items-center justify-center fc-text-secondary text-xs font-semibold shrink-0">
              ${currentUser.first_name.charAt(0)}${currentUser.last_name.charAt(0)}
            </div>
            <span class="hidden md:block text-sm fc-text-secondary">${currentUser.first_name} ${currentUser.last_name}</span>
          </div>

          <button id="logout-btn"
            class="text-xs fc-text-muted hover:text-red-400 transition-colors px-2 py-1 rounded hover:bg-red-400/10">
            Sign out
          </button>
        </div>
      </header>
    `;
  }

  setupEventListeners(): void {
    document.getElementById('logout-btn')?.addEventListener('click', () => {
      this.auth.logout();
    });

    document.querySelectorAll<HTMLButtonElement>('[data-theme-id]').forEach(btn => {
      btn.addEventListener('click', () => {
        const themeId = btn.dataset.themeId as any;
        if (themeId) {
          this.themeManager.setTheme(themeId);
          // Re-render dots to reflect new active state
          document.querySelectorAll<HTMLButtonElement>('[data-theme-id]').forEach(b => {
            b.classList.toggle('fc-dot-active', b.dataset.themeId === themeId);
          });
        }
      });
    });
  }
}
