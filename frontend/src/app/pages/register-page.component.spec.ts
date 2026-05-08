import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { RegisterPageComponent } from './register-page.component';
import { AuthService } from '../services/auth.service';

describe('RegisterPageComponent', () => {
  beforeEach(async () => {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');

    await TestBed.configureTestingModule({
      imports: [RegisterPageComponent],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: 'import', children: [] }, { path: 'login', children: [] }]),
      ],
    }).compileComponents();
  });

  it('registers successfully and authenticates user', async () => {
    const fixture = TestBed.createComponent(RegisterPageComponent);
    const component = fixture.componentInstance;
    const authService = TestBed.inject(AuthService);
    const http = TestBed.inject(HttpTestingController);

    const seedRegister = firstValueFrom(
      authService.registerApi({
        name: 'Seed User',
        email: 'seed@example.com',
        password: 'strong123',
      })
    );
    http.expectOne('/api/auth/register').flush({
      token: 'seed-access',
      tokenType: 'Bearer',
      expiresIn: 3600,
      refreshToken: 'seed-refresh',
      user: { username: 'seed@example.com' },
    });
    await seedRegister;
    authService.logout();
    http.expectOne('/api/auth/logout').flush({ ok: true });

    component.name = 'John Doe';
    component.email = 'john@example.com';
    component.password = 'strong123';
    component.confirmPassword = 'strong123';
    const submitRegister = component.register();
    http.expectOne('/api/auth/register').flush({
      token: 'john-access',
      tokenType: 'Bearer',
      expiresIn: 3600,
      refreshToken: 'john-refresh',
      user: { username: 'john@example.com' },
    });
    await submitRegister;

    expect(component.apiError).toBeNull();
    expect(authService.isAuthenticated()).toBeTrue();
  });

  it('shows email exists error for duplicate registration', async () => {
    const authService = TestBed.inject(AuthService);
    const http = TestBed.inject(HttpTestingController);
    const seedRegister = firstValueFrom(
      authService.registerApi({
        name: 'John Doe',
        email: 'john@example.com',
        password: 'strong123',
      })
    );
    http.expectOne('/api/auth/register').flush({
      token: 'john-access',
      tokenType: 'Bearer',
      expiresIn: 3600,
      refreshToken: 'john-refresh',
      user: { username: 'john@example.com' },
    });
    await seedRegister;
    authService.logout();
    http.expectOne('/api/auth/logout').flush({ ok: true });

    const fixture = TestBed.createComponent(RegisterPageComponent);
    const component = fixture.componentInstance;
    component.name = 'John Doe 2';
    component.email = 'john@example.com';
    component.password = 'strong123';
    component.confirmPassword = 'strong123';
    const duplicateRegister = component.register();
    http.expectOne('/api/auth/register').flush(
      { code: 'HTTP_409', message: 'Email already exists', details: [] },
      { status: 409, statusText: 'Conflict' }
    );
    await duplicateRegister;

    expect(component.apiError?.message).toBe('Этот email уже зарегистрирован.');
  });
});
