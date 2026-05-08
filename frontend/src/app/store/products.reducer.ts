import { createReducer, on } from '@ngrx/store';
import { AppError } from '../models/app-error.model';
import { Product } from '../models/product.model';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

export interface ProductsState {
  items: Product[];
  loading: boolean;
  error: AppError | null;
  totalPages: number;
}

export const initialState: ProductsState = {
  items: [],
  loading: false,
  error: null,
  totalPages: 0,
};

export const productsReducer = createReducer(
  initialState,
  on(loadProducts, (state) => ({ ...state, loading: true, error: null })),
  on(loadProductsSuccess, (state, { response }) => ({
    ...state,
    loading: false,
    items: response.items,
    totalPages: response.totalPages,
  })),
  on(loadProductsFailure, (state, { error }) => ({ ...state, loading: false, error }))
);
