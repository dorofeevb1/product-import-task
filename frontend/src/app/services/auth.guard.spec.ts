import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { Router, provideRouter } from '@angular/router';
import { guestGuard } from './auth.guard';

describe('guestGuard', () => {
  beforeEach(() => {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
  });

  it('blocks /register for authenticated user', () => {
    localStorage.setItem('access_token', 'stub-access');
    localStorage.setItem('refresh_token', 'stub-refresh');
    const router = TestBed.inject(Router);

    const result = TestBed.runInInjectionContext(() => guestGuard({} as never, {} as never));
    expect(result).not.toBeTrue();
    expect(router.serializeUrl(result as ReturnType<Router['createUrlTree']>)).toBe('/import');
  });
});
