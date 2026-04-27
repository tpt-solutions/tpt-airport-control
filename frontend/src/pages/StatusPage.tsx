/**
 * TPT Flight Control System
 * Public System Status Page
 * 
 * Publicly accessible real-time system status, uptime metrics and incident history
 */

import { useState, useEffect } from 'react';
import { format, formatDistanceToNow } from 'date-fns';

interface SystemStatus {
  status: 'operational' | 'degraded' | 'partial_outage' | 'major_outage';
  components: Array<{
    name: string;
    status: 'operational' | 'degraded' | 'outage';
    description: string;
    updated_at: string;
  }>;
  active_incidents: Array<{
    id: number;
    title: string;
    severity: 'low' | 'medium' | 'high' | 'critical';
    status: 'investigating' | 'identified' | 'monitoring' | 'resolved';
    description: string;
    started_at: string;
    updates: Array<{
      message: string;
      timestamp: string;
    }>;
  }>;
  uptime: {
    '24h': number;
    '7d': number;
    '30d': number;
    '90d': number;
  };
  sla_availability: number;
  sla_target: number;
}

export default function StatusPage() {
  const [status, setStatus] = useState<SystemStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null);

  useEffect(() => {
    loadStatus();
    const interval = setInterval(loadStatus, 60000);
    return () => clearInterval(interval);
  }, []);

  const loadStatus = async () => {
    const response = await fetch('/api/system-status.php');
    const data = await response.json();
    setStatus(data);
    setLoading(false);
    setLastUpdated(new Date());
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'operational': return 'bg-green-500';
      case 'degraded': return 'bg-yellow-500';
      case 'partial_outage': return 'bg-orange-500';
      case 'major_outage': return 'bg-red-500';
      case 'outage': return 'bg-red-500';
      default: return 'bg-gray-500';
    }
  };

  const getStatusText = (status: string) => {
    switch (status) {
      case 'operational': return 'All Systems Operational';
      case 'degraded': return 'Degraded Performance';
      case 'partial_outage': return 'Partial Outage';
      case 'major_outage': return 'Major Outage';
      default: return 'Unknown Status';
    }
  };

  const getSeverityBadge = (severity: string) => {
    switch (severity) {
      case 'critical': return 'bg-red-100 text-red-800';
      case 'high': return 'bg-orange-100 text-orange-800';
      case 'medium': return 'bg-yellow-100 text-yellow-800';
      case 'low': return 'bg-blue-100 text-blue-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50">
        <div className="max-w-4xl mx-auto px-4 py-12">
          <div className="text-center text-gray-500">Loading system status...</div>
        </div>
      </div>
    );
  }

  if (!status) {
    return (
      <div className="min-h-screen bg-gray-50">
        <div className="max-w-4xl mx-auto px-4 py-12">
          <div className="text-center text-gray-500">Unable to load system status</div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-4xl mx-auto px-4 py-12">
        {/* Header */}
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-gray-900">TPT Flight Control Status</h1>
          <p className="text-gray-500 mt-1">Real-time system operational status</p>
        </div>

        {/* Overall Status Banner */}
        <div className={`rounded-lg shadow p-6 mb-6 ${
          status.status === 'operational' ? 'bg-green-50 border border-green-200' :
          status.status === 'degraded' ? 'bg-yellow-50 border border-yellow-200' :
          status.status === 'partial_outage' ? 'bg-orange-50 border border-orange-200' :
          'bg-red-50 border border-red-200'
        }`}>
          <div className="flex items-center justify-center gap-3">
            <span className={`w-4 h-4 rounded-full ${getStatusColor(status.status)} animate-pulse`}></span>
            <span className={`text-xl font-semibold ${
              status.status === 'operational' ? 'text-green-800' :
              status.status === 'degraded' ? 'text-yellow-800' :
              status.status === 'partial_outage' ? 'text-orange-800' :
              'text-red-800'
            }`}>
              {getStatusText(status.status)}
            </span>
          </div>
        </div>

        {/* Component Status */}
        <div className="bg-white rounded-lg shadow mb-6">
          <div className="p-4 border-b">
            <h2 className="font-semibold text-lg">Component Status</h2>
          </div>
          <div className="divide-y">
            {status.components.map((component, index) => (
              <div key={index} className="p-4 flex items-center justify-between">
                <div>
                  <div className="font-medium">{component.name}</div>
                  <div className="text-sm text-gray-500">{component.description}</div>
                </div>
                <div className="flex items-center gap-2">
                  <span className={`w-3 h-3 rounded-full ${getStatusColor(component.status)}`}></span>
                  <span className="text-sm font-medium capitalize">{component.status}</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Uptime Statistics */}
        <div className="bg-white rounded-lg shadow mb-6">
          <div className="p-4 border-b">
            <h2 className="font-semibold text-lg">Uptime Statistics</h2>
          </div>
          <div className="p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
            {Object.entries(status.uptime).map(([period, percentage]) => (
              <div key={period} className="p-3">
                <div className="text-2xl font-bold text-gray-900">{percentage}%</div>
                <div className="text-sm text-gray-500 uppercase">{period}</div>
              </div>
            ))}
          </div>
          <div className="p-4 border-t bg-gray-50 text-center text-sm text-gray-600">
            SLA Availability Target: {status.sla_target}% | Current: {status.sla_availability}%
          </div>
        </div>

        {/* Active Incidents */}
        {status.active_incidents.length > 0 && (
          <div className="bg-white rounded-lg shadow mb-6">
            <div className="p-4 border-b">
              <h2 className="font-semibold text-lg">Active Incidents</h2>
            </div>
            <div className="divide-y">
              {status.active_incidents.map((incident) => (
                <div key={incident.id} className="p-4">
                  <div className="flex items-start justify-between mb-3">
                    <div>
                      <div className="flex items-center gap-2">
                        <span className={`px-2 py-1 rounded text-xs font-medium ${getSeverityBadge(incident.severity)}`}>
                          {incident.severity}
                        </span>
                        <h3 className="font-semibold">{incident.title}</h3>
                      </div>
                      <div className="text-sm text-gray-500 mt-1">
                        Started {formatDistanceToNow(new Date(incident.started_at), { addSuffix: true })}
                      </div>
                    </div>
                    <span className="text-sm font-medium capitalize px-2 py-1 bg-gray-100 rounded">
                      {incident.status}
                    </span>
                  </div>
                  
                  <p className="text-gray-700 mb-3">{incident.description}</p>
                  
                  {incident.updates.length > 0 && (
                    <div className="mt-3 pl-4 border-l-2 border-gray-200 space-y-2">
                      {incident.updates.map((update, idx) => (
                        <div key={idx} className="text-sm">
                          <div className="text-gray-500">{format(new Date(update.timestamp), 'yyyy-MM-dd HH:mm')}</div>
                          <div className="text-gray-700">{update.message}</div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Footer */}
        <div className="text-center text-sm text-gray-500 mt-8">
          <p>Last updated: {lastUpdated && format(lastUpdated, 'yyyy-MM-dd HH:mm:ss')}</p>
          <p className="mt-1">Automatically refreshes every 60 seconds</p>
        </div>
      </div>
    </div>
  );
}