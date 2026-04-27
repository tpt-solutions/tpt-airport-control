import { DashboardApiService } from '../services/DashboardApiService.js';
import type { Flight, User } from '../types.js';

export class FlightsManagementView {
  private container: HTMLElement;
  private apiService: DashboardApiService;

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new DashboardApiService();
  }

  async render(user: User): Promise<string> {
    try {
      const flights = await this.apiService.fetchFlights();

      return `
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Flight Management</h2>
            <div class="flex space-x-2">
              <input type="text" id="flight-search" placeholder="Search flights..."
                     class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
              <button id="add-flight-btn" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                Add Flight
              </button>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Flight</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departure</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gate</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                ${flights.map(flight => `
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900">${flight.flight_number}</div>
                      <div class="text-sm text-gray-500">${flight.airline_name}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      ${flight.origin} → ${flight.destination}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      ${new Date(flight.scheduled_departure).toLocaleString()}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                        flight.status === 'scheduled' ? 'bg-yellow-100 text-yellow-800' :
                        flight.status === 'boarding' ? 'bg-blue-100 text-blue-800' :
                        flight.status === 'departed' ? 'bg-green-100 text-green-800' :
                        flight.status === 'arrived' ? 'bg-gray-100 text-gray-800' :
                        'bg-red-100 text-red-800'
                      }">
                        ${flight.status}
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      ${flight.gate || 'Not assigned'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <button class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                      <button class="text-red-600 hover:text-red-900">Delete</button>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;
    } catch (error) {
      console.error('Failed to load flights:', error);
      return '<div class="text-center text-red-500">Failed to load flight data</div>';
    }
  }

  setupEventListeners(): void {
    // Flight search
    const flightSearch = document.getElementById('flight-search') as HTMLInputElement;
    if (flightSearch) {
      flightSearch.addEventListener('input', (e) => {
        // Implement search functionality
        console.log('Search:', (e.target as HTMLInputElement).value);
      });
    }

    // Add flight button
    const addFlightBtn = document.getElementById('add-flight-btn');
    if (addFlightBtn) {
      addFlightBtn.addEventListener('click', () => {
        // Implement add flight modal
        console.log('Add flight clicked');
      });
    }
  }
}
