/**
 * Authentication Flow End-to-End Tests
 *
 * Tests the complete user authentication journey from registration
 * to login, password reset, and session management
 */

describe('Authentication Flow', () => {
  beforeEach(() => {
    // Clear cookies and local storage before each test
    cy.clearCookies()
    cy.clearLocalStorage()

    // Visit the application
    cy.visit('/')
  })

  it('should allow user registration and login', () => {
    const testUser = {
      username: `testuser_${Date.now()}`,
      email: `testuser_${Date.now()}@example.com`,
      password: 'SecurePass123!',
      firstName: 'Test',
      lastName: 'User'
    }

    // Navigate to registration page
    cy.contains('Register').click()

    // Fill registration form
    cy.get('[data-cy="username"]').type(testUser.username)
    cy.get('[data-cy="email"]').type(testUser.email)
    cy.get('[data-cy="password"]').type(testUser.password)
    cy.get('[data-cy="confirm-password"]').type(testUser.password)
    cy.get('[data-cy="first-name"]').type(testUser.firstName)
    cy.get('[data-cy="last-name"]').type(testUser.lastName)

    // Submit registration
    cy.get('[data-cy="register-submit"]').click()

    // Verify successful registration
    cy.contains('Registration successful').should('be.visible')
    cy.url().should('include', '/login')

    // Login with registered credentials
    cy.get('[data-cy="username"]').type(testUser.username)
    cy.get('[data-cy="password"]').type(testUser.password)
    cy.get('[data-cy="login-submit"]').click()

    // Verify successful login
    cy.contains('Welcome').should('be.visible')
    cy.url().should('include', '/dashboard')

    // Verify user menu shows correct user
    cy.get('[data-cy="user-menu"]').should('contain', testUser.firstName)
  })

  it('should handle login with invalid credentials', () => {
    // Navigate to login page
    cy.contains('Login').click()

    // Try to login with invalid credentials
    cy.get('[data-cy="username"]').type('invaliduser')
    cy.get('[data-cy="password"]').type('wrongpassword')
    cy.get('[data-cy="login-submit"]').click()

    // Verify error message
    cy.contains('Invalid username or password').should('be.visible')

    // Verify still on login page
    cy.url().should('include', '/login')
  })

  it('should handle password reset flow', () => {
    const testEmail = 'existinguser@example.com'

    // Navigate to login page
    cy.visit('/login')

    // Click forgot password link
    cy.contains('Forgot Password?').click()

    // Enter email for password reset
    cy.get('[data-cy="reset-email"]').type(testEmail)
    cy.get('[data-cy="reset-submit"]').click()

    // Verify success message
    cy.contains('Password reset email sent').should('be.visible')

    // Mock receiving reset email and clicking link
    cy.visit('/reset-password?token=mock_reset_token')

    // Enter new password
    cy.get('[data-cy="new-password"]').type('NewSecurePass456!')
    cy.get('[data-cy="confirm-new-password"]').type('NewSecurePass456!')
    cy.get('[data-cy="reset-password-submit"]').click()

    // Verify success
    cy.contains('Password reset successful').should('be.visible')
    cy.url().should('include', '/login')
  })

  it('should maintain session across page refreshes', () => {
    // Login first
    cy.login('testuser', 'testpass')

    // Verify on dashboard
    cy.url().should('include', '/dashboard')
    cy.contains('Dashboard').should('be.visible')

    // Refresh page
    cy.reload()

    // Verify still logged in
    cy.url().should('include', '/dashboard')
    cy.contains('Dashboard').should('be.visible')
    cy.get('[data-cy="user-menu"]').should('be.visible')
  })

  it('should handle session timeout', () => {
    // Login with short session timeout (mock)
    cy.login('testuser', 'testpass')

    // Fast-forward time to simulate session timeout
    cy.clock().then((clock) => {
      clock.tick(25 * 60 * 1000) // 25 minutes
    })

    // Try to access protected resource
    cy.visit('/dashboard')

    // Should redirect to login
    cy.url().should('include', '/login')
    cy.contains('Session expired').should('be.visible')
  })

  it('should allow logout', () => {
    // Login first
    cy.login('testuser', 'testpass')

    // Click logout
    cy.get('[data-cy="user-menu"]').click()
    cy.contains('Logout').click()

    // Verify logged out
    cy.url().should('include', '/login')
    cy.contains('Login').should('be.visible')

    // Try to access protected page
    cy.visit('/dashboard')

    // Should redirect to login
    cy.url().should('include', '/login')
  })

  it('should handle concurrent sessions properly', () => {
    // Login in first tab
    cy.login('testuser', 'testpass')

    // Open new tab/window
    cy.window().then((win) => {
      const newTab = win.open('/dashboard', '_blank')
      cy.wrap(newTab).as('newTab')
    })

    // Both tabs should work
    cy.get('@newTab').should('not.be.null')

    // Logout from first tab
    cy.get('[data-cy="user-menu"]').click()
    cy.contains('Logout').click()

    // First tab should be logged out
    cy.url().should('include', '/login')

    // Second tab should also be logged out (or show session expired)
    cy.get('@newTab').its('location.href').should('include', '/login')
  })

  it('should handle role-based access control', () => {
    // Login as admin
    cy.login('admin', 'adminpass')

    // Should see admin features
    cy.get('[data-cy="admin-panel"]').should('be.visible')
    cy.get('[data-cy="user-management"]').should('be.visible')

    // Logout
    cy.logout()

    // Login as regular user
    cy.login('regularuser', 'userpass')

    // Should not see admin features
    cy.get('[data-cy="admin-panel"]').should('not.exist')
    cy.get('[data-cy="user-management"]').should('not.exist')

    // Should see user features
    cy.get('[data-cy="user-profile"]').should('be.visible')
    cy.get('[data-cy="flight-bookings"]').should('be.visible')
  })

  it('should handle account lockout after failed attempts', () => {
    const username = 'testuser'

    // Attempt multiple failed logins
    for (let i = 0; i < 5; i++) {
      cy.get('[data-cy="username"]').clear().type(username)
      cy.get('[data-cy="password"]').clear().type('wrongpassword')
      cy.get('[data-cy="login-submit"]').click()

      // Wait for error message
      cy.contains('Invalid username or password').should('be.visible')
    }

    // Next attempt should show account locked
    cy.get('[data-cy="username"]').clear().type(username)
    cy.get('[data-cy="password"]').clear().type('correctpassword')
    cy.get('[data-cy="login-submit"]').click()

    cy.contains('Account locked').should('be.visible')
  })

  it('should validate password strength requirements', () => {
    // Navigate to registration
    cy.contains('Register').click()

    // Try weak password
    cy.get('[data-cy="username"]').type('testuser')
    cy.get('[data-cy="email"]').type('test@example.com')
    cy.get('[data-cy="password"]').type('weak')
    cy.get('[data-cy="confirm-password"]').type('weak')
    cy.get('[data-cy="register-submit"]').click()

    // Should show password strength error
    cy.contains('Password must be at least 8 characters').should('be.visible')

    // Try password without special characters
    cy.get('[data-cy="password"]').clear().type('weakpassword')
    cy.get('[data-cy="confirm-password"]').clear().type('weakpassword')
    cy.get('[data-cy="register-submit"]').click()

    cy.contains('Password must contain special characters').should('be.visible')

    // Try valid password
    cy.get('[data-cy="password"]').clear().type('StrongPass123!')
    cy.get('[data-cy="confirm-password"]').clear().type('StrongPass123!')
    cy.get('[data-cy="register-submit"]').click()

    // Should not show password errors
    cy.contains('Password must').should('not.exist')
  })

  it('should handle social login integration', () => {
    // Click Google login button
    cy.get('[data-cy="google-login"]').click()

    // Should redirect to Google OAuth
    cy.url().should('include', 'accounts.google.com')

    // Mock successful OAuth callback
    cy.visit('/auth/google/callback?code=mock_auth_code')

    // Should redirect to dashboard
    cy.url().should('include', '/dashboard')
    cy.contains('Welcome').should('be.visible')
  })

  it('should handle two-factor authentication', () => {
    // Login with 2FA enabled user
    cy.get('[data-cy="username"]').type('2fauser')
    cy.get('[data-cy="password"]').type('password')
    cy.get('[data-cy="login-submit"]').click()

    // Should show 2FA input
    cy.contains('Enter verification code').should('be.visible')
    cy.get('[data-cy="2fa-code"]').should('be.visible')

    // Enter valid 2FA code
    cy.get('[data-cy="2fa-code"]').type('123456')
    cy.get('[data-cy="2fa-submit"]').click()

    // Should login successfully
    cy.url().should('include', '/dashboard')
  })
})
