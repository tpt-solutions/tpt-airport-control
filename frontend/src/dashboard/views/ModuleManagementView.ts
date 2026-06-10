import { DashboardApiService } from '../services/DashboardApiService.js';
import { AuthManager } from '../../auth.js';
import type { User } from '../types.js';

interface Module {
  module_id: number;
  module_name: string;
  display_name: string;
  description: string;
  version: string;
  category: string;
  is_enabled: boolean;
  is_core: boolean;
  dependencies: string[];
  configuration: any;
  config_schema: any;
  permissions: { [role: string]: string };
  feature_flags: Array<{
    flag_name: string;
    display_name: string;
    description: string;
    is_enabled: boolean;
    rollout_percentage: number;
  }>;
  health_status: string;
  last_check: string;
  response_time: number;
  created_at: string;
  updated_at: string;
}

interface SystemHealth {
  total_modules: number;
  enabled_modules: number;
  healthy_modules: number;
  degraded_modules: number;
  unhealthy_modules: number;
}

interface AuditLogEntry {
  audit_id: number;
  module_id: number;
  module_name: string;
  display_name: string;
  action: string;
  user_id: number;
  username: string;
  new_value: any;
  created_at: string;
}

export class ModuleManagementView {
  private container: HTMLElement;
  private apiService: DashboardApiService;
  private modules: Module[] = [];
  private systemHealth: SystemHealth | null = null;
  private auditLog: AuditLogEntry[] = [];
  private currentFilter: string = 'all';
  private currentCategory: string = 'all';

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new DashboardApiService();
  }

  async render(user: User): Promise<string> {
    // Check if user has admin permissions
    if (!user.role_name.includes('admin') && user.role_name !== 'super_admin') {
      return `
        <div class="text-center text-red-500 p-8">
          <div class="text-6xl mb-4">🔒</div>
          <h3 class="text-xl font-semibold mb-2">Access Denied</h3>
          <p class="text-gray-600">You need administrator privileges to access module management.</p>
        </div>
      `;
    }

    try {
      // Fetch initial data
      await this.loadModules();
      await this.loadSystemHealth();
      await this.loadAuditLog();

      return `
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Module Management</h2>
            <div class="flex space-x-2">
              <button id="refresh-modules" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Refresh
              </button>
              <button id="view-audit-log" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                Audit Log
              </button>
            </div>
          </div>

          <!-- System Health Overview -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">System Health</h3>
            ${this.renderSystemHealth()}
          </div>

          <!-- Filters and Controls -->
          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
              <div class="flex space-x-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Status Filter</label>
                  <select id="status-filter" class="border border-gray-300 rounded px-3 py-2">
                    <option value="all">All Modules</option>
                    <option value="enabled">Enabled Only</option>
                    <option value="disabled">Disabled Only</option>
                    <option value="core">Core Only</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                  <select id="category-filter" class="border border-gray-300 rounded px-3 py-2">
                    <option value="all">All Categories</option>
                    <option value="operations">Operations</option>
                    <option value="passenger">Passenger Services</option>
                    <option value="infrastructure">Infrastructure</option>
                    <option value="security">Security</option>
                    <option value="commercial">Commercial</option>
                  </select>
                </div>
              </div>
              <div class="text-sm text-gray-600">
                Showing ${this.getFilteredModules().length} of ${this.modules.length} modules
              </div>
            </div>
          </div>

          <!-- Modules List -->
          <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b">
              <h3 class="text-lg font-semibold">Modules</h3>
            </div>
            <div class="divide-y">
              ${this.renderModulesList()}
            </div>
          </div>

          <!-- Audit Log Modal (hidden by default) -->
          <div id="audit-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
              <div class="bg-white rounded-lg max-w-4xl w-full max-h-96 overflow-y-auto">
                <div class="p-6 border-b">
                  <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Module Audit Log</h3>
                    <button id="close-audit-modal" class="text-gray-400 hover:text-gray-600">
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                      </svg>
                    </button>
                  </div>
                </div>
                <div class="p-6">
                  ${this.renderAuditLog()}
                </div>
              </div>
            </div>
          </div>

          <!-- Module Configuration Modal (hidden by default) -->
          <div id="config-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
              <div class="bg-white rounded-lg max-w-2xl w-full max-h-96 overflow-y-auto">
                <div class="p-6 border-b">
                  <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold" id="config-modal-title">Module Configuration</h3>
                    <button id="close-config-modal" class="text-gray-400 hover:text-gray-600">
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                      </svg>
                    </button>
                  </div>
                </div>
                <div id="config-modal-content" class="p-6">
                  <!-- Configuration content will be loaded here -->
                </div>
              </div>
            </div>
          </div>
        </div>
      `;
    } catch (error) {
      console.error('Failed to load module management:', error);
      return `
        <div class="text-center text-red-500 p-8">
          <div class="text-6xl mb-4">⚠️</div>
          <h3 class="text-xl font-semibold mb-2">Failed to Load Module Management</h3>
          <p class="text-gray-600">Please check your connection and try again.</p>
          <button id="retry-modules" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Retry
          </button>
        </div>
      `;
    }
  }

  private renderSystemHealth(): string {
    if (!this.systemHealth) return '<p>Loading system health...</p>';

    const healthPercentage = this.systemHealth.total_modules > 0
      ? Math.round((this.systemHealth.healthy_modules / this.systemHealth.total_modules) * 100)
      : 0;

    return `
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="text-center p-4 border rounded">
          <div class="text-2xl font-bold text-blue-600">${this.systemHealth.total_modules}</div>
          <div class="text-sm text-gray-600">Total Modules</div>
        </div>
        <div class="text-center p-4 border rounded">
          <div class="text-2xl font-bold text-green-600">${this.systemHealth.enabled_modules}</div>
          <div class="text-sm text-gray-600">Enabled</div>
        </div>
        <div class="text-center p-4 border rounded">
          <div class="text-2xl font-bold text-green-600">${this.systemHealth.healthy_modules}</div>
          <div class="text-sm text-gray-600">Healthy</div>
        </div>
        <div class="text-center p-4 border rounded">
          <div class="text-2xl font-bold text-yellow-600">${this.systemHealth.degraded_modules}</div>
          <div class="text-sm text-gray-600">Degraded</div>
        </div>
        <div class="text-center p-4 border rounded">
          <div class="text-2xl font-bold text-red-600">${this.systemHealth.unhealthy_modules}</div>
          <div class="text-sm text-gray-600">Unhealthy</div>
        </div>
      </div>
      <div class="mt-4">
        <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
          <span>Overall System Health</span>
          <span>${healthPercentage}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
          <div class="bg-green-600 h-2 rounded-full" style="width: ${healthPercentage}%"></div>
        </div>
      </div>
    `;
  }

  private renderModulesList(): string {
    const filteredModules = this.getFilteredModules();

    if (filteredModules.length === 0) {
      return `
        <div class="p-8 text-center text-gray-500">
          <p>No modules found matching the current filters.</p>
        </div>
      `;
    }

    return filteredModules.map(module => this.renderModuleCard(module)).join('');
  }

  private renderModuleCard(module: Module): string {
    const statusClass = module.is_enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    const healthClass = this.getHealthClass(module.health_status);
    const categoryClass = this.getCategoryClass(module.category);

    return `
      <div class="p-6 hover:bg-gray-50">
        <div class="flex items-center justify-between">
          <div class="flex-1">
            <div class="flex items-center space-x-3">
              <h4 class="text-lg font-medium text-gray-900">${module.display_name}</h4>
              <span class="px-2 py-1 text-xs rounded-full ${statusClass}">
                ${module.is_enabled ? 'Enabled' : 'Disabled'}
              </span>
              ${module.is_core ? '<span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Core</span>' : ''}
              <span class="px-2 py-1 text-xs rounded-full ${healthClass}">
                ${module.health_status || 'Unknown'}
              </span>
            </div>
            <p class="text-sm text-gray-600 mt-1">${module.description}</p>
            <div class="flex items-center space-x-4 mt-2 text-xs text-gray-500">
              <span>Version: ${module.version}</span>
              <span class="capitalize ${categoryClass}">${module.category}</span>
              ${module.last_check ? `<span>Last Check: ${new Date(module.last_check).toLocaleString()}</span>` : ''}
            </div>
          </div>
          <div class="flex items-center space-x-2">
            ${!module.is_core ? `
              <button class="toggle-module-btn px-3 py-1 text-sm rounded ${
                module.is_enabled
                  ? 'bg-red-100 text-red-700 hover:bg-red-200'
                  : 'bg-green-100 text-green-700 hover:bg-green-200'
              }" data-module-id="${module.module_id}" data-action="${module.is_enabled ? 'disable' : 'enable'}">
                ${module.is_enabled ? 'Disable' : 'Enable'}
              </button>
            ` : ''}
            <button class="config-module-btn px-3 py-1 text-sm rounded bg-blue-100 text-blue-700 hover:bg-blue-200" data-module-id="${module.module_id}">
              Configure
            </button>
          </div>
        </div>
        ${module.dependencies && module.dependencies.length > 0 ? `
          <div class="mt-3 text-xs text-gray-500">
            <strong>Dependencies:</strong> ${module.dependencies.join(', ')}
          </div>
        ` : ''}
      </div>
    `;
  }

  private renderAuditLog(): string {
    if (this.auditLog.length === 0) {
      return '<p class="text-gray-500">No audit entries found.</p>';
    }

    return `
      <div class="space-y-3">
        ${this.auditLog.map(entry => `
          <div class="flex items-center justify-between p-3 border rounded">
            <div class="flex-1">
              <div class="flex items-center space-x-2">
                <span class="font-medium">${entry.display_name}</span>
                <span class="px-2 py-1 text-xs rounded-full bg-gray-100">${entry.action}</span>
              </div>
              <div class="text-sm text-gray-600 mt-1">
                by ${entry.username || 'System'} on ${new Date(entry.created_at).toLocaleString()}
              </div>
            </div>
          </div>
        `).join('')}
      </div>
    `;
  }

  private getFilteredModules(): Module[] {
    return this.modules.filter(module => {
      // Status filter
      if (this.currentFilter === 'enabled' && !module.is_enabled) return false;
      if (this.currentFilter === 'disabled' && module.is_enabled) return false;
      if (this.currentFilter === 'core' && !module.is_core) return false;

      // Category filter
      if (this.currentCategory !== 'all' && module.category !== this.currentCategory) return false;

      return true;
    });
  }

  private getHealthClass(status: string): string {
    const classes = {
      'healthy': 'bg-green-100 text-green-800',
      'degraded': 'bg-yellow-100 text-yellow-800',
      'unhealthy': 'bg-red-100 text-red-800',
      'unknown': 'bg-gray-100 text-gray-800'
    };
    return classes[status as keyof typeof classes] || classes.unknown;
  }

  private getCategoryClass(category: string): string {
    const classes = {
      'operations': 'text-blue-600',
      'passenger': 'text-green-600',
      'infrastructure': 'text-purple-600',
      'security': 'text-red-600',
      'commercial': 'text-yellow-600'
    };
    return classes[category as keyof typeof classes] || 'text-gray-600';
  }

  private async loadModules(): Promise<void> {
    try {
      const response = await fetch('/backend/api/modules', {
        headers: {
          'Authorization': `Bearer ${AuthManager.getInstance().getToken() ?? ''}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        this.modules = await response.json();
      }
    } catch (error) {
      console.error('Failed to load modules:', error);
    }
  }

  private async loadSystemHealth(): Promise<void> {
    try {
      const response = await fetch('/backend/api/modules/health', {
        headers: {
          'Authorization': `Bearer ${AuthManager.getInstance().getToken() ?? ''}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        this.systemHealth = await response.json();
      }
    } catch (error) {
      console.error('Failed to load system health:', error);
    }
  }

  private async loadAuditLog(): Promise<void> {
    try {
      const response = await fetch('/backend/api/modules/audit', {
        headers: {
          'Authorization': `Bearer ${AuthManager.getInstance().getToken() ?? ''}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        this.auditLog = await response.json();
      }
    } catch (error) {
      console.error('Failed to load audit log:', error);
    }
  }

  private async toggleModule(moduleId: number, action: 'enable' | 'disable'): Promise<void> {
    try {
      const response = await fetch(`/backend/api/modules/${moduleId}/${action}`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${AuthManager.getInstance().getToken() ?? ''}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        const result = await response.json();
        this.showSuccess(result.message);

        // Reload modules and health data
        await this.loadModules();
        await this.loadSystemHealth();
        await this.loadAuditLog();

        // Re-render the view
        this.renderModulesList();
      } else {
        const error = await response.json();
        this.showError(error.message || 'Failed to toggle module');
      }
    } catch (error) {
      console.error('Failed to toggle module:', error);
      this.showError('Failed to toggle module');
    }
  }

  private showModuleConfig(moduleId: number): void {
    const module = this.modules.find(m => m.module_id === moduleId);
    if (!module) return;

    const modal = document.getElementById('config-modal');
    const title = document.getElementById('config-modal-title');
    const content = document.getElementById('config-modal-content');

    if (modal && title && content) {
      title.textContent = `Configure ${module.display_name}`;

      content.innerHTML = `
        <div class="space-y-4">
          <div>
            <h4 class="font-medium mb-2">Basic Information</h4>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div><strong>Name:</strong> ${module.module_name}</div>
              <div><strong>Version:</strong> ${module.version}</div>
              <div><strong>Category:</strong> ${module.category}</div>
              <div><strong>Status:</strong> ${module.is_enabled ? 'Enabled' : 'Disabled'}</div>
            </div>
          </div>

          ${module.configuration ? `
            <div>
              <h4 class="font-medium mb-2">Configuration</h4>
              <pre class="bg-gray-100 p-3 rounded text-xs overflow-x-auto">${JSON.stringify(module.configuration, null, 2)}</pre>
            </div>
          ` : ''}

          ${module.permissions && Object.keys(module.permissions).length > 0 ? `
            <div>
              <h4 class="font-medium mb-2">Permissions</h4>
              <div class="space-y-2">
                ${Object.entries(module.permissions).map(([role, level]) => `
                  <div class="flex justify-between text-sm">
                    <span>${role}:</span>
                    <span>${level}</span>
                  </div>
                `).join('')}
              </div>
            </div>
          ` : ''}

          ${module.feature_flags && module.feature_flags.length > 0 ? `
            <div>
              <h4 class="font-medium mb-2">Feature Flags</h4>
              <div class="space-y-2">
                ${module.feature_flags.map(flag => `
                  <div class="flex items-center justify-between text-sm">
                    <div>
                      <div class="font-medium">${flag.display_name}</div>
                      <div class="text-gray-600 text-xs">${flag.description}</div>
                    </div>
                    <div class="flex items-center space-x-2">
                      <span class="px-2 py-1 text-xs rounded-full ${flag.is_enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                        ${flag.is_enabled ? 'Enabled' : 'Disabled'}
                      </span>
                      <span class="text-xs text-gray-500">${flag.rollout_percentage}%</span>
                    </div>
                  </div>
                `).join('')}
              </div>
            </div>
          ` : ''}

          <div class="flex justify-end space-x-2 pt-4 border-t">
            <button id="save-config-btn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
              Save Changes
            </button>
            <button id="cancel-config-btn" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
              Close
            </button>
          </div>
        </div>
      `;

      modal.classList.remove('hidden');
    }
  }

  private showSuccess(message: string): void {
    // Simple success notification
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg z-50';
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
      notification.remove();
    }, 3000);
  }

  private showError(message: string): void {
    // Simple error notification
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded shadow-lg z-50';
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
      notification.remove();
    }, 5000);
  }

  setupEventListeners(): void {
    // Refresh button
    const refreshBtn = document.getElementById('refresh-modules');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', async () => {
        await this.loadModules();
        await this.loadSystemHealth();
        await this.loadAuditLog();
        this.renderModulesList();
      });
    }

    // Audit log button
    const auditBtn = document.getElementById('view-audit-log');
    if (auditBtn) {
      auditBtn.addEventListener('click', () => {
        const modal = document.getElementById('audit-modal');
        if (modal) modal.classList.remove('hidden');
      });
    }

    // Close audit modal
    const closeAuditBtn = document.getElementById('close-audit-modal');
    if (closeAuditBtn) {
      closeAuditBtn.addEventListener('click', () => {
        const modal = document.getElementById('audit-modal');
        if (modal) modal.classList.add('hidden');
      });
    }

    // Close config modal
    const closeConfigBtn = document.getElementById('close-config-modal');
    if (closeConfigBtn) {
      closeConfigBtn.addEventListener('click', () => {
        const modal = document.getElementById('config-modal');
        if (modal) modal.classList.add('hidden');
      });
    }

    // Filter changes
    const statusFilter = document.getElementById('status-filter') as HTMLSelectElement;
    if (statusFilter) {
      statusFilter.addEventListener('change', () => {
        this.currentFilter = statusFilter.value;
        this.renderModulesList();
      });
    }

    const categoryFilter = document.getElementById('category-filter') as HTMLSelectElement;
    if (categoryFilter) {
      categoryFilter.addEventListener('change', () => {
        this.currentCategory = categoryFilter.value;
        this.renderModulesList();
      });
    }

    // Toggle module buttons
    document.addEventListener('click', (e) => {
      const target = e.target as HTMLElement;
      if (target.classList.contains('toggle-module-btn')) {
        const moduleId = parseInt(target.dataset.moduleId || '0');
        const action = target.dataset.action as 'enable' | 'disable';
        if (moduleId && action) {
          this.toggleModule(moduleId, action);
        }
      }

      if (target.classList.contains('config-module-btn')) {
        const moduleId = parseInt(target.dataset.moduleId || '0');
        if (moduleId) {
          this.showModuleConfig(moduleId);
        }
      }
    });

    // Retry button
    const retryBtn = document.getElementById('retry-modules');
    if (retryBtn) {
      retryBtn.addEventListener('click', () => {
        window.location.reload();
      });
    }
  }
}
