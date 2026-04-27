/**
 * TPT Flight Control System
 * Two Factor Authentication Management Page
 * 
 * User interface for TOTP 2FA setup, verification and management
 */

import { useState, useEffect } from 'react';

interface TwoFactorStatus {
  enabled: boolean;
  backup_codes_remaining: number;
  created_at: string | null;
}

interface SetupData {
  secret: string;
  qrcode_url: string;
  backup_codes: string[];
}

export default function TwoFactorAuthPage() {
  const [status, setStatus] = useState<TwoFactorStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [setupMode, setSetupMode] = useState(false);
  const [setupData, setSetupData] = useState<SetupData | null>(null);
  const [verificationCode, setVerificationCode] = useState('');
  const [verificationError, setVerificationError] = useState('');
  const [showBackupCodes, setShowBackupCodes] = useState(false);

  useEffect(() => {
    loadStatus();
  }, []);

  const loadStatus = async () => {
    const response = await fetch('/api/2fa-status.php');
    const data = await response.json();
    setStatus(data);
    setLoading(false);
  };

  const startSetup = async () => {
    const response = await fetch('/api/2fa-setup.php', { method: 'POST' });
    const data = await response.json();
    setSetupData(data);
    setSetupMode(true);
    setVerificationCode('');
    setVerificationError('');
  };

  const verifyAndEnable = async (e: React.FormEvent) => {
    e.preventDefault();
    setVerificationError('');

    const response = await fetch('/api/2fa-enable.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        secret: setupData?.secret,
        code: verificationCode
      })
    });

    const result = await response.json();
    
    if (result.success) {
      setSetupMode(false);
      setSetupData(null);
      loadStatus();
    } else {
      setVerificationError(result.message || 'Verification failed. Please check the code and try again.');
    }
  };

  const disable2FA = async () => {
    if (!confirm('Are you sure you want to disable Two Factor Authentication? This will reduce your account security.')) return;
    
    await fetch('/api/2fa-disable.php', { method: 'POST' });
    loadStatus();
  };

  const regenerateBackupCodes = async () => {
    if (!confirm('Generating new backup codes will invalidate all existing codes. Are you sure you want to continue?')) return;
    
    const response = await fetch('/api/2fa-regenerate-codes.php', { method: 'POST' });
    const data = await response.json();
    
    if (data.backup_codes) {
      setSetupData({ ...setupData!, backup_codes: data.backup_codes });
      setShowBackupCodes(true);
    }
    
    loadStatus();
  };

  if (loading) {
    return (
      <div className="p-6 max-w-2xl mx-auto">
        <div className="p-12 text-center text-gray-500">Loading status...</div>
      </div>
    );
  }

  return (
    <div className="p-6 max-w-2xl mx-auto">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Two Factor Authentication</h1>
        <p className="text-gray-500">Add an extra layer of security to your account</p>
      </div>

      {/* Status Card */}
      <div className="bg-white rounded-lg shadow mb-6">
        <div className="p-5 border-b">
          <div className="flex justify-between items-center">
            <div>
              <div className="font-semibold text-lg">Status</div>
              <div className="flex items-center gap-2 mt-1">
                {status?.enabled ? (
                  <>
                    <span className="w-3 h-3 bg-green-500 rounded-full"></span>
                    <span className="text-green-700 font-medium">Enabled</span>
                  </>
                ) : (
                  <>
                    <span className="w-3 h-3 bg-gray-300 rounded-full"></span>
                    <span className="text-gray-600">Disabled</span>
                  </>
                )}
              </div>
            </div>
            
            {status?.enabled ? (
              <button
                onClick={disable2FA}
                className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
              >
                Disable 2FA
              </button>
            ) : (
              <button
                onClick={startSetup}
                className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
              >
                Setup 2FA
              </button>
            )}
          </div>
        </div>

        {status?.enabled && (
          <div className="p-5">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <div className="text-gray-500">Enabled since</div>
                <div className="font-medium">{status.created_at}</div>
              </div>
              <div>
                <div className="text-gray-500">Backup codes remaining</div>
                <div className="font-medium">{status.backup_codes_remaining} codes</div>
              </div>
            </div>
            
            <div className="mt-4">
              <button
                onClick={regenerateBackupCodes}
                className="text-blue-600 hover:text-blue-800 text-sm"
              >
                Regenerate backup codes
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Setup Wizard */}
      {setupMode && setupData && (
        <div className="bg-white rounded-lg shadow mb-6">
          <div className="p-5 border-b">
            <h3 className="font-semibold text-lg">Setup Two Factor Authentication</h3>
          </div>
          
          <div className="p-5 space-y-6">
            <div>
              <div className="text-sm font-medium text-gray-700 mb-2">Step 1: Scan QR Code</div>
              <p className="text-sm text-gray-500 mb-3">
                Scan this QR code with your authenticator app (Google Authenticator, Authy, 1Password, etc.)
              </p>
              
              <div className="bg-white p-4 border rounded flex justify-center">
                <div className="w-48 h-48 bg-gray-100 flex items-center justify-center text-gray-500 text-sm">
                  {/* QR Code will be rendered here */}
                  QR Code
                </div>
              </div>
              
              <div className="mt-3 text-sm">
                <span className="text-gray-500">Manual setup code:</span>
                <code className="ml-2 bg-gray-100 px-2 py-1 rounded font-mono">
                  {setupData.secret}
                </code>
              </div>
            </div>

            <div>
              <div className="text-sm font-medium text-gray-700 mb-2">Step 2: Verify Code</div>
              <p className="text-sm text-gray-500 mb-3">
                Enter the 6-digit verification code from your authenticator app
              </p>
              
              <form onSubmit={verifyAndEnable}>
                <input
                  type="text"
                  maxLength={6}
                  pattern="[0-9]{6}"
                  required
                  value={verificationCode}
                  onChange={(e) => {
                    setVerificationCode(e.target.value.replace(/[^0-9]/g, ''));
                    setVerificationError('');
                  }}
                  className="w-full max-w-xs border rounded px-3 py-2 text-center text-2xl tracking-[0.5em] font-mono"
                  placeholder="000000"
                />
                
                {verificationError && (
                  <div className="mt-2 text-sm text-red-600">{verificationError}</div>
                )}
                
                <div className="mt-4 flex gap-2">
                  <button
                    type="submit"
                    className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                  >
                    Enable 2FA
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      setSetupMode(false);
                      setSetupData(null);
                    }}
                    className="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300"
                  >
                    Cancel
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Backup Codes Display */}
      {showBackupCodes && setupData?.backup_codes && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-5 mb-6">
          <div className="font-semibold text-yellow-800 mb-2">⚠️ Save these backup codes</div>
          <p className="text-sm text-yellow-700 mb-4">
            These codes can be used if you lose access to your authenticator device. Store them securely and do not share them with anyone.
          </p>
          
          <div className="grid grid-cols-2 gap-2 font-mono text-sm bg-white p-3 rounded border mb-4">
            {setupData.backup_codes.map((code, index) => (
              <div key={index}>{code}</div>
            ))}
          </div>
          
          <button
            onClick={() => setShowBackupCodes(false)}
            className="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700"
          >
            I have saved these codes
          </button>
        </div>
      )}

      {/* Security Information */}
      <div className="bg-gray-50 border border-gray-200 rounded-lg p-5">
        <h3 className="font-semibold mb-3">About Two Factor Authentication</h3>
        <ul className="text-sm text-gray-600 space-y-2">
          <li>• Uses Time-based One-Time Password (TOTP) standard RFC 6238</li>
          <li>• 30 second rotating verification codes</li>
          <li>• Compatible with all major authenticator applications</li>
          <li>• Recovery codes provided for emergency access</li>
          <li>• All verification operations are performed server-side</li>
        </ul>
      </div>
    </div>
  );
}