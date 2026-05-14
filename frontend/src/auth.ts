// Authentication and User Management for Flight Control Software

interface User {
  id: number;
  username: string;
  email: string;
  first_name: string;
  last_name: string;
  role_name: string;
  passenger_id?: number;
}

interface AuthResponse {
  success: boolean;
  token?: string;
  user?: User;
  message?: string;
}

class AuthManager {
  private static instance: AuthManager;
  private token: string | null = null;
  private user: User | null = null;
  private readonly API_BASE = '/api';

  private constructor() {
    // Load stored authentication data
    this.loadStoredAuth();
  }

  static getInstance(): AuthManager {
    if (!AuthManager.instance) {
      AuthManager.instance = new AuthManager();
    }
    return AuthManager.instance;
  }

  private loadStoredAuth() {
    const storedToken = localStorage.getItem('auth_token');
    const storedUser = localStorage.getItem('auth_user');

    if (storedToken && storedUser) {
      this.token = storedToken;
      try {
        this.user = JSON.parse(storedUser);
      } catch (error) {
        console.error('[AuthManager] Failed to parse stored user data:', error);
        this.logout();
      }
    }
  }


  private saveAuth(token: string, user: User) {
    this.token = token;
    this.user = user;
    localStorage.setItem('auth_token', token);
    localStorage.setItem('auth_user', JSON.stringify(user));
  }


  private clearAuth() {
    this.token = null;
    this.user = null;
    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_user');
  }

