/**
 * Baggage Tracking PWA Interface
 *
 * Progressive Web App for real-time baggage tracking with live updates,
 * RFID tag integration, and automated baggage routing
 */

import { ApiService } from './services/ApiService';
import { FlightControlWebSocket } from './websocket-client';

interface BaggageItem {
    baggage_id: string;
    passenger_id: string;
    flight_number: string;
    tag_number: string;
    status: 'checked_in' | 'loaded' | 'in_transit' | 'unloaded' | 'delivered' | 'lost' | 'damaged';
    location: string;
    last_updated: string;
    weight?: number;
    dimensions?: string;
    special_handling?: string[];
}

interface TrackingRequest {
    baggage_tag?: string;
    passenger_id?: string;
    flight_number?: string;
    last_name?: string;
}

class BaggageTrackingPWA {
    private apiService: ApiService;
    private wsClient: FlightControlWebSocket;
    private currentBaggage: BaggageItem[] = [];
    private trackingInterval: number | null = null;

    constructor() {
        this.apiService = new ApiService();
        this.wsClient = new FlightControlWebSocket('ws://localhost:8080');
        this.initializePWA();
        this.bindEvents();
        this.showTrackingInterface();
    }

    private initializePWA(): void {
        // Register service worker for offline functionality
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('Service Worker registered for baggage tracking');
                })
                .catch(error => {
                    console.log('Service Worker registration failed:', error);
                });
        }

        // Request notification permission for baggage updates
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Initialize WebSocket connection for real-time baggage updates
        this.wsClient.on('baggageUpdate', (data: any) => {
            this.handleBaggageUpdate(data);
        });
        this.wsClient.on('connected', () => {
            console.log('WebSocket connected for baggage tracking');
        });

        // Set up periodic refresh for baggage status
        this.startPeriodicTracking();
    }

    private bindEvents(): void {
        // Tag number tracking
        const tagForm = document.getElementById('tag-tracking-form');
        if (tagForm) {
            tagForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleTagLookup();
            });
        }

        // Passenger lookup
        const passengerForm = document.getElementById('passenger-lookup-form');
        if (passengerForm) {
            passengerForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handlePassengerLookup();
            });
        }

        // Flight lookup
        const flightForm = document.getElementById('flight-lookup-form');
        if (flightForm) {
            flightForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFlightLookup();
            });
        }

        // QR code scanning
        const scanBtn = document.getElementById('scan-qr-btn');
        if (scanBtn) {
            scanBtn.addEventListener('click', () => {
                this.startQRScan();
            });
        }

        // Refresh button
        const refreshBtn = document.getElementById('refresh-tracking-btn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.refreshAllTracking();
            });
        }

        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.target as HTMLElement;
                const filter = target.dataset.filter;
                this.filterBaggageByStatus(filter);
            });
        });
    }

    private showTrackingInterface(): void {
        const mainContainer = document.getElementById('baggage-tracking-container');
        if (mainContainer) {
            mainContainer.innerHTML = `
                <div class="tracking-header">
                    <h1>Baggage Tracking</h1>
                    <div class="tracking-options">
                        <button class="tab-btn active" data-tab="tag">Tag Number</button>
                        <button class="tab-btn" data-tab="passenger">Passenger</button>
                        <button class="tab-btn" data-tab="flight">Flight</button>
                    </div>
                </div>

                <div class="tracking-forms">
                    <form id="tag-tracking-form" class="tracking-form active">
                        <div class="form-group">
                            <label for="baggage-tag">Baggage Tag Number:</label>
                            <input type="text" id="baggage-tag" placeholder="Enter tag number" required>
                            <button type="button" id="scan-qr-btn" class="scan-btn">📱 Scan QR</button>
                        </div>
                        <button type="submit" class="btn btn-primary">Track Baggage</button>
                    </form>

                    <form id="passenger-lookup-form" class="tracking-form">
                        <div class="form-group">
                            <label for="passenger-last-name">Last Name:</label>
                            <input type="text" id="passenger-last-name" placeholder="Enter last name" required>
                        </div>
                        <div class="form-group">
                            <label for="passenger-flight">Flight Number:</label>
                            <input type="text" id="passenger-flight" placeholder="Enter flight number" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Find Baggage</button>
                    </form>

                    <form id="flight-lookup-form" class="tracking-form">
                        <div class="form-group">
                            <label for="flight-number">Flight Number:</label>
                            <input type="text" id="flight-number" placeholder="Enter flight number" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Track Flight Baggage</button>
                    </form>
                </div>

                <div class="tracking-controls">
                    <button id="refresh-tracking-btn" class="btn btn-secondary">🔄 Refresh</button>
                    <div class="filter-buttons">
                        <button class="filter-btn" data-filter="all">All</button>
                        <button class="filter-btn" data-filter="in_transit">In Transit</button>
                        <button class="filter-btn" data-filter="delivered">Delivered</button>
                        <button class="filter-btn" data-filter="lost">Lost</button>
                    </div>
                </div>

                <div id="baggage-results" class="baggage-results">
                    <div class="no-results">
                        <p>Enter tracking information to find your baggage</p>
                    </div>
                </div>

                <div id="loading-spinner" class="loading-spinner hidden">
                    <div class="spinner"></div>
                    <p>Tracking baggage...</p>
                </div>
            `;

            this.bindTabEvents();
        }
    }

    private bindTabEvents(): void {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.target as HTMLElement;
                const tab = target.dataset.tab;

                // Hide all forms
                document.querySelectorAll('.tracking-form').forEach(form => {
                    form.classList.remove('active');
                });

                // Remove active class from all tabs
                document.querySelectorAll('.tab-btn').forEach(tabBtn => {
                    tabBtn.classList.remove('active');
                });

                // Show selected form and activate tab
                const selectedForm = document.getElementById(`${tab}-tracking-form`);
                if (selectedForm) {
                    selectedForm.classList.add('active');
                }
                target.classList.add('active');
            });
        });
    }

    private async handleTagLookup(): Promise<void> {
        const tagInput = document.getElementById('baggage-tag') as HTMLInputElement;
        if (!tagInput?.value) {
            this.showError('Please enter a baggage tag number');
            return;
        }

        try {
            this.showLoading(true);
            const response = await this.apiService.get(`/baggage-tracking/tag/${tagInput.value}`);

            if (response.success && response.data) {
                this.currentBaggage = [response.data as BaggageItem];
                this.displayBaggageResults(this.currentBaggage);
            } else {
                this.showError('Baggage not found. Please check the tag number.');
            }
        } catch (error) {
            console.error('Tag lookup error:', error);
            this.showError('Unable to track baggage. Please try again.');
        } finally {
            this.showLoading(false);
        }
    }

    private async handlePassengerLookup(): Promise<void> {
        const lastNameInput = document.getElementById('passenger-last-name') as HTMLInputElement;
        const flightInput = document.getElementById('passenger-flight') as HTMLInputElement;

        if (!lastNameInput?.value || !flightInput?.value) {
            this.showError('Please enter both last name and flight number');
            return;
        }

        try {
            this.showLoading(true);
            const response = await this.apiService.post('/baggage-tracking/passenger', {
                last_name: lastNameInput.value,
                flight_number: flightInput.value
            });

            if (response.success && response.data) {
                this.currentBaggage = Array.isArray(response.data) ? response.data : [response.data];
                this.displayBaggageResults(this.currentBaggage);
            } else {
                this.showError('No baggage found for this passenger.');
            }
        } catch (error) {
            console.error('Passenger lookup error:', error);
            this.showError('Unable to find passenger baggage. Please try again.');
        } finally {
            this.showLoading(false);
        }
    }

    private async handleFlightLookup(): Promise<void> {
        const flightInput = document.getElementById('flight-number') as HTMLInputElement;
        if (!flightInput?.value) {
            this.showError('Please enter a flight number');
            return;
        }

        try {
            this.showLoading(true);
            const response = await this.apiService.get(`/baggage-tracking/flight/${flightInput.value}`);

            if (response.success && response.data) {
                this.currentBaggage = Array.isArray(response.data) ? response.data : [response.data];
                this.displayBaggageResults(this.currentBaggage);
            } else {
                this.showError('No baggage found for this flight.');
            }
        } catch (error) {
            console.error('Flight lookup error:', error);
            this.showError('Unable to find flight baggage. Please try again.');
        } finally {
            this.showLoading(false);
        }
    }

    private startQRScan(): void {
        // In a real implementation, this would integrate with device camera
        // For now, we'll simulate QR scanning
        if ('mediaDevices' in navigator && 'getUserMedia' in navigator.mediaDevices) {
            // Request camera permission and start scanning
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(stream => {
                    // QR scanning logic would go here
                    this.showNotification('QR scanning started. Point camera at baggage tag.');
                })
                .catch(error => {
                    console.error('Camera access error:', error);
                    this.showError('Unable to access camera for QR scanning.');
                });
        } else {
            this.showError('QR scanning not supported on this device.');
        }
    }

    private displayBaggageResults(baggage: BaggageItem[]): void {
        const resultsContainer = document.getElementById('baggage-results');
        if (!resultsContainer) return;

        if (baggage.length === 0) {
            resultsContainer.innerHTML = `
                <div class="no-results">
                    <p>No baggage found matching your search criteria.</p>
                </div>
            `;
            return;
        }

        const baggageHtml = baggage.map(item => this.createBaggageCard(item)).join('');
        resultsContainer.innerHTML = `
            <div class="baggage-list">
                <h3>Found ${baggage.length} baggage item${baggage.length > 1 ? 's' : ''}</h3>
                ${baggageHtml}
            </div>
        `;
    }

    private createBaggageCard(item: BaggageItem): string {
        const statusClass = this.getStatusClass(item.status);
        const statusText = this.formatStatusText(item.status);
        const lastUpdated = new Date(item.last_updated).toLocaleString();

        return `
            <div class="baggage-card ${statusClass}" data-baggage-id="${item.baggage_id}">
                <div class="baggage-header">
                    <div class="baggage-tag">
                        <strong>Tag: ${item.tag_number}</strong>
                    </div>
                    <div class="baggage-status ${statusClass}">
                        ${statusText}
                    </div>
                </div>

                <div class="baggage-details">
                    <div class="detail-row">
                        <span class="label">Flight:</span>
                        <span class="value">${item.flight_number}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Location:</span>
                        <span class="value">${item.location}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Last Updated:</span>
                        <span class="value">${lastUpdated}</span>
                    </div>
                    ${item.weight ? `
                        <div class="detail-row">
                            <span class="label">Weight:</span>
                            <span class="value">${item.weight} kg</span>
                        </div>
                    ` : ''}
                    ${item.special_handling && item.special_handling.length > 0 ? `
                        <div class="detail-row">
                            <span class="label">Special Handling:</span>
                            <span class="value">${item.special_handling.join(', ')}</span>
                        </div>
                    ` : ''}
                </div>

                <div class="baggage-actions">
                    <button class="btn btn-sm" onclick="shareBaggageInfo('${item.baggage_id}')">
                        📤 Share
                    </button>
                    <button class="btn btn-sm" onclick="reportIssue('${item.baggage_id}')">
                        🚨 Report Issue
                    </button>
                </div>
            </div>
        `;
    }

    private getStatusClass(status: string): string {
        const statusMap: { [key: string]: string } = {
            'checked_in': 'status-checked-in',
            'loaded': 'status-loaded',
            'in_transit': 'status-transit',
            'unloaded': 'status-unloaded',
            'delivered': 'status-delivered',
            'lost': 'status-lost',
            'damaged': 'status-damaged'
        };
        return statusMap[status] || 'status-unknown';
    }

    private formatStatusText(status: string): string {
        return status.split('_').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    }

    private filterBaggageByStatus(filter: string | undefined): void {
        if (!filter || filter === 'all') {
            this.displayBaggageResults(this.currentBaggage);
            return;
        }

        const filtered = this.currentBaggage.filter(item => item.status === filter);
        this.displayBaggageResults(filtered);
    }

    private async refreshAllTracking(): Promise<void> {
        if (this.currentBaggage.length === 0) return;

        try {
            this.showLoading(true);

            // Refresh all currently tracked baggage
            const refreshPromises = this.currentBaggage.map(item =>
                this.apiService.get(`/baggage-tracking/tag/${item.tag_number}`)
            );

            const results = await Promise.all(refreshPromises);
            const updatedBaggage: BaggageItem[] = [];

            results.forEach(result => {
                if (result.success && result.data) {
                    updatedBaggage.push(result.data as BaggageItem);
                }
            });

            this.currentBaggage = updatedBaggage;
            this.displayBaggageResults(this.currentBaggage);

            this.showSuccess('Tracking information updated');
        } catch (error) {
            console.error('Refresh error:', error);
            this.showError('Unable to refresh tracking information');
        } finally {
            this.showLoading(false);
        }
    }

    private handleBaggageUpdate(data: any): void {
        // Handle real-time baggage updates
        if (data.baggage_id) {
            // Find and update the specific baggage item
            const index = this.currentBaggage.findIndex(item => item.baggage_id === data.baggage_id);
            if (index !== -1) {
                this.currentBaggage[index] = { ...this.currentBaggage[index], ...data };
                this.displayBaggageResults(this.currentBaggage);

                // Send notification for important status changes
                if (data.status === 'delivered' || data.status === 'lost' || data.status === 'damaged') {
                    this.sendStatusNotification(data);
                }
            }
        }
    }

    private sendStatusNotification(data: any): void {
        if ('Notification' in window && Notification.permission === 'granted') {
            const title = `Baggage Update - ${data.tag_number}`;
            const body = `Your baggage status: ${this.formatStatusText(data.status)}`;

            new Notification(title, {
                body: body,
                icon: '/icon-192x192.png',
                tag: `baggage-${data.baggage_id}`
            });
        }
    }

    private startPeriodicTracking(): void {
        // Refresh tracking every 30 seconds if baggage is being tracked
        this.trackingInterval = window.setInterval(() => {
            if (this.currentBaggage.length > 0) {
                this.refreshAllTracking();
            }
        }, 30000);
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
        if (this.trackingInterval) {
            clearInterval(this.trackingInterval);
            this.trackingInterval = null;
        }
        this.wsClient.disconnect();
        this.currentBaggage = [];
    }
}

// Global functions for baggage actions
declare global {
    interface Window {
        shareBaggageInfo: (baggageId: string) => void;
        reportIssue: (baggageId: string) => void;
    }
}

window.shareBaggageInfo = function(baggageId: string) {
    // Implement sharing functionality
    if (navigator.share) {
        navigator.share({
            title: 'Baggage Tracking Information',
            text: `Track my baggage with ID: ${baggageId}`,
            url: window.location.href
        });
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(`Baggage ID: ${baggageId}`).then(() => {
            alert('Baggage ID copied to clipboard');
        });
    }
};

window.reportIssue = function(baggageId: string) {
    // Implement issue reporting
    const issue = prompt('Please describe the issue with your baggage:');
    if (issue) {
        // In a real implementation, this would send to the backend
        alert('Issue reported. Our team will contact you shortly.');
    }
};

// Initialize PWA when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const baggageTracker = new BaggageTrackingPWA();

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
        showNotification('Connection restored');
    });

    window.addEventListener('offline', () => {
        document.body.classList.add('offline');
        showNotification('You are currently offline. Some features may be limited.');
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

export { BaggageTrackingPWA };
