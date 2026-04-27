// WebSocket Client for Real-time Flight Control Updates

interface WebSocketMessage {
  type: string;
  data?: any;
  timestamp?: string;
  id?: string;
}

interface FlightUpdate {
  flight_id: number;
  flight_number: string;
  status: string;
  position?: {
    latitude: number;
    longitude: number;
    altitude: number;
    heading: number;
    speed: number;
  };
  gate?: string;
  delay_minutes?: number;
  estimated_arrival?: string;
}

interface PassengerUpdate {
  booking_id: number;
  passenger_name: string;
  status: string;
  gate?: string;
  boarding_time?: string;
}

interface SystemAlert {
  level: 'info' | 'warning' | 'error' | 'critical';
  message: string;
  category: string;
  timestamp: string;
  source?: string;
}

type MessageHandler = (data: any) => void;

export class FlightControlWebSocket {
  private ws: WebSocket | null = null;
  private reconnectAttempts = 0;
  private maxReconnectAttempts = 5;
  private reconnectInterval = 3000;
  private heartbeatInterval: number | null = null;
  private url: string;
  private connected = false;
  private messageHandlers: Map<string, MessageHandler[]> = new Map();
  private pendingMessages: WebSocketMessage[] = [];
  private subscriptions: Set<string> = new Set();

  constructor(url: string) {
    this.url = url;
    this.connect();
  }

  // Connection Management
  private connect(): void {
    try {
      console.log('Connecting to WebSocket server:', this.url);
      this.ws = new WebSocket(this.url);

      this.ws.onopen = this.onOpen.bind(this);
      this.ws.onmessage = this.onMessage.bind(this);
      this.ws.onclose = this.onClose.bind(this);
      this.ws.onerror = this.onError.bind(this);

    } catch (error) {
      console.error('WebSocket connection error:', error);
      this.attemptReconnect();
    }
  }

  private onOpen(event: Event): void {
    console.log('WebSocket connected successfully');
    this.connected = true;
    this.reconnectAttempts = 0;

    // Start heartbeat
    this.startHeartbeat();

    // Send pending messages
    this.sendPendingMessages();

    // Re-subscribe to previous subscriptions
    this.resubscribe();

    // Notify connection established
    this.emit('connected', { timestamp: new Date().toISOString() });
  }

  private onMessage(event: MessageEvent): void {
    try {
      const message: WebSocketMessage = JSON.parse(event.data);
      this.handleMessage(message);
    } catch (error) {
      console.error('Failed to parse WebSocket message:', error);
    }
  }

  private onClose(event: CloseEvent): void {
    console.log('WebSocket disconnected:', event.code, event.reason);
    this.connected = false;
    this.stopHeartbeat();

    if (event.code !== 1000) { // Not a normal closure
      this.attemptReconnect();
    }

    this.emit('disconnected', {
      code: event.code,
      reason: event.reason,
      timestamp: new Date().toISOString()
    });
  }

  private onError(event: Event): void {
    console.error('WebSocket error:', event);
    this.emit('error', { timestamp: new Date().toISOString() });
  }

