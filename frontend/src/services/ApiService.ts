/**
 * API Service for handling HTTP requests to the backend
 */

export interface ApiResponse<T = any> {
    success: boolean;
    data?: T;
    message?: string;
    error?: string;
}

export class ApiService {
    private baseUrl: string;
    private token: string | null = null;

    constructor(baseUrl: string = '') {
        this.baseUrl = baseUrl || window.location.origin + '/backend/api';
        this.token = localStorage.getItem('auth_token');
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

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        const config: RequestInit = {
            method,
            headers,
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

    setToken(token: string): void {
        this.token = token;
        localStorage.setItem('auth_token', token);
    }

    clearToken(): void {
        this.token = null;
        localStorage.removeItem('auth_token');
    }

    isAuthenticated(): boolean {
        return this.token !== null;
    }
}