  async login(username: string, password: string): Promise<AuthResponse> {
    try {
      const response = await fetch(`${this.API_BASE}/auth.php?action=login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ username, password }),
      });

      const data = await response.json();

      if (data.success && data.token && data.user) {
        this.saveAuth(data.token, data.user);
        return { success: true, token: data.token, user: data.user };
      } else {
        return { success: false, message: data.message || 'Login failed' };
      }
    } catch (error) {
      console.error('[AuthManager] Login error:', error);
      return { success: false, message: 'Network error. Please try again.' };
    }
  }


  async register(userData: {
    username: string;
    email: string;
    password: string;
    first_name: string;
    last_name: string;
  }): Promise<AuthResponse> {
    try {
      const response = await fetch(`${this.API_BASE}/auth.php?action=register`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(userData),
      });

      const data = await response.json();

      if (data.success) {
        return { success: true, message: data.message };
      } else {
        return { success: false, message: data.message || 'Registration failed' };
      }
    } catch (error) {
      console.error('Registration error:', error);
      return { success: false, message: 'Network error. Please try again.' };
    }
  }

  async requestPasswordReset(email: string): Promise<{ success: boolean; message: string }> {
    try {
      const response = await fetch(`${this.API_BASE}/password-reset.php?action=request`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email }),
      });

      const data = await response.json();
      return { success: data.success !== false, message: data.message || 'Request sent' };
    } catch (error) {
      console.error('Password reset request error:', error);
      return { success: false, message: 'Network error. Please try again.' };
    }
  }

  async resetPassword(token: string, password: string, confirmPassword: string): Promise<{ success: boolean; message: string }> {
    try {
      const response = await fetch(`${this.API_BASE}/password-reset.php?action=reset`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ token, password, confirm_password: confirmPassword }),
      });

      const data = await response.json();
      return { success: data.success !== false, message: data.message || 'Password reset successful' };
    } catch (error) {
      console.error('Password reset error:', error);
      return { success: false, message: 'Network error. Please try again.' };
    }
  }

  logout(): void {
    this.clearAuth();
    window.location.reload();
  }

  isAuthenticated(): boolean {
    return this.token !== null && this.user !== null;
  }

  getToken(): string | null {
    return this.token;
  }

  getUser(): User | null {
    return this.user;
  }


  hasRole(role: string): boolean {
    return this.user?.role_name === role;
  }

  hasPermission(permission: string): boolean {
    if (!this.user) return false;

    // Super admin has all permissions
    if (this.user.role_name === 'super_admin') return true;

    // Admin has most permissions
    if (this.user.role_name === 'admin') {
      return !['super_admin_only'].includes(permission);
    }

    // Operator permissions
    if (this.user.role_name === 'operator') {
      const operatorPermissions = ['read', 'flights', 'passengers', 'baggage', 'security'];
      return operatorPermissions.some(p => permission.includes(p));
    }

    // Passenger permissions (limited)
    if (this.user.role_name === 'passenger') {
      const passengerPermissions = ['read_own', 'passengers', 'baggage'];
      return passengerPermissions.some(p => permission.includes(p));
    }

    return false;
  }

  // API request helper with authentication
  async authenticatedFetch(url: string, options: RequestInit = {}): Promise<Response> {
    const headers = new Headers(options.headers);

    if (this.token) {
      headers.set('Authorization', `Bearer ${this.token}`);
    }

    const response = await fetch(url, {
      ...options,
      headers,
    });

    // Handle authentication errors
    if (response.status === 401) {
      const refreshed = await this.refreshToken();
      if (refreshed) {
        headers.set('Authorization', `Bearer ${this.token}`);
        return fetch(url, {
          ...options,
          headers,
        });
      } else {
        console.error(`[Auth] Refresh failed after 401 on ${url} — logging out`);
        this.logout();
        throw new Error('Authentication expired. Please login again.');
      }
    }

    if (!response.ok) {
      console.warn(`[Auth] Non-OK response ${response.status} from ${url}`);
    }

    // Handle server errors that return HTML instead of JSON
    const contentType = response.headers.get('content-type');
    if (!response.ok && contentType && contentType.includes('text/html')) {
      console.error(`Server returned HTML error for ${url}: ${response.status}`);
      throw new Error(`Server error: ${response.status} ${response.statusText}`);
    }

    return response;
  }

  // Check if token is still valid
  async validateToken(): Promise<boolean> {
    if (!this.token) return false;

    try {
      const response = await this.authenticatedFetch(`${this.API_BASE}/auth.php?action=validate`);
      const data = await response.json();
      return data.valid === true;
    } catch (error) {
      console.error('[AuthManager] Token validation error:', error);
      return false;
    }
  }

  // Refresh token if needed
  async refreshToken(): Promise<boolean> {
    if (!this.token) return false;

    try {
      const response = await fetch(`${this.API_BASE}/auth.php?action=refresh`, {
        headers: { 'Authorization': `Bearer ${this.token}` },
      });

      if (!response.ok) return false;

      const data = await response.json();

      if (data.success && data.token) {
        this.token = data.token;
        localStorage.setItem('auth_token', data.token);
        return true;
      }
    } catch (error) {
      console.error('[AuthManager] Token refresh error:', error);
    }

    return false;
  }

}

// Authentication UI Components
class AuthUI {
  private auth: AuthManager;
  private container: HTMLElement;

  constructor(container: HTMLElement) {
    this.auth = AuthManager.getInstance();
    this.container = container;
  }

  renderLoginForm(): void {
    this.container.innerHTML = `
      <div class="min-h-screen bg-slate-950 flex items-center justify-center px-4">
        <div class="w-full max-w-md">
          <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-8">
            <div class="flex items-center justify-center gap-3 mb-8">
              <div class="w-10 h-10 rounded-lg bg-linear-to-br from-blue-500 to-violet-600 flex items-center justify-center shrink-0">
                <span class="text-white font-bold tracking-tight">FC</span>
              </div>
              <h2 class="text-xl font-semibold text-slate-100">Flight Control</h2>
            </div>

            <form id="login-form" class="space-y-5">
              <div>
                <label for="username" class="block text-sm font-medium text-slate-300 mb-1.5">Username</label>
                <input type="text" id="username" name="username" required autocomplete="username"
                       class="block w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
              </div>

              <div>
                <label for="password" class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="block w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
              </div>

              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <input type="checkbox" id="remember" name="remember"
                         class="h-4 w-4 bg-slate-700 border-slate-600 rounded text-blue-500 focus:ring-blue-500 focus:ring-offset-0">
                  <label for="remember" class="ml-2 block text-sm text-slate-400">Remember me</label>
                </div>

                <a href="#" id="forgot-password" class="text-sm text-blue-400 hover:text-blue-300 transition-colors">Forgot password?</a>
              </div>

              <button type="submit"
                      class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                <span id="login-text">Sign In</span>
                <div id="login-spinner" class="hidden ml-2">
                  <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                </div>
              </button>
            </form>

            <div class="mt-6 text-center">
              <p class="text-sm text-slate-400">
                Don't have an account?
                <a href="#" id="show-register" class="text-blue-400 hover:text-blue-300 font-medium transition-colors">Sign up</a>
              </p>
            </div>

            <div id="message" class="mt-4 hidden">
              <div id="message-content" class="text-sm text-center"></div>
            </div>
          </div>
        </div>
      </div>
    `;

    this.setupLoginForm();
  }

  renderRegisterForm(): void {
    this.container.innerHTML = `
      <div class="min-h-screen bg-slate-950 flex items-center justify-center px-4">
        <div class="w-full max-w-md">
          <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-8">
            <div class="flex items-center justify-center gap-3 mb-8">
              <div class="w-10 h-10 rounded-lg bg-linear-to-br from-blue-500 to-violet-600 flex items-center justify-center shrink-0">
                <span class="text-white font-bold tracking-tight">FC</span>
              </div>
              <h2 class="text-xl font-semibold text-slate-100">Create Account</h2>
            </div>

            <form id="register-form" class="space-y-5">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label for="first_name" class="block text-sm font-medium text-slate-300 mb-1.5">First Name</label>
                  <input type="text" id="first_name" name="first_name" required
                         class="block w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                </div>
                <div>
                  <label for="last_name" class="block text-sm font-medium text-slate-300 mb-1.5">Last Name</label>
                  <input type="text" id="last_name" name="last_name" required
                         class="block w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                </div>
              </div>

              <div>
                <label for="reg_username" class="block text-sm font-medium text-slate-300 mb-1.5">Username</label>
                <input type="text" id="reg_username" name="username" required
                       class="block w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
              </div>

              <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                <input type="email" id="email" name="email" required
                       class="block w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
              </div>

              <div>
                <label for="reg_password" class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
                <input type="password" id="reg_password" name="password" required minlength="8"
                       class="block w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
              </div>

              <div>
                <label for="confirm_password" class="block text-sm font-medium text-slate-300 mb-1.5">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                       class="block w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
              </div>

              <button type="submit"
                      class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                <span id="register-text">Create Account</span>
                <div id="register-spinner" class="hidden ml-2">
                  <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                </div>
              </button>
            </form>

            <div class="mt-6 text-center">
              <p class="text-sm text-slate-400">
                Already have an account?
                <a href="#" id="show-login" class="text-blue-400 hover:text-blue-300 font-medium transition-colors">Sign in</a>
              </p>
            </div>

            <div id="register-message" class="mt-4 hidden">
              <div id="register-message-content" class="text-sm text-center"></div>
            </div>
          </div>
        </div>
      </div>
    `;

    this.setupRegisterForm();
  }

  renderForgotPasswordForm(): void {
    this.container.innerHTML = `
      <div class="min-h-screen bg-slate-950 flex items-center justify-center px-4">
        <div class="w-full max-w-md">
          <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-8">
            <div class="flex items-center justify-center gap-3 mb-8">
              <div class="w-10 h-10 rounded-lg bg-linear-to-br from-blue-500 to-violet-600 flex items-center justify-center shrink-0">
                <span class="text-white font-bold tracking-tight">FC</span>
              </div>
              <h2 class="text-xl font-semibold text-slate-100">Reset Password</h2>
            </div>
            <p class="text-sm text-slate-400 text-center mb-6">
              Enter your email address and we'll send you a link to reset your password.
            </p>

            <form id="forgot-form" class="space-y-5">
              <div>
                <label for="reset_email" class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                <input type="email" id="reset_email" name="email" required
                       class="block w-full px-3 py-2.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
              </div>

              <button type="submit"
                      class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                <span id="reset-text">Send Reset Link</span>
                <div id="reset-spinner" class="hidden ml-2">
                  <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                </div>
              </button>
            </form>

            <div class="mt-6 text-center">
              <p class="text-sm text-slate-400">
                Remember your password?
                <a href="#" id="back-to-login" class="text-blue-400 hover:text-blue-300 font-medium transition-colors">Sign in</a>
              </p>
            </div>

            <div id="reset-message" class="mt-4 hidden">
              <div id="reset-message-content" class="text-sm text-center"></div>
            </div>
          </div>
        </div>
      </div>
    `;

    this.setupForgotPasswordForm();
  }

  private setupLoginForm(): void {
    const form = document.getElementById('login-form') as HTMLFormElement;
    const forgotLink = document.getElementById('forgot-password');
    const registerLink = document.getElementById('show-register');

    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      await this.handleLogin();
    });

    forgotLink?.addEventListener('click', (e) => {
      e.preventDefault();
      this.renderForgotPasswordForm();
    });

    registerLink?.addEventListener('click', (e) => {
      e.preventDefault();
      this.renderRegisterForm();
    });
  }

  private setupRegisterForm(): void {
    const form = document.getElementById('register-form') as HTMLFormElement;
    const loginLink = document.getElementById('show-login');

    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      await this.handleRegister();
    });

    loginLink?.addEventListener('click', (e) => {
      e.preventDefault();
      this.renderLoginForm();
    });
  }

  private setupForgotPasswordForm(): void {
    const form = document.getElementById('forgot-form') as HTMLFormElement;
    const loginLink = document.getElementById('back-to-login');

    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      await this.handleForgotPassword();
    });

    loginLink?.addEventListener('click', (e) => {
      e.preventDefault();
      this.renderLoginForm();
    });
  }

  private async handleLogin(): Promise<void> {
    const usernameInput = document.getElementById('username') as HTMLInputElement;
    const passwordInput = document.getElementById('password') as HTMLInputElement;
    const loginText = document.getElementById('login-text');
    const loginSpinner = document.getElementById('login-spinner');
    const messageDiv = document.getElementById('message');
    const messageContent = document.getElementById('message-content');

    if (!usernameInput || !passwordInput || !loginText || !loginSpinner || !messageDiv || !messageContent) return;

    const username = usernameInput.value.trim();
    const password = passwordInput.value;

    if (!username || !password) {
      this.showMessage('Please fill in all fields', 'error');
      return;
    }

    // Show loading state
    loginText.textContent = 'Signing In...';
    loginSpinner.classList.remove('hidden');
    this.disableForm('login-form', true);

    try {
      const result = await this.auth.login(username, password);

      if (result.success) {
        this.showMessage('Login successful! Redirecting...', 'success');
        // Redirect or update UI
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        this.showMessage(result.message || 'Login failed', 'error');
      }
    } catch (error) {
      console.error('Login error:', error);
      this.showMessage('An error occurred. Please try again.', 'error');
    } finally {
      // Reset loading state
      loginText.textContent = 'Sign In';
      loginSpinner.classList.add('hidden');
      this.disableForm('login-form', false);
    }
  }

  private async handleRegister(): Promise<void> {
    const formData = new FormData(document.getElementById('register-form') as HTMLFormElement);
    const registerText = document.getElementById('register-text');
    const registerSpinner = document.getElementById('register-spinner');

    if (!registerText || !registerSpinner) return;

    const password = formData.get('password') as string;
    const confirmPassword = formData.get('confirm_password') as string;

    if (password !== confirmPassword) {
      this.showMessage('Passwords do not match', 'error', 'register-message');
      return;
    }

    // Show loading state
    registerText.textContent = 'Creating Account...';
    registerSpinner.classList.remove('hidden');
    this.disableForm('register-form', true);

    try {
      const result = await this.auth.register({
        username: formData.get('username') as string,
        email: formData.get('email') as string,
        password: password,
        first_name: formData.get('first_name') as string,
        last_name: formData.get('last_name') as string,
      });

      if (result.success) {
        this.showMessage('Account created successfully! Please sign in.', 'success', 'register-message');
        setTimeout(() => {
          this.renderLoginForm();
        }, 2000);
      } else {
        this.showMessage(result.message || 'Registration failed', 'error', 'register-message');
      }
    } catch (error) {
      console.error('Registration error:', error);
      this.showMessage('An error occurred. Please try again.', 'error', 'register-message');
    } finally {
      // Reset loading state
      registerText.textContent = 'Create Account';
      registerSpinner.classList.add('hidden');
      this.disableForm('register-form', false);
    }
  }

  private async handleForgotPassword(): Promise<void> {
    const emailInput = document.getElementById('reset_email') as HTMLInputElement;
    const resetText = document.getElementById('reset-text');
    const resetSpinner = document.getElementById('reset-spinner');

    if (!emailInput || !resetText || !resetSpinner) return;

    const email = emailInput.value.trim();

    if (!email) {
      this.showMessage('Please enter your email address', 'error', 'reset-message');
      return;
    }

    // Show loading state
    resetText.textContent = 'Sending...';
    resetSpinner.classList.remove('hidden');
    this.disableForm('forgot-form', true);

    try {
      const result = await this.auth.requestPasswordReset(email);

      if (result.success) {
        this.showMessage('Password reset link sent to your email', 'success', 'reset-message');
      } else {
        this.showMessage(result.message || 'Failed to send reset link', 'error', 'reset-message');
      }
    } catch (error) {
      console.error('Password reset error:', error);
      this.showMessage('An error occurred. Please try again.', 'error', 'reset-message');
    } finally {
      // Reset loading state
      resetText.textContent = 'Send Reset Link';
      resetSpinner.classList.add('hidden');
      this.disableForm('forgot-form', false);
    }
  }

  private showMessage(message: string, type: 'success' | 'error' | 'info', containerId = 'message'): void {
    const container = document.getElementById(containerId);
    const content = document.getElementById(`${containerId}-content`);

    if (!container || !content) return;

    content.textContent = message;
    content.className = `text-sm ${
      type === 'success' ? 'text-emerald-400' :
      type === 'error' ? 'text-red-400' :
      'text-blue-400'
    }`;
    container.classList.remove('hidden');
  }

  private disableForm(formId: string, disabled: boolean): void {
    const form = document.getElementById(formId) as HTMLFormElement;
    if (!form) return;

    const inputs = form.querySelectorAll('input, button');
    inputs.forEach(input => {
      (input as HTMLInputElement).disabled = disabled;
    });
  }
}

// Export for use in other modules
export { AuthManager, AuthUI, type User, type AuthResponse };