  private attemptReconnect(): void {
    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      this.reconnectAttempts++;
      const delay = this.reconnectInterval * Math.pow(2, this.reconnectAttempts - 1); // Exponential backoff

      console.log(`Attempting to reconnect (${this.reconnectAttempts}/${this.maxReconnectAttempts}) in ${delay}ms...`);

      setTimeout(() => {
        this.connect();
      }, delay);
    } else {
      console.error('Max reconnection attempts reached');
      this.emit('maxReconnectAttemptsReached', {
        attempts: this.reconnectAttempts,
        timestamp: new Date().toISOString()
      });
    }
  }

  private startHeartbeat(): void {
    this.heartbeatInterval = window.setInterval(() => {
      if (this.connected) {
        this.send({ type: 'ping', timestamp: new Date().toISOString() });
      }
    }, 30000); // Send heartbeat every 30 seconds
  }

  private stopHeartbeat(): void {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
    }
  }

  // Message Handling
  private handleMessage(message: WebSocketMessage): void {
    switch (message.type) {
      case 'pong':
        // Handle heartbeat response
        break;

      case 'flight_update':
        this.emit('flightUpdate', message.data);
        break;

      case 'passenger_update':
        this.emit('passengerUpdate', message.data);
        break;

      case 'system_alert':
        this.emit('systemAlert', message.data);
        break;

      case 'bulk_update':
        this.handleBulkUpdate(message.data);
        break;

      case 'subscription_confirmed':
        console.log('Subscription confirmed:', message.data);
        break;

      case 'error':
        console.error('Server error:', message.data);
        this.emit('serverError', message.data);
        break;

      default:
        console.log('Unknown message type:', message.type, message);
        this.emit('unknownMessage', message);
    }
  }

  private handleBulkUpdate(data: any): void {
    if (data.flights) {
      data.flights.forEach((flight: FlightUpdate) => {
        this.emit('flightUpdate', flight);
      });
    }

    if (data.passengers) {
      data.passengers.forEach((passenger: PassengerUpdate) => {
        this.emit('passengerUpdate', passenger);
      });
    }

    if (data.alerts) {
      data.alerts.forEach((alert: SystemAlert) => {
        this.emit('systemAlert', alert);
      });
    }
  }

  // Public API
  send(message: WebSocketMessage): void {
    if (this.connected && this.ws) {
      this.ws.send(JSON.stringify(message));
    } else {
      // Queue message for when connection is restored
      this.pendingMessages.push(message);
    }
  }

  private sendPendingMessages(): void {
    while (this.pendingMessages.length > 0 && this.connected) {
      const message = this.pendingMessages.shift();
      if (message) {
        this.send(message);
      }
    }
  }

  // Subscription Management
  subscribeToFlights(filters?: {
    airline?: string;
    origin?: string;
    destination?: string;
    status?: string;
  }): void {
    const subscriptionId = 'flights';
    this.subscriptions.add(subscriptionId);

    this.send({
      type: 'subscribe',
      data: {
        channel: 'flights',
        filters: filters || {},
        subscription_id: subscriptionId
      }
    });
  }

  subscribeToPassengers(filters?: {
    flight_id?: number;
    status?: string;
  }): void {
    const subscriptionId = 'passengers';
    this.subscriptions.add(subscriptionId);

    this.send({
      type: 'subscribe',
      data: {
        channel: 'passengers',
        filters: filters || {},
        subscription_id: subscriptionId
      }
    });
  }

  subscribeToAlerts(filters?: {
    level?: string;
    category?: string;
  }): void {
    const subscriptionId = 'alerts';
    this.subscriptions.add(subscriptionId);

    this.send({
      type: 'subscribe',
      data: {
        channel: 'alerts',
        filters: filters || {},
        subscription_id: subscriptionId
      }
    });
  }

  subscribeToSystemStatus(): void {
    const subscriptionId = 'system';
    this.subscriptions.add(subscriptionId);

    this.send({
      type: 'subscribe',
      data: {
        channel: 'system',
        subscription_id: subscriptionId
      }
    });
  }

  unsubscribe(channel: string): void {
    this.subscriptions.delete(channel);

    this.send({
      type: 'unsubscribe',
      data: { channel }
    });
  }

  private resubscribe(): void {
    // Re-subscribe to all previous subscriptions
    this.subscriptions.forEach(subscription => {
      switch (subscription) {
        case 'flights':
          this.subscribeToFlights();
          break;
        case 'passengers':
          this.subscribeToPassengers();
          break;
        case 'alerts':
          this.subscribeToAlerts();
          break;
        case 'system':
          this.subscribeToSystemStatus();
          break;
      }
    });
  }

  // Event System
  on(event: string, handler: MessageHandler): void {
    if (!this.messageHandlers.has(event)) {
      this.messageHandlers.set(event, []);
    }
    this.messageHandlers.get(event)!.push(handler);
  }

  off(event: string, handler?: MessageHandler): void {
    if (!this.messageHandlers.has(event)) return;

    if (handler) {
      const handlers = this.messageHandlers.get(event)!;
      const index = handlers.indexOf(handler);
      if (index > -1) {
        handlers.splice(index, 1);
      }
    } else {
      this.messageHandlers.delete(event);
    }
  }

  private emit(event: string, data: any): void {
    if (!this.messageHandlers.has(event)) return;

    const handlers = this.messageHandlers.get(event)!;
    handlers.forEach(handler => {
      try {
        handler(data);
      } catch (error) {
        console.error(`Error in ${event} handler:`, error);
      }
    });
  }

  // Connection Status
  isConnected(): boolean {
    return this.connected;
  }

  getConnectionState(): string {
    if (!this.ws) return 'disconnected';

    switch (this.ws.readyState) {
      case WebSocket.CONNECTING:
        return 'connecting';
      case WebSocket.OPEN:
        return 'connected';
      case WebSocket.CLOSING:
        return 'closing';
      case WebSocket.CLOSED:
        return 'closed';
      default:
        return 'unknown';
    }
  }

  // Cleanup
  disconnect(): void {
    this.stopHeartbeat();
    this.subscriptions.clear();
    this.messageHandlers.clear();
    this.pendingMessages.length = 0;

    if (this.ws) {
      this.ws.close(1000, 'Client disconnecting');
    }
  }
}

