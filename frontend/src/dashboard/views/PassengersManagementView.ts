import { DashboardApiService } from '../services/DashboardApiService.js';
import { NotificationManager } from '../../components/NotificationManager.js';
import type { User } from '../types.js';

interface Passenger {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  passport_number: string;
  nationality: string;
  date_of_birth: string;
  flight_number?: string;
  status?: string;
}

export class PassengersManagementView {
  private apiService: DashboardApiService;
  private notifications: NotificationManager;

  constructor(_container: HTMLElement) {
    this.apiService = new DashboardApiService();
    this.notifications = new NotificationManager(document.body);
  }

  async render(_user: User): Promise<string> {
    try {
      const passengers = await this.apiService.fetchPassengers();

      return `
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Passenger Management</h2>
            <div class="flex space-x-2">
              <input type="text" id="passenger-search" placeholder="Search passengers..."
                     class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
              <button id="add-passenger-btn" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                Add Passenger
              </button>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Passport</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nationality</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Flight</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                ${passengers.map((passenger: Passenger) => `
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900">${passenger.first_name} ${passenger.last_name}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      ${passenger.email}<br>
                      ${passenger.phone}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900">${passenger.passport_number}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${passenger.nationality}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      ${passenger.flight_number || 'No flight'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <button class="text-blue-600 hover:text-blue-900 mr-3 edit-passenger" data-id="${passenger.id}">Edit</button>
                      <button class="text-red-600 hover:text-red-900 delete-passenger" data-id="${passenger.id}">Delete</button>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>

          <div class="bg-blue-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Statistics</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-2xl font-bold text-blue-600">${passengers.length}</div>
                <div class="text-gray-500">Total Passengers</div>
              </div>
              <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-2xl font-bold text-green-600">${passengers.filter((p: Passenger) => p.status === 'checked-in').length}</div>
                <div class="text-gray-500">Checked In</div>
              </div>
              <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-2xl font-bold text-yellow-600">${passengers.filter((p: Passenger) => p.status === 'boarding').length}</div>
                <div class="text-gray-500">Boarding</div>
              </div>
              <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-2xl font-bold text-purple-600">${new Set(passengers.map((p: Passenger) => p.nationality)).size}</div>
                <div class="text-gray-500">Nationalities</div>
              </div>
            </div>
          </div>
        </div>
      `;
    } catch (error) {
      console.error('Failed to load passengers:', error);
      return '<div class="text-center text-red-500 py-8">Failed to load passenger data. Please try refreshing.</div>';
    }
  }

  setupEventListeners(): void {
    // Search
    const searchInput = document.getElementById('passenger-search') as HTMLInputElement;
    if (searchInput) {
      searchInput.addEventListener('input', this.handleSearch.bind(this));
    }

    // Add passenger
    const addBtn = document.getElementById('add-passenger-btn');
    if (addBtn) {
      addBtn.addEventListener('click', this.openAddModal.bind(this));
    }

    // Edit/Delete buttons
    document.querySelectorAll('.edit-passenger, .delete-passenger').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const id = (e.target as HTMLElement).dataset.id;
        if (id) {
          if ((e.target as HTMLElement).classList.contains('edit-passenger')) {
            this.editPassenger(Number(id));
          } else {
            this.deletePassenger(Number(id));
          }
        }
      });
    });
  }

  private async handleSearch(e: Event): Promise<void> {
    const query = (e.target as HTMLInputElement).value;
    if (query.length > 2) {
      // Trigger search API call
      window.dispatchEvent(new CustomEvent('searchPassengers', { detail: query }));
    }
  }

  private openAddModal(): void {
    window.dispatchEvent(new CustomEvent('openAddPassengerModal'));
  }

  private async editPassenger(id: number): Promise<void> {
    window.dispatchEvent(new CustomEvent('openEditPassengerModal', { detail: { id } }));
  }

  private async deletePassenger(id: number): Promise<void> {
    if (!confirm('Are you sure you want to delete this passenger?')) return;
    try {
      await this.apiService.callApi('/api/passengers/' + id, 'DELETE');
      this.notifications.success('Passenger deleted successfully.');
      window.dispatchEvent(new CustomEvent('refreshPassengers'));
    } catch (error) {
      console.error('Failed to delete passenger:', error);
      this.notifications.error('Failed to delete passenger. They may have active bookings.');
    }
  }
}

