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
    console.debug('[AuthManager] loadStoredAuth() called');
    const storedToken = localStorage.getItem('auth_token');
    const storedUser = localStorage.getItem('auth_user');
    console.debug('[AuthManager] localStorage auth_token exists:', !!storedToken, 'auth_user exists:', !!storedUser);

    if (storedToken && storedUser) {
      this.token = storedToken;
      try {
        this.user = JSON.parse(storedUser);
        console.debug('[AuthManager] Loaded user from localStorage:', this.user?.username, 'role:', this.user?.role_name);
      } catch (error) {
        console.error('[AuthManager] Failed to parse stored user data:', error);
        this.logout();
      }
    } else {
      console.debug('[AuthManager] No stored auth data found');
    }
  }


  private saveAuth(token: string, user: User) {
    console.debug('[AuthManager] saveAuth() called for user:', user.username, 'role:', user.role_name, 'token length:', token.length);
    this.token = token;
    this.user = user;
    localStorage.setItem('auth_token', token);
    localStorage.setItem('auth_user', JSON.stringify(user));
    console.debug('[AuthManager] Auth data saved to localStorage');
  }


  private clearAuth() {
    this.token = null;
    this.user = null;
    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_user');
  }

  async login(username: string, password: string): Promise<AuthResponse> {
    console.debug('[AuthManager] login() called for username:', username);
    try {
      const response = await fetch(`${this.API_BASE}/auth.php?action=login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ username, password }),
      });
      console.debug('[AuthManager] Login response status:', response.status);

      const data = await response.json();
      console.debug('[AuthManager] Login response data success:', data.success, 'has token:', !!data.token, 'has user:', !!data.user);

      if (data.success && data.token && data.user) {
        this.saveAuth(data.token, data.user);
        console.debug('[AuthManager] Login successful, returning success');
        return { success: true, token: data.token, user: data.user };
      } else {
        console.debug('[AuthManager] Login failed:', data.message || 'Unknown reason');
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
    console.debug('[AuthManager] logout() called');
    this.clearAuth();
    // Redirect to login or reload page
    window.location.reload();
  }


  isAuthenticated(): boolean {
    const result = this.token !== null && this.user !== null;
    console.debug('[AuthManager] isAuthenticated() returning:', result, 'token exists:', !!this.token, 'user exists:', !!this.user);
    return result;
  }


  getToken(): string | null {
    return this.token;
  }

  getUser(): User | null {
    console.debug('[AuthManager] getUser() returning:', this.user ? `${this.user.username} (${this.user.role_name})` : 'null');
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
      // Token is invalid, try to refresh first
      const refreshed = await this.refreshToken();
      if (refreshed) {
        // Retry the original request with new token
        headers.set('Authorization', `Bearer ${this.token}`);
        return fetch(url, {
          ...options,
          headers,
        });
      } else {
        // Refresh failed, clear auth and logout
        this.logout();
        throw new Error('Authentication expired. Please login again.');
      }
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
    console.debug('[AuthManager] validateToken() called, token exists:', !!this.token);
    if (!this.token) return false;

    try {
      const response = await this.authenticatedFetch(`${this.API_BASE}/auth.php?action=validate`);
      const data = await response.json();
      console.debug('[AuthManager] validateToken() response valid:', data.valid === true);
      return data.valid === true;
    } catch (error) {
      console.error('[AuthManager] Token validation error:', error);
      return false;
    }
  }


  // Refresh token if needed
  async refreshToken(): Promise<boolean> {
    console.debug('[AuthManager] refreshToken() called, token exists:', !!this.token);
    if (!this.token) return false;

    try {
      // Use plain fetch — never authenticatedFetch — to avoid infinite 401 loop
      const response = await fetch(`${this.API_BASE}/auth.php?action=refresh`, {
        headers: { 'Authorization': `Bearer ${this.token}` },
      });
      console.debug('[AuthManager] refreshToken() response ok:', response.ok);

      if (!response.ok) return false;

      const data = await response.json();

      if (data.success && data.token) {
        this.token = data.token;
        localStorage.setItem('auth_token', data.token);
        console.debug('[AuthManager] refreshToken() successful, new token saved');
        return true;
      }
    } catch (error) {
      console.error('[AuthManager] Token refresh error:', error);
    }

    console.debug('[AuthManager] refreshToken() failed, returning false');
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
      <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-center mb-6 text-gray-800">Flight Control Login</h2>

        <form id="login-form" class="space-y-4">
          <div>
            <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
            <input type="text" id="username" name="username" required
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" id="password" name="password" required
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
          </div>

          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <input type="checkbox" id="remember" name="remember"
                     class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
              <label for="remember" class="ml-2 block text-sm text-gray-900">Remember me</label>
            </div>

            <a href="#" id="forgot-password" class="text-sm text-blue-600 hover:text-blue-500">Forgot password?</a>
          </div>

          <button type="submit"
                  class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
            <span id="login-text">Sign In</span>
            <div id="login-spinner" class="hidden ml-2">
              <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
            </div>
          </button>
        </form>

        <div class="mt-6 text-center">
          <p class="text-sm text-gray-600">
            Don't have an account?
            <a href="#" id="show-register" class="text-blue-600 hover:text-blue-500 font-medium">Sign up</a>
          </p>
        </div>

        <div id="message" class="mt-4 hidden">
          <div id="message-content" class="text-sm"></div>
        </div>
      </div>
    `;

    this.setupLoginForm();
  }

  renderRegisterForm(): void {
    this.container.innerHTML = `
      <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-center mb-6 text-gray-800">Create Account</h2>

        <form id="register-form" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
              <input type="text" id="first_name" name="first_name" required
                     class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
              <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
              <input type="text" id="last_name" name="last_name" required
                     class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
          </div>

          <div>
            <label for="reg_username" class="block text-sm font-medium text-gray-700">Username</label>
            <input type="text" id="reg_username" name="username" required
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
          </div>

          <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" id="email" name="email" required
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
          </div>

          <div>
            <label for="reg_password" class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" id="reg_password" name="password" required minlength="8"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
          </div>

          <div>
            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
          </div>

          <button type="submit"
                  class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed">
            <span id="register-text">Create Account</span>
            <div id="register-spinner" class="hidden ml-2">
              <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
            </div>
          </button>
        </form>

        <div class="mt-6 text-center">
          <p class="text-sm text-gray-600">
            Already have an account?
            <a href="#" id="show-login" class="text-blue-600 hover:text-blue-500 font-medium">Sign in</a>
          </p>
        </div>

        <div id="register-message" class="mt-4 hidden">
          <div id="register-message-content" class="text-sm"></div>
        </div>
      </div>
    `;

    this.setupRegisterForm();
  }

  renderForgotPasswordForm(): void {
    this.container.innerHTML = `
      <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-center mb-6 text-gray-800">Reset Password</h2>
        <p class="text-sm text-gray-600 text-center mb-6">
          Enter your email address and we'll send you a link to reset your password.
        </p>

        <form id="forgot-form" class="space-y-4">
          <div>
            <label for="reset_email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" id="reset_email" name="email" required
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
          </div>

          <button type="submit"
                  class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
            <span id="reset-text">Send Reset Link</span>
            <div id="reset-spinner" class="hidden ml-2">
              <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
            </div>
          </button>
        </form>

        <div class="mt-6 text-center">
          <p class="text-sm text-gray-600">
            Remember your password?
            <a href="#" id="back-to-login" class="text-blue-600 hover:text-blue-500 font-medium">Sign in</a>
          </p>
        </div>

        <div id="reset-message" class="mt-4 hidden">
          <div id="reset-message-content" class="text-sm"></div>
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
      type === 'success' ? 'text-green-600' :
      type === 'error' ? 'text-red-600' :
      'text-blue-600'
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
