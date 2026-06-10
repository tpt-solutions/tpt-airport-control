import './style.css'
import { AuthManager, AuthUI } from './auth.js'
import { DashboardManager } from './dashboard/DashboardManager.js'
import { ThemeManager } from './services/ThemeManager.js'

// Silence debug-level logging in production to avoid leaking implementation details.
if (!import.meta.env.DEV) {
  console.log = () => {};
  console.debug = () => {};
}

ThemeManager.getInstance();

// PWA Service Worker Registration
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then(registration => {
        console.log('SW registered: ', registration);
      })
      .catch(registrationError => {
        console.log('SW registration failed: ', registrationError);
      });
  });
}



// Global error handling
window.addEventListener('error', (_event) => {
  console.error('Global error occurred');
  // Could send to monitoring service
});

window.addEventListener('unhandledrejection', (_event) => {
  console.error('Unhandled promise rejection occurred');
  // Could send to monitoring service
});



// App initialization
class FlightControlApp {
  private app: HTMLElement;
  private auth: AuthManager;
  private authUI: AuthUI;
  private dashboard: DashboardManager;
  constructor() {
    this.app = document.querySelector<HTMLDivElement>('#app')!;
    this.auth = AuthManager.getInstance();
    this.authUI = new AuthUI(this.app);
    this.dashboard = new DashboardManager(this.app);
    this.init().catch(error => {
      console.error('[FlightControlApp] Failed to initialize app:', error);
    });
  }

  private async init() {
    // Restore in-memory JWT from httpOnly cookie (no-op if already authenticated)
    await this.auth.initialize();
    const authenticated = this.auth.isAuthenticated();
    if (authenticated) {
      await this.showDashboard();
    } else {
      this.showLogin();
    }
  }

  private async showDashboard() {
    try {
      await this.dashboard.render();
      this.maybeShowPasswordChangeBanner();
    } catch (error) {
      console.error('[FlightControlApp] showDashboard() error:', error);
      throw error;
    }
  }

  private maybeShowPasswordChangeBanner() {
    if (!this.auth.isPasswordChangeRequired()) return;
    if (document.getElementById('password-change-banner')) return;

    const banner = document.createElement('div');
    banner.id = 'password-change-banner';
    banner.style.cssText = [
      'position:fixed', 'top:0', 'left:0', 'right:0', 'z-index:9999',
      'background:#b45309', 'color:#fff', 'text-align:center',
      'padding:10px 16px', 'font-size:14px', 'display:flex',
      'align-items:center', 'justify-content:center', 'gap:12px',
    ].join(';');

    const msg = document.createElement('span');
    msg.textContent = 'You are using the default admin password. Change it now to secure your system.';
    banner.appendChild(msg);

    const link = document.createElement('a');
    link.textContent = 'Change password';
    link.href = '#';
    link.style.cssText = 'color:#fef3c7;font-weight:600;text-decoration:underline;margin-left:8px';
    link.addEventListener('click', (e) => {
      e.preventDefault();
      this.showPasswordChangeModal();
    });
    banner.appendChild(link);
    document.body.prepend(banner);
  }

  private showPasswordChangeModal() {
    if (document.getElementById('pw-change-modal')) return;
    const overlay = document.createElement('div');
    overlay.id = 'pw-change-modal';
    overlay.style.cssText = [
      'position:fixed', 'inset:0', 'z-index:10000', 'background:rgba(0,0,0,0.7)',
      'display:flex', 'align-items:center', 'justify-content:center',
    ].join(';');
    overlay.innerHTML = `
      <div style="background:#1e293b;border:1px solid #334155;border-radius:12px;padding:32px;width:100%;max-width:400px">
        <h2 style="color:#f1f5f9;font-size:18px;font-weight:600;margin:0 0 16px">Change Admin Password</h2>
        <p style="color:#94a3b8;font-size:13px;margin:0 0 20px">Set a strong password before using this system.</p>
        <div style="margin-bottom:12px">
          <label style="display:block;color:#cbd5e1;font-size:13px;margin-bottom:4px">New Password</label>
          <input id="pwc-new" type="password" minlength="10"
            style="width:100%;box-sizing:border-box;padding:8px 12px;background:#0f172a;border:1px solid #475569;border-radius:8px;color:#f1f5f9;font-size:14px">
        </div>
        <div style="margin-bottom:20px">
          <label style="display:block;color:#cbd5e1;font-size:13px;margin-bottom:4px">Confirm Password</label>
          <input id="pwc-confirm" type="password" minlength="10"
            style="width:100%;box-sizing:border-box;padding:8px 12px;background:#0f172a;border:1px solid #475569;border-radius:8px;color:#f1f5f9;font-size:14px">
        </div>
        <div id="pwc-error" style="color:#f87171;font-size:13px;margin-bottom:12px;display:none"></div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <button id="pwc-cancel" style="padding:8px 16px;background:#334155;border:none;border-radius:8px;color:#cbd5e1;cursor:pointer;font-size:14px">Later</button>
          <button id="pwc-submit" style="padding:8px 16px;background:#2563eb;border:none;border-radius:8px;color:#fff;cursor:pointer;font-size:14px;font-weight:600">Save</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);

    const newInput = overlay.querySelector<HTMLInputElement>('#pwc-new')!;
    const confirmInput = overlay.querySelector<HTMLInputElement>('#pwc-confirm')!;
    const errorDiv = overlay.querySelector<HTMLElement>('#pwc-error')!;
    const cancelBtn = overlay.querySelector<HTMLButtonElement>('#pwc-cancel')!;
    const submitBtn = overlay.querySelector<HTMLButtonElement>('#pwc-submit')!;

    const showError = (msg: string) => {
      errorDiv.textContent = msg;
      errorDiv.style.display = 'block';
    };

    cancelBtn.addEventListener('click', () => overlay.remove());

    submitBtn.addEventListener('click', async () => {
      const np = newInput.value;
      const cp = confirmInput.value;
      if (np.length < 10) { showError('Password must be at least 10 characters.'); return; }
      if (np !== cp) { showError('Passwords do not match.'); return; }
      errorDiv.style.display = 'none';
      submitBtn.textContent = 'Saving...';
      submitBtn.disabled = true;
      try {
        const resp = await this.auth.authenticatedFetch('/api/account.php?action=change_password', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ new_password: np, confirm_password: cp }),
        });
        const data = await resp.json();
        if (data.success || data.status === 'success') {
          this.auth.clearPasswordChangeRequired();
          overlay.remove();
          const banner = document.getElementById('password-change-banner');
          if (banner) banner.remove();
        } else {
          showError(data.message || 'Failed to change password. Try again.');
          submitBtn.textContent = 'Save';
          submitBtn.disabled = false;
        }
      } catch {
        showError('Network error. Please try again.');
        submitBtn.textContent = 'Save';
        submitBtn.disabled = false;
      }
    });
  }

  private showLogin() {
    this.authUI.renderLoginForm();
  }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  new FlightControlApp();
});
