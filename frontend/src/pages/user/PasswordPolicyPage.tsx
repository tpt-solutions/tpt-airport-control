/**
 * TPT Flight Control System
 * Password Policy & Expiry Management Page
 * 
 * User interface for password policy compliance, strength indicators and expiry notifications
 */

import { useState, useEffect, useCallback } from 'react';

interface PasswordStatus {
  password_age_days: number;
  days_until_expiry: number;
  is_expired: boolean;
  last_changed: string;
  policy_compliant: boolean;
}

interface PolicyRequirement {
  id: string;
  label: string;
  met: boolean;
}

export default function PasswordPolicyPage() {
  const [status, setStatus] = useState<PasswordStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [currentPassword, setCurrentPassword] = useState('');
  const [requirements, setRequirements] = useState<PolicyRequirement[]>([]);
  const [entropy, setEntropy] = useState(0);
  const [changeInProgress, setChangeInProgress] = useState(false);
  const [changeResult, setChangeResult] = useState<{ success: boolean; message: string } | null>(null);

  useEffect(() => {
    loadPasswordStatus();
  }, []);

  const loadPasswordStatus = async () => {
    const response = await fetch('/api/password-status.php');
    const data = await response.json();
    setStatus(data);
    setLoading(false);
  };

  const validatePassword = useCallback((password: string) => {
    const checks: PolicyRequirement[] = [
      { id: 'length', label: 'Minimum 12 characters', met: password.length >= 12 },
      { id: 'uppercase', label: 'Contains uppercase letter', met: /[A-Z]/.test(password) },
      { id: 'lowercase', label: 'Contains lowercase letter', met: /[a-z]/.test(password) },
      { id: 'number', label: 'Contains number', met: /[0-9]/.test(password) },
      { id: 'special', label: 'Contains special character', met: /[!@#$%^&*()\-_=+{};:,<.>]/.test(password) },
      { id: 'entropy', label: 'Minimum 60 bits entropy', met: calculateEntropy(password) >= 60 },
    ];
    
    setRequirements(checks);
    setEntropy(calculateEntropy(password));
  }, []);

  const calculateEntropy = (password: string): number => {
    let charsetSize = 0;
    if (/[a-z]/.test(password)) charsetSize += 26;
    if (/[A-Z]/.test(password)) charsetSize += 26;
    if (/[0-9]/.test(password)) charsetSize += 10;
    if (/[^a-zA-Z0-9]/.test(password)) charsetSize += 32;
    
    return charsetSize > 0 ? password.length * Math.log2(charsetSize) : 0;
  };

  const getStrengthLevel = () => {
    if (newPassword.length === 0) return { level: 0, label: '', color: 'bg-gray-200' };
    if (entropy < 40) return { level: 25, label: 'Weak', color: 'bg-red-500' };
    if (entropy < 60) return { level: 50, label: 'Fair', color: 'bg-yellow-500' };
    if (entropy < 80) return { level: 75, label: 'Good', color: 'bg-blue-500' };
    return { level: 100, label: 'Strong', color: 'bg-green-500' };
  };

  const handlePasswordChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const password = e.target.value;
    setNewPassword(password);
    validatePassword(password);
    setChangeResult(null);
  };

  const changePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (newPassword !== confirmPassword) {
      setChangeResult({ success: false, message: 'Passwords do not match' });
      return;
    }

    setChangeInProgress(true);
    
    const response = await fetch('/api/change-password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        current_password: currentPassword,
        new_password: newPassword
      })
    });

    const result = await response.json();
    setChangeInProgress(false);
    setChangeResult(result);

    if (result.success) {
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
      setRequirements([]);
      loadPasswordStatus();
    }
  };

  const strength = getStrengthLevel();

  if (loading) {
    return (
      <div className="p-6 max-w-2xl mx-auto">
        <div className="p-12 text-center text-gray-500">Loading password status...</div>
      </div>
    );
  }

  return (
    <div className="p-6 max-w-2xl mx-auto">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Password Security</h1>
        <p className="text-gray-500">Manage your password and policy compliance</p>
      </div>

      {/* Expiry Warning Banner */}
      {status && status.days_until_expiry <= 14 && (
        <div className={`mb-6 p-4 rounded-lg ${status.is_expired ? 'bg-red-50 border border-red-200' : 'bg-yellow-50 border border-yellow-200'}`}>
          <div className={`font-semibold ${status.is_expired ? 'text-red-800' : 'text-yellow-800'}`}>
            {status.is_expired ? '⚠️ Password Expired' : '⚠️ Password Expiring Soon'}
          </div>
          <p className={`text-sm mt-1 ${status.is_expired ? 'text-red-700' : 'text-yellow-700'}`}>
            {status.is_expired 
              ? 'Your password has expired. Please change your password immediately.'
              : `Your password will expire in ${status.days_until_expiry} days.`
            }
          </p>
        </div>
      )}

      {/* Status Card */}
      {status && (
        <div className="bg-white rounded-lg shadow mb-6 p-5">
          <h3 className="font-semibold text-lg mb-4">Password Status</h3>
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <div className="text-gray-500">Last changed</div>
              <div className="font-medium">{status.last_changed}</div>
            </div>
            <div>
              <div className="text-gray-500">Password age</div>
              <div className="font-medium">{status.password_age_days} days</div>
            </div>
            <div>
              <div className="text-gray-500">Days until expiry</div>
              <div className={`font-medium ${status.days_until_expiry <= 14 ? 'text-red-600' : ''}`}>
                {status.days_until_expiry} days
              </div>
            </div>
            <div>
              <div className="text-gray-500">Policy compliance</div>
              <div className={`font-medium ${status.policy_compliant ? 'text-green-600' : 'text-red-600'}`}>
                {status.policy_compliant ? 'Compliant' : 'Non-Compliant'}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Change Password Form */}
      <div className="bg-white rounded-lg shadow mb-6">
        <div className="p-5 border-b">
          <h3 className="font-semibold text-lg">Change Password</h3>
        </div>
        
        <div className="p-5">
          <form onSubmit={changePassword} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
              <input
                type="password"
                required
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                className="w-full border rounded px-3 py-2"
                autoComplete="current-password"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">New Password</label>
              <input
                type="password"
                required
                value={newPassword}
                onChange={handlePasswordChange}
                className="w-full border rounded px-3 py-2"
                autoComplete="new-password"
              />
              
              {/* Strength Bar */}
              {newPassword.length > 0 && (
                <div className="mt-2">
                  <div className="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className={`h-full transition-all duration-300 ${strength.color}`}
                      style={{ width: `${strength.level}%` }}
                    />
                  </div>
                  <div className="flex justify-between mt-1 text-xs">
                    <span className={`font-medium ${strength.level >= 75 ? 'text-green-600' : strength.level >= 50 ? 'text-yellow-600' : 'text-red-600'}`}>
                      {strength.label}
                    </span>
                    <span className="text-gray-500">{entropy.toFixed(1)} bits entropy</span>
                  </div>
                </div>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
              <input
                type="password"
                required
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                className={`w-full border rounded px-3 py-2 ${
                  confirmPassword && newPassword !== confirmPassword ? 'border-red-500' : ''
                }`}
                autoComplete="new-password"
              />
              {confirmPassword && newPassword !== confirmPassword && (
                <div className="text-sm text-red-600 mt-1">Passwords do not match</div>
              )}
            </div>

            {/* Policy Requirements */}
            {requirements.length > 0 && (
              <div className="bg-gray-50 p-4 rounded">
                <div className="text-sm font-medium text-gray-700 mb-2">Password Requirements</div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                  {requirements.map((req) => (
                    <div key={req.id} className="flex items-center gap-2 text-sm">
                      <span className={`w-4 h-4 rounded-full flex items-center justify-center text-xs ${
                        req.met ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-400'
                      }`}>
                        {req.met ? '✓' : '○'}
                      </span>
                      <span className={req.met ? 'text-gray-700' : 'text-gray-500'}>{req.label}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {changeResult && (
              <div className={`p-3 rounded ${changeResult.success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}>
                {changeResult.message}
              </div>
            )}

            <button
              type="submit"
              disabled={changeInProgress}
              className="w-full px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            >
              {changeInProgress ? 'Changing Password...' : 'Change Password'}
            </button>
          </form>
        </div>
      </div>

      {/* Policy Information */}
      <div className="bg-gray-50 border border-gray-200 rounded-lg p-5">
        <h3 className="font-semibold mb-3">Password Policy Information</h3>
        <ul className="text-sm text-gray-600 space-y-2">
          <li>• Minimum 12 character password length</li>
          <li>• Requires uppercase, lowercase, number and special character</li>
          <li>• Minimum 60 bits of entropy required</li>
          <li>• Passwords expire every 90 days</li>
          <li>• Cannot reuse last 12 passwords</li>
          <li>• Common passwords are automatically blocked</li>
          <li>• Compliant with NIST SP 800-63B security standards</li>
        </ul>
      </div>
    </div>
  );
}