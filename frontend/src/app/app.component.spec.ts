import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter, withDisabledInitialNavigation } from '@angular/router';
import { AppComponent } from './app.component';
import { AuthService } from './services/auth.service';

describe('AppComponent auth state wiring', () => {
  beforeEach(async () => {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    await TestBed.configureTestingModule({
      imports: [AppComponent],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter(
          [{ path: 'login', children: [] }, { path: 'register', children: [] }, { path: 'import', children: [] }],
          withDisabledInitialNavigation()
        ),
      ],
    }).compileComponents();
  });

  it('keeps guest as unauthenticated', () => {
    const authService = TestBed.inject(AuthService);
    expect(authService.isAuthenticated()).toBeFalse();
  });

  it('marks authenticated user when both tokens exist', () => {
    localStorage.setItem('access_token', 'stub-access');
    localStorage.setItem('refresh_token', 'stub-refresh');
    const authService = TestBed.inject(AuthService);
    expect(authService.isAuthenticated()).toBeTrue();
  });
});
