import { AuthManager } from '../../auth.js';
import type { DashboardStats, Flight, Booking } from '../types.js';

export class DashboardApiService {
  private auth: AuthManager;

  constructor() {
    this.auth = AuthManager.getInstance();
  }

  async fetchDashboardStats(): Promise<DashboardStats> {
    try {
      const response = await this.auth.authenticatedFetch('/api/analytics.php?action=stats');
      const data = await response.json();
      return data.stats || {
        total_flights: 0,
        active_flights: 0,
        total_passengers: 0,
        checked_in_passengers: 0,
        total_bookings: 0,
        pending_maintenance: 0,
        security_alerts: 0,
        system_health: 'healthy'
      };
    } catch (error) {
      console.error('Failed to fetch dashboard stats:', error);
      throw error;
    }
  }

  async fetchFlights(): Promise<Flight[]> {
    try {
      const response = await this.auth.authenticatedFetch('/api/flights.php?action=list&page=1&limit=20');
      const data = await response.json();
      return data.flights || [];
    } catch (error) {
      console.error('Failed to fetch flights:', error);
      throw error;
    }
  }

  async fetchUserBookings(userId: number): Promise<Booking[]> {
    try {
      const response = await this.auth.authenticatedFetch(`/api/bookings.php?passenger_id=${userId}`);
      const data = await response.json();
      return data.bookings || [];
    } catch (error) {
      console.error('Failed to fetch user bookings:', error);
      throw error;
    }
  }

  async searchFlights(searchTerm: string): Promise<Flight[]> {
    try {
      const response = await this.auth.authenticatedFetch(`/api/flights.php?action=search&search=${encodeURIComponent(searchTerm)}`);
      const data = await response.json();
      return data.flights || [];
    } catch (error) {
      console.error('Failed to search flights:', error);
      throw error;
    }
  }

  async getActiveFlights(): Promise<Flight[]> {
    try {
      const response = await this.auth.authenticatedFetch('/api/flights.php?action=active');
      const data = await response.json();
      return data.flights || [];
    } catch (error) {
      console.error('Failed to fetch active flights:', error);
      throw error;
    }
  }

  async callApi(endpoint: string, method: string = 'GET', body?: any): Promise<any> {
    try {
      const config: RequestInit = {
        method,
        headers: {
          'Content-Type': 'application/json',
        },
      };

      if (body && (method === 'POST' || method === 'PUT')) {
        config.body = JSON.stringify(body);
      }

      const response = await this.auth.authenticatedFetch(endpoint, config);
      const data = await response.json();
      return data;
    } catch (error) {
      console.error(`API call failed for ${endpoint}:`, error);
      throw error;
    }
  }
}
