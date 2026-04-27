/**
 * TPT Flight Control System
 * Rate Limit Status & Usage Page
 * 
 * User interface for viewing current rate limit usage, quotas and request history
 */

import { useState, useEffect } from 'react';
import { formatDistanceToNow, format } from 'date-fns';

interface RateLimitData {
  user_id: number;
  current_plan: string;
  limits: {
    requests_per_minute: number;
    requests_per_hour: number;
    requests_per_day: number;
  };
  usage: {
    minute: number;
    hour: number;
    day: number;
  };
  remaining: {
    requests_per_minute: number;
    requests_per_hour: number;
    requests_per_day: number;
  };
  reset_times: {
    minute: number;
    hour: number;
    day: number;
  };
  history: Array<{
    timestamp: number;
    endpoint: string;
    method: string;
    response_code: number;
    rate_limited: boolean;
  }>;
  rate_limit_increase_allowed: boolean;
  support_ticket_link: string;
}

export default function RateLimitsPage() {
  const [data, setData] = useState<RateLimitData | null>(null);
  const [loading, setLoading] = useState(true);
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null);

  useEffect(() => {
    loadRateLimits();
    const interval = setInterval(loadRateLimits, 15000);
    return () => clearInterval(interval);
  }, []);

  const loadRateLimits = async () => {
    const response = await fetch('/api/rate-limits.php');
    const result = await response.json();
    setData(result);
    setLoading(false);
    setLastUpdated(new Date());
  };

  const getUsagePercentage = (used: number, limit: number) => {
    return Math.min(100, (used / limit) * 100);
  };

  const getProgressBarColor = (percentage: number) => {
    if (percentage >= 90) return 'bg-red-500';
    if (percentage >= 75) return 'bg-yellow-500';
    return 'bg-green-500';
  };

  const formatResetTime = (timestamp: number) => {
    const seconds = timestamp - Math.floor(Date.now() / 1000);
    if (seconds < 60) return `${seconds} seconds`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)} minutes`;
    return `${Math.floor(seconds / 3600)} hours ${Math.floor((seconds % 3600) / 60)} minutes`;
  };

  if (loading) {
    return (
      <div className="p-6 max-w-5xl mx-auto">
        <div className="p-12 text-center text-gray-500">Loading rate limit status...</div>
      </div>
    );
  }

  if (!data) {
    return (
      <div className="p-6 max-w-5xl mx-auto">
        <div className="p-12 text-center text-gray-500">Failed to load rate limit information</div>
      </div>
    );
  }

  return (
    <div className="p-6 max-w-5xl mx-auto">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Rate Limits</h1>
          <p className="text-gray-500">Current plan: <span className="font-medium">{data.current_plan}</span></p>
        </div>
        <div className="text-sm text-gray-500">
          Last updated: {lastUpdated && formatDistanceToNow(lastUpdated, { addSuffix: true })}
        </div>
      </div>

      {/* Rate Limit Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        {/* Per Minute */}
        <div className="bg-white p-5 rounded-lg shadow">
          <div className="text-sm font-medium text-gray-500 mb-1">Requests per Minute</div>
          <div className="text-3xl font-bold mb-2">
            {data.usage.minute} <span className="text-xl text-gray-400">/ {data.limits.requests_per_minute}</span>
          </div>
          <div className="w-full h-3 bg-gray-200 rounded-full overflow-hidden mb-2">
            <div 
              className={`h-full ${getProgressBarColor(getUsagePercentage(data.usage.minute, data.limits.requests_per_minute))} transition-all duration-300`}
              style={{ width: `${getUsagePercentage(data.usage.minute, data.limits.requests_per_minute)}%` }}
            />
          </div>
          <div className="flex justify-between text-sm">
            <span className="text-gray-500">{data.remaining.requests_per_minute} remaining</span>
            <span className="text-gray-500">Resets in {formatResetTime(data.reset_times.minute)}</span>
          </div>
        </div>

        {/* Per Hour */}
        <div className="bg-white p-5 rounded-lg shadow">
          <div className="text-sm font-medium text-gray-500 mb-1">Requests per Hour</div>
          <div className="text-3xl font-bold mb-2">
            {data.usage.hour} <span className="text-xl text-gray-400">/ {data.limits.requests_per_hour}</span>
          </div>
          <div className="w-full h-3 bg-gray-200 rounded-full overflow-hidden mb-2">
            <div 
              className={`h-full ${getProgressBarColor(getUsagePercentage(data.usage.hour, data.limits.requests_per_hour))} transition-all duration-300`}
              style={{ width: `${getUsagePercentage(data.usage.hour, data.limits.requests_per_hour)}%` }}
            />
          </div>
          <div className="flex justify-between text-sm">
            <span className="text-gray-500">{data.remaining.requests_per_hour} remaining</span>
            <span className="text-gray-500">Resets in {formatResetTime(data.reset_times.hour)}</span>
          </div>
        </div>

        {/* Per Day */}
        <div className="bg-white p-5 rounded-lg shadow">
          <div className="text-sm font-medium text-gray-500 mb-1">Requests per Day</div>
          <div className="text-3xl font-bold mb-2">
            {data.usage.day} <span className="text-xl text-gray-400">/ {data.limits.requests_per_day}</span>
          </div>
          <div className="w-full h-3 bg-gray-200 rounded-full overflow-hidden mb-2">
            <div 
              className={`h-full ${getProgressBarColor(getUsagePercentage(data.usage.day, data.limits.requests_per_day))} transition-all duration-300`}
              style={{ width: `${getUsagePercentage(data.usage.day, data.limits.requests_per_day)}%` }}
            />
          </div>
          <div className="flex justify-between text-sm">
            <span className="text-gray-500">{data.remaining.requests_per_day} remaining</span>
            <span className="text-gray-500">Resets in {formatResetTime(data.reset_times.day)}</span>
          </div>
        </div>
      </div>

      {/* Request History */}
      <div className="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div className="px-4 py-3 border-b bg-gray-50">
          <h3 className="font-semibold text-gray-700">Request History (Last 24 Hours)</h3>
        </div>
        <div className="divide-y divide-gray-100">
          {data.history.length === 0 ? (
            <div className="p-8 text-center text-gray-500">No request history available</div>
          ) : (
            data.history.slice(0, 20).map((entry, index) => (
              <div key={index} className="px-4 py-3 flex items-center justify-between hover:bg-gray-50">
                <div className="flex items-center gap-4">
                  <span className={`px-2 py-1 rounded text-xs font-medium ${
                    entry.rate_limited ? 'bg-red-100 text-red-800' :
                    entry.response_code >= 400 ? 'bg-yellow-100 text-yellow-800' :
                    'bg-green-100 text-green-800'
                  }`}>
                    {entry.method} {entry.response_code}
                  </span>
                  <span className="font-mono text-sm">{entry.endpoint}</span>
                </div>
                <div className="text-sm text-gray-500">
                  {format(new Date(entry.timestamp * 1000), 'HH:mm:ss')}
                </div>
              </div>
            ))
          )}
        </div>
      </div>

      {/* Rate Limit Increase Request */}
      {data.rate_limit_increase_allowed ? (
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <div className="flex justify-between items-center">
            <div>
              <div className="font-medium text-blue-800">Need higher rate limits?</div>
              <div className="text-sm text-blue-600">Contact support to request increased quota for your account</div>
            </div>
            <a 
              href={data.support_ticket_link}
              className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
            >
              Request Increase
            </a>
          </div>
        </div>
      ) : (
        <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
          <div className="text-sm text-gray-600">
            Rate limit increases are available for paid plan accounts. Upgrade your plan to request higher quotas.
          </div>
        </div>
      )}
    </div>
  );
}