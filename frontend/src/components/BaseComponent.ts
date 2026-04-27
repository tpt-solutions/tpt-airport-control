// Base Component class for all UI components
export abstract class Component {
  protected element: HTMLElement | null = null;

  constructor() {
    this.element = null;
  }

  // Abstract method to render component HTML
  abstract render(): string;

  // Method to mount component to DOM
  mount(): void {
    // Default implementation - can be overridden
  }

  // Method to unmount component from DOM
  unmount(): void {
    if (this.element) {
      this.element.remove();
      this.element = null;
    }
  }

  // Method to update component
  update(): void {
    if (this.element) {
      this.element.innerHTML = this.render();
      this.mount();
    }
  }

  // Method to get component element
  getElement(): HTMLElement | null {
    return this.element;
  }

  // Method to set component element
  setElement(element: HTMLElement): void {
    this.element = element;
  }
}
