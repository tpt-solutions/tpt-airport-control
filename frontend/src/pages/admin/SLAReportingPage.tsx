/**
 * TPT Flight Control System
 * SLA Reporting Dashboard UI
 * 
 * Administration interface for SLA compliance monitoring, reporting and metrics
 */

import { useState, useEffect } from 'react';
import { format } from 'date-fns';

interface SLAReport {
  period_start: string;
  period_end: string;
  availability_percent: number;
  sla_target: number;
  sla_compliant: boolean;
  downtime_seconds: number;
  outage_count: number;
  sla_credit_earned: number;
}

interface PerformanceMetrics {
  average_response_ms: number;
  p95_response_ms: number;
  p99_response_ms: number;
  total_requests: number;
  sla_target_ms: number;
  sla_compliant: boolean;
}

interface Incident {
  id: number;
  title: string;
  start_time: string;
  end_time: string;
  duration_seconds: number;
  severity: 'low' | 'medium' | 'high' | 'critical';
  impacted_services: string[];
}

export default function SLAReportingPage() {
  const [report, setReport] = useState<SLAReport | null>(null);
  const [metrics, setMetrics] = useState<PerformanceMetrics | null>(null);
  const [incidents, setIncidents] = useState<Incident[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedPeriod, setSelectedPeriod] = useState(30);

  useEffect(() => {
    loadSLAReport();
  }, [selectedPeriod]);

  const loadSLAReport = async () => {
    setLoading(true);
    
    const [reportRes, metricsRes, incidentsRes] = await Promise.all([
      fetch(`/api/sla-report.php?period=${selectedPeriod}`),
      fetch('/api/sla-performance.php'),
      fetch('/api/sla-incidents.php')
    ]);

    const reportData = await reportRes.json();
    const metricsData = await metricsRes.json();
    const incidentsData = await incidentsRes.json();

    setReport(reportData);
    setMetrics(metricsData);
    setIncidents(incidentsData);
    setLoading(false);
  };

  const formatDuration = (seconds: number) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) return `${hours}h ${minutes}m ${secs}s`;
    if (minutes > 0) return `${minutes}m ${secs}s`;
    return `${secs}s`;
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
      <div className="p-6 max-w-6xl mx-auto">
        <div className="p-12 text-center text-gray-500">Loading SLA report data...</div>
      </div>
    );
  }

  return (
    <div className="p-6 max-w-6xl mx-auto">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">SLA Reporting Dashboard</h1>
          <p className="text-gray-500">Service level agreement compliance monitoring</p>
        </div>
        
        <select
          value={selectedPeriod}
          onChange={(e) => setSelectedPeriod(Number(e.target.value))}
          className="border rounded px-3 py-2"
        >
          <option value={7}>Last 7 Days</option>
          <option value={30}>Last 30 Days</option>
          <option value={90}>Last 90 Days</option>
          <option value={365}>Last 12 Months</option>
        </select>
      </div>

      {/* Availability Metrics */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div className="bg-white p-5 rounded-lg shadow">
          <div className="text-sm font-medium text-gray-500 mb-1">Availability</div>
          <div className={`text-3xl font-bold ${report?.sla_compliant ? 'text-green-600' : 'text-red-600'}`}>
            {report?.availability_percent}%
          </div>
          <div className="text-sm text-gray-500 mt-1">Target: {report?.sla_target}%</div>
        </div>

        <div className="bg-white p-5 rounded-lg shadow">
          <div className="text-sm font-medium text-gray-500 mb-1">Total Downtime</div>
          <div className="text-3xl font-bold text-gray-900">
            {report && formatDuration(report.downtime_seconds)}
          </div>
          <div className="text-sm text-gray-500 mt-1">Over {selectedPeriod} days</div>
        </div>

        <div className="bg-white p-5 rounded-lg shadow">
          <div className="text-sm font-medium text-gray-500 mb-1">Outage Count</div>
          <div className="text-3xl font-bold text-gray-900">{report?.outage_count}</div>
          <div className="text-sm text-gray-500 mt-1">Recorded incidents</div>
        </div>

        <div className="bg-white p-5 rounded-lg shadow">
          <div className="text-sm font-medium text-gray-500 mb-1">SLA Credit</div>
          <div className="text-3xl font-bold text-gray-900">${report?.sla_credit_earned}</div>
          <div className="text-sm text-gray-500 mt-1">Earned this period</div>
        </div>
      </div>

      {/* Performance Metrics */}
      <div className="bg-white rounded-lg shadow mb-6">
        <div className="p-4 border-b">
          <h3 className="font-semibold text-lg">Performance Metrics</h3>
        </div>
        <div className="p-4 grid grid-cols-1 md:grid-cols-4 gap-4">
          <div className="text-center p-3">
            <div className="text-sm text-gray-500">Average Response</div>
            <div className="text-2xl font-bold">{metrics?.average_response_ms}ms</div>
          </div>
          <div className="text-center p-3">
            <div className="text-sm text-gray-500">p95 Response</div>
            <div className={`text-2xl font-bold ${metrics && metrics.p95_response_ms <= metrics.sla_target_ms ? 'text-green-600' : 'text-red-600'}`}>
              {metrics?.p95_response_ms}ms
            </div>
          </div>
          <div className="text-center p-3">
            <div className="text-sm text-gray-500">p99 Response</div>
            <div className="text-2xl font-bold">{metrics?.p99_response_ms}ms</div>
          </div>
          <div className="text-center p-3">
            <div className="text-sm text-gray-500">Total Requests</div>
            <div className="text-2xl font-bold">{metrics?.total_requests.toLocaleString()}</div>
          </div>
        </div>
        <div className="p-4 border-t bg-gray-50 text-center text-sm">
          SLA Response Time Target: {metrics?.sla_target_ms}ms | 
          <span className={metrics?.sla_compliant ? 'text-green-600 font-medium' : 'text-red-600 font-medium'}>
            {metrics?.sla_compliant ? ' Compliant' : ' Non-Compliant'}
          </span>
        </div>
      </div>

      {/* Compliance Status */}
      <div className={`rounded-lg p-5 mb-6 ${
        report?.sla_compliant && metrics?.sla_compliant
          ? 'bg-green-50 border border-green-200'
          : 'bg-red-50 border border-red-200'
      }`}>
        <div className="flex items-center justify-between">
          <div>
            <div className={`font-semibold text-lg ${
              report?.sla_compliant && metrics?.sla_compliant ? 'text-green-800' : 'text-red-800'
            }`}>
              Overall SLA Status
            </div>
            <div className={`text-sm ${
              report?.sla_compliant && metrics?.sla_compliant ? 'text-green-700' : 'text-red-700'
            }`}>
              {report?.sla_compliant && metrics?.sla_compliant
                ? 'All service level agreement targets are currently being met'
                : 'One or more SLA targets are not being met'
              }
            </div>
          </div>
          <div className={`text-3xl font-bold ${
            report?.sla_compliant && metrics?.sla_compliant ? 'text-green-600' : 'text-red-600'
          }`}>
            {report?.sla_compliant && metrics?.sla_compliant ? '✓ COMPLIANT' : '⚠️ NON-COMPLIANT'}
          </div>
        </div>
      </div>

      {/* Incident History */}
      <div className="bg-white rounded-lg shadow">
        <div className="p-4 border-b">
          <h3 className="font-semibold text-lg">Incident History</h3>
        </div>
        <div className="divide-y">
          {incidents.length === 0 ? (
            <div className="p-8 text-center text-gray-500">No incidents recorded for this period</div>
          ) : (
            incidents.map((incident) => (
              <div key={incident.id} className="p-4">
                <div className="flex justify-between items-start">
                  <div>
                    <div className="flex items-center gap-2">
                      <span className={`px-2 py-1 rounded text-xs font-medium ${getSeverityBadge(incident.severity)}`}>
                        {incident.severity}
                      </span>
                      <span className="font-semibold">{incident.title}</span>
                    </div>
                    <div className="text-sm text-gray-500 mt-1">
                      {format(new Date(incident.start_time), 'yyyy-MM-dd HH:mm')} - 
                      {incident.end_time ? format(new Date(incident.end_time), 'HH:mm') : 'Ongoing'}
                      ({formatDuration(incident.duration_seconds)})
                    </div>
                    <div className="text-sm text-gray-600 mt-1">
                      Impacted: {incident.impacted_services.join(', ')}
                    </div>
                  </div>
                </div>
              </div>
            ))
          )}
        </div>
      </div>

      {/* Report Period Info */}
      <div className="mt-6 text-center text-sm text-gray-500">
        <p>Reporting Period: {report && format(new Date(report.period_start), 'yyyy-MM-dd HH:mm')} to {report && format(new Date(report.period_end), 'yyyy-MM-dd HH:mm')}</p>
        <p className="mt-1">SLA Target: 99.9% Availability | 100ms p95 Response Time</p>
      </div>
    </div>
  );
}