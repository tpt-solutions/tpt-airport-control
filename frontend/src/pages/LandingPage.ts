import { Component } from '../components/BaseComponent';

interface Role {
  id: string;
  name: string;
  description: string;
  difficulty: 'beginner' | 'intermediate' | 'advanced';
  estimatedTime: number;
  scenarios: number;
  skills: string[];
  modules: string[];
  icon: string;
  color: string;
}

export class LandingPage extends Component {
  private roles: Role[] = [
    {
      id: 'controller',
      name: 'Air Traffic Controller',
      description: 'Manage airspace, coordinate with pilots, handle emergencies in real-time',
      difficulty: 'advanced',
      estimatedTime: 20,
      scenarios: 15,
      skills: ['Communication', 'Decision Making', 'Stress Management'],
      modules: ['atc_operations', 'flight_management', 'emergency'],
      icon: '🎯',
      color: 'from-blue-500 to-blue-600'
    },
    {
      id: 'dispatcher',
      name: 'Flight Dispatcher',
      description: 'Coordinate flight crews, manage schedules, handle delays efficiently',
      difficulty: 'intermediate',
      estimatedTime: 15,
      scenarios: 12,
      skills: ['Organization', 'Communication', 'Problem Solving'],
      modules: ['flight_management', 'crew_scheduling', 'passenger_services'],
      icon: '📋',
      color: 'from-green-500 to-green-600'
    },
    {
      id: 'cargo_manager',
      name: 'Cargo Operations Manager',
      description: 'Oversee freight operations, manage perishables, coordinate logistics',
      difficulty: 'intermediate',
      estimatedTime: 12,
      scenarios: 10,
      skills: ['Logistics', 'Quality Control', 'Time Management'],
      modules: ['cargo_operations', 'customs', 'infrastructure'],
      icon: '📦',
      color: 'from-orange-500 to-orange-600'
    },
    {
      id: 'security_officer',
      name: 'Security Officer',
      description: 'Monitor security systems, respond to threats, manage access control',
      difficulty: 'intermediate',
      estimatedTime: 18,
      scenarios: 14,
      skills: ['Situational Awareness', 'Emergency Response', 'Risk Assessment'],
      modules: ['advanced_security', 'emergency', 'infrastructure'],
      icon: '🛡️',
      color: 'from-red-500 to-red-600'
    },
    {
      id: 'emergency_coordinator',
      name: 'Emergency Coordinator',
      description: 'Coordinate emergency responses, manage crisis situations, ensure safety',
      difficulty: 'advanced',
      estimatedTime: 25,
      scenarios: 18,
      skills: ['Crisis Management', 'Leadership', 'Emergency Protocols'],
      modules: ['emergency', 'security', 'communications'],
      icon: '🚨',
      color: 'from-red-600 to-red-700'
    },
    {
      id: 'infrastructure_manager',
      name: 'Infrastructure Manager',
      description: 'Monitor building systems, manage utilities, maintain facilities',
      difficulty: 'intermediate',
      estimatedTime: 14,
      scenarios: 11,
      skills: ['Technical Knowledge', 'Maintenance Planning', 'System Monitoring'],
      modules: ['infrastructure', 'sustainability', 'maintenance'],
      icon: '🏗️',
      color: 'from-gray-500 to-gray-600'
    },
    {
      id: 'commercial_manager',
      name: 'Commercial Manager',
      description: 'Manage retail operations, optimize revenue, coordinate concessions',
      difficulty: 'beginner',
      estimatedTime: 8,
      scenarios: 6,
      skills: ['Business Management', 'Customer Service', 'Revenue Optimization'],
      modules: ['commercial', 'analytics', 'passenger_services'],
      icon: '💰',
      color: 'from-yellow-500 to-yellow-600'
    },
    {
      id: 'passenger_services_rep',
      name: 'Passenger Services Rep',
      description: 'Assist special needs passengers, manage alerts, coordinate services',
      difficulty: 'beginner',
      estimatedTime: 10,
      scenarios: 8,
      skills: ['Customer Service', 'Accessibility Awareness', 'Coordination'],
      modules: ['special_services', 'passenger_alerts', 'passenger_services'],
      icon: '🙋‍♀️',
      color: 'from-purple-500 to-purple-600'
    },
    {
      id: 'passenger',
      name: 'Passenger Experience',
      description: 'Experience airport from passenger perspective, use self-service features',
      difficulty: 'beginner',
      estimatedTime: 5,
      scenarios: 3,
      skills: ['Self-Service Usage', 'Navigation', 'Time Management'],
      modules: ['passenger_services', 'self_checkin', 'passenger_alerts'],
      icon: '✈️',
      color: 'from-indigo-500 to-indigo-600'
    }
  ];

