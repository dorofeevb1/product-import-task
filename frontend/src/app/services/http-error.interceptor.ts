import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { throwError } from 'rxjs';
import { catchError, switchMap } from 'rxjs/operators';
import { AuthService } from './auth.service';
import { SessionService } from './session.service';

export const apiErrorInterceptor: HttpInterceptorFn = (req, next) => {
  const authService = inject(AuthService);
  const sessionService = inject(SessionService);
  const isAuthEndpoint = req.url.includes('/api/auth/login')
    || req.url.includes('/api/auth/register')
    || req.url.includes('/api/auth/refresh')
    || req.url.includes('/api/auth/logout');
  const token = authService.getToken();
  const authReq = !isAuthEndpoint && token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req;
  return next(authReq).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 401 && !isAuthEndpoint) {
        return authService.refreshSession().pipe(
          switchMap((refreshed) => {
            if (!refreshed) {
              sessionService.handleUnauthorized();
              return throwError(() => error);
            }
            const nextToken = authService.getToken();
            if (!nextToken) {
              sessionService.handleUnauthorized();
              return throwError(() => error);
            }
            return next(req.clone({ setHeaders: { Authorization: `Bearer ${nextToken}` } }));
          }),
          catchError(() => {
            sessionService.handleUnauthorized();
            return throwError(() => error);
          })
        );
      }
      if (error.status >= 500) {
        console.error('Server error');
      }
      return throwError(() => error);
    })
  );
};
