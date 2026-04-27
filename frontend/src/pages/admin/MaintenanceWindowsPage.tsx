/**
 * TPT Flight Control System
 * Maintenance Window Management Page
 * 
 * User interface for scheduling, managing and monitoring system maintenance windows
 */

import { useState, useEffect } from 'react';
import { format, differenceInMinutes } from 'date-fns';

interface MaintenanceWindow {
  id: number;
  title: string;
  description: string;
  start_time: string;
  end_time: string;
  read_only_mode: boolean;
  notify_users: boolean;
  status: 'scheduled' | 'active' | 'completed' | 'cancelled';
  created_by: number;
  created_at: string;
  activated_at: string | null;
  completed_at: string | null;
}

interface NewWindowForm {
  title: string;
  description: string;
  start_time: string;
  end_time: string;
  read_only_mode: boolean;
  notify_users: boolean;
}

export default function MaintenanceWindowsPage() {
  const [windows, setWindows] = useState<MaintenanceWindow[]>([]);
  const [loading, setLoading] = useState(true);
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [activeWindow, setActiveWindow] = useState<MaintenanceWindow | null>(null);
  const [formData, setFormData] = useState<NewWindowForm>({
    title: '',
    description: '',
    start_time: '',
    end_time: '',
    read_only_mode: false,
    notify_users: true
  });

  useEffect(() => {
    loadMaintenanceWindows();
  }, []);

  const loadMaintenanceWindows = async () => {
    setLoading(true);
    const response = await fetch('/api/maintenance-windows.php');
    const data = await response.json();
    
    setWindows(data.windows || []);
    setActiveWindow(data.active || null);
    setLoading(false);
  };

  const createWindow = async (e: React.FormEvent) => {
    e.preventDefault();
    
    await fetch('/api/maintenance-windows.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(formData)
    });

    setShowCreateForm(false);
    setFormData({
      title: '',
      description: '',
      start_time: '',
      end_time: '',
      read_only_mode: false,
      notify_users: true
    });
    
    loadMaintenanceWindows();
  };

  const activateWindow = async (windowId: number) => {
    if (!confirm('Are you sure you want to activate this maintenance window now? System may enter read-only mode.')) return;
    
    await fetch(`/api/maintenance-windows.php?id=${windowId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'activate' })
    });
    
    loadMaintenanceWindows();
  };

  const completeWindow = async (windowId: number) => {
    if (!confirm('Are you sure you want to complete this maintenance window? System will return to normal operation.')) return;
    
    await fetch(`/api/maintenance-windows.php?id=${windowId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'complete' })
    });
    
    loadMaintenanceWindows();
  };

  const cancelWindow = async (windowId: number) => {
    if (!confirm('Are you sure you want to cancel this scheduled maintenance window?')) return;
    
    await fetch(`/api/maintenance-windows.php?id=${windowId}`, {
      method: 'DELETE'
    });
    
    loadMaintenanceWindows();
  };

  const getStatusBadgeClass = (status: string) => {
    switch (status) {
      case 'active': return 'bg-red-100 text-red-800';
      case 'scheduled': return 'bg-yellow-100 text-yellow-800';
      case 'completed': return 'bg-green-100 text-green-800';
      case 'cancelled': return 'bg-gray-100 text-gray-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getDuration = (start: string, end: string) => {
    const minutes = differenceInMinutes(new Date(end), new Date(start));
    if (minutes < 60) return `${minutes} minutes`;
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;
    return remaining > 0 ? `${hours}h ${remaining}m` : `${hours} hours`;
  };

  return (
    <div className="p-6 max-w-7xl mx-auto">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Maintenance Windows</h1>
          <p className="text-gray-500">Schedule and manage system maintenance periods</p>
        </div>
        
        <button 
          onClick={() => setShowCreateForm(true)}
          className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          Schedule Maintenance
        </button>
      </div>

      {/* Active Maintenance Banner */}
      {activeWindow && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <div className="flex justify-between items-center">
            <div>
              <div className="font-semibold text-red-800 flex items-center gap-2">
                <span className="animate-pulse">●</span>
                MAINTENANCE IN PROGRESS
              </div>
              <div className="text-red-700 mt-1">
                {activeWindow.title} - Scheduled until {format(new Date(activeWindow.end_time), 'yyyy-MM-dd HH:mm')}
              </div>
              {activeWindow.read_only_mode && (
                <div className="text-red-600 text-sm mt-1 font-medium">
                  ⚠️ System is currently in READ-ONLY mode
                </div>
              )}
            </div>
            <button
              onClick={() => completeWindow(activeWindow.id)}
              className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
            >
              Complete Now
            </button>
          </div>
        </div>
      )}

      {/* Create New Window Form */}
      {showCreateForm && (
        <div className="mb-6 bg-white p-6 rounded-lg shadow">
          <h3 className="text-lg font-semibold mb-4">Schedule New Maintenance Window</h3>
          <form onSubmit={createWindow} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <input
                  type="text"
                  required
                  value={formData.title}
                  onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                  className="w-full border rounded px-3 py-2"
                  placeholder="Maintenance window title"
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <input
                  type="text"
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  className="w-full border rounded px-3 py-2"
                  placeholder="Brief description of maintenance work"
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                <input
                  type="datetime-local"
                  required
                  value={formData.start_time}
                  onChange={(e) => setFormData({ ...formData, start_time: e.target.value })}
                  className="w-full border rounded px-3 py-2"
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                <input
                  type="datetime-local"
                  required
                  value={formData.end_time}
                  onChange={(e) => setFormData({ ...formData, end_time: e.target.value })}
                  className="w-full border rounded px-3 py-2"
                />
              </div>
            </div>
            
            <div className="flex items-center gap-6 mt-4">
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={formData.read_only_mode}
                  onChange={(e) => setFormData({ ...formData, read_only_mode: e.target.checked })}
                />
                <span className="text-sm">Enable Read-Only Mode during maintenance</span>
              </label>
              
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={formData.notify_users}
                  onChange={(e) => setFormData({ ...formData, notify_users: e.target.checked })}
                />
                <span className="text-sm">Notify all users about this maintenance</span>
              </label>
            </div>
            
            <div className="flex gap-2 mt-6">
              <button
                type="submit"
                className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
              >
                Schedule Maintenance
              </button>
              <button
                type="button"
                onClick={() => setShowCreateForm(false)}
                className="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300"
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Maintenance Windows List */}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        {loading ? (
          <div className="p-12 text-center text-gray-500">Loading maintenance windows...</div>
        ) : windows.length === 0 ? (
          <div className="p-12 text-center text-gray-500">No maintenance windows scheduled</div>
        ) : (
          <table className="w-full">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Schedule</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {windows.map((window) => (
                <tr key={window.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3">
                    <span className={`px-2 py-1 rounded text-xs ${getStatusBadgeClass(window.status)}`}>
                      {window.status}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="font-medium">{window.title}</div>
                    <div className="text-sm text-gray-500">{window.description}</div>
                  </td>
                  <td className="px-4 py-3 text-sm">
                    <div>{format(new Date(window.start_time), 'yyyy-MM-dd HH:mm')}</div>
                    <div className="text-gray-500">to {format(new Date(window.end_time), 'HH:mm')}</div>
                  </td>
                  <td className="px-4 py-3 text-sm">
                    {getDuration(window.start_time, window.end_time)}
                  </td>
                  <td className="px-4 py-3 text-sm">
                    {window.read_only_mode ? (
                      <span className="text-yellow-600">Read-Only</span>
                    ) : (
                      <span className="text-gray-500">Normal</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-sm">
                    <div className="flex gap-2">
                      {window.status === 'scheduled' && (
                        <>
                          <button
                            onClick={() => activateWindow(window.id)}
                            className="text-blue-600 hover:text-blue-800"
                          >
                            Activate
                          </button>
                          <button
                            onClick={() => cancelWindow(window.id)}
                            className="text-red-600 hover:text-red-800"
                          >
                            Cancel
                          </button>
                        </>
                      )}
                      {window.status === 'active' && (
                        <button
                          onClick={() => completeWindow(window.id)}
                          className="text-green-600 hover:text-green-800"
                        >
                          Complete
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}