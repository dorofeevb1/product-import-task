import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable, map, of, tap } from 'rxjs';

export type RegisterErrorCode = 'EMAIL_EXISTS' | 'NETWORK' | 'SERVER';
export type LogoutReason = 'manual' | 'unauthorized' | 'session_expired';

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
}

export interface RegisterResult {
  ok: boolean;
  errorCode?: RegisterErrorCode;
}

interface LoginResponse {
  token: string;
  tokenType: string;
  expiresIn: number;
  refreshToken: string;
  user: {
    username: string;
  };
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly tokenKey = 'access_token';
  private readonly refreshTokenKey = 'refresh_token';
  private readonly authenticatedSubject = new BehaviorSubject<boolean>(this.hasToken());
  readonly authenticated$: Observable<boolean> = this.authenticatedSubject.asObservable();
  private readonly logoutReasonSubject = new BehaviorSubject<LogoutReason | null>(null);
  readonly logoutReason$: Observable<LogoutReason | null> = this.logoutReasonSubject.asObservable();

  constructor(private readonly http: HttpClient) {}

  private hasToken(): boolean {
    return Boolean(localStorage.getItem(this.tokenKey) && localStorage.getItem(this.refreshTokenKey));
  }

  isAuthenticated(): boolean {
    return this.hasToken();
  }

  loginApi(username: string, password: string): Observable<boolean> {
    return this.http.post<LoginResponse>('/api/auth/login', { username, password }).pipe(
      tap((response) => this.storeSession(response)),
      map(() => true)
    );
  }

  getToken(): string | null {
    return localStorage.getItem(this.tokenKey);
  }

  getRefreshToken(): string | null {
    return localStorage.getItem(this.refreshTokenKey);
  }

  logout(reason: LogoutReason = 'manual'): void {
    const refreshToken = this.getRefreshToken();
    if (refreshToken) {
      this.http.post('/api/auth/logout', { refreshToken }).subscribe({ error: () => undefined });
    }
    localStorage.removeItem(this.tokenKey);
    localStorage.removeItem(this.refreshTokenKey);
    this.authenticatedSubject.next(false);
    this.logoutReasonSubject.next(reason);
  }

  registerApi(payload: RegisterPayload): Observable<RegisterResult> {
    return this.http.post<LoginResponse>('/api/auth/register', payload).pipe(
      tap((response) => this.storeSession(response)),
      map(() => ({ ok: true }))
    );
  }

  refreshSession(): Observable<boolean> {
    const refreshToken = this.getRefreshToken();
    if (!refreshToken) {
      return of(false);
    }
    return this.http.post<LoginResponse>('/api/auth/refresh', { refreshToken }).pipe(
      tap((response) => this.storeSession(response)),
      map(() => true)
    );
  }

  private storeSession(response: LoginResponse): void {
    localStorage.setItem(this.tokenKey, response.token);
    localStorage.setItem(this.refreshTokenKey, response.refreshToken);
    this.authenticatedSubject.next(true);
    this.logoutReasonSubject.next(null);
  }
}
