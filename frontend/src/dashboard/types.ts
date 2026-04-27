// Dashboard Types and Interfaces

export interface DashboardStats {
  total_flights: number;
  active_flights: number;
  total_passengers: number;
  checked_in_passengers: number;
  total_bookings: number;
  pending_maintenance: number;
  security_alerts: number;
  system_health: 'healthy' | 'warning' | 'critical';
}

export interface Flight {
  id: number;
  flight_number: string;
  origin: string;
  destination: string;
  scheduled_departure: string;
  scheduled_arrival: string;
  status: string;
  airline_name: string;
  gate?: string;
  terminal?: string;
}

export interface Booking {
  id: number;
  booking_reference: string;
  flight_number: string;
  origin: string;
  destination: string;
  scheduled_departure: string;
  status: string;
  seat_number?: string;
}

export interface User {
  id: number;
  username: string;
  first_name: string;
  last_name: string;
  role_name: string;
}

export type DashboardView = 'overview' | 'flights' | 'passengers' | 'maintenance' | 'security' | 'my-bookings' | 'my-baggage' | 'infrastructure' | 'infrastructure-reports' | 'drones' | 'drone-reports' | 'customs' | 'customs-reports' | 'advanced-security' | 'advanced-security-reports' | 'ai-conflict-prediction' | 'ai-reports' | 'virtual-assistant' | 'module-management';

export interface MenuItem {
  id: DashboardView;
  label: string;
  icon: string;
}
