import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './services/auth.guard';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'import' },
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () => import('./pages/login-page.component').then((m) => m.LoginPageComponent),
  },
  {
    path: 'register',
    canActivate: [guestGuard],
    loadComponent: () => import('./pages/register-page.component').then((m) => m.RegisterPageComponent),
  },
  {
    path: 'import',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/import/import-page.component').then((m) => m.ImportPageComponent),
  },
  {
    path: 'products',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/products/products-page.component').then((m) => m.ProductsPageComponent),
  },
  {
    path: 'products/:id',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/product-card/product-card-page.component').then((m) => m.ProductCardPageComponent),
  },
  { path: '**', redirectTo: 'login' },
];
