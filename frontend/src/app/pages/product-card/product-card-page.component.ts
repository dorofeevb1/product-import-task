import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { EMPTY, Observable, catchError, finalize, interval, map, of, startWith, switchMap, takeWhile } from 'rxjs';
import { ImportTaskStatus, Product, ProductAttribute } from '../../models/product.model';
import { mapHttpError } from '../../services/error-mapper';
import { ImportService } from '../../services/import.service';
import { ProductService } from '../../services/product.service';

@Component({
  selector: 'app-product-card-page',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="card stack">
      <p class="state-block state-error" *ngIf="errorMessage">{{ errorMessage }}</p>

      <ng-container *ngIf="product$ | async as p">
        <h2>{{ p.name }}</h2>
        <ng-container *ngIf="importStatus$ | async as importStatus">
          <p class="state-block state-loading">
            Статус импорта: {{ statusLabel(importStatus.status) }} | Обработано: {{ importStatus.processedRows }} | Ошибок: {{ importStatus.failedRows }}
          </p>
          <ul class="list" *ngIf="importStatus.errors?.length">
            <li class="list-item" *ngFor="let importError of importStatus.errors">{{ importError }}</li>
          </ul>
        </ng-container>
        <div class="row">
          <span class="pill pill-info">Код: {{ p.externalCode }}</span>
          <span class="pill pill-success">Цена: {{ p.price | number: '1.2-2' }}</span>
          <span class="pill pill-danger">Скидка: {{ p.discount | number: '1.0-2' }}%</span>
        </div>

        <h3 class="mt-2">Изображения</h3>
        <div class="image-grid" *ngIf="p.images.length > 0; else noImages">
          <article class="image-card" *ngFor="let i of p.images">
            <img class="product-image" [src]="i.path || i.url" [alt]="p.name" loading="lazy" />
            <a [href]="i.url" target="_blank" rel="noopener" class="muted">Открыть источник</a>
          </article>
        </div>
        <ng-template #noImages>
          <p class="state-block state-empty">Для этого товара нет изображений.</p>
        </ng-template>

        <p class="muted no-margin">{{ p.description }}</p>

        <h3 class="mt-2">Атрибуты</h3>
        <ng-container *ngFor="let section of groupedAttributes(p.attributes)">
          <h4 class="muted">{{ section.title }}</h4>
          <ul class="list">
            <li class="list-item" *ngFor="let a of section.items">
              {{ a.key }}: {{ a.value }}
            </li>
          </ul>
        </ng-container>
      </ng-container>

      <button *ngIf="errorMessage" class="btn btn-ghost" (click)="retry()">Повторить</button>
      <div *ngIf="!errorMessage && isLoading" class="state-block state-loading">Загрузка товара...</div>
    </div>
  `,
})
export class ProductCardPageComponent {
  product$: Observable<Product>;
  importStatus$: Observable<ImportTaskStatus | null>;
  errorMessage: string | null = null;
  isLoading = true;

  constructor(
    private readonly route: ActivatedRoute,
    private readonly productService: ProductService,
    private readonly importService: ImportService
  ) {
    this.product$ = this.loadProduct();
    this.importStatus$ = this.watchLatestImportStatus();
  }

  retry(): void {
    this.product$ = this.loadProduct();
  }

  private loadProduct(): Observable<Product> {
    this.errorMessage = null;
    this.isLoading = true;
    return this.route.paramMap.pipe(
      map((params) => Number(params.get('id'))),
      switchMap((id) => {
        if (!Number.isInteger(id) || id <= 0) {
          this.errorMessage = 'Некорректный ID товара.';
          this.isLoading = false;
          return EMPTY;
        }
        return this.productService.getProduct(id).pipe(
          catchError((error: unknown) => {
            this.errorMessage = mapHttpError(error).message;
            return EMPTY;
          }),
          finalize(() => {
            this.isLoading = false;
          })
        );
      })
    );
  }

  private watchLatestImportStatus(): Observable<ImportTaskStatus | null> {
    const taskId = this.importService.getLastTaskId();
    if (!taskId) {
      return of(null);
    }

    return interval(2500).pipe(
      startWith(0),
      switchMap(() =>
        this.importService.status(taskId).pipe(
          catchError(() => of(null))
        )
      ),
      takeWhile(
        (status) => status === null || status.status === 'queued' || status.status === 'processing',
        true
      )
    );
  }

  statusLabel(status: ImportTaskStatus['status']): string {
    if (status === 'queued') return 'в очереди';
    if (status === 'processing') return 'в обработке';
    if (status === 'completed') return 'завершено';
    if (status === 'failed') return 'ошибка';

    return status;
  }

  groupedAttributes(attributes: ProductAttribute[] | undefined): Array<{ title: string; items: ProductAttribute[] }> {
    if (!attributes || attributes.length === 0) {
      return [];
    }

    const sections = new Map<string, ProductAttribute[]>([
      ['Основное', []],
      ['Логистика', []],
      ['SEO', []],
      ['Маркировка', []],
      ['Прочее', []],
    ]);

    for (const attribute of attributes) {
      const section = this.resolveAttributeSection(attribute.key);
      sections.get(section)?.push(attribute);
    }

    return Array.from(sections.entries())
      .filter(([, items]) => items.length > 0)
      .map(([title, items]) => ({ title, items }));
  }

  private resolveAttributeSection(rawKey: string): string {
    const key = rawKey.toLowerCase();

    if (this.hasAny(key, ['seo', 'meta', 'slug', 'keywords', 'description'])) {
      return 'SEO';
    }

    if (this.hasAny(key, ['маркиров', 'штрих', 'barcode', 'ean', 'gtin', 'артикул', 'sku'])) {
      return 'Маркировка';
    }

    if (this.hasAny(key, ['вес', 'weight', 'габар', 'размер', 'объем', 'длина', 'ширина', 'высота', 'упаков', 'склад', 'логист'])) {
      return 'Логистика';
    }

    if (this.hasAny(key, ['бренд', 'категор', 'производ', 'страна', 'материал', 'цвет', 'гарант', 'модель', 'тип'])) {
      return 'Основное';
    }

    return 'Прочее';
  }

  private hasAny(value: string, needles: string[]): boolean {
    return needles.some((needle) => value.includes(needle));
  }
}
