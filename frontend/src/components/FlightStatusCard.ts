// Flight Status Display Component
export class FlightStatusCard {
  private container: HTMLElement;

  constructor(container: HTMLElement) {
    this.container = container;
  }

  render(flight: any) {
    const statusColor = this.getStatusColor(flight.status);
    const statusText = this.getStatusText(flight.status);

    this.container.innerHTML = `
      <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 ${statusColor} rounded-full flex items-center justify-center text-white font-bold text-sm">
              ✈️
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-900">${flight.flight_number}</h3>
              <p class="text-sm text-gray-600">${flight.airline_name}</p>
            </div>
          </div>
          <span class="px-3 py-1 rounded-full text-sm font-medium ${statusColor.replace('bg-', 'bg-').replace('-500', '-100').replace('-600', '-800')} text-${statusColor.split('-')[1]}-800">
            ${statusText}
          </span>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <p class="text-sm text-gray-500">From</p>
            <p class="font-medium">${flight.origin}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">To</p>
            <p class="font-medium">${flight.destination}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Departure</p>
            <p class="font-medium">${new Date(flight.scheduled_departure).toLocaleString()}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Gate</p>
            <p class="font-medium">${flight.gate || 'Not assigned'}</p>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4 text-sm text-gray-600">
            <span>🛫 ${flight.origin}</span>
            <span>🛬 ${flight.destination}</span>
          </div>
          <div class="flex space-x-2">
            <button class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors">
              View Details
            </button>
            <button class="px-3 py-1 text-sm bg-green-100 text-green-700 rounded hover:bg-green-200 transition-colors">
              Update Status
            </button>
          </div>
        </div>
      </div>
    `;
  }

  private getStatusColor(status: string): string {
    switch (status.toLowerCase()) {
      case 'scheduled': return 'bg-yellow-500';
      case 'boarding': return 'bg-blue-500';
      case 'departed': return 'bg-green-500';
      case 'arrived': return 'bg-gray-500';
      case 'delayed': return 'bg-orange-500';
      case 'cancelled': return 'bg-red-500';
      default: return 'bg-gray-500';
    }
  }

  private getStatusText(status: string): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
  }
}
