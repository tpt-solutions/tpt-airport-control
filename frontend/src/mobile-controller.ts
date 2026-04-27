/**
 * Mobile Controller for Airport Operations Simulator
 * Touch-optimized controls for mobile and tablet devices
 */

export class MobileController {
    private touchStartX: number = 0;
    private touchStartY: number = 0;
    private touchEndX: number = 0;
    private touchEndY: number = 0;
    private isDragging: boolean = false;
    private dragThreshold: number = 50;
    private longPressTimer: number | null = null;
    private longPressDelay: number = 500;
    private pinchStartDistance: number = 0;
    private isPinching: boolean = false;

    // Callbacks for different interactions
    private onSwipeLeft?: () => void;
    private onSwipeRight?: () => void;
    private onSwipeUp?: () => void;
    private onSwipeDown?: () => void;
    private onTap?: (x: number, y: number) => void;
    private onDoubleTap?: (x: number, y: number) => void;
    private onLongPress?: (x: number, y: number) => void;
    private onPinch?: (scale: number, centerX: number, centerY: number) => void;
    private onDrag?: (deltaX: number, deltaY: number, x: number, y: number) => void;
    private onDragEnd?: (x: number, y: number) => void;

    private lastTapTime: number = 0;
    private doubleTapDelay: number = 300;
    private element: HTMLElement;

    constructor(element: HTMLElement) {
        this.element = element;
        this.initializeEventListeners();
        this.setupMobileOptimizations();
    }

