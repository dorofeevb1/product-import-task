import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';
import { Observable } from 'rxjs';
import { AppError } from '../../models/app-error.model';
import { Product } from '../../models/product.model';
import { loadProducts } from '../../store/products.actions';
import { selectAllProducts, selectProductsError, selectProductsLoading, selectTotalPages } from '../../store/products.selectors';

@Component({
  selector: 'app-products-page',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="card stack">
      <h2 class="mb-0">Товары</h2>
      <div class="row">
        <label class="sr-only" for="product-search">Поиск по названию товара</label>
        <input id="product-search" class="input form-control" [(ngModel)]="name" placeholder="Поиск по названию товара" />
        <label class="sr-only" for="product-price-min">Цена от</label>
        <input id="product-price-min" class="input form-control" [(ngModel)]="priceMin" type="number" placeholder="Цена от" />
        <label class="sr-only" for="product-price-max">Цена до</label>
        <input id="product-price-max" class="input form-control" [(ngModel)]="priceMax" type="number" placeholder="Цена до" />
        <button class="btn btn-primary" (click)="applyFilters()">Найти</button>
      </div>
      <p *ngIf="loading$ | async" class="state-block state-loading">Загрузка товаров...</p>
      <p *ngIf="error$ | async as error" class="state-block state-error">{{ error.message }}</p>
      <p *ngIf="!(loading$ | async) && !(error$ | async) && (products$ | async)?.length === 0" class="state-block state-empty">
        По текущим фильтрам товары не найдены.
      </p>
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Наименование</th>
              <th>Цена</th>
            </tr>
          </thead>
          <tbody>
            <tr *ngFor="let item of products$ | async">
              <td><a [routerLink]="['/products', item.id]"><strong>{{ item.name }}</strong></a></td>
              <td class="muted">{{ item.price | number: '1.2-2' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="row row-center" *ngIf="totalPages$ | async as totalPages">
        <button class="btn btn-ghost" (click)="prevPage()" [disabled]="currentPage <= 1">Назад</button>
        <span class="pill pill-info">Страница {{ currentPage }} / {{ totalPages || 1 }}</span>
        <button class="btn btn-ghost" (click)="nextPage(totalPages || 1)" [disabled]="currentPage >= (totalPages || 1)">Вперед</button>
      </div>
    </div>
  `,
})
export class ProductsPageComponent {
  products$: Observable<Product[]>;
  loading$: Observable<boolean>;
  error$: Observable<AppError | null>;
  totalPages$: Observable<number>;

  name = '';
  priceMin: number | null = null;
  priceMax: number | null = null;
  currentPage = 1;
  readonly limit = 20;

  constructor(private readonly store: Store) {
    this.products$ = this.store.select(selectAllProducts);
    this.loading$ = this.store.select(selectProductsLoading);
    this.error$ = this.store.select(selectProductsError);
    this.totalPages$ = this.store.select(selectTotalPages);
    this.reload();
  }

  reload(): void {
    this.store.dispatch(
      loadProducts({
        page: this.currentPage,
        limit: this.limit,
        name: this.name,
        priceMin: this.priceMin ?? undefined,
        priceMax: this.priceMax ?? undefined,
      })
    );
  }

  applyFilters(): void {
    this.currentPage = 1;
    this.reload();
  }

  prevPage(): void {
    if (this.currentPage <= 1) return;
    this.currentPage--;
    this.reload();
  }

  nextPage(totalPages: number): void {
    if (this.currentPage >= (totalPages || 1)) return;
    this.currentPage++;
    this.reload();
  }
}
