/**
 * Self Check-in PWA Interface
 *
 * Progressive Web App for passenger self check-in with QR code generation,
 * biometric verification, and real-time flight information
 */

import { ApiService } from './services/ApiService';
import { FlightControlWebSocket } from './websocket-client';

interface Passenger {
    passenger_id: string;
    first_name: string;
    last_name: string;
    passport_number: string;
    flight_number: string;
    seat_number?: string;
}

interface CheckInData {
    passenger_id: string;
    flight_id: string;
    seat_selection?: string;
    special_requests?: string[];
    biometric_verified?: boolean;
}

class SelfCheckInPWA {
    private apiService: ApiService;
    private wsClient: FlightControlWebSocket;
    private currentPassenger: Passenger | null = null;
    private currentStep: number = 1;
    private selectedSeat: string | null = null;

    constructor() {
        this.apiService = new ApiService();
        this.wsClient = new FlightControlWebSocket('ws://localhost:8080');
        this.initializePWA();
        this.bindEvents();
        this.showStep(1);
    }

    private initializePWA(): void {
        // Register service worker for offline functionality
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('Service Worker registered:', registration);
                })
                .catch(error => {
                    console.log('Service Worker registration failed:', error);
                });
        }

        // Request notification permission for flight updates
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Initialize WebSocket connection for real-time updates
        this.wsClient.on('flightUpdate', (data: any) => {
            this.handleWebSocketMessage(data);
        });
        this.wsClient.on('connected', () => {
            console.log('WebSocket connected for self-checkin');
        });
    }

    private bindEvents(): void {
        // Step 1: Passenger identification
        const passportForm = document.getElementById('passport-form');
        if (passportForm) {
            passportForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handlePassportLookup();
            });
        }

        // Step 2: Flight verification
        const flightSelect = document.getElementById('flight-select');
        if (flightSelect) {
            flightSelect.addEventListener('change', () => {
                this.handleFlightSelection();
            });
        }

        // Step 3: Seat selection
        const seatMap = document.getElementById('seat-map');
        if (seatMap) {
            seatMap.addEventListener('click', (e) => {
                const target = e.target as HTMLElement;
                if (target.classList.contains('seat')) {
                    this.handleSeatSelection(target);
                }
            });
        }

        // Step 4: Special services
        const specialServicesForm = document.getElementById('special-services-form');
        if (specialServicesForm) {
            specialServicesForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleSpecialServices();
            });
        }

        // Step 5: Biometric verification
        const biometricBtn = document.getElementById('biometric-verify-btn');
        if (biometricBtn) {
            biometricBtn.addEventListener('click', () => {
                this.handleBiometricVerification();
            });
        }

        // Step 6: Final confirmation
        const confirmBtn = document.getElementById('confirm-checkin-btn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                this.handleCheckInConfirmation();
            });
        }

        // Navigation buttons
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.target as HTMLElement;
                const direction = target.dataset.direction;
                if (direction === 'next') {
                    this.nextStep();
                } else if (direction === 'prev') {
                    this.prevStep();
                }
            });
        });
    }

    private showStep(step: number): void {
        // Hide all steps
        document.querySelectorAll('.checkin-step').forEach(stepEl => {
            stepEl.classList.add('hidden');
        });

        // Show current step
        const currentStepEl = document.getElementById(`step-${step}`);
        if (currentStepEl) {
            currentStepEl.classList.remove('hidden');
        }

        // Update progress indicator
        this.updateProgress(step);

        this.currentStep = step;
    }

    private updateProgress(step: number): void {
        const progressBar = document.getElementById('progress-bar');
        if (progressBar) {
            const progress = (step / 6) * 100;
            progressBar.style.width = `${progress}%`;
        }

        // Update step indicators
        document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
            if (index + 1 <= step) {
                indicator.classList.add('completed');
            } else {
                indicator.classList.remove('completed');
            }
        });
    }

    private nextStep(): void {
        if (this.currentStep < 6) {
            this.showStep(this.currentStep + 1);
        }
    }

    private prevStep(): void {
        if (this.currentStep > 1) {
            this.showStep(this.currentStep - 1);
        }
    }

    private async handlePassportLookup(): Promise<void> {
        const passportInput = document.getElementById('passport-number') as HTMLInputElement;
        const lastNameInput = document.getElementById('last-name') as HTMLInputElement;

        if (!passportInput?.value || !lastNameInput?.value) {
            this.showError('Please enter both passport number and last name');
            return;
        }

        try {
            this.showLoading(true);

            // Call backend API to find passenger
            const response = await this.apiService.post('/passengers/lookup', {
                passport_number: passportInput.value,
                last_name: lastNameInput.value
            });

            if (response.success && response.data) {
                this.currentPassenger = response.data as Passenger;
                this.showPassengerInfo();
                this.nextStep();
            } else {
                this.showError('Passenger not found. Please check your information.');
            }
        } catch (error) {
            console.error('Passport lookup error:', error);
            this.showError('Unable to verify passenger information. Please try again.');
        } finally {
            this.showLoading(false);
        }
    }

    private showPassengerInfo(): void {
        if (!this.currentPassenger) return;

        const passengerInfo = document.getElementById('passenger-info');
        if (passengerInfo) {
            passengerInfo.innerHTML = `
                <div class="passenger-card">
                    <h3>${this.currentPassenger.first_name} ${this.currentPassenger.last_name}</h3>
                    <p>Passport: ${this.currentPassenger.passport_number}</p>
                    <p>Flight: ${this.currentPassenger.flight_number}</p>
                    ${this.currentPassenger.seat_number ? `<p>Seat: ${this.currentPassenger.seat_number}</p>` : ''}
                </div>
            `;
        }
    }

    private async handleFlightSelection(): Promise<void> {
        const flightSelect = document.getElementById('flight-select') as HTMLSelectElement;
        if (!flightSelect?.value) return;

        try {
            // Load flight details and seat map
            const response = await this.apiService.get(`/flights/${flightSelect.value}`);
            if (response.success) {
                this.renderSeatMap(response.data.seats);
            }
        } catch (error) {
            console.error('Flight selection error:', error);
            this.showError('Unable to load flight information.');
        }
    }

    private renderSeatMap(seats: any[]): void {
        const seatMap = document.getElementById('seat-map');
        if (!seatMap) return;

        seatMap.innerHTML = '';

        seats.forEach(seat => {
            const seatEl = document.createElement('div');
            seatEl.className = `seat ${seat.status}`;
            seatEl.dataset.seatId = seat.seat_id;
            seatEl.textContent = seat.seat_number;

            if (seat.status === 'available') {
                seatEl.classList.add('available');
            } else if (seat.status === 'occupied') {
                seatEl.classList.add('occupied');
            }

            seatMap.appendChild(seatEl);
        });
    }

    private handleSeatSelection(seatElement: HTMLElement): void {
        if (!seatElement.classList.contains('available')) return;

        // Clear previous selection
        document.querySelectorAll('.seat.selected').forEach(el => {
            el.classList.remove('selected');
        });

        // Select new seat
        seatElement.classList.add('selected');
        this.selectedSeat = seatElement.dataset.seatId || null;

        // Update seat display
        const selectedSeatDisplay = document.getElementById('selected-seat-display');
        if (selectedSeatDisplay) {
            selectedSeatDisplay.textContent = seatElement.textContent || '';
        }
    }

    private async handleSpecialServices(): Promise<void> {
        const specialRequests: string[] = [];
        const checkboxes = document.querySelectorAll('input[name="special_request"]:checked');

        checkboxes.forEach(checkbox => {
            const input = checkbox as HTMLInputElement;
            if (input.value) {
                specialRequests.push(input.value);
            }
        });

        if (specialRequests.length > 0) {
            try {
                // Submit special services request
                await this.apiService.post('/special-services', {
                    passenger_id: this.currentPassenger?.passenger_id,
                    service_type: 'checkin_assistance',
                    request_details: specialRequests.join(', ')
                });
            } catch (error) {
                console.error('Special services error:', error);
                // Continue with check-in even if special services fail
            }
        }

        this.nextStep();
    }

    private async handleBiometricVerification(): Promise<void> {
        try {
            this.showLoading(true);

            // Simulate biometric verification (in real implementation, this would integrate with device camera/fingerprint scanner)
            const response = await this.apiService.post('/biometric/verify', {
                passenger_id: this.currentPassenger?.passenger_id,
                biometric_type: 'facial',
                verification_method: 'live_scan'
            });

            if (response.success) {
                this.showSuccess('Biometric verification successful!');
                this.nextStep();
            } else {
                this.showError('Biometric verification failed. Please try again.');
            }
        } catch (error) {
            console.error('Biometric verification error:', error);
            this.showError('Biometric verification failed. Please try manual verification.');
        } finally {
            this.showLoading(false);
        }
    }

    private async handleCheckInConfirmation(): Promise<void> {
        if (!this.currentPassenger || !this.selectedSeat) {
            this.showError('Please complete all required information.');
            return;
        }

        try {
            this.showLoading(true);

            const checkInData: CheckInData = {
                passenger_id: this.currentPassenger.passenger_id,
                flight_id: this.currentPassenger.flight_number,
                seat_selection: this.selectedSeat,
                biometric_verified: true
            };

            const response = await this.apiService.post('/checkin', checkInData);

            if (response.success) {
                this.showCheckInComplete(response.data);
                this.generateBoardingPass(response.data);
            } else {
                this.showError('Check-in failed. Please try again.');
            }
        } catch (error) {
            console.error('Check-in error:', error);
            this.showError('Check-in failed. Please contact airline staff.');
        } finally {
            this.showLoading(false);
        }
    }

    private showCheckInComplete(data: any): void {
        const completionStep = document.getElementById('step-6');
        if (completionStep) {
            completionStep.innerHTML = `
                <div class="checkin-complete">
                    <div class="success-icon">✓</div>
                    <h2>Check-in Complete!</h2>
                    <p>You have successfully checked in for your flight.</p>
                    <div class="flight-info">
                        <p><strong>Flight:</strong> ${data.flight_number}</p>
                        <p><strong>Seat:</strong> ${data.seat_number}</p>
                        <p><strong>Boarding Time:</strong> ${data.boarding_time}</p>
                        <p><strong>Gate:</strong> ${data.gate_number}</p>
                    </div>
                    <div class="qr-code" id="boarding-pass-qr"></div>
                    <button class="btn btn-primary" onclick="window.print()">Print Boarding Pass</button>
                </div>
            `;
        }
    }

    private generateBoardingPass(data: any): void {
        // Generate QR code for boarding pass
        const qrCode = document.getElementById('boarding-pass-qr');
        if (qrCode) {
            // In a real implementation, use a QR code library
            qrCode.innerHTML = `
                <div class="qr-placeholder">
                    <p>Boarding Pass QR Code</p>
                    <small>Scan at gate for boarding</small>
                </div>
            `;
        }

        // Send notification
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Check-in Complete!', {
                body: `Your boarding pass for flight ${data.flight_number} is ready.`,
                icon: '/icon-192x192.png'
            });
        }
    }

    private handleWebSocketMessage(data: any): void {
        // Handle real-time updates
        if (data.type === 'flight_update' && this.currentPassenger) {
            if (data.flight_number === this.currentPassenger.flight_number) {
                this.showNotification(`Flight ${data.flight_number}: ${data.message}`);
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
}

// Initialize PWA when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new SelfCheckInPWA();
});

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
    showNotification('You are currently offline');
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
