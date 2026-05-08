import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { map } from 'rxjs';
import { AuthService } from './services/auth.service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule, RouterLink, RouterLinkActive, RouterOutlet],
  template: `
    <div class="container">
      <nav class="card nav-shell" *ngIf="authService.authenticated$ | async">
        <div class="nav-brand">
          <span class="nav-brand-dot" aria-hidden="true"></span>
          <span>Импорт товаров</span>
        </div>
        <div class="nav-links">
          <a routerLink="/import" routerLinkActive="nav-active">Импорт</a>
          <a routerLink="/products" routerLinkActive="nav-active" [routerLinkActiveOptions]="{ exact: true }">Товары</a>
          <button class="btn btn-ghost" (click)="logout()">Выйти</button>
        </div>
      </nav>
      <p *ngIf="sessionNotice$ | async as notice" class="state-block state-error">{{ notice }}</p>
      <router-outlet></router-outlet>
    </div>
  `,
})
export class AppComponent {
  readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  readonly sessionNotice$ = this.authService.logoutReason$.pipe(
    map((reason) => (reason === 'unauthorized' ? 'Сессия истекла. Войдите снова.' : null))
  );

  logout(): void {
    this.authService.logout();
    this.router.navigateByUrl('/login');
  }
}
