import { createAction, props } from '@ngrx/store';
import { AppError } from '../models/app-error.model';
import { ProductListResponse } from '../models/product.model';

export const loadProducts = createAction(
  '[Products] Load Products',
  props<{ page: number; limit: number; name?: string; priceMin?: number; priceMax?: number }>()
);

export const loadProductsSuccess = createAction(
  '[Products] Load Products Success',
  props<{ response: ProductListResponse }>()
);

export const loadProductsFailure = createAction('[Products] Load Products Failure', props<{ error: AppError }>());