    private initializeEventListeners(): void {
        // Touch events
        this.element.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: false });
        this.element.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
        this.element.addEventListener('touchend', this.handleTouchEnd.bind(this), { passive: false });

        // Mouse events for desktop testing
        this.element.addEventListener('mousedown', this.handleMouseDown.bind(this));
        this.element.addEventListener('mousemove', this.handleMouseMove.bind(this));
        this.element.addEventListener('mouseup', this.handleMouseUp.bind(this));

        // Prevent default touch behaviors that interfere with the app
        this.element.addEventListener('touchstart', (e) => {
            if (this.shouldPreventDefault(e)) {
                e.preventDefault();
            }
        }, { passive: false });

        this.element.addEventListener('touchmove', (e) => {
            if (this.shouldPreventDefault(e)) {
                e.preventDefault();
            }
        }, { passive: false });

        // Context menu prevention on mobile
        this.element.addEventListener('contextmenu', (e) => {
            e.preventDefault();
        });
    }

    private shouldPreventDefault(event: TouchEvent): boolean {
        // Prevent default for multi-touch gestures and certain elements
        return event.touches.length > 1 ||
               event.target instanceof HTMLInputElement ||
               event.target instanceof HTMLTextAreaElement ||
               event.target instanceof HTMLSelectElement;
    }

    private handleTouchStart(event: TouchEvent): void {
        const touch = event.touches[0];
        this.touchStartX = touch.clientX;
        this.touchStartY = touch.clientY;

        // Handle multi-touch (pinch)
        if (event.touches.length === 2) {
            this.handlePinchStart(event);
        }

        // Start long press timer
        this.startLongPressTimer(touch.clientX, touch.clientY);

        // Reset dragging state
        this.isDragging = false;
    }

    private handleTouchMove(event: TouchEvent): void {
        const touch = event.touches[0];
        this.touchEndX = touch.clientX;
        this.touchEndY = touch.clientY;

        // Handle multi-touch (pinch)
        if (event.touches.length === 2) {
            this.handlePinchMove(event);
            return;
        }

        // Handle single touch drag
        const deltaX = this.touchEndX - this.touchStartX;
        const deltaY = this.touchEndY - this.touchStartY;

        // Check if this is a drag gesture
        if (Math.abs(deltaX) > 10 || Math.abs(deltaY) > 10) {
            this.isDragging = true;
            this.cancelLongPressTimer();

            if (this.onDrag) {
                this.onDrag(deltaX, deltaY, this.touchEndX, this.touchEndY);
            }
        }
    }

    private handleTouchEnd(event: TouchEvent): void {
        this.cancelLongPressTimer();

        // Handle pinch end
        if (this.isPinching) {
            this.isPinching = false;
            return;
        }

        // Calculate swipe distance
        const deltaX = this.touchEndX - this.touchStartX;
        const deltaY = this.touchEndY - this.touchStartY;
        const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);

        // Check for swipe gestures
        if (distance > this.dragThreshold && !this.isDragging) {
            this.handleSwipe(deltaX, deltaY);
        } else if (!this.isDragging) {
            // Handle tap gestures
            this.handleTap(this.touchEndX, this.touchEndY);
        } else if (this.onDragEnd) {
            // Handle drag end
            this.onDragEnd(this.touchEndX, this.touchEndY);
        }

        // Reset state
        this.isDragging = false;
    }

    private handlePinchStart(event: TouchEvent): void {
        const touch1 = event.touches[0];
        const touch2 = event.touches[1];
        this.pinchStartDistance = this.getTouchDistance(touch1, touch2);
        this.isPinching = true;
        this.cancelLongPressTimer();
    }

    private handlePinchMove(event: TouchEvent): void {
        if (!this.isPinching || event.touches.length !== 2) return;

        const touch1 = event.touches[0];
        const touch2 = event.touches[1];
        const currentDistance = this.getTouchDistance(touch1, touch2);
        const scale = currentDistance / this.pinchStartDistance;

        const centerX = (touch1.clientX + touch2.clientX) / 2;
        const centerY = (touch1.clientY + touch2.clientY) / 2;

        if (this.onPinch) {
            this.onPinch(scale, centerX, centerY);
        }
    }

    private getTouchDistance(touch1: Touch, touch2: Touch): number {
        const dx = touch1.clientX - touch2.clientX;
        const dy = touch1.clientY - touch2.clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    private handleSwipe(deltaX: number, deltaY: number): void {
        const absX = Math.abs(deltaX);
        const absY = Math.abs(deltaY);

        if (absX > absY) {
            // Horizontal swipe
            if (deltaX > 0 && this.onSwipeRight) {
                this.onSwipeRight();
            } else if (deltaX < 0 && this.onSwipeLeft) {
                this.onSwipeLeft();
            }
        } else {
            // Vertical swipe
            if (deltaY > 0 && this.onSwipeDown) {
                this.onSwipeDown();
            } else if (deltaY < 0 && this.onSwipeUp) {
                this.onSwipeUp();
            }
        }
    }

    private handleTap(x: number, y: number): void {
        const currentTime = Date.now();
        const timeDiff = currentTime - this.lastTapTime;

        if (timeDiff < this.doubleTapDelay && timeDiff > 0) {
            // Double tap
            if (this.onDoubleTap) {
                this.onDoubleTap(x, y);
            }
            this.lastTapTime = 0; // Reset to prevent triple taps
        } else {
            // Single tap
            if (this.onTap) {
                this.onTap(x, y);
            }
            this.lastTapTime = currentTime;
        }
    }

    private startLongPressTimer(x: number, y: number): void {
        this.cancelLongPressTimer();
        this.longPressTimer = window.setTimeout(() => {
            if (this.onLongPress && !this.isDragging) {
                this.onLongPress(x, y);
            }
        }, this.longPressDelay);
    }

    private cancelLongPressTimer(): void {
        if (this.longPressTimer) {
            clearTimeout(this.longPressTimer);
            this.longPressTimer = null;
        }
    }

    // Mouse event handlers for desktop testing
    private handleMouseDown(event: MouseEvent): void {
        this.touchStartX = event.clientX;
        this.touchStartY = event.clientY;
        this.startLongPressTimer(event.clientX, event.clientY);
        this.isDragging = false;
    }

    private handleMouseMove(event: MouseEvent): void {
        if (event.buttons === 1) { // Left mouse button
            this.touchEndX = event.clientX;
            this.touchEndY = event.clientY;

            const deltaX = this.touchEndX - this.touchStartX;
            const deltaY = this.touchEndY - this.touchStartY;

            if (Math.abs(deltaX) > 10 || Math.abs(deltaY) > 10) {
                this.isDragging = true;
                this.cancelLongPressTimer();

                if (this.onDrag) {
                    this.onDrag(deltaX, deltaY, this.touchEndX, this.touchEndY);
                }
            }
        }
    }

    private handleMouseUp(event: MouseEvent): void {
        this.cancelLongPressTimer();

        if (!this.isDragging) {
            this.handleTap(event.clientX, event.clientY);
        } else if (this.onDragEnd) {
            this.onDragEnd(event.clientX, event.clientY);
        }

        this.isDragging = false;
    }

    private setupMobileOptimizations(): void {
        // Add mobile-specific CSS classes
        this.element.classList.add('mobile-optimized');

        // Prevent zoom on double tap
        let lastTouchEnd = 0;
        this.element.addEventListener('touchend', (event) => {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);

        // Handle viewport changes
        window.addEventListener('resize', this.handleViewportChange.bind(this));
        window.addEventListener('orientationchange', this.handleOrientationChange.bind(this));

        // Add haptic feedback support (if available)
        this.setupHapticFeedback();
    }

    private handleViewportChange(): void {
        // Adjust UI elements based on viewport size
        const viewportHeight = window.innerHeight;
        const viewportWidth = window.innerWidth;

        // Update CSS custom properties for mobile layouts
        document.documentElement.style.setProperty('--vh', `${viewportHeight * 0.01}px`);
        document.documentElement.style.setProperty('--vw', `${viewportWidth * 0.01}px`);

        // Trigger layout adjustments
        this.element.dispatchEvent(new CustomEvent('viewportchange', {
            detail: { width: viewportWidth, height: viewportHeight }
        }));
    }

    private handleOrientationChange(): void {
        // Handle orientation changes
        setTimeout(() => {
            this.handleViewportChange();
            this.element.dispatchEvent(new CustomEvent('orientationchange', {
                detail: { orientation: screen.orientation?.angle || 0 }
            }));
        }, 100);
    }

    private setupHapticFeedback(): void {
        // Check for haptic feedback support
        if ('vibrate' in navigator) {
            // Store haptic feedback functions
            (window as any).hapticFeedback = {
                light: () => navigator.vibrate(50),
                medium: () => navigator.vibrate(100),
                heavy: () => navigator.vibrate(200),
                success: () => navigator.vibrate([50, 50, 50]),
                error: () => navigator.vibrate([200, 100, 200])
            };
        }
    }

    // Public API for setting callbacks
    public setSwipeCallbacks(callbacks: {
        left?: () => void;
        right?: () => void;
        up?: () => void;
        down?: () => void;
    }): void {
        this.onSwipeLeft = callbacks.left;
        this.onSwipeRight = callbacks.right;
        this.onSwipeUp = callbacks.up;
        this.onSwipeDown = callbacks.down;
    }

    public setTapCallbacks(callbacks: {
        tap?: (x: number, y: number) => void;
        doubleTap?: (x: number, y: number) => void;
        longPress?: (x: number, y: number) => void;
    }): void {
        this.onTap = callbacks.tap;
        this.onDoubleTap = callbacks.doubleTap;
        this.onLongPress = callbacks.longPress;
    }

    public setGestureCallbacks(callbacks: {
        pinch?: (scale: number, centerX: number, centerY: number) => void;
        drag?: (deltaX: number, deltaY: number, x: number, y: number) => void;
        dragEnd?: (x: number, y: number) => void;
    }): void {
        this.onPinch = callbacks.pinch;
        this.onDrag = callbacks.drag;
        this.onDragEnd = callbacks.dragEnd;
    }

    // Utility methods
    public isMobileDevice(): boolean {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
               (window.innerWidth <= 768 && window.innerHeight <= 1024);
    }

    public getTouchCapabilities(): {
        hasTouch: boolean;
        maxTouchPoints: number;
        hasHapticFeedback: boolean;
        hasOrientationAPI: boolean;
    } {
        return {
            hasTouch: 'ontouchstart' in window || navigator.maxTouchPoints > 0,
            maxTouchPoints: navigator.maxTouchPoints || 0,
            hasHapticFeedback: 'vibrate' in navigator,
            hasOrientationAPI: 'orientation' in screen || 'orientation' in window
        };
    }

    // Cleanup method
    public destroy(): void {
        this.cancelLongPressTimer();

        // Remove event listeners
        this.element.removeEventListener('touchstart', this.handleTouchStart.bind(this));
        this.element.removeEventListener('touchmove', this.handleTouchMove.bind(this));
        this.element.removeEventListener('touchend', this.handleTouchEnd.bind(this));
        this.element.removeEventListener('mousedown', this.handleMouseDown.bind(this));
        this.element.removeEventListener('mousemove', this.handleMouseMove.bind(this));
        this.element.removeEventListener('mouseup', this.handleMouseUp.bind(this));

        window.removeEventListener('resize', this.handleViewportChange.bind(this));
        window.removeEventListener('orientationchange', this.handleOrientationChange.bind(this));
    }
}

// Mobile-specific UI utilities
export class MobileUIUtils {
    static addMobileCSS(): void {
        const style = document.createElement('style');
        style.textContent = `
            .mobile-optimized {
                touch-action: manipulation;
                -webkit-touch-callout: none;
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;
                -webkit-tap-highlight-color: transparent;
            }

            .mobile-optimized * {
                -webkit-touch-callout: none;
            }

            /* Mobile-specific button styles */
            .mobile-button {
                min-height: 44px;
                min-width: 44px;
                padding: 12px 16px;
                font-size: 16px;
                border-radius: 8px;
                transition: all 0.2s ease;
            }

            .mobile-button:active {
                transform: scale(0.95);
                opacity: 0.8;
            }

            /* Touch-friendly form elements */
            .mobile-input {
                min-height: 44px;
                font-size: 16px;
                padding: 12px;
                border-radius: 8px;
                border: 2px solid #e5e7eb;
            }

            .mobile-input:focus {
                border-color: #2563eb;
                outline: none;
            }

            /* Swipe indicators */
            .swipe-indicator {
                position: absolute;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                opacity: 0.7;
                pointer-events: none;
                animation: fade-out 3s ease-out forwards;
            }

            @keyframes fade-out {
                to { opacity: 0; }
            }

            /* Mobile navigation */
            .mobile-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                border-top: 1px solid #e5e7eb;
                padding: 8px;
                padding-bottom: calc(8px + env(safe-area-inset-bottom));
                z-index: 1000;
            }

            .mobile-nav-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 8px;
            }

            .mobile-nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 8px 4px;
                border-radius: 8px;
                transition: background-color 0.2s ease;
                font-size: 12px;
                text-align: center;
            }

            .mobile-nav-item:active {
                background-color: #f3f4f6;
            }

            .mobile-nav-icon {
                width: 24px;
                height: 24px;
                margin-bottom: 4px;
            }

            /* Mobile modal styles */
            .mobile-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: flex-end;
                z-index: 2000;
            }

            .mobile-modal-content {
                background: white;
                border-radius: 16px 16px 0 0;
                padding: 20px;
                padding-bottom: calc(20px + env(safe-area-inset-bottom));
                max-height: 80vh;
                overflow-y: auto;
                width: 100%;
                animation: slide-up 0.3s ease-out;
            }

            @keyframes slide-up {
                from { transform: translateY(100%); }
                to { transform: translateY(0); }
            }

            /* Loading spinner for mobile */
            .mobile-spinner {
                width: 40px;
                height: 40px;
                border: 4px solid #f3f4f6;
                border-top: 4px solid #2563eb;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 20px auto;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            /* Offline indicator */
            .offline-indicator {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: #ef4444;
                color: white;
                text-align: center;
                padding: 8px;
                font-size: 14px;
                z-index: 3000;
                transform: translateY(-100%);
                transition: transform 0.3s ease;
            }

            .offline-indicator.visible {
                transform: translateY(0);
            }

            /* Pull to refresh indicator */
            .pull-refresh-indicator {
                position: absolute;
                top: -50px;
                left: 50%;
                transform: translateX(-50%);
                background: #2563eb;
                color: white;
                padding: 8px 16px;
                border-radius: 20px;
                font-size: 14px;
                opacity: 0;
                transition: opacity 0.3s ease;
                pointer-events: none;
            }

            .pull-refresh-indicator.visible {
                opacity: 1;
            }
        `;
        document.head.appendChild(style);
    }

    static showSwipeIndicator(message: string = "Swipe for more options"): void {
        const indicator = document.createElement('div');
        indicator.className = 'swipe-indicator';
        indicator.textContent = message;
        document.body.appendChild(indicator);

        setTimeout(() => {
            if (indicator.parentNode) {
                indicator.parentNode.removeChild(indicator);
            }
        }, 3000);
    }

    static vibrate(pattern: number | number[] = 100): void {
        if ('vibrate' in navigator) {
            navigator.vibrate(pattern);
        }
    }

    static hapticFeedback(type: 'light' | 'medium' | 'heavy' | 'success' | 'error' = 'light'): void {
        const patterns = {
            light: 50,
            medium: 100,
            heavy: 200,
            success: [50, 50, 50],
            error: [200, 100, 200]
        };

        this.vibrate(patterns[type]);
    }

    static isOnline(): boolean {
        return navigator.onLine;
    }

    static onOnlineStatusChange(callback: (isOnline: boolean) => void): void {
        window.addEventListener('online', () => callback(true));
        window.addEventListener('offline', () => callback(false));
    }

    static showOfflineIndicator(): void {
        let indicator = document.querySelector('.offline-indicator') as HTMLElement;
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'offline-indicator';
            indicator.textContent = 'You are currently offline. Some features may be limited.';
            document.body.appendChild(indicator);
        }
        indicator.classList.add('visible');
    }

    static hideOfflineIndicator(): void {
        const indicator = document.querySelector('.offline-indicator') as HTMLElement;
        if (indicator) {
            indicator.classList.remove('visible');
        }
    }
}

// Initialize mobile optimizations when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    MobileUIUtils.addMobileCSS();

    // Monitor online/offline status
    MobileUIUtils.onOnlineStatusChange((isOnline) => {
        if (isOnline) {
            MobileUIUtils.hideOfflineIndicator();
            MobileUIUtils.hapticFeedback('success');
        } else {
            MobileUIUtils.showOfflineIndicator();
            MobileUIUtils.hapticFeedback('error');
        }
    });

    // Show initial swipe indicator for mobile users
    if (window.innerWidth <= 768) {
        setTimeout(() => {
            MobileUIUtils.showSwipeIndicator();
        }, 2000);
    }
});

export default MobileController;
