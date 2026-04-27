/**
 * PWA Install Prompt Management
 *
 * Handles Progressive Web App installation prompts and user experience
 */

interface BeforeInstallPromptEvent extends Event {
  readonly platforms: string[];
  readonly userChoice: Promise<{
    outcome: 'accepted' | 'dismissed';
    platform: string;
  }>;
  prompt(): Promise<void>;
}

class PWAInstallManager {
  private deferredPrompt: BeforeInstallPromptEvent | null = null;
  private installButton: HTMLElement | null = null;
  private dismissButton: HTMLElement | null = null;
  private installPrompt: HTMLElement | null = null;
  private isInstalled = false;
  private dismissedUntil: number = 0;

  constructor() {
    this.initialize();
  }

  private initialize(): void {
    // Check if already installed
    this.checkIfInstalled();

    // Listen for the beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      this.deferredPrompt = e as BeforeInstallPromptEvent;
      this.showInstallPrompt();
    });

    // Listen for successful installation
    window.addEventListener('appinstalled', () => {
      this.handleSuccessfulInstall();
    });

    // Check for standalone mode (already installed)
    if (this.isInStandaloneMode()) {
      this.isInstalled = true;
    }

    // Create install prompt elements
    this.createInstallPrompt();
  }

  private checkIfInstalled(): void {
    // Check if running in standalone mode
    if (window.matchMedia('(display-mode: standalone)').matches) {
      this.isInstalled = true;
      return;
    }

    // Check if running as PWA
    if ('standalone' in window.navigator && (window.navigator as any).standalone === true) {
      this.isInstalled = true;
      return;
    }

    // Check localStorage for previous dismissal
    const dismissedUntil = localStorage.getItem('pwa-install-dismissed-until');
    if (dismissedUntil) {
      this.dismissedUntil = parseInt(dismissedUntil);
      if (Date.now() < this.dismissedUntil) {
        return; // Still dismissed
      }
    }
  }

  private isInStandaloneMode(): boolean {
    return window.matchMedia('(display-mode: standalone)').matches ||
           ('standalone' in window.navigator && (window.navigator as any).standalone === true);
  }

  private createInstallPrompt(): void {
    // Create the install prompt container
    const promptContainer = document.createElement('div');
    promptContainer.id = 'pwa-install-prompt';
    promptContainer.className = 'fixed bottom-4 left-4 right-4 md:left-auto md:right-4 md:w-96 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50 transform translate-y-full transition-transform duration-300 ease-in-out';
    promptContainer.innerHTML = `
      <div class="p-4">
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
              </svg>
            </div>
          </div>
          <div class="ml-3 flex-1">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
              Install Flight Control
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Install our app for a better experience with offline access and native features.
            </p>
            <div class="mt-4 flex space-x-3">
              <button id="pwa-install-button" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200">
                Install App
              </button>
              <button id="pwa-dismiss-button" class="flex-1 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200">
                Not Now
              </button>
            </div>
          </div>
          <div class="ml-4 flex-shrink-0">
            <button id="pwa-close-button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(promptContainer);

    // Get references to elements
    this.installPrompt = promptContainer;
    this.installButton = promptContainer.querySelector('#pwa-install-button');
    this.dismissButton = promptContainer.querySelector('#pwa-dismiss-button');

    // Add event listeners
    this.installButton?.addEventListener('click', () => this.installApp());
    this.dismissButton?.addEventListener('click', () => this.dismissPrompt());
    promptContainer.querySelector('#pwa-close-button')?.addEventListener('click', () => this.dismissPrompt());

    // Hide initially
    this.hideInstallPrompt();
  }

  private showInstallPrompt(): void {
    if (this.isInstalled || Date.now() < this.dismissedUntil) {
      return;
    }

    if (this.installPrompt) {
      this.installPrompt.classList.remove('translate-y-full');
      this.installPrompt.classList.add('translate-y-0');

      // Track prompt shown
      this.trackEvent('pwa_install_prompt_shown');
    }
  }

  private hideInstallPrompt(): void {
    if (this.installPrompt) {
      this.installPrompt.classList.remove('translate-y-0');
      this.installPrompt.classList.add('translate-y-full');
    }
  }

  private async installApp(): Promise<void> {
    if (!this.deferredPrompt) {
      console.warn('Install prompt not available');
      return;
    }

    try {
      // Show the install prompt
      await this.deferredPrompt.prompt();

      // Wait for the user to respond to the prompt
      const { outcome } = await this.deferredPrompt.userChoice;

      if (outcome === 'accepted') {
        this.trackEvent('pwa_install_accepted');
        this.handleSuccessfulInstall();
      } else {
        this.trackEvent('pwa_install_dismissed');
      }

      // Clear the deferred prompt
      this.deferredPrompt = null;

    } catch (error) {
      console.error('Error during app installation:', error);
      this.trackEvent('pwa_install_error');
    }

    // Hide the prompt
    this.hideInstallPrompt();
  }

  private dismissPrompt(): void {
    // Dismiss for 24 hours
    this.dismissedUntil = Date.now() + (24 * 60 * 60 * 1000);
    localStorage.setItem('pwa-install-dismissed-until', this.dismissedUntil.toString());

    this.hideInstallPrompt();
    this.trackEvent('pwa_install_prompt_dismissed');
  }

  private handleSuccessfulInstall(): void {
    this.isInstalled = true;
    this.hideInstallPrompt();

    // Show success message
    this.showInstallSuccessMessage();

    this.trackEvent('pwa_install_success');
  }

  private showInstallSuccessMessage(): void {
    const successMessage = document.createElement('div');
    successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300 ease-in-out';
    successMessage.innerHTML = `
      <div class="flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <span class="font-medium">App installed successfully!</span>
      </div>
    `;

    document.body.appendChild(successMessage);

    // Show the message
    setTimeout(() => {
      successMessage.classList.remove('translate-x-full');
      successMessage.classList.add('translate-x-0');
    }, 100);

    // Hide after 3 seconds
    setTimeout(() => {
      successMessage.classList.remove('translate-x-0');
      successMessage.classList.add('translate-x-full');
      setTimeout(() => {
        document.body.removeChild(successMessage);
      }, 300);
    }, 3000);
  }

  // Public API methods
  public showInstallPromptManually(): void {
    if (!this.isInstalled && this.deferredPrompt) {
      this.showInstallPrompt();
    }
  }

  public isAppInstalled(): boolean {
    return this.isInstalled;
  }

  public canInstall(): boolean {
    return !this.isInstalled && this.deferredPrompt !== null;
  }

  public resetDismissal(): void {
    this.dismissedUntil = 0;
    localStorage.removeItem('pwa-install-dismissed-until');
  }

  // Analytics tracking
  private trackEvent(eventName: string, properties: Record<string, any> = {}): void {
    // Send to analytics service
    if (typeof window !== 'undefined' && (window as any).gtag) {
      (window as any).gtag('event', eventName, {
        event_category: 'pwa',
        event_label: 'flight_control_app',
        ...properties
      });
    }

    // Also log to console for debugging
    console.log(`PWA Event: ${eventName}`, properties);
  }

  // Get install statistics
  public getInstallStats(): {
    canInstall: boolean;
    isInstalled: boolean;
    dismissedUntil: number;
    timeUntilAvailable: number;
  } {
    const now = Date.now();
    return {
      canInstall: this.canInstall(),
      isInstalled: this.isInstalled,
      dismissedUntil: this.dismissedUntil,
      timeUntilAvailable: Math.max(0, this.dismissedUntil - now)
    };
  }
}

// Create global instance
const pwaInstallManager = new PWAInstallManager();

// Export for use in other modules
export { pwaInstallManager, PWAInstallManager };

// Auto-show prompt after page load (with delay)
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
      if (pwaInstallManager.canInstall()) {
        pwaInstallManager.showInstallPromptManually();
      }
    }, 3000); // Show after 3 seconds
  });
} else {
  setTimeout(() => {
    if (pwaInstallManager.canInstall()) {
      pwaInstallManager.showInstallPromptManually();
    }
  }, 3000);
}

// Add to window for debugging
if (typeof window !== 'undefined') {
  (window as any).pwaInstallManager = pwaInstallManager;
}
