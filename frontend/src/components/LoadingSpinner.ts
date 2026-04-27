// Loading Spinner Component
export class LoadingSpinner {
  private container: HTMLElement;
  private size: 'sm' | 'md' | 'lg' = 'md';
  private color: string = 'blue';

  constructor(container: HTMLElement, size: 'sm' | 'md' | 'lg' = 'md', color: string = 'blue') {
    this.container = container;
    this.size = size;
    this.color = color;
  }

  show() {
    const sizeClasses = {
      sm: 'w-4 h-4',
      md: 'w-8 h-8',
      lg: 'w-12 h-12'
    };

    const colorClasses = {
      blue: 'border-blue-500',
      green: 'border-green-500',
      red: 'border-red-500',
      gray: 'border-gray-500'
    };

    this.container.innerHTML = `
      <div class="flex items-center justify-center">
        <div class="animate-spin rounded-full border-2 border-t-transparent ${sizeClasses[this.size]} ${colorClasses[this.color as keyof typeof colorClasses]}"></div>
      </div>
    `;
  }

  hide() {
    this.container.innerHTML = '';
  }
}