  private selectedRole: Role | null = null;
  private isLoading = false;

  constructor() {
    super();
    this.checkExistingSession();
  }

  private async checkExistingSession() {
    // Check if user is already in demo mode
    const response = await fetch('/api/auth/status');
    const data = await response.json();

    if (data.demo_mode) {
      // Redirect to dashboard if already in demo
      window.location.href = '/dashboard';
    }
  }

  render() {
    return `
      <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900">
        <!-- Navigation -->
        <nav class="bg-black/20 backdrop-blur-sm border-b border-white/10">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
              <div class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                  <span class="text-white font-bold text-sm">AOS</span>
                </div>
                <span class="text-white font-bold text-xl">Airport Operations Simulator</span>
              </div>
              <div class="flex items-center space-x-4">
                <button id="loginBtn" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                  Sign In
                </button>
                <button id="demoBtn" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:from-blue-600 hover:to-purple-700 transition-all">
                  Start Demo
                </button>
              </div>
            </div>
          </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative py-20 px-4 sm:px-6 lg:px-8">
          <div class="max-w-7xl mx-auto text-center">
            <h1 class="text-5xl md:text-7xl font-bold text-white mb-6">
              Experience Airport
              <span class="bg-gradient-to-r from-blue-400 to-purple-600 bg-clip-text text-transparent">
                Operations
              </span>
            </h1>
            <p class="text-xl md:text-2xl text-gray-300 mb-8 max-w-3xl mx-auto">
              Step into the heart of airport operations. Manage flights, coordinate teams,
              handle emergencies, and keep passengers moving in this realistic airport simulator
              powered by actual operational software.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center mb-12">
              <button id="exploreRolesBtn" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-8 py-4 rounded-xl text-lg font-semibold hover:from-blue-600 hover:to-purple-700 transition-all transform hover:scale-105 shadow-lg">
                🚀 Explore Roles
              </button>
              <button id="watchDemoBtn" class="border-2 border-white/20 text-white px-8 py-4 rounded-xl text-lg font-semibold hover:bg-white/10 transition-all">
                ▶️ Watch Demo
              </button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 max-w-4xl mx-auto">
              <div class="text-center">
                <div class="text-3xl font-bold text-white mb-2">15+</div>
                <div class="text-gray-400">Specialized Roles</div>
              </div>
              <div class="text-center">
                <div class="text-3xl font-bold text-white mb-2">50+</div>
                <div class="text-gray-400">Realistic Scenarios</div>
              </div>
              <div class="text-center">
                <div class="text-3xl font-bold text-white mb-2">12</div>
                <div class="text-gray-400">Airport Modules</div>
              </div>
              <div class="text-center">
                <div class="text-3xl font-bold text-white mb-2">∞</div>
                <div class="text-gray-400">Learning Opportunities</div>
              </div>
            </div>
          </div>
        </section>

        <!-- Role Selection Section -->
        <section id="rolesSection" class="py-20 px-4 sm:px-6 lg:px-8 bg-black/20">
          <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
              <h2 class="text-4xl font-bold text-white mb-4">Choose Your Role</h2>
              <p class="text-xl text-gray-300 max-w-2xl mx-auto">
                Experience airport operations from different perspectives.
                Each role offers unique challenges and learning opportunities.
              </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="rolesGrid">
              ${this.roles.map(role => this.renderRoleCard(role)).join('')}
            </div>
          </div>
        </section>

        <!-- Features Section -->
        <section class="py-20 px-4 sm:px-6 lg:px-8">
          <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
              <h2 class="text-4xl font-bold text-white mb-4">Why Choose Airport Operations Simulator?</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
              <div class="bg-white/5 backdrop-blur-sm rounded-xl p-6 border border-white/10">
                <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center mb-4">
                  <span class="text-white text-2xl">🎮</span>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Gamified Learning</h3>
                <p class="text-gray-300">Earn achievements, climb leaderboards, and unlock new scenarios as you master airport operations.</p>
              </div>

              <div class="bg-white/5 backdrop-blur-sm rounded-xl p-6 border border-white/10">
                <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-blue-600 rounded-lg flex items-center justify-center mb-4">
                  <span class="text-white text-2xl">🏗️</span>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Real Software</h3>
                <p class="text-gray-300">Experience the same operational software used by real airports worldwide.</p>
              </div>

              <div class="bg-white/5 backdrop-blur-sm rounded-xl p-6 border border-white/10">
                <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-600 rounded-lg flex items-center justify-center mb-4">
                  <span class="text-white text-2xl">🚀</span>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Career Exploration</h3>
                <p class="text-gray-300">Discover aviation careers and understand the complexity of airport operations.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-gradient-to-r from-blue-600 to-purple-600">
          <div class="max-w-4xl mx-auto text-center">
            <h2 class="text-4xl font-bold text-white mb-4">Ready to Take Control?</h2>
            <p class="text-xl text-blue-100 mb-8">
              Start your airport operations journey today. Choose a role and begin managing
              one of the world's busiest airports.
            </p>
            <button id="startJourneyBtn" class="bg-white text-blue-600 px-8 py-4 rounded-xl text-lg font-semibold hover:bg-gray-100 transition-all transform hover:scale-105 shadow-lg">
              🎯 Start Your Journey
            </button>
          </div>
        </section>

        <!-- Footer -->
        <footer class="bg-black/40 backdrop-blur-sm border-t border-white/10 py-12 px-4 sm:px-6 lg:px-8">
          <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
              <div>
                <div class="flex items-center space-x-2 mb-4">
                  <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                    <span class="text-white font-bold text-sm">AOS</span>
                  </div>
                  <span class="text-white font-bold text-lg">Airport Operations Simulator</span>
                </div>
                <p class="text-gray-400 text-sm">
                  Experience the thrill and complexity of managing a modern international airport.
                </p>
              </div>

              <div>
                <h4 class="text-white font-semibold mb-4">Features</h4>
                <ul class="space-y-2 text-gray-400 text-sm">
                  <li>Real-time Operations</li>
                  <li>Emergency Management</li>
                  <li>Team Coordination</li>
                  <li>Performance Analytics</li>
                </ul>
              </div>

              <div>
                <h4 class="text-white font-semibold mb-4">Roles</h4>
                <ul class="space-y-2 text-gray-400 text-sm">
                  <li>Air Traffic Controller</li>
                  <li>Emergency Coordinator</li>
                  <li>Cargo Manager</li>
                  <li>Security Officer</li>
                </ul>
              </div>

              <div>
                <h4 class="text-white font-semibold mb-4">Support</h4>
                <ul class="space-y-2 text-gray-400 text-sm">
                  <li>Documentation</li>
                  <li>Community</li>
                  <li>FAQ</li>
                  <li>Contact</li>
                </ul>
              </div>
            </div>

            <div class="border-t border-white/10 mt-8 pt-8 text-center">
              <p class="text-gray-400 text-sm">
                © 2025 Airport Operations Simulator. Experience the future of airport management.
              </p>
            </div>
          </div>
        </footer>

        <!-- Role Details Modal -->
        <div id="roleModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50">
          <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-slate-800 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
              <div id="roleModalContent">
                <!-- Modal content will be populated dynamically -->
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  private renderRoleCard(role: Role): string {
    const difficultyColors = {
      beginner: 'bg-green-500',
      intermediate: 'bg-yellow-500',
      advanced: 'bg-red-500'
    };

    return `
      <div class="role-card bg-white/5 backdrop-blur-sm rounded-xl p-6 border border-white/10 hover:bg-white/10 transition-all cursor-pointer group"
           data-role-id="${role.id}">
        <div class="flex items-start justify-between mb-4">
          <div class="w-12 h-12 bg-gradient-to-r ${role.color} rounded-lg flex items-center justify-center text-2xl">
            ${role.icon}
          </div>
          <span class="px-3 py-1 ${difficultyColors[role.difficulty]} text-white text-xs font-medium rounded-full">
            ${role.difficulty.toUpperCase()}
          </span>
        </div>

        <h3 class="text-xl font-semibold text-white mb-2 group-hover:text-blue-400 transition-colors">
          ${role.name}
        </h3>

        <p class="text-gray-300 text-sm mb-4 line-clamp-3">
          ${role.description}
        </p>

        <div class="space-y-3">
          <div class="flex items-center justify-between text-sm">
            <span class="text-gray-400">Scenarios:</span>
            <span class="text-white font-medium">${role.scenarios}</span>
          </div>
          <div class="flex items-center justify-between text-sm">
            <span class="text-gray-400">Est. Time:</span>
            <span class="text-white font-medium">${role.estimatedTime} min</span>
          </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-1">
          ${role.skills.slice(0, 2).map(skill =>
            `<span class="px-2 py-1 bg-white/10 text-xs text-gray-300 rounded-md">${skill}</span>`
          ).join('')}
          ${role.skills.length > 2 ? `<span class="px-2 py-1 bg-white/10 text-xs text-gray-300 rounded-md">+${role.skills.length - 2}</span>` : ''}
        </div>

        <button class="w-full mt-4 bg-gradient-to-r ${role.color} text-white py-3 px-4 rounded-lg font-medium hover:opacity-90 transition-opacity">
          Select Role
        </button>
      </div>
    `;
  }

  private renderRoleModal(role: Role): string {
    const difficultyColors = {
      beginner: 'text-green-400 bg-green-500/20',
      intermediate: 'text-yellow-400 bg-yellow-500/20',
      advanced: 'text-red-400 bg-red-500/20'
    };

    return `
      <div class="p-6">
        <div class="flex items-start justify-between mb-6">
          <div class="flex items-center space-x-4">
            <div class="w-16 h-16 bg-gradient-to-r ${role.color} rounded-xl flex items-center justify-center text-3xl">
              ${role.icon}
            </div>
            <div>
              <h3 class="text-2xl font-bold text-white">${role.name}</h3>
              <span class="inline-block px-3 py-1 ${difficultyColors[role.difficulty]} text-sm font-medium rounded-full mt-2">
                ${role.difficulty.toUpperCase()} LEVEL
              </span>
            </div>
          </div>
          <button id="closeModalBtn" class="text-gray-400 hover:text-white text-2xl">&times;</button>
        </div>

        <div class="space-y-6">
          <div>
            <h4 class="text-lg font-semibold text-white mb-2">Role Description</h4>
            <p class="text-gray-300">${role.description}</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="bg-white/5 rounded-lg p-4">
              <div class="text-2xl font-bold text-white">${role.scenarios}</div>
              <div class="text-gray-400 text-sm">Available Scenarios</div>
            </div>
            <div class="bg-white/5 rounded-lg p-4">
              <div class="text-2xl font-bold text-white">${role.estimatedTime}</div>
              <div class="text-gray-400 text-sm">Avg. Session (min)</div>
            </div>
          </div>

          <div>
            <h4 class="text-lg font-semibold text-white mb-3">Key Skills You'll Develop</h4>
            <div class="flex flex-wrap gap-2">
              ${role.skills.map(skill =>
                `<span class="px-3 py-2 bg-blue-500/20 text-blue-300 rounded-lg text-sm font-medium">${skill}</span>`
              ).join('')}
            </div>
          </div>

          <div>
            <h4 class="text-lg font-semibold text-white mb-3">Airport Modules You'll Use</h4>
            <div class="flex flex-wrap gap-2">
              ${role.modules.map(module =>
                `<span class="px-3 py-2 bg-purple-500/20 text-purple-300 rounded-lg text-sm font-medium">${module.replace('_', ' ')}</span>`
              ).join('')}
            </div>
          </div>

          <div class="bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-lg p-4">
            <h4 class="text-lg font-semibold text-white mb-2">Ready to Start?</h4>
            <p class="text-gray-300 mb-4">
              Begin your journey as ${role.name.toLowerCase()}. You'll start with basic scenarios
              and progress to advanced challenges as you gain experience.
            </p>
            <button id="startRoleBtn" class="w-full bg-gradient-to-r ${role.color} text-white py-3 px-6 rounded-lg font-semibold hover:opacity-90 transition-opacity" data-role-id="${role.id}">
              🚀 Start as ${role.name}
            </button>
          </div>
        </div>
      </div>
    `;
  }

  mount() {
    this.attachEventListeners();
  }

  private attachEventListeners() {
    // Role card clicks
    document.querySelectorAll('.role-card').forEach(card => {
      card.addEventListener('click', (e) => {
        const roleId = (e.currentTarget as HTMLElement).dataset.roleId;
        const role = this.roles.find(r => r.id === roleId);
        if (role) {
          this.showRoleModal(role);
        }
      });
    });

    // Modal close
    document.getElementById('closeModalBtn')?.addEventListener('click', () => {
      this.hideRoleModal();
    });

    // Start role button
    document.getElementById('startRoleBtn')?.addEventListener('click', (e) => {
      const roleId = (e.target as HTMLElement).dataset.roleId;
      if (roleId) {
        this.startDemoSession(roleId);
      }
    });

    // Navigation buttons
    document.getElementById('exploreRolesBtn')?.addEventListener('click', () => {
      document.getElementById('rolesSection')?.scrollIntoView({ behavior: 'smooth' });
    });

    document.getElementById('startJourneyBtn')?.addEventListener('click', () => {
      document.getElementById('rolesSection')?.scrollIntoView({ behavior: 'smooth' });
    });

    document.getElementById('loginBtn')?.addEventListener('click', () => {
      window.location.href = '/login';
    });

    document.getElementById('demoBtn')?.addEventListener('click', () => {
      document.getElementById('rolesSection')?.scrollIntoView({ behavior: 'smooth' });
    });

    // Watch demo button (placeholder)
    document.getElementById('watchDemoBtn')?.addEventListener('click', () => {
      alert('Demo video coming soon! For now, try selecting a role above.');
    });
  }

  private showRoleModal(role: Role) {
    const modal = document.getElementById('roleModal');
    const content = document.getElementById('roleModalContent');

    if (modal && content) {
      content.innerHTML = this.renderRoleModal(role);
      modal.classList.remove('hidden');

      // Re-attach event listeners for the new modal content
      document.getElementById('closeModalBtn')?.addEventListener('click', () => {
        this.hideRoleModal();
      });

      document.getElementById('startRoleBtn')?.addEventListener('click', (e) => {
        const roleId = (e.target as HTMLElement).dataset.roleId;
        if (roleId) {
          this.startDemoSession(roleId);
        }
      });
    }
  }

  private hideRoleModal() {
    const modal = document.getElementById('roleModal');
    if (modal) {
      modal.classList.add('hidden');
    }
  }

  private async startDemoSession(roleId: string) {
    this.isLoading = true;
    this.updateLoadingState();

    try {
      // Start demo session via API
      const response = await fetch('/api/demo/start', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          role_id: roleId
        })
      });

      const data = await response.json();

      if (response.ok) {
        // Redirect to dashboard with demo mode
        window.location.href = '/dashboard?demo=true';
      } else {
        alert('Failed to start demo session: ' + (data.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Demo session start failed:', error);
      alert('Failed to start demo session. Please try again.');
    } finally {
      this.isLoading = false;
      this.updateLoadingState();
    }
  }

  private updateLoadingState() {
    const startBtn = document.getElementById('startRoleBtn');
    if (startBtn) {
      if (this.isLoading) {
        startBtn.textContent = '🚀 Starting...';
        startBtn.disabled = true;
      } else {
        startBtn.textContent = '🚀 Start Demo Session';
        startBtn.disabled = false;
      }
    }
  }
}
