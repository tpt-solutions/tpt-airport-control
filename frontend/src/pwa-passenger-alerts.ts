/**
 * Passenger Alerts PWA Interface
 *
 * Progressive Web App for real-time passenger alerts with push notifications,
 * travel reminders, and personalized travel tips
 */

import { ApiService } from './services/ApiService';
import { FlightControlWebSocket } from './websocket-client';

interface PassengerAlert {
    alert_id: string;
    passenger_id: string;
    alert_type: 'flight_delay' | 'gate_change' | 'boarding_call' | 'security_alert' | 'weather_warning' | 'custom';
    title: string;
    message: string;
    priority: 'low' | 'medium' | 'high' | 'critical';
    status: 'active' | 'acknowledged' | 'dismissed';
    created_at: string;
    expires_at?: string;
    flight_number?: string;
    gate_number?: string;
}

interface TravelReminder {
    reminder_id: string;
    passenger_id: string;
    reminder_type: 'check_in' | 'boarding' | 'departure' | 'arrival' | 'connection';
    title: string;
    message: string;
    scheduled_time: string;
    flight_number: string;
    status: 'pending' | 'sent' | 'acknowledged';
}

interface NotificationPreference {
    passenger_id: string;
    email_notifications: boolean;
    push_notifications: boolean;
    sms_notifications: boolean;
    flight_updates: boolean;
    gate_changes: boolean;
    security_alerts: boolean;
    weather_alerts: boolean;
    promotional_offers: boolean;
}

class PassengerAlertsPWA {
    private apiService: ApiService;
    private wsClient: FlightControlWebSocket;
    private currentPassengerId: string | null = null;
    private alerts: PassengerAlert[] = [];
    private reminders: TravelReminder[] = [];
    private preferences: NotificationPreference | null = null;
    private notificationInterval: number | null = null;

    constructor() {
        this.apiService = new ApiService();
        this.wsClient = new FlightControlWebSocket('ws://localhost:8080');
        this.initializePWA();
        this.bindEvents();
        this.showAlertsInterface();
    }