// Real-time Data Manager
export class RealTimeDataManager {
  private wsClient: FlightControlWebSocket;
  private flightData: Map<number, FlightUpdate> = new Map();
  private passengerData: Map<number, PassengerUpdate> = new Map();
  private alerts: SystemAlert[] = [];
  private listeners: Map<string, Function[]> = new Map();

  constructor(wsUrl: string) {
    this.wsClient = new FlightControlWebSocket(wsUrl);
    this.setupEventHandlers();
  }

  private setupEventHandlers(): void {
    // Flight updates
    this.wsClient.on('flightUpdate', (data: FlightUpdate) => {
      this.flightData.set(data.flight_id, data);
      this.notifyListeners('flightUpdate', data);
    });

    // Passenger updates
    this.wsClient.on('passengerUpdate', (data: PassengerUpdate) => {
      this.passengerData.set(data.booking_id, data);
      this.notifyListeners('passengerUpdate', data);
    });

    // System alerts
    this.wsClient.on('systemAlert', (data: SystemAlert) => {
      this.alerts.unshift(data); // Add to beginning
      // Keep only last 100 alerts
      if (this.alerts.length > 100) {
        this.alerts = this.alerts.slice(0, 100);
      }
      this.notifyListeners('systemAlert', data);
    });

    // Connection events
    this.wsClient.on('connected', () => {
      this.notifyListeners('connected');
    });

    this.wsClient.on('disconnected', () => {
      this.notifyListeners('disconnected');
    });

    this.wsClient.on('error', (error) => {
      this.notifyListeners('error', error);
    });
  }

  // Subscription methods
  subscribeToAllFlights(): void {
    this.wsClient.subscribeToFlights();
  }

  subscribeToFlight(flightId: number): void {
    this.wsClient.subscribeToFlights({ /* specific flight filter */ });
  }

  subscribeToAllPassengers(): void {
    this.wsClient.subscribeToPassengers();
  }

  subscribeToFlightPassengers(flightId: number): void {
    this.wsClient.subscribeToPassengers({ flight_id: flightId });
  }

  subscribeToAlerts(): void {
    this.wsClient.subscribeToAlerts();
  }

  subscribeToSystemStatus(): void {
    this.wsClient.subscribeToSystemStatus();
  }

  // Data access methods
  getFlightData(flightId: number): FlightUpdate | undefined {
    return this.flightData.get(flightId);
  }

  getAllFlightData(): FlightUpdate[] {
    return Array.from(this.flightData.values());
  }

  getPassengerData(bookingId: number): PassengerUpdate | undefined {
    return this.passengerData.get(bookingId);
  }

  getAllPassengerData(): PassengerUpdate[] {
    return Array.from(this.passengerData.values());
  }

  getRecentAlerts(limit: number = 10): SystemAlert[] {
    return this.alerts.slice(0, limit);
  }

