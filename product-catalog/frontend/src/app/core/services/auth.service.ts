import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { API_URL } from '../api.config';
import { AuthResponse, MeResponse, User } from '../../models/user';

const TOKEN_KEY = 'pc_token';
const USER_KEY = 'pc_user';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);

  login(email: string, password: string): Observable<AuthResponse> {
    return this.http
      .post<AuthResponse>(`${API_URL}/auth/login`, { email, password })
      .pipe(
        tap((response) => {
          localStorage.setItem(TOKEN_KEY, response.token);
          localStorage.setItem(USER_KEY, JSON.stringify(response.user));
        })
      );
  }

  me(): Observable<MeResponse> {
    return this.http.get<MeResponse>(`${API_URL}/auth/me`);
  }

  logout(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  }

  getToken(): string | null {
    return localStorage.getItem(TOKEN_KEY);
  }

  isAuthenticated(): boolean {
    return this.getToken() !== null;
  }

  currentUser(): User | null {
    const raw = localStorage.getItem(USER_KEY);

    if (raw === null) {
      return null;
    }

    try {
      return JSON.parse(raw) as User;
    } catch {
      return null;
    }
  }
}
