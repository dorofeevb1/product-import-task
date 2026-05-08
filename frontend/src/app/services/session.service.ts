import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from './auth.service';

@Injectable({ providedIn: 'root' })
export class SessionService {
  private unauthorizedRedirectInProgress = false;

  constructor(private readonly authService: AuthService, private readonly router: Router) {}

  handleUnauthorized(): void {
    if (this.unauthorizedRedirectInProgress) {
      return;
    }
    this.unauthorizedRedirectInProgress = true;
    this.authService.logout('unauthorized');

    if (this.router.url === '/login' || this.router.url === '/register') {
      this.unauthorizedRedirectInProgress = false;
      return;
    }

    void this.router.navigateByUrl('/login').finally(() => {
      this.unauthorizedRedirectInProgress = false;
    });
  }
}