    private initializePWA(): void {
        // Register service worker for push notifications
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('Service Worker registered for passenger alerts');
                    this.initializePushNotifications(registration);
                })
                .catch(error => {
                    console.log('Service Worker registration failed:', error);
                });
        }

        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Initialize WebSocket connection for real-time alerts
        this.wsClient.on('alertUpdate', (data: any) => {
            this.handleAlertUpdate(data);
        });
        this.wsClient.on('flightUpdate', (data: any) => {
            this.handleFlightUpdate(data);
        });
        this.wsClient.on('connected', () => {
            console.log('WebSocket connected for passenger alerts');
        });

        // Set up periodic alert checking
        this.startPeriodicAlertCheck();
    }

    private initializePushNotifications(registration: ServiceWorkerRegistration): void {
        // Check if push messaging is supported
        if (!('PushManager' in window)) {
            console.log('Push messaging not supported');
            return;
        }

        // Register push
        registration.pushManager.getSubscription()
            .then(subscription => {
                if (!subscription) {
                    // Subscribe user
                    return registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: this.urlBase64ToUint8Array('YOUR_PUBLIC_VAPID_KEY') as any
                    });
                }
                return subscription;
            })
            .then(subscription => {
                if (subscription) {
                    // Send subscription to backend
                    this.registerPushSubscription(subscription);
                }
            })
            .catch(error => {
                console.error('Push subscription failed:', error);
            });
    }

    private urlBase64ToUint8Array(base64String: string): Uint8Array {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    private async registerPushSubscription(subscription: PushSubscription): Promise<void> {
        try {
            await this.apiService.post('/notifications/subscribe', {
                endpoint: subscription.endpoint,
                keys: {
                    p256dh: this.arrayBufferToBase64(subscription.getKey('p256dh')!),
                    auth: this.arrayBufferToBase64(subscription.getKey('auth')!)
                }
            });
        } catch (error) {
            console.error('Failed to register push subscription:', error);
        }
    }

    private arrayBufferToBase64(buffer: ArrayBuffer): string {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    private bindEvents(): void {
        // Tab navigation
        document.querySelectorAll('.alerts-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const target = e.target as HTMLElement;
                const tabType = target.dataset.tab;
                this.switchTab(tabType);
            });
        });

        // Alert actions
        document.addEventListener('click', (e) => {
            const target = e.target as HTMLElement;
            if (target.classList.contains('acknowledge-alert')) {
                const alertId = target.dataset.alertId;
                if (alertId) this.acknowledgeAlert(alertId);
            } else if (target.classList.contains('dismiss-alert')) {
                const alertId = target.dataset.alertId;
                if (alertId) this.dismissAlert(alertId);
            }
        });

        // Preferences form
        const preferencesForm = document.getElementById('notification-preferences-form');
        if (preferencesForm) {
            preferencesForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveNotificationPreferences();
            });
        }

        // Passenger identification
        const passengerForm = document.getElementById('passenger-identification-form');
        if (passengerForm) {
            passengerForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.identifyPassenger();
            });
        }

        // Refresh button
        const refreshBtn = document.getElementById('refresh-alerts-btn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.refreshAllAlerts();
            });
        }
    }

    private showAlertsInterface(): void {
        const mainContainer = document.getElementById('passenger-alerts-container');
        if (mainContainer) {
            mainContainer.innerHTML = `
                <div class="alerts-header">
                    <h1>Passenger Alerts</h1>
                    <div class="alerts-tabs">
                        <button class="alerts-tab active" data-tab="alerts">Alerts</button>
                        <button class="alerts-tab" data-tab="reminders">Reminders</button>
                        <button class="alerts-tab" data-tab="preferences">Preferences</button>
                    </div>
                </div>

                <!-- Passenger Identification -->
                <div id="passenger-identification" class="passenger-identification">
                    <form id="passenger-identification-form" class="identification-form">
                        <h3>Identify Yourself</h3>
                        <div class="form-group">
                            <label for="passenger-email">Email Address:</label>
                            <input type="email" id="passenger-email" placeholder="your.email@example.com" required>
                        </div>
                        <div class="form-group">
                            <label for="passenger-flight">Flight Number:</label>
                            <input type="text" id="passenger-flight" placeholder="e.g., AA123" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Get My Alerts</button>
                    </form>
                </div>

                <!-- Alerts Tab -->
                <div id="alerts-tab" class="alerts-tab-content active">
                    <div class="tab-header">
                        <h3>Active Alerts</h3>
                        <button id="refresh-alerts-btn" class="btn btn-secondary">🔄 Refresh</button>
                    </div>
                    <div id="alerts-list" class="alerts-list">
                        <div class="no-alerts">
                            <p>No active alerts at this time.</p>
                        </div>
                    </div>
                </div>

                <!-- Reminders Tab -->
                <div id="reminders-tab" class="alerts-tab-content">
                    <div class="tab-header">
                        <h3>Travel Reminders</h3>
                    </div>
                    <div id="reminders-list" class="reminders-list">
                        <div class="no-reminders">
                            <p>No upcoming reminders.</p>
                        </div>
                    </div>
                </div>

                <!-- Preferences Tab -->
                <div id="preferences-tab" class="alerts-tab-content">
                    <div class="tab-header">
                        <h3>Notification Preferences</h3>
                    </div>
                    <form id="notification-preferences-form" class="preferences-form">
                        <div class="preference-section">
                            <h4>Notification Methods</h4>
                            <label class="preference-item">
                                <input type="checkbox" id="push-notifications" checked>
                                <span>Push Notifications</span>
                            </label>
                            <label class="preference-item">
                                <input type="checkbox" id="email-notifications">
                                <span>Email Notifications</span>
                            </label>
                            <label class="preference-item">
                                <input type="checkbox" id="sms-notifications">
                                <span>SMS Notifications</span>
                            </label>
                        </div>

                        <div class="preference-section">
                            <h4>Alert Types</h4>
                            <label class="preference-item">
                                <input type="checkbox" id="flight-updates" checked>
                                <span>Flight Updates</span>
                            </label>
                            <label class="preference-item">
                                <input type="checkbox" id="gate-changes" checked>
                                <span>Gate Changes</span>
                            </label>
                            <label class="preference-item">
                                <input type="checkbox" id="security-alerts" checked>
                                <span>Security Alerts</span>
                            </label>
                            <label class="preference-item">
                                <input type="checkbox" id="weather-alerts" checked>
                                <span>Weather Alerts</span>
                            </label>
                            <label class="preference-item">
                                <input type="checkbox" id="promotional-offers">
                                <span>Promotional Offers</span>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary">Save Preferences</button>
                    </form>
                </div>

                <div id="loading-spinner" class="loading-spinner hidden">
                    <div class="spinner"></div>
                    <p>Loading alerts...</p>
                </div>
            `;

            this.bindTabEvents();
        }
    }

    private bindTabEvents(): void {
        document.querySelectorAll('.alerts-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const target = e.target as HTMLElement;
                const tabType = target.dataset.tab;
                this.switchTab(tabType);
            });
        });
    }

    private switchTab(tabType: string | undefined): void {
        if (!tabType) return;

        // Hide all tabs
        document.querySelectorAll('.alerts-tab-content').forEach(content => {
            content.classList.remove('active');
        });

        // Remove active class from all tab buttons
        document.querySelectorAll('.alerts-tab').forEach(tab => {
            tab.classList.remove('active');
        });

        // Show selected tab
        const selectedTab = document.getElementById(`${tabType}-tab`);
        const selectedTabBtn = document.querySelector(`[data-tab="${tabType}"]`);

        if (selectedTab) {
            selectedTab.classList.add('active');
        }
        if (selectedTabBtn) {
            selectedTabBtn.classList.add('active');
        }
    }

    private async identifyPassenger(): Promise<void> {
        const emailInput = document.getElementById('passenger-email') as HTMLInputElement;
        const flightInput = document.getElementById('passenger-flight') as HTMLInputElement;

        if (!emailInput?.value || !flightInput?.value) {
            this.showError('Please enter both email and flight number');
            return;
        }

        try {
            this.showLoading(true);

            // Identify passenger and get their alerts
            const response = await this.apiService.post('/passengers/identify', {
                email: emailInput.value,
                flight_number: flightInput.value
            });

            if (response.success && response.data) {
                this.currentPassengerId = response.data.passenger_id;
                this.alerts = response.data.alerts || [];
                this.reminders = response.data.reminders || [];
                this.preferences = response.data.preferences || null;

                // Hide identification form and show alerts
                const identificationDiv = document.getElementById('passenger-identification');
                if (identificationDiv) {
                    identificationDiv.style.display = 'none';
                }

                this.displayAlerts();
                this.displayReminders();
                this.loadPreferences();

                this.showSuccess('Welcome! Your alerts are now active.');
            } else {
                this.showError('Passenger not found. Please check your information.');
            }
        } catch (error) {
            console.error('Passenger identification error:', error);
            this.showError('Unable to identify passenger. Please try again.');
        } finally {
            this.showLoading(false);
        }
    }

    private displayAlerts(): void {
        const alertsList = document.getElementById('alerts-list');
        if (!alertsList) return;

        if (this.alerts.length === 0) {
            alertsList.innerHTML = `
                <div class="no-alerts">
                    <p>No active alerts at this time.</p>
                </div>
            `;
            return;
        }

        const alertsHtml = this.alerts.map(alert => this.createAlertCard(alert)).join('');
        alertsList.innerHTML = `
            <div class="alerts-container">
                ${alertsHtml}
            </div>
        `;
    }

    private createAlertCard(alert: PassengerAlert): string {
        const priorityClass = `priority-${alert.priority}`;
        const statusClass = `status-${alert.status}`;
        const createdDate = new Date(alert.created_at).toLocaleString();

        return `
            <div class="alert-card ${priorityClass} ${statusClass}" data-alert-id="${alert.alert_id}">
                <div class="alert-header">
                    <div class="alert-priority ${priorityClass}">
                        ${alert.priority.toUpperCase()}
                    </div>
                    <div class="alert-type">
                        ${this.formatAlertType(alert.alert_type)}
                    </div>
                </div>

                <div class="alert-content">
                    <h4 class="alert-title">${alert.title}</h4>
                    <p class="alert-message">${alert.message}</p>

                    <div class="alert-details">
                        <div class="detail-item">
                            <span class="label">Created:</span>
                            <span class="value">${createdDate}</span>
                        </div>
                        ${alert.flight_number ? `
                            <div class="detail-item">
                                <span class="label">Flight:</span>
                                <span class="value">${alert.flight_number}</span>
                            </div>
                        ` : ''}
                        ${alert.gate_number ? `
                            <div class="detail-item">
                                <span class="label">Gate:</span>
                                <span class="value">${alert.gate_number}</span>
                            </div>
                        ` : ''}
                    </div>
                </div>

                <div class="alert-actions">
                    ${alert.status === 'active' ? `
                        <button class="btn btn-sm acknowledge-alert" data-alert-id="${alert.alert_id}">
                            ✓ Acknowledge
                        </button>
                        <button class="btn btn-sm btn-secondary dismiss-alert" data-alert-id="${alert.alert_id}">
                            ✕ Dismiss
                        </button>
                    ` : `
                        <span class="alert-status-text">${alert.status}</span>
                    `}
                </div>
            </div>
        `;
    }

    private formatAlertType(type: string): string {
        return type.split('_').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    }

    private displayReminders(): void {
        const remindersList = document.getElementById('reminders-list');
        if (!remindersList) return;

        if (this.reminders.length === 0) {
            remindersList.innerHTML = `
                <div class="no-reminders">
                    <p>No upcoming reminders.</p>
                </div>
            `;
            return;
        }

        const remindersHtml = this.reminders.map(reminder => this.createReminderCard(reminder)).join('');
        remindersList.innerHTML = `
            <div class="reminders-container">
                ${remindersHtml}
            </div>
        `;
    }

    private createReminderCard(reminder: TravelReminder): string {
        const scheduledDate = new Date(reminder.scheduled_time);
        const now = new Date();
        const timeDiff = scheduledDate.getTime() - now.getTime();
        const hoursDiff = Math.floor(timeDiff / (1000 * 60 * 60));
        const minutesDiff = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));

        let timeText = '';
        if (hoursDiff > 0) {
            timeText = `In ${hoursDiff}h ${minutesDiff}m`;
        } else if (minutesDiff > 0) {
            timeText = `In ${minutesDiff}m`;
        } else {
            timeText = 'Now';
        }

        return `
            <div class="reminder-card" data-reminder-id="${reminder.reminder_id}">
                <div class="reminder-header">
                    <div class="reminder-type">
                        ${this.formatReminderType(reminder.reminder_type)}
                    </div>
                    <div class="reminder-time">
                        ${timeText}
                    </div>
                </div>

                <div class="reminder-content">
                    <h4 class="reminder-title">${reminder.title}</h4>
                    <p class="reminder-message">${reminder.message}</p>

                    <div class="reminder-details">
                        <div class="detail-item">
                            <span class="label">Flight:</span>
                            <span class="value">${reminder.flight_number}</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Scheduled:</span>
                            <span class="value">${scheduledDate.toLocaleString()}</span>
                        </div>
                    </div>
                </div>

                <div class="reminder-status">
                    <span class="status-text ${reminder.status}">${reminder.status}</span>
                </div>
            </div>
        `;
    }

    private formatReminderType(type: string): string {
        return type.split('_').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    }

    private loadPreferences(): void {
        if (!this.preferences) return;

        // Load notification preferences into form
        const pushCheckbox = document.getElementById('push-notifications') as HTMLInputElement;
        const emailCheckbox = document.getElementById('email-notifications') as HTMLInputElement;
        const smsCheckbox = document.getElementById('sms-notifications') as HTMLInputElement;
        const flightCheckbox = document.getElementById('flight-updates') as HTMLInputElement;
        const gateCheckbox = document.getElementById('gate-changes') as HTMLInputElement;
        const securityCheckbox = document.getElementById('security-alerts') as HTMLInputElement;
        const weatherCheckbox = document.getElementById('weather-alerts') as HTMLInputElement;
        const promoCheckbox = document.getElementById('promotional-offers') as HTMLInputElement;

        if (pushCheckbox) pushCheckbox.checked = this.preferences.push_notifications;
        if (emailCheckbox) emailCheckbox.checked = this.preferences.email_notifications;
        if (smsCheckbox) smsCheckbox.checked = this.preferences.sms_notifications;
        if (flightCheckbox) flightCheckbox.checked = this.preferences.flight_updates;
        if (gateCheckbox) gateCheckbox.checked = this.preferences.gate_changes;
        if (securityCheckbox) securityCheckbox.checked = this.preferences.security_alerts;
        if (weatherCheckbox) weatherCheckbox.checked = this.preferences.weather_alerts;
        if (promoCheckbox) promoCheckbox.checked = this.preferences.promotional_offers;
    }

    private async saveNotificationPreferences(): Promise<void> {
        if (!this.currentPassengerId) {
            this.showError('Please identify yourself first');
            return;
        }

        const preferences: NotificationPreference = {
            passenger_id: this.currentPassengerId,
            push_notifications: (document.getElementById('push-notifications') as HTMLInputElement)?.checked || false,
            email_notifications: (document.getElementById('email-notifications') as HTMLInputElement)?.checked || false,
            sms_notifications: (document.getElementById('sms-notifications') as HTMLInputElement)?.checked || false,
            flight_updates: (document.getElementById('flight-updates') as HTMLInputElement)?.checked || false,
            gate_changes: (document.getElementById('gate-changes') as HTMLInputElement)?.checked || false,
            security_alerts: (document.getElementById('security-alerts') as HTMLInputElement)?.checked || false,
            weather_alerts: (document.getElementById('weather-alerts') as HTMLInputElement)?.checked || false,
            promotional_offers: (document.getElementById('promotional-offers') as HTMLInputElement)?.checked || false,
        };

        try {
            this.showLoading(true);

            const response = await this.apiService.post('/notifications/preferences', preferences);

            if (response.success) {
                this.preferences = preferences;
                this.showSuccess('Notification preferences saved successfully!');
            } else {
                this.showError('Failed to save preferences. Please try again.');
            }
        } catch (error) {
            console.error('Save preferences error:', error);
            this.showError('Failed to save preferences. Please try again.');
        } finally {
            this.showLoading(false);
        }
    }

    private async acknowledgeAlert(alertId: string): Promise<void> {
        try {
            const response = await this.apiService.put(`/alerts/${alertId}/acknowledge`);

            if (response.success) {
                // Update local alert status
                const alert = this.alerts.find(a => a.alert_id === alertId);
                if (alert) {
                    alert.status = 'acknowledged';
                    this.displayAlerts();
                }
            }
        } catch (error) {
            console.error('Acknowledge alert error:', error);
            this.showError('Failed to acknowledge alert');
        }
    }

    private async dismissAlert(alertId: string): Promise<void> {
        try {
            const response = await this.apiService.put(`/alerts/${alertId}/dismiss`);

            if (response.success) {
                // Update local alert status
                const alert = this.alerts.find(a => a.alert_id === alertId);
                if (alert) {
                    alert.status = 'dismissed';
                    this.displayAlerts();
                }
            }
        } catch (error) {
            console.error('Dismiss alert error:', error);
            this.showError('Failed to dismiss alert');
        }
    }

    private async refreshAllAlerts(): Promise<void> {
        if (!this.currentPassengerId) return;

        try {
            this.showLoading(true);

            const response = await this.apiService.get(`/alerts/passenger/${this.currentPassengerId}`);

            if (response.success && response.data) {
                this.alerts = response.data.alerts || [];
                this.reminders = response.data.reminders || [];
                this.displayAlerts();
                this.displayReminders();
            }
        } catch (error) {
            console.error('Refresh alerts error:', error);
            this.showError('Failed to refresh alerts');
        } finally {
            this.showLoading(false);
        }
    }

    private handleAlertUpdate(data: any): void {
        // Handle real-time alert updates
        if (data.passenger_id === this.currentPassengerId) {
            // Add new alert or update existing one
            const existingIndex = this.alerts.findIndex(alert => alert.alert_id === data.alert_id);

            if (existingIndex !== -1) {
                this.alerts[existingIndex] = { ...this.alerts[existingIndex], ...data };
            } else {
                this.alerts.unshift(data); // Add to beginning
            }

            this.displayAlerts();

            // Send push notification for high priority alerts
            if (data.priority === 'high' || data.priority === 'critical') {
                this.sendAlertNotification(data);
            }
        }
    }

    private handleFlightUpdate(data: any): void {
        // Handle flight updates that might affect alerts
        if (this.currentPassengerId) {
            // Check if this flight affects our passenger
            this.refreshAllAlerts();
        }
    }

    private sendAlertNotification(alert: PassengerAlert): void {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(alert.title, {
                body: alert.message,
                icon: '/icon-192x192.png',
                tag: `alert-${alert.alert_id}`,
                requireInteraction: alert.priority === 'critical'
            });
        }
    }

    private startPeriodicAlertCheck(): void {
        // Check for new alerts every 2 minutes
        this.notificationInterval = window.setInterval(() => {
            if (this.currentPassengerId) {
                this.refreshAllAlerts();
            }
        }, 120000); // 2 minutes
    }

    private showError(message: string): void {
        const errorDiv = document.getElementById('error-message');
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.classList.remove('hidden');

            setTimeout(() => {
                errorDiv.classList.add('hidden');
            }, 5000);
        }
    }

    private showSuccess(message: string): void {
        const successDiv = document.getElementById('success-message');
        if (successDiv) {
            successDiv.textContent = message;
            successDiv.classList.remove('hidden');

            setTimeout(() => {
                successDiv.classList.add('hidden');
            }, 3000);
        }
    }

    private showLoading(show: boolean): void {
        const loadingEl = document.getElementById('loading-spinner');
        if (loadingEl) {
            if (show) {
                loadingEl.classList.remove('hidden');
            } else {
                loadingEl.classList.add('hidden');
            }
        }
    }

    private showNotification(message: string): void {
        const notification = document.createElement('div');
        notification.className = 'notification';
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    // Cleanup method
    public destroy(): void {
        if (this.notificationInterval) {
            clearInterval(this.notificationInterval);
            this.notificationInterval = null;
        }
        this.wsClient.disconnect();
        this.alerts = [];
        this.reminders = [];
    }
}

// Initialize PWA when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const passengerAlerts = new PassengerAlertsPWA();

    // Register for PWA installation
    let deferredPrompt: any;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;

        const installBtn = document.getElementById('install-pwa-btn');
        if (installBtn) {
            installBtn.classList.remove('hidden');
            installBtn.addEventListener('click', () => {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult: any) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted PWA installation');
                    }
                    deferredPrompt = null;
                });
            });
        }
    });

    // Handle offline/online status
    window.addEventListener('online', () => {
        document.body.classList.remove('offline');
        showNotification('Connection restored - alerts are active');
    });

    window.addEventListener('offline', () => {
        document.body.classList.add('offline');
        showNotification('You are currently offline. Some alert features may be limited.');
    });

    function showNotification(message: string): void {
        const notification = document.createElement('div');
        notification.className = 'notification offline-notification';
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
});

export { PassengerAlertsPWA };
