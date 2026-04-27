import { DashboardApiService } from '../services/DashboardApiService.js';
import type { DashboardConfig } from '../../dashboard.js';

export class EmergencyManagementView {
  private apiService: DashboardApiService;
  private config: DashboardConfig;
  private container: HTMLElement | null = null;
  private refreshInterval: number | null = null;

  constructor(apiService: DashboardApiService, config: DashboardConfig) {
    this.apiService = apiService;
    this.config = config;
  }

  async render(): Promise<string> {
    return `
      <div class="emergency-management-view">
        <div class="mb-6">
          <h1 class="text-2xl font-bold text-gray-900">Emergency Management</h1>
          <p class="text-gray-600">Monitor incidents, activate protocols, and manage crisis response</p>
        </div>

        <!-- Dashboard Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" id="emergency-stats">
          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-red-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Active Incidents</p>
                <p class="text-2xl font-semibold text-gray-900" id="active-incidents">0</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Critical Incidents</p>
                <p class="text-2xl font-semibold text-gray-900" id="critical-incidents">0</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Response Teams</p>
                <p class="text-2xl font-semibold text-gray-900" id="response-teams">0</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Resolved Today</p>
                <p class="text-2xl font-semibold text-gray-900" id="resolved-today">0</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-6">
          <div class="flex flex-wrap gap-3">
            <button id="report-incident" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
              Report Incident
            </button>
            <button id="activate-protocol" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
              </svg>
              Activate Protocol
            </button>
            <button id="manage-teams" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
              </svg>
              Manage Teams
            </button>
            <button id="emergency-reports" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
              <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              Emergency Reports
            </button>
          </div>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Active Incidents -->
          <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
              <h3 class="text-lg font-medium text-gray-900">Active Incidents</h3>
            </div>
            <div class="p-6" id="active-incidents-list">
              <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-red-600 mx-auto"></div>
                <p class="mt-2 text-gray-600">Loading incidents...</p>
              </div>
            </div>
          </div>

          <!-- Response Teams Status -->
          <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
              <h3 class="text-lg font-medium text-gray-900">Response Teams</h3>
            </div>
            <div class="p-6" id="response-teams-status">
              <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="mt-2 text-gray-600">Loading teams...</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Emergency Protocols -->
        <div class="mt-6 bg-white rounded-lg shadow">
          <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Emergency Protocols</h3>
          </div>
          <div class="p-6" id="emergency-protocols">
            <div class="text-center py-8">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-orange-600 mx-auto"></div>
              <p class="mt-2 text-gray-600">Loading protocols...</p>
            </div>
          </div>
        </div>

        <!-- Recent Communications -->
        <div class="mt-6 bg-white rounded-lg shadow">
          <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Recent Communications</h3>
          </div>
          <div class="p-6" id="recent-communications">
            <div class="text-center py-8">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600 mx-auto"></div>
              <p class="mt-2 text-gray-600">Loading communications...</p>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  async init(): Promise<void> {
    this.container = document.querySelector('.emergency-management-view') as HTMLElement;

    if (!this.container) {
      throw new Error('Emergency management view container not found');
    }

    // Setup event listeners
    this.setupEventListeners();

    // Load initial data
    await this.loadDashboardData();

    // Set up auto-refresh
    this.refreshInterval = window.setInterval(() => {
      this.loadDashboardData();
    }, 30000); // Refresh every 30 seconds
  }

  private setupEventListeners(): void {
    if (!this.container) return;

    // Report incident button
    const reportBtn = this.container.querySelector('#report-incident') as HTMLButtonElement;
    if (reportBtn) {
      reportBtn.addEventListener('click', () => this.showReportIncidentModal());
    }

    // Activate protocol button
    const protocolBtn = this.container.querySelector('#activate-protocol') as HTMLButtonElement;
    if (protocolBtn) {
      protocolBtn.addEventListener('click', () => this.showActivateProtocolView());
    }

    // Manage teams button
    const teamsBtn = this.container.querySelector('#manage-teams') as HTMLButtonElement;
    if (teamsBtn) {
      teamsBtn.addEventListener('click', () => this.showManageTeamsView());
    }

    // Emergency reports button
    const reportsBtn = this.container.querySelector('#emergency-reports') as HTMLButtonElement;
    if (reportsBtn) {
      reportsBtn.addEventListener('click', () => this.showEmergencyReportsView());
    }
  }

  private async loadDashboardData(): Promise<void> {
    try {
      // Load emergency dashboard data
      const dashboardData = await this.apiService.callApi('/api/emergency', 'GET');

      // Update stats
      this.updateStats(dashboardData);

      // Load active incidents
      await this.loadActiveIncidents();

      // Load response teams
      await this.loadResponseTeams();

      // Load emergency protocols
      await this.loadEmergencyProtocols();

      // Load recent communications
      await this.loadRecentCommunications();

    } catch (error) {
      console.error('Error loading emergency dashboard data:', error);
      this.showError('Failed to load dashboard data');
    }
  }

  private updateStats(data: any): void {
    const activeEl = this.container?.querySelector('#active-incidents');
    const criticalEl = this.container?.querySelector('#critical-incidents');
    const teamsEl = this.container?.querySelector('#response-teams');
    const resolvedEl = this.container?.querySelector('#resolved-today');

    if (activeEl) activeEl.textContent = data.active_incidents || '0';
    if (criticalEl) criticalEl.textContent = data.critical_incidents || '0';
    if (teamsEl) teamsEl.textContent = data.available_teams || '0';
    if (resolvedEl) resolvedEl.textContent = data.resolved_today || '0';
  }

  private async loadActiveIncidents(): Promise<void> {
    try {
      const incidentsData = await this.apiService.callApi('/api/emergency/incidents', 'GET');

      const container = this.container?.querySelector('#active-incidents-list');
      if (!container) return;

      if (!incidentsData || incidentsData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No active incidents</p>';
        return;
      }

      const incidentsHTML = incidentsData.slice(0, 5).map((incident: any) => `
        <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-b-0">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                ${this.getSeverityColor(incident.severity_level)}">
                ${incident.severity_level || 'low'}
              </span>
            </div>
            <div class="ml-4">
              <p class="font-medium text-gray-900">${incident.incident_number}</p>
              <p class="text-sm text-gray-600">${incident.incident_type} - ${incident.location}</p>
            </div>
          </div>
          <div class="text-right">
            <p class="text-sm font-medium text-gray-900">${incident.incident_status}</p>
            <p class="text-xs text-gray-500">${new Date(incident.reported_at).toLocaleString()}</p>
          </div>
        </div>
      `).join('');

      container.innerHTML = incidentsHTML;

    } catch (error) {
      console.error('Error loading active incidents:', error);
      const container = this.container?.querySelector('#active-incidents-list');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load incidents</p>';
      }
    }
  }

  private async loadResponseTeams(): Promise<void> {
    try {
      const teamsData = await this.apiService.callApi('/api/emergency/teams', 'GET');

      const container = this.container?.querySelector('#response-teams-status');
      if (!container) return;

      if (!teamsData || teamsData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No response teams available</p>';
        return;
      }

      const teamsHTML = teamsData.slice(0, 4).map((team: any) => `
        <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-b-0">
          <div>
            <p class="font-medium text-gray-900">${team.team_name}</p>
            <p class="text-sm text-gray-600">${team.team_type}</p>
          </div>
          <div class="text-right">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
              ${team.status === 'available' ? 'bg-green-100 text-green-800' :
                team.status === 'deployed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'}">
              ${team.status || 'Unknown'}
            </span>
            <p class="text-xs text-gray-500 mt-1">${team.active_incidents || 0} active</p>
          </div>
        </div>
      `).join('');

      container.innerHTML = teamsHTML;

    } catch (error) {
      console.error('Error loading response teams:', error);
      const container = this.container?.querySelector('#response-teams-status');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load teams</p>';
      }
    }
  }

  private async loadEmergencyProtocols(): Promise<void> {
    try {
      const protocolsData = await this.apiService.callApi('/api/emergency/protocols', 'GET');

      const container = this.container?.querySelector('#emergency-protocols');
      if (!container) return;

      if (!protocolsData || protocolsData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No emergency protocols available</p>';
        return;
      }

      const protocolsHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          ${protocolsData.slice(0, 6).map((protocol: any) => `
            <div class="p-4 bg-gray-50 rounded-lg">
              <div class="flex items-center justify-between mb-2">
                <h4 class="font-medium text-gray-900">${protocol.protocol_name}</h4>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                  ${protocol.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                  ${protocol.status}
                </span>
              </div>
              <p class="text-sm text-gray-600 mb-2">${protocol.protocol_type}</p>
              <p class="text-xs text-gray-500">Severity: ${protocol.severity_level}</p>
            </div>
          `).join('')}
        </div>
      `;

      container.innerHTML = protocolsHTML;

    } catch (error) {
      console.error('Error loading emergency protocols:', error);
      const container = this.container?.querySelector('#emergency-protocols');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load protocols</p>';
      }
    }
  }

  private async loadRecentCommunications(): Promise<void> {
    try {
      const commsData = await this.apiService.callApi('/api/emergency/communications', 'GET');

      const container = this.container?.querySelector('#recent-communications');
      if (!container) return;

      if (!commsData || commsData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No recent communications</p>';
        return;
      }

      const commsHTML = commsData.slice(0, 5).map((comm: any) => `
        <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-b-0">
          <div>
            <p class="font-medium text-gray-900">${comm.sender}</p>
            <p class="text-sm text-gray-600">${comm.message_type} - ${comm.communication_channel}</p>
          </div>
          <div class="text-right">
            <p class="text-sm text-gray-900">${comm.message_content?.substring(0, 50)}${comm.message_content?.length > 50 ? '...' : ''}</p>
            <p class="text-xs text-gray-500">${new Date(comm.sent_at).toLocaleString()}</p>
          </div>
        </div>
      `).join('');

      container.innerHTML = commsHTML;

    } catch (error) {
      console.error('Error loading recent communications:', error);
      const container = this.container?.querySelector('#recent-communications');
      if (container) {
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load communications</p>';
      }
    }
  }

  private getSeverityColor(severity: string): string {
    const colors: { [key: string]: string } = {
      'critical': 'bg-red-100 text-red-800',
      'high': 'bg-orange-100 text-orange-800',
      'medium': 'bg-yellow-100 text-yellow-800',
      'low': 'bg-blue-100 text-blue-800'
    };
    return colors[severity] || 'bg-gray-100 text-gray-800';
  }

  private showReportIncidentModal(): void {
    // Implementation for report incident modal
    console.log('Show report incident modal');
  }

  private showActivateProtocolView(): void {
    // Implementation for activate protocol view
    console.log('Show activate protocol view');
  }

  private showManageTeamsView(): void {
    // Implementation for manage teams view
    console.log('Show manage teams view');
  }

  private showEmergencyReportsView(): void {
    // Implementation for emergency reports view
    console.log('Show emergency reports view');
  }

  private showError(message: string): void {
    const errorHTML = `
      <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-red-800">Error</h3>
            <p class="mt-1 text-sm text-red-700">${message}</p>
          </div>
        </div>
      </div>
    `;

    const mainContent = document.getElementById('main-content');
    if (mainContent) {
      mainContent.innerHTML = errorHTML;
    }
  }

  destroy(): void {
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }

    // Clean up event listeners
    if (this.container) {
      const buttons = this.container.querySelectorAll('button');
      buttons.forEach(button => {
        button.removeEventListener('click', () => {});
      });
    }
  }
}