  getAlertsByLevel(level: string): SystemAlert[] {
    return this.alerts.filter(alert => alert.level === level);
  }

  getAlertsByCategory(category: string): SystemAlert[] {
    return this.alerts.filter(alert => alert.category === category);
  }

  // Event listener system
  addEventListener(event: string, callback: Function): void {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, []);
    }
    this.listeners.get(event)!.push(callback);
  }

  removeEventListener(event: string, callback: Function): void {
    const listeners = this.listeners.get(event);
    if (listeners) {
      const index = listeners.indexOf(callback);
      if (index > -1) {
        listeners.splice(index, 1);
      }
    }
  }

  private notifyListeners(event: string, data?: any): void {
    const listeners = this.listeners.get(event);
    if (listeners) {
      listeners.forEach(callback => {
        try {
          callback(data);
        } catch (error) {
          console.error(`Error in ${event} listener:`, error);
        }
      });
    }
  }

  // Connection status
  isConnected(): boolean {
    return this.wsClient.isConnected();
  }

  getConnectionState(): string {
    return this.wsClient.getConnectionState();
  }

  // Cleanup
  disconnect(): void {
    this.wsClient.disconnect();
    this.listeners.clear();
    this.flightData.clear();
    this.passengerData.clear();
    this.alerts.length = 0;
  }
}

// Utility functions for real-time data processing
export class RealTimeUtils {
  static formatFlightStatus(status: string): string {
    const statusMap: { [key: string]: string } = {
      'scheduled': 'Scheduled',
      'boarding': 'Boarding',
      'departed': 'Departed',
      'arrived': 'Arrived',
      'delayed': 'Delayed',
      'cancelled': 'Cancelled',
      'diverted': 'Diverted'
    };
    return statusMap[status] || status;
  }

  static getStatusColor(status: string): string {
    const colorMap: { [key: string]: string } = {
      'scheduled': '#10B981', // green
      'boarding': '#3B82F6',  // blue
      'departed': '#8B5CF6',  // purple
      'arrived': '#06B6D4',   // cyan
      'delayed': '#F59E0B',   // amber
      'cancelled': '#EF4444', // red
      'diverted': '#F97316'   // orange
    };
    return colorMap[status] || '#6B7280'; // gray
  }

  static formatAltitude(altitude: number): string {
    if (altitude < 1000) {
      return `${altitude} ft`;
    } else {
      return `${(altitude / 1000).toFixed(1)}k ft`;
    }
  }

  static formatSpeed(speed: number): string {
    return `${Math.round(speed)} kts`;
  }

  static formatHeading(heading: number): string {
    return `${Math.round(heading)}°`;
  }

  static calculateDistance(lat1: number, lon1: number, lat2: number, lon2: number): number {
    const R = 6371; // Earth's radius in kilometers
    const dLat = this.toRadians(lat2 - lat1);
    const dLon = this.toRadians(lon2 - lon1);
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(this.toRadians(lat1)) * Math.cos(this.toRadians(lat2)) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
  }

  static toRadians(degrees: number): number {
    return degrees * (Math.PI / 180);
  }

  static formatTimeRemaining(minutes: number): string {
    if (minutes < 60) {
      return `${minutes}m`;
    } else {
      const hours = Math.floor(minutes / 60);
      const mins = minutes % 60;
      return `${hours}h ${mins}m`;
    }
  }

  static getAlertIcon(level: string): string {
    const iconMap: { [key: string]: string } = {
      'info': 'ℹ️',
      'warning': '⚠️',
      'error': '❌',
      'critical': '🚨'
    };
    return iconMap[level] || '📢';
  }

  static getAlertColor(level: string): string {
    const colorMap: { [key: string]: string } = {
      'info': '#3B82F6',     // blue
      'warning': '#F59E0B',  // amber
      'error': '#EF4444',    // red
      'critical': '#DC2626'  // red-600
    };
    return colorMap[level] || '#6B7280'; // gray
  }
}

// Export all classes
export type { WebSocketMessage, FlightUpdate, PassengerUpdate, SystemAlert, MessageHandler };
