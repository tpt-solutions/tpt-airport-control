// Booking Form Component
export class BookingForm {
  private container: HTMLElement;
  private onSubmit: (data: any) => void;

  constructor(container: HTMLElement, onSubmit: (data: any) => void) {
    this.container = container;
    this.onSubmit = onSubmit;
  }

  render() {
    this.container.innerHTML = `
      <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Create New Booking</h2>

        <form id="booking-form" class="space-y-6">
          <!-- Passenger Selection -->
          <div>
            <label for="passenger_id" class="block text-sm font-medium text-gray-700 mb-2">
              Select Passenger
            </label>
            <select id="passenger_id" name="passenger_id" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
              <option value="">Choose a passenger...</option>
            </select>
          </div>

          <!-- Flight Selection -->
          <div>
            <label for="flight_id" class="block text-sm font-medium text-gray-700 mb-2">
              Select Flight
            </label>
            <select id="flight_id" name="flight_id" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
              <option value="">Choose a flight...</option>
            </select>
          </div>

          <!-- Seat Selection -->
          <div>
            <label for="seat_number" class="block text-sm font-medium text-gray-700 mb-2">
              Seat Number (Optional)
            </label>
            <input type="text" id="seat_number" name="seat_number"
                   placeholder="e.g., 12A"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
          </div>

          <!-- Booking Details -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label for="total_amount" class="block text-sm font-medium text-gray-700 mb-2">
                Total Amount
              </label>
              <input type="number" id="total_amount" name="total_amount" step="0.01" min="0"
                     class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
              <label for="currency" class="block text-sm font-medium text-gray-700 mb-2">
                Currency
              </label>
              <select id="currency" name="currency"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="GBP">GBP</option>
              </select>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="flex justify-end space-x-3">
            <button type="button" id="cancel-booking"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
              Cancel
            </button>
            <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
              <span id="booking-submit-text">Create Booking</span>
              <div id="booking-spinner" class="hidden ml-2 inline-block">
                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
              </div>
            </button>
          </div>
        </form>

        <div id="booking-message" class="mt-4 hidden">
          <div id="booking-message-content" class="text-sm"></div>
        </div>
      </div>
    `;

    this.setupEventListeners();
    this.loadPassengers();
    this.loadFlights();
  }

  private setupEventListeners() {
    const form = document.getElementById('booking-form') as HTMLFormElement;
    const cancelBtn = document.getElementById('cancel-booking');

    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      await this.handleSubmit();
    });

    cancelBtn?.addEventListener('click', () => {
      this.container.innerHTML = '';
    });
  }

  private async loadPassengers() {
    try {
      const response = await fetch('/api/passengers.php?page=1&limit=100');
      const data = await response.json();

      const select = document.getElementById('passenger_id') as HTMLSelectElement;
      if (select && data.passengers) {
        data.passengers.forEach((passenger: any) => {
          const option = document.createElement('option');
          option.value = passenger.id;
          option.textContent = `${passenger.first_name} ${passenger.last_name} (${passenger.email})`;
          select.appendChild(option);
        });
      }
    } catch (error) {
      console.error('Failed to load passengers:', error);
    }
  }

  private async loadFlights() {
    try {
      const response = await fetch('/api/flights.php?action=list&page=1&limit=100');
      const data = await response.json();

      const select = document.getElementById('flight_id') as HTMLSelectElement;
      if (select && data.flights) {
        data.flights.forEach((flight: any) => {
          const option = document.createElement('option');
          option.value = flight.id;
          option.textContent = `${flight.flight_number} - ${flight.origin} → ${flight.destination} (${new Date(flight.scheduled_departure).toLocaleDateString()})`;
          select.appendChild(option);
        });
      }
    } catch (error) {
      console.error('Failed to load flights:', error);
    }
  }

  private async handleSubmit() {
    const formData = new FormData(document.getElementById('booking-form') as HTMLFormElement);
    const submitText = document.getElementById('booking-submit-text');
    const spinner = document.getElementById('booking-spinner');

    if (!submitText || !spinner) return;

    // Show loading state
    submitText.textContent = 'Creating...';
    spinner.classList.remove('hidden');
    this.disableForm(true);

    try {
      const bookingData = {
        passenger_id: formData.get('passenger_id'),
        flight_id: formData.get('flight_id'),
        seat_number: formData.get('seat_number') || null,
        total_amount: formData.get('total_amount') ? parseFloat(formData.get('total_amount') as string) : null,
        currency: formData.get('currency') || 'USD'
      };

      const response = await fetch('/api/bookings.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(bookingData),
      });

      const data = await response.json();

      if (data.success !== false) {
        this.showMessage('Booking created successfully!', 'success');
        setTimeout(() => {
          this.container.innerHTML = '';
          // Trigger refresh of booking list if it exists
          window.dispatchEvent(new CustomEvent('bookingCreated'));
        }, 2000);
      } else {
        this.showMessage(data.message || 'Failed to create booking', 'error');
      }
    } catch (error) {
      console.error('Booking creation error:', error);
      this.showMessage('An error occurred. Please try again.', 'error');
    } finally {
      // Reset loading state
      submitText.textContent = 'Create Booking';
      spinner.classList.add('hidden');
      this.disableForm(false);
    }
  }

  private showMessage(message: string, type: 'success' | 'error') {
    const container = document.getElementById('booking-message');
    const content = document.getElementById('booking-message-content');

    if (!container || !content) return;

    content.textContent = message;
    content.className = `text-sm ${type === 'success' ? 'text-green-600' : 'text-red-600'}`;
    container.classList.remove('hidden');
  }

  private disableForm(disabled: boolean) {
    const form = document.getElementById('booking-form') as HTMLFormElement;
    if (!form) return;

    const inputs = form.querySelectorAll('input, select, button');
    inputs.forEach(input => {
      (input as HTMLInputElement).disabled = disabled;
    });
  }
}
