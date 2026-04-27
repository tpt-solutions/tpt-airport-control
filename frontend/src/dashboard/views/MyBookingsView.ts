import { DashboardApiService } from '../services/DashboardApiService.js';
import type { Booking, User } from '../types.js';

export class MyBookingsView {
  private container: HTMLElement;
  private apiService: DashboardApiService;

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new DashboardApiService();
  }

  async render(user: User): Promise<string> {
    try {
      const bookings = await this.apiService.fetchUserBookings(user.id);

      return `
        <div class="space-y-6">
          <h2 class="text-2xl font-bold text-gray-900">My Bookings</h2>

          <div class="grid gap-6">
            ${bookings.map((booking: Booking) => `
              <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                  <div>
                    <h3 class="text-lg font-semibold">${booking.flight_number}</h3>
                    <p class="text-gray-600">${booking.origin} → ${booking.destination}</p>
                  </div>
                  <span class="px-3 py-1 rounded-full text-sm font-medium ${
                    booking.status === 'confirmed' ? 'bg-green-100 text-green-800' :
                    booking.status === 'checked-in' ? 'bg-blue-100 text-blue-800' :
                    'bg-red-100 text-red-800'
                  }">
                    ${booking.status}
                  </span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                  <div>
                    <p class="text-sm text-gray-500">Departure</p>
                    <p class="font-medium">${new Date(booking.scheduled_departure).toLocaleString()}</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-500">Booking Reference</p>
                    <p class="font-medium">${booking.booking_reference}</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-500">Seat</p>
                    <p class="font-medium">${booking.seat_number || 'Not assigned'}</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-500">Actions</p>
                    <div class="space-x-2">
                      <button class="text-blue-600 hover:text-blue-800 text-sm">View Details</button>
                      ${booking.status === 'confirmed' ? '<button class="text-green-600 hover:text-green-800 text-sm">Check-in</button>' : ''}
                    </div>
                  </div>
                </div>
              </div>
            `).join('')}

            ${bookings.length === 0 ? '<div class="text-center text-gray-500 py-8">No bookings found</div>' : ''}
          </div>
        </div>
      `;
    } catch (error) {
      console.error('Failed to load bookings:', error);
      return '<div class="text-center text-red-500">Failed to load booking data</div>';
    }
  }

  setupEventListeners(): void {
    // Add event listeners for booking actions if needed
    // This would be expanded based on specific requirements
  }
}
