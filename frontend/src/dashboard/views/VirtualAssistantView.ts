import { DashboardApiService } from '../services/DashboardApiService.js';
import type { User } from '../types.js';

interface VirtualAssistant {
  id: number;
  user_id: number;
  name: string;
  voice_enabled: boolean;
  language: string;
  personality: string;
  voice_speed: number;
  voice_pitch: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

interface UserPreferences {
  id: number;
  user_id: number;
  preferred_language: string;
  voice_enabled: boolean;
  notification_preferences: string[];
  interaction_style: string;
  response_length: string;
  auto_listen: boolean;
  created_at: string;
  updated_at: string;
}

interface InteractionHistory {
  id: number;
  user_id: number;
  interaction_type: string;
  input: string;
  output: string;
  response_time: number;
  success: boolean;
  error_message: string;
  confidence_score: number;
  timestamp: string;
  username: string;
}

interface VoiceCommand {
  command: string;
  description: string;
  category: string;
}

interface Recommendation {
  type: string;
  title: string;
  description: string;
  priority: string;
  action: string;
}

export class VirtualAssistantView {
  private container: HTMLElement;
  private apiService: DashboardApiService;
  private assistant: VirtualAssistant | null = null;
  private preferences: UserPreferences | null = null;
  private history: InteractionHistory[] = [];
  private commands: VoiceCommand[] = [];
  private recommendations: Recommendation[] = [];
  private isListening: boolean = false;
  private recognition: any = null;

  constructor(container: HTMLElement) {
    this.container = container;
    this.apiService = new DashboardApiService();
    this.initializeSpeechRecognition();
  }

  async render(user: User): Promise<string> {
    try {
      // Fetch initial data
      await this.loadData();

      return `
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Virtual Assistant</h2>
            <div class="flex space-x-2">
              <button id="refresh-assistant" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Refresh
              </button>
              <button id="settings-assistant" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                Settings
              </button>
            </div>
          </div>

          <!-- Assistant Status -->
          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-semibold">Assistant Status</h3>
              <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-600">Status:</span>
                <span class="px-2 py-1 text-xs rounded-full ${this.assistant?.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                  ${this.assistant?.is_active ? 'Active' : 'Inactive'}
                </span>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="text-center p-4 border rounded">
                <div class="text-2xl mb-2">${this.assistant?.name || 'Assistant'}</div>
                <div class="text-sm text-gray-600">Assistant Name</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-2xl mb-2">${this.assistant?.language?.toUpperCase() || 'EN'}</div>
                <div class="text-sm text-gray-600">Language</div>
              </div>
              <div class="text-center p-4 border rounded">
                <div class="text-2xl mb-2">${this.assistant?.personality || 'Professional'}</div>
                <div class="text-sm text-gray-600">Personality</div>
              </div>
            </div>
          </div>

          <!-- Voice Interaction Panel -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Voice Interaction</h3>

            <!-- Voice Input -->
            <div class="mb-4">
              <div class="flex items-center space-x-4 mb-4">
                <button id="voice-toggle" class="px-6 py-3 ${this.isListening ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'} text-white rounded-lg flex items-center space-x-2">
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7 4a3 3 0 016 0v4a3 3 0 11-6 0V4zm4 10.93A7.001 7.001 0 0017 8a1 1 0 10-2 0A5 5 0 015 8a1 1 0 00-2 0 7.001 7.001 0 006 6.93V17H6a1 1 0 100 2h8a1 1 0 100-2h-3v-2.07z" clip-rule="evenodd"/>
                  </svg>
                  <span>${this.isListening ? 'Stop Listening' : 'Start Voice'}</span>
                </button>
                <div class="flex-1">
                  <input type="text" id="text-input" placeholder="Type your message or use voice..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <button id="send-text" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                  Send
                </button>
              </div>

              <!-- Voice Status -->
              <div id="voice-status" class="text-sm text-gray-600 ${this.isListening ? 'text-red-600' : 'text-gray-600'}">
                ${this.isListening ? '🎤 Listening... Speak now or click stop.' : 'Click "Start Voice" to begin voice interaction.'}
              </div>
            </div>

            <!-- Response Area -->
            <div id="response-area" class="min-h-32 p-4 bg-gray-50 rounded-lg">
              <div class="text-gray-500 italic">Responses will appear here...</div>
            </div>
          </div>

          <!-- Quick Commands -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Quick Commands</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              ${this.renderQuickCommands()}
            </div>
          </div>

          <!-- Recent Interactions -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Recent Interactions</h3>
            <div class="space-y-3 max-h-64 overflow-y-auto">
              ${this.history.slice(0, 10).map(interaction => this.renderInteraction(interaction)).join('') || '<p class="text-gray-500">No recent interactions</p>'}
            </div>
          </div>

          <!-- Personalized Recommendations -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Personalized Recommendations</h3>
            <div class="space-y-4">
              ${this.recommendations.map(rec => this.renderRecommendation(rec)).join('') || '<p class="text-gray-500">No recommendations available</p>'}
            </div>
          </div>

          <!-- Assistant Analytics -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Usage Statistics -->
            <div class="bg-white rounded-lg shadow p-6">
              <h3 class="text-lg font-semibold mb-4">Usage Statistics</h3>
              <div class="space-y-4">
                <div class="flex justify-between items-center">
                  <span class="text-gray-600">Total Interactions</span>
                  <span class="font-semibold">${this.history.length}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-gray-600">Voice Commands</span>
                  <span class="font-semibold">${this.history.filter(h => h.interaction_type === 'voice_command').length}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-gray-600">Text Queries</span>
                  <span class="font-semibold">${this.history.filter(h => h.interaction_type === 'text_query').length}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-gray-600">Success Rate</span>
                  <span class="font-semibold">${this.calculateSuccessRate()}%</span>
                </div>
              </div>
            </div>

            <!-- Voice Settings -->
            <div class="bg-white rounded-lg shadow p-6">
              <h3 class="text-lg font-semibold mb-4">Voice Settings</h3>
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-gray-600">Voice Enabled</span>
                  <label class="flex items-center">
                    <input type="checkbox" id="voice-enabled" ${this.preferences?.voice_enabled ? 'checked' : ''} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm">Enable voice responses</span>
                  </label>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-gray-600">Auto Listen</span>
                  <label class="flex items-center">
                    <input type="checkbox" id="auto-listen" ${this.preferences?.auto_listen ? 'checked' : ''} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm">Auto-start listening</span>
                  </label>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Voice Speed</label>
                  <input type="range" id="voice-speed" min="0.5" max="2.0" step="0.1" value="${this.assistant?.voice_speed || 1.0}" class="w-full">
                  <div class="flex justify-between text-xs text-gray-500">
                    <span>Slow</span>
                    <span id="speed-value">${this.assistant?.voice_speed || 1.0}x</span>
                    <span>Fast</span>
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Voice Pitch</label>
                  <input type="range" id="voice-pitch" min="0.5" max="2.0" step="0.1" value="${this.assistant?.voice_pitch || 1.0}" class="w-full">
                  <div class="flex justify-between text-xs text-gray-500">
                    <span>Low</span>
                    <span id="pitch-value">${this.assistant?.voice_pitch || 1.0}x</span>
                    <span>High</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      `;
    } catch (error) {
      console.error('Failed to load virtual assistant:', error);
      return `
        <div class="text-center text-red-500 p-8">
          <div class="text-6xl mb-4">🎙️</div>
          <h3 class="text-xl font-semibold mb-2">Failed to Load Virtual Assistant</h3>
          <p class="text-gray-600">Please check your connection and try again.</p>
          <button id="retry-assistant" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Retry
          </button>
        </div>
      `;
    }
  }

