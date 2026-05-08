import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { mapHttpError } from '../services/error-mapper';
import { AuthService } from '../services/auth.service';

@Component({
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <section class="auth-layout">
      <div class="card stack auth-card">
        <h2>Вход</h2>
        <p class="muted no-top-margin">Войдите, чтобы продолжить импорт и управление товарами.</p>
        <p *ngIf="errorMessage" class="state-block state-error">{{ errorMessage }}</p>
        <div class="stack form-stack">
          <label class="sr-only" for="login-email">Электронная почта</label>
          <input id="login-email" class="input" [(ngModel)]="email" placeholder="Электронная почта" type="email" />
          <label class="sr-only" for="login-password">Пароль</label>
          <div class="password-field">
            <input
              id="login-password"
              class="input input-password"
              [(ngModel)]="password"
              [type]="showPassword ? 'text' : 'password'"
              placeholder="Пароль"
            />
            <button
              type="button"
              class="password-toggle"
              [attr.aria-label]="showPassword ? 'Скрыть пароль' : 'Показать пароль'"
              (click)="showPassword = !showPassword"
            >
              {{ showPassword ? 'Скрыть' : 'Показать' }}
            </button>
          </div>
          <button class="btn btn-primary" [disabled]="!email || !password || loading" (click)="login()">
            {{ loading ? 'Вход...' : 'Войти' }}
          </button>
          <a class="btn btn-ghost" routerLink="/register">Создать аккаунт</a>
        </div>
      </div>
    </section>
  `,
})
export class LoginPageComponent {
  email = '';
  password = '';
  showPassword = false;
  loading = false;
  errorMessage: string | null = null;

  constructor(private readonly authService: AuthService, private readonly router: Router) {}

  async login(): Promise<void> {
    this.loading = true;
    this.errorMessage = null;
    try {
      await firstValueFrom(this.authService.loginApi(this.email, this.password));
      await this.router.navigateByUrl('/import');
    } catch (error: unknown) {
      const appError = mapHttpError(error);
      this.errorMessage =
        appError.code === 'UNAUTHORIZED' ? 'Неверная почта или пароль.' : appError.message;
    } finally {
      this.loading = false;
    }
  }
}
