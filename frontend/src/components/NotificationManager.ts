// Notification System
export class NotificationManager {
  private container: HTMLElement;

  constructor(container: HTMLElement) {
    this.container = container;
  }

  show(message: string, type: 'success' | 'error' | 'warning' | 'info' = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-sm ${
      type === 'success' ? 'bg-green-500' :
      type === 'error' ? 'bg-red-500' :
      type === 'warning' ? 'bg-yellow-500' :
      'bg-blue-500'
    } text-white`;

    const inner = document.createElement('div');
    inner.className = 'flex items-center';

    const msgEl = document.createElement('div');
    msgEl.className = 'flex-1';
    msgEl.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 text-white hover:text-gray-200';
    closeBtn.textContent = '×';
    closeBtn.addEventListener('click', () => notification.remove());

    inner.appendChild(msgEl);
    inner.appendChild(closeBtn);
    notification.appendChild(inner);

    this.container.appendChild(notification);

    // Auto remove after duration
    setTimeout(() => {
      if (notification.parentElement) {
        notification.remove();
      }
    }, duration);
  }

  success(message: string, duration?: number) {
    this.show(message, 'success', duration);
  }

  error(message: string, duration?: number) {
    this.show(message, 'error', duration);
  }

  warning(message: string, duration?: number) {
    this.show(message, 'warning', duration);
  }

  info(message: string, duration?: number) {
    this.show(message, 'info', duration);
  }
}