  private async loadData(): Promise<void> {
    try {
      const [assistantResponse, preferencesResponse, historyResponse, commandsResponse, recommendationsResponse] = await Promise.all([
        fetch('/backend/api/virtual-assistant', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch('/backend/api/virtual-assistant/preferences', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch('/backend/api/virtual-assistant/history?limit=20', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch('/backend/api/virtual-assistant/commands', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }),
        fetch('/backend/api/virtual-assistant/recommendations', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        })
      ]);

      if (assistantResponse.ok) {
        this.assistant = await assistantResponse.json();
      }

      if (preferencesResponse.ok) {
        this.preferences = await preferencesResponse.json();
      }

      if (historyResponse.ok) {
        this.history = await historyResponse.json();
      }

      if (commandsResponse.ok) {
        this.commands = await commandsResponse.json();
      }

      if (recommendationsResponse.ok) {
        this.recommendations = await recommendationsResponse.json();
      }
    } catch (error) {
      console.error('Failed to load virtual assistant data:', error);
    }
  }

  private renderQuickCommands(): string {
    const quickCommands = [
      { command: 'status', icon: '📊', label: 'System Status' },
      { command: 'weather', icon: '🌤️', label: 'Weather' },
      { command: 'time', icon: '🕐', label: 'Current Time' },
      { command: 'help', icon: '❓', label: 'Help' },
      { command: 'flight status', icon: '✈️', label: 'Flight Status' },
      { command: 'gate location', icon: '🚪', label: 'Gate Info' }
    ];

    return quickCommands.map(cmd => `
      <button class="quick-command-btn p-4 border border-gray-200 rounded-lg hover:bg-gray-50 hover:border-blue-300 transition-colors text-left" data-command="${cmd.command}">
        <div class="text-2xl mb-2">${cmd.icon}</div>
        <div class="text-sm font-medium text-gray-900">${cmd.label}</div>
      </button>
    `).join('');
  }

  private renderInteraction(interaction: InteractionHistory): string {
    const timeAgo = this.formatTimeAgo(new Date(interaction.timestamp));
    const isSuccess = interaction.success;

    return `
      <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded">
        <div class="shrink-0">
          <div class="w-8 h-8 ${isSuccess ? 'bg-green-100' : 'bg-red-100'} rounded-full flex items-center justify-center">
            <span class="text-sm">${isSuccess ? '✓' : '✗'}</span>
          </div>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center space-x-2 mb-1">
            <span class="text-sm font-medium text-gray-900">${interaction.interaction_type.replace('_', ' ')}</span>
            <span class="text-xs text-gray-500">${timeAgo}</span>
          </div>
          <p class="text-sm text-gray-600 truncate">${interaction.input}</p>
          ${interaction.output ? `<p class="text-xs text-gray-500 mt-1">${interaction.output.substring(0, 100)}${interaction.output.length > 100 ? '...' : ''}</p>` : ''}
        </div>
      </div>
    `;
  }

  private renderRecommendation(rec: Recommendation): string {
    const priorityColors = {
      high: 'bg-red-100 text-red-800 border-red-200',
      medium: 'bg-yellow-100 text-yellow-800 border-yellow-200',
      low: 'bg-green-100 text-green-800 border-green-200'
    };

    return `
      <div class="flex items-start space-x-4 p-4 border rounded-lg ${priorityColors[rec.priority as keyof typeof priorityColors]}">
        <div class="shrink-0">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
          </svg>
        </div>
        <div class="flex-1">
          <div class="flex items-center space-x-2 mb-1">
            <h4 class="text-sm font-medium">${rec.title}</h4>
            <span class="px-2 py-1 text-xs rounded-full ${priorityColors[rec.priority as keyof typeof priorityColors]}">
              ${rec.priority}
            </span>
          </div>
          <p class="text-sm text-gray-700 mb-2">${rec.description}</p>
          <button class="text-sm text-blue-600 hover:text-blue-800 underline" onclick="handleRecommendation('${rec.action}')">
            ${rec.action.replace('_', ' ')}
          </button>
        </div>
      </div>
    `;
  }

  private calculateSuccessRate(): number {
    if (this.history.length === 0) return 0;
    const successful = this.history.filter(h => h.success).length;
    return Math.round((successful / this.history.length) * 100);
  }

  private formatTimeAgo(date: Date): string {
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} min ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hr ago`;
    return `${Math.floor(diffInSeconds / 86400)} days ago`;
  }

  private initializeSpeechRecognition(): void {
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
      const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
      this.recognition = new SpeechRecognition();
      this.recognition.continuous = false;
      this.recognition.interimResults = false;
      this.recognition.lang = 'en-US';

      this.recognition.onstart = () => {
        this.isListening = true;
        this.updateVoiceStatus();
      };

      this.recognition.onend = () => {
        this.isListening = false;
        this.updateVoiceStatus();
      };

      this.recognition.onresult = (event) => {
        const transcript = event.results[0][0].transcript;
        this.processVoiceCommand(transcript);
      };

      this.recognition.onerror = (event) => {
        console.error('Speech recognition error:', event.error);
        this.isListening = false;
        this.updateVoiceStatus();
      };
    }
  }

  private updateVoiceStatus(): void {
    const statusElement = document.getElementById('voice-status');
    if (statusElement) {
      statusElement.textContent = this.isListening ? '🎤 Listening... Speak now or click stop.' : 'Click "Start Voice" to begin voice interaction.';
      statusElement.className = `text-sm ${this.isListening ? 'text-red-600' : 'text-gray-600'}`;
    }
  }

  private async processVoiceCommand(command: string): Promise<void> {
    try {
      const response = await fetch('/backend/api/virtual-assistant/voice-command', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ command })
      });

      if (response.ok) {
        const result = await response.json();
        this.displayResponse(result.response, result.type);
        this.speakResponse(result.response);
      } else {
        this.displayResponse('Sorry, I couldn\'t process that command. Please try again.', 'error');
      }
    } catch (error) {
      console.error('Failed to process voice command:', error);
      this.displayResponse('Sorry, there was an error processing your command.', 'error');
    }
  }

  private async processTextQuery(query: string): Promise<void> {
    try {
      const response = await fetch('/backend/api/virtual-assistant/text-query', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ query })
      });

      if (response.ok) {
        const result = await response.json();
        this.displayResponse(result.response, result.type);
        if (this.preferences?.voice_enabled) {
          this.speakResponse(result.response);
        }
      } else {
        this.displayResponse('Sorry, I couldn\'t process that query. Please try again.', 'error');
      }
    } catch (error) {
      console.error('Failed to process text query:', error);
      this.displayResponse('Sorry, there was an error processing your query.', 'error');
    }
  }

  private displayResponse(response: string, type: string = 'info'): void {
    const responseArea = document.getElementById('response-area');
    if (responseArea) {
      const typeClasses = {
        info: 'text-blue-800',
        success: 'text-green-800',
        warning: 'text-yellow-800',
        error: 'text-red-800'
      };

      responseArea.innerHTML = `
        <div class="p-4 rounded-lg ${typeClasses[type as keyof typeof typeClasses] || 'text-gray-800'}">
          <p class="font-medium mb-2">Assistant Response:</p>
          <p>${response}</p>
          <p class="text-xs mt-2 opacity-75">${new Date().toLocaleTimeString()}</p>
        </div>
      `;
    }
  }

  private speakResponse(text: string): void {
    if ('speechSynthesis' in window && this.preferences?.voice_enabled) {
      const utterance = new SpeechSynthesisUtterance(text);
      utterance.rate = this.assistant?.voice_speed || 1.0;
      utterance.pitch = this.assistant?.voice_pitch || 1.0;

      window.speechSynthesis.speak(utterance);
    }
  }

  setupEventListeners(): void {
    const voiceToggleBtn = document.getElementById('voice-toggle');
    const sendTextBtn = document.getElementById('send-text');
    const textInput = document.getElementById('text-input') as HTMLInputElement;
    const refreshBtn = document.getElementById('refresh-assistant');
    const settingsBtn = document.getElementById('settings-assistant');
    const retryBtn = document.getElementById('retry-assistant');
    const voiceEnabledCheckbox = document.getElementById('voice-enabled') as HTMLInputElement;
    const autoListenCheckbox = document.getElementById('auto-listen') as HTMLInputElement;
    const voiceSpeedSlider = document.getElementById('voice-speed') as HTMLInputElement;
    const voicePitchSlider = document.getElementById('voice-pitch') as HTMLInputElement;

    if (voiceToggleBtn) {
      voiceToggleBtn.addEventListener('click', () => {
        if (this.isListening) {
          this.recognition?.stop();
        } else {
          this.recognition?.start();
        }
      });
    }

    if (sendTextBtn) {
      sendTextBtn.addEventListener('click', () => {
        const query = textInput?.value?.trim();
        if (query) {
          this.processTextQuery(query);
          textInput.value = '';
        }
      });
    }

    if (textInput) {
      textInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          const query = textInput.value.trim();
          if (query) {
            this.processTextQuery(query);
            textInput.value = '';
          }
        }
      });
    }

    // Quick command buttons
    document.querySelectorAll('.quick-command-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const command = (e.currentTarget as HTMLElement).dataset.command;
        if (command) {
          this.processTextQuery(command);
        }
      });
    });

    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshVirtualAssistant'));
      });
    }

    if (settingsBtn) {
      settingsBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('showVirtualAssistantSettings'));
      });
    }

    if (retryBtn) {
      retryBtn.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('refreshVirtualAssistant'));
      });
    }

    // Voice settings
    if (voiceEnabledCheckbox) {
      voiceEnabledCheckbox.addEventListener('change', (e) => {
        this.updateVoiceSetting('voice_enabled', (e.target as HTMLInputElement).checked);
      });
    }

    if (autoListenCheckbox) {
      autoListenCheckbox.addEventListener('change', (e) => {
        this.updateVoiceSetting('auto_listen', (e.target as HTMLInputElement).checked);
      });
    }

    if (voiceSpeedSlider) {
      voiceSpeedSlider.addEventListener('input', (e) => {
        const value = parseFloat((e.target as HTMLInputElement).value);
        const valueElement = document.getElementById('speed-value');
        if (valueElement) {
          valueElement.textContent = `${value}x`;
        }
        this.updateVoiceSetting('voice_speed', value);
      });
    }

    if (voicePitchSlider) {
      voicePitchSlider.addEventListener('input', (e) => {
        const value = parseFloat((e.target as HTMLInputElement).value);
        const valueElement = document.getElementById('pitch-value');
        if (valueElement) {
          valueElement.textContent = `${value}x`;
        }
        this.updateVoiceSetting('voice_pitch', value);
      });
    }
  }

  private async updateVoiceSetting(setting: string, value: any): Promise<void> {
    try {
      const data: any = {};
      data[setting] = value;

      if (setting === 'voice_enabled' || setting === 'auto_listen') {
        // Update preferences
        await fetch('/backend/api/virtual-assistant/preferences', {
          method: 'PUT',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(data)
        });
      } else {
        // Update assistant settings
        await fetch(`/backend/api/virtual-assistant/${this.assistant?.id}`, {
          method: 'PUT',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(data)
        });
      }
    } catch (error) {
      console.error('Failed to update voice setting:', error);
    }
  }
}
