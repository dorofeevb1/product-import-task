import { Injectable, inject } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { of } from 'rxjs';
import { catchError, map, switchMap } from 'rxjs/operators';
import { ProductService } from '../services/product.service';
import { mapHttpError } from '../services/error-mapper';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

@Injectable()
export class ProductsEffects {
  private readonly actions$ = inject(Actions);
  private readonly productService = inject(ProductService);

  loadProducts$ = createEffect(() =>
    this.actions$.pipe(
      ofType(loadProducts),
      switchMap((action) =>
        this.productService.getProducts(action.page, action.limit, action.name ?? '', action.priceMin, action.priceMax).pipe(
          map((response) => loadProductsSuccess({ response })),
          catchError((error: unknown) => of(loadProductsFailure({ error: mapHttpError(error) })))
        )
      )
    )
  );
}
