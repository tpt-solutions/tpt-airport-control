/**
 * IPC Bridge for Electron <-> Frontend communication
 * Provides safe fallback when running in standard browser environment
 */

interface IPCBridge {
  invoke: (channel: string, ...args: any[]) => Promise<any>;
  send: (channel: string, ...args: any[]) => void;
  on: (channel: string, listener: (...args: any[]) => void) => void;
  off: (channel: string, listener: (...args: any[]) => void) => void;
  removeAllListeners: (channel: string) => void;
}

declare global {
  interface Window {
    electron?: {
      ipcRenderer: IPCBridge;
    };
  }
}

class IPCBridgeService {
  private isElectron: boolean;
  private mockListeners: Map<string, Set<(...args: any[]) => void>>;

  constructor() {
    this.isElectron = !!window.electron?.ipcRenderer;
    this.mockListeners = new Map();
    
    // Bind all public methods to preserve 'this' context
    this.invoke = this.invoke.bind(this);
    this.send = this.send.bind(this);
    this.on = this.on.bind(this);
    this.off = this.off.bind(this);
    this.removeAllListeners = this.removeAllListeners.bind(this);
    this.onStreamChunk = this.onStreamChunk.bind(this);
    this.getSettings = this.getSettings.bind(this);
    this.isRunningInElectron = this.isRunningInElectron.bind(this);
    
    if (!this.isElectron) {
      console.warn('[IPC-Bridge] Running in browser environment - IPC features will be mocked');
    }
  }

  /**
   * Invoke a method on the main process and await response
   */
  public async invoke(channel: string, ...args: any[]): Promise<any> {
    if (this.isElectron) {
      return window.electron!.ipcRenderer.invoke(channel, ...args);
    }
    
    // Mock implementation for browser
    console.debug(`[IPC-Bridge] Mock invoke: ${channel}`, args);
    
    // Return sensible defaults for known channels
    switch (channel) {
      case 'getSettings':
        return {
          theme: 'system',
          notifications: true,
          autoRefresh: true,
          refreshInterval: 5000
        };
      default:
        return null;
    }
  }

  /**
   * Send a message to main process without waiting for response
   */
  public send(channel: string, ...args: any[]): void {
    if (this.isElectron) {
      window.electron!.ipcRenderer.send(channel, ...args);
      return;
    }
    
    console.debug(`[IPC-Bridge] Mock send: ${channel}`, args);
  }

  /**
   * Register listener for events from main process
   */
  public on(channel: string, listener: (...args: any[]) => void): void {
    if (this.isElectron) {
      window.electron!.ipcRenderer.on(channel, listener);
      return;
    }
    
    if (!this.mockListeners.has(channel)) {
      this.mockListeners.set(channel, new Set());
    }
    this.mockListeners.get(channel)!.add(listener);
    console.debug(`[IPC-Bridge] Mock listener registered: ${channel}`);
  }

  /**
   * Remove event listener
   */
  public off(channel: string, listener: (...args: any[]) => void): void {
    if (this.isElectron) {
      window.electron!.ipcRenderer.off(channel, listener);
      return;
    }
    
    if (this.mockListeners.has(channel)) {
      this.mockListeners.get(channel)!.delete(listener);
    }
  }

  /**
   * Remove all listeners for a channel
   */
  public removeAllListeners(channel: string): void {
    if (this.isElectron) {
      window.electron!.ipcRenderer.removeAllListeners(channel);
      return;
    }
    
    this.mockListeners.delete(channel);
  }

  /**
   * Handle stream chunk events (specific implementation from error stack)
   */
  public onStreamChunk(callback: (chunk: any) => void): void {
    this.on('stream:chunk', callback);
  }

  /**
   * Get application settings
   */
  public async getSettings(): Promise<any> {
    return this.invoke('getSettings');
  }

  /**
   * Check if running in Electron environment
   */
  public isRunningInElectron(): boolean {
    return this.isElectron;
  }
}

// Export singleton instance
export const ipcBridge = new IPCBridgeService();