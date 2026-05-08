import { bootstrapApplication } from '@angular/platform-browser';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideStore } from '@ngrx/store';
import { provideEffects } from '@ngrx/effects';
import { AppComponent } from './app/app.component';
import { routes } from './app/app.routes';
import { productsReducer } from './app/store/products.reducer';
import { ProductsEffects } from './app/store/products.effects';
import { apiErrorInterceptor } from './app/services/http-error.interceptor';

bootstrapApplication(AppComponent, {
  providers: [
    provideRouter(routes),
    provideHttpClient(withInterceptors([apiErrorInterceptor])),
    provideStore({ products: productsReducer }),
    provideEffects([ProductsEffects]),
  ],
}).catch((error) => console.error(error));
