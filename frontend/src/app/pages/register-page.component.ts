import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { AppError } from '../models/app-error.model';
import { mapHttpError } from '../services/error-mapper';
import { AuthService } from '../services/auth.service';

@Component({
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <section class="auth-layout">
      <div class="card stack auth-card">
        <h2>Регистрация</h2>
        <p class="muted no-top-margin">Создайте аккаунт для доступа к импорту и товарам.</p>

        <div class="stack form-stack">
          <label class="sr-only" for="register-name">Имя</label>
          <input
            id="register-name"
            class="input"
            [(ngModel)]="name"
            placeholder="Имя"
            [attr.aria-invalid]="validationError !== null"
            [attr.aria-describedby]="validationError ? 'register-validation-error' : null"
          />
          <label class="sr-only" for="register-email">Электронная почта</label>
          <input
            id="register-email"
            class="input"
            [(ngModel)]="email"
            type="email"
            placeholder="Электронная почта"
            [attr.aria-invalid]="validationError !== null || apiError !== null"
            [attr.aria-describedby]="validationError || apiError ? 'register-validation-error' : null"
          />
          <label class="sr-only" for="register-password">Пароль</label>
          <div class="password-field">
            <input
              id="register-password"
              class="input input-password"
              [(ngModel)]="password"
              [type]="showPassword ? 'text' : 'password'"
              placeholder="Пароль (минимум 6 символов)"
              [attr.aria-invalid]="validationError !== null"
              [attr.aria-describedby]="validationError ? 'register-validation-error' : null"
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
          <label class="sr-only" for="register-confirm-password">Подтвердите пароль</label>
          <div class="password-field">
            <input
              id="register-confirm-password"
              class="input input-password"
              [(ngModel)]="confirmPassword"
              [type]="showConfirmPassword ? 'text' : 'password'"
              placeholder="Подтвердите пароль"
              [attr.aria-invalid]="validationError !== null"
              [attr.aria-describedby]="validationError ? 'register-validation-error' : null"
            />
            <button
              type="button"
              class="password-toggle"
              [attr.aria-label]="showConfirmPassword ? 'Скрыть подтверждение пароля' : 'Показать подтверждение пароля'"
              (click)="showConfirmPassword = !showConfirmPassword"
            >
              {{ showConfirmPassword ? 'Скрыть' : 'Показать' }}
            </button>
          </div>
        </div>

        <p id="register-validation-error" *ngIf="validationError" class="state-block state-error">{{ validationError }}</p>
        <p *ngIf="apiError" class="state-block state-error">{{ apiError.message }}</p>

        <div class="row">
          <button class="btn btn-primary" [disabled]="loading" (click)="register()">
            {{ loading ? 'Создание аккаунта...' : 'Создать аккаунт' }}
          </button>
          <a class="btn btn-ghost" routerLink="/login">Назад ко входу</a>
        </div>
      </div>
    </section>
  `,
})
export class RegisterPageComponent {
  name = '';
  email = '';
  password = '';
  confirmPassword = '';
  showPassword = false;
  showConfirmPassword = false;
  loading = false;
  validationError: string | null = null;
  apiError: AppError | null = null;

  constructor(private readonly authService: AuthService, private readonly router: Router) {}

  async register(): Promise<void> {
    this.validationError = this.validate();
    this.apiError = null;
    if (this.validationError !== null) {
      return;
    }

    this.loading = true;
    try {
      await firstValueFrom(
        this.authService.registerApi({
          name: this.name,
          email: this.email,
          password: this.password,
        })
      );
      await this.router.navigateByUrl('/import');
    } catch (error: unknown) {
      const appError = mapHttpError(error);
      this.apiError = appError.code === 'VALIDATION' && appError.status === 409
        ? { code: 'VALIDATION', message: 'Этот email уже зарегистрирован.', status: 409 }
        : appError;
    } finally {
      this.loading = false;
    }
  }

  private validate(): string | null {
    if (!this.name.trim()) return 'Введите имя.';
    const normalizedEmail = this.email.trim();
    if (!normalizedEmail) return 'Введите email.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedEmail)) return 'Введите корректный email.';
    if (this.password.length < 6) return 'Пароль должен быть не короче 6 символов.';
    if (this.password !== this.confirmPassword) return 'Пароли не совпадают.';
    return null;
  }

}
