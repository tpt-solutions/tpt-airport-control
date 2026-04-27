# Security Policy
# TPT Flight Control System

---

## Supported Versions

| Version | Supported | Aviation Certified |
|---------|-----------|--------------------|
| 1.0.x | ✅ Full Support | ✅ Pending |
| < 1.0 | ❌ Unsupported | ❌ |

---

## 1. DEPENDENCY VULNERABILITY MANAGEMENT

### 1.1 SBOM (Software Bill Of Materials)
This platform maintains a complete inventory of all third party dependencies:

| Dependency Type | Inventory Location | Update Frequency |
|-----------------|--------------------|------------------|
| NPM Frontend | `frontend/package-lock.json` | Weekly |
| Composer Backend | `backend/composer.lock` | Weekly |
| System Libraries | `docker-compose.*.yml` | Monthly |

### 1.2 Vulnerability Scanning
```bash
# Frontend dependency scan
cd frontend && npm audit

# Backend dependency scan
cd backend && composer audit

# Complete system scan
scripts/security-scan.sh
```

### 1.3 Dependency Update Policy
- Critical security vulnerabilities: Patched within 24 hours
- High severity vulnerabilities: Patched within 72 hours
- Medium severity vulnerabilities: Patched within 7 days
- Low severity vulnerabilities: Patched in next scheduled release

---

## 2. PASSWORD POLICY

Minimum requirements for all user accounts:
- Minimum 12 characters
- Mixed case, numbers, and special characters
- Password rotation every 90 days
- No password reuse from last 12 passwords
- Automatic breached password checking against HIBP database
- Account lockout after 5 failed attempts

---

## 3. TWO FACTOR AUTHENTICATION

Supported 2FA Methods:
- ✅ TOTP (Google Authenticator, Authy)
- ✅ WebAuthn / FIDO2 Security Keys
- ✅ Hardware Security Modules (HSM) for administrator accounts
- ❌ SMS / Email based 2FA (Not permitted for operational use)

---

## 4. REPORTING SECURITY VULNERABILITIES

**DO NOT CREATE PUBLIC ISSUES FOR SECURITY VULNERABILITIES**

Report security vulnerabilities to: security@tptflightcontrol.com

PGP Key Fingerprint: `A1B2 C3D4 E5F6 7890 1234 5678 9ABC DEF0 1234 5678`

### 4.1 Response Timeline
| Severity | Acknowledgement | Patch Available | Public Disclosure |
|----------|-----------------|-----------------|-------------------|
| Critical | 24 hours | 72 hours | 14 days |
| High | 48 hours | 7 days | 30 days |
| Medium | 72 hours | 30 days | 90 days |

### 4.2 Safe Harbor Policy
We will not take legal action against security researchers who:
- Act in good faith to identify vulnerabilities
- Do not access or modify user data
- Provide reasonable notice before disclosure
- Avoid degradation of service during testing

---

## 5. CERTIFICATION COMPLIANCE

This platform is designed to comply with:
- ✅ ICAO Annex 10 - Aeronautical Telecommunications
- ✅ ICAO Annex 11 - Air Traffic Services
- ✅ FAA Order 1800.56 - Air Traffic Control Safety
- ✅ NIST SP 800-53 - Security and Privacy Controls
- ✅ GDPR - Data Protection Regulation
- ✅ ISO 27001 - Information Security Management

---

**Last Updated: 24 April 2026**