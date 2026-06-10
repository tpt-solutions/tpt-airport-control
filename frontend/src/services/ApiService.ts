import { AuthManager } from '../auth.js';

export interface ApiResponse<T = any> {
    success: boolean;
    data?: T;
    message?: string;
    error?: string;
}

export class ApiService {
    private baseUrl: string;

    constructor(baseUrl: string = '') {
        this.baseUrl = baseUrl || window.location.origin + '/backend/api';
    }

    private async request<T>(
        method: string,
        endpoint: string,
        data?: any
    ): Promise<ApiResponse<T>> {
        const url = `${this.baseUrl}${endpoint}`;

        const headers: HeadersInit = {
            'Content-Type': 'application/json',
        };

        const token = AuthManager.getInstance().getToken();
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        const config: RequestInit = {
            method,
            headers,
            credentials: 'include', // send httpOnly JWT cookie
        };

        if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
            config.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, config);
            const result = await response.json();

            if (!response.ok) {
                return {
                    success: false,
                    error: result.message || `HTTP ${response.status}`,
                };
            }

            return {
                success: true,
                data: result,
            };
        } catch (error) {
            console.error('API request failed:', error);
            return {
                success: false,
                error: 'Network error or server unavailable',
            };
        }
    }

    async get<T>(endpoint: string): Promise<ApiResponse<T>> {
        return this.request<T>('GET', endpoint);
    }

    async post<T>(endpoint: string, data?: any): Promise<ApiResponse<T>> {
        return this.request<T>('POST', endpoint, data);
    }

    async put<T>(endpoint: string, data?: any): Promise<ApiResponse<T>> {
        return this.request<T>('PUT', endpoint, data);
    }

    async patch<T>(endpoint: string, data?: any): Promise<ApiResponse<T>> {
        return this.request<T>('PATCH', endpoint, data);
    }

    async delete<T>(endpoint: string): Promise<ApiResponse<T>> {
        return this.request<T>('DELETE', endpoint);
    }

    setToken(_token: string): void {
        // No-op: token management is handled by AuthManager + httpOnly cookie.
    }

    clearToken(): void {
        AuthManager.getInstance().clearAuth();
    }

    isAuthenticated(): boolean {
        return AuthManager.getInstance().isAuthenticated();
    }
}
