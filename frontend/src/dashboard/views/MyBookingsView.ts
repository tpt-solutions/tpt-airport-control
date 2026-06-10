import { DashboardApiService } from '../services/DashboardApiService.js';
import type { Booking, User } from '../types.js';

export class MyBookingsView {
  private apiService: DashboardApiService;

  constructor(_container: HTMLElement) {
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
                      <button class="booking-details-btn text-blue-600 hover:text-blue-800 text-sm" data-booking-id="${booking.id}">View Details</button>
                      ${booking.status === 'confirmed' ? `<button class="booking-checkin-btn text-green-600 hover:text-green-800 text-sm" data-booking-id="${booking.id}">Check-in</button>` : ''}
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
    document.querySelectorAll('.booking-details-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const id = (e.currentTarget as HTMLElement).dataset.bookingId;
        if (id) this.showBookingDetails(Number(id));
      });
    });

    document.querySelectorAll('.booking-checkin-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const id = (e.currentTarget as HTMLElement).dataset.bookingId;
        if (id) this.checkIn(Number(id));
      });
    });
  }

  private showBookingDetails(bookingId: number): void {
    window.dispatchEvent(new CustomEvent('showBookingDetails', { detail: { bookingId } }));
  }

  private async checkIn(bookingId: number): Promise<void> {
    try {
      await this.apiService.callApi(`/api/bookings/${bookingId}/check-in`, 'POST');
      window.dispatchEvent(new CustomEvent('refreshOverview'));
    } catch (error) {
      console.error('Check-in failed:', error);
    }
  }
}
