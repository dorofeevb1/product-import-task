import { CommonModule } from '@angular/common';
import { Component, OnDestroy } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { EMPTY, Subscription, catchError, finalize, interval, switchMap, takeWhile } from 'rxjs';
import { ImportTaskStatus } from '../../models/product.model';
import { mapHttpError } from '../../services/error-mapper';
import { ImportService } from '../../services/import.service';

@Component({
  selector: 'app-import-page',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="card stack">
      <h2>Импорт товаров</h2>
      <p class="muted no-top-margin">Загрузите .xlsx файл для асинхронного импорта с отслеживанием статуса.</p>
      <div class="row">
        <label class="sr-only" for="import-file">Файл импорта</label>
        <input id="import-file" class="field" type="file" accept=".xlsx" (change)="onSelect($event)" />
        <button class="btn btn-primary" [disabled]="!file || uploading" (click)="upload()">
          {{ uploading ? 'Загрузка...' : 'Загрузить' }}
        </button>
      </div>
      <p class="state-block state-error" *ngIf="errorMessage">{{ errorMessage }}</p>
      <p class="state-block state-loading" *ngIf="uploading && !taskStatus">Подготавливаем задачу импорта...</p>
      <div class="row" *ngIf="taskStatus">
        <span class="pill pill-info">Статус: {{ statusLabel(taskStatus.status) }}</span>
        <span class="pill pill-success">Обработано: {{ taskStatus.processedRows }}</span>
        <span class="pill pill-danger">Ошибок: {{ taskStatus.failedRows }}</span>
      </div>
      <ul class="list" *ngIf="taskStatus?.errors?.length">
        <li class="list-item" *ngFor="let error of taskStatus?.errors">{{ error }}</li>
      </ul>
    </div>
  `,
})
export class ImportPageComponent implements OnDestroy {
  file: File | null = null;
  uploading = false;
  taskStatus: ImportTaskStatus | null = null;
  errorMessage: string | null = null;
  private pollSubscription: Subscription | null = null;

  constructor(private readonly importService: ImportService) {}

  onSelect(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.file = input.files?.[0] ?? null;
  }

  upload(): void {
    if (!this.file) return;
    this.uploading = true;
    this.errorMessage = null;
    this.taskStatus = null;
    this.importService
      .upload(this.file)
      .pipe(
        catchError((error: unknown) => {
          this.uploading = false;
          this.errorMessage = mapHttpError(error).message;
          return EMPTY;
        })
      )
      .subscribe((res) => {
        this.importService.saveLastTaskId(res.taskId);
        this.pollSubscription?.unsubscribe();
        this.pollSubscription = interval(2000)
          .pipe(
            switchMap(() => this.importService.status(res.taskId)),
            takeWhile((status) => status.status === 'queued' || status.status === 'processing', true),
            catchError((error: unknown) => {
              this.errorMessage = mapHttpError(error).message;
              return EMPTY;
            }),
            finalize(() => {
              this.uploading = false;
            })
          )
          .subscribe((status) => {
            this.taskStatus = status;
          });
      });
  }

  ngOnDestroy(): void {
    this.pollSubscription?.unsubscribe();
  }

  statusLabel(status: ImportTaskStatus['status']): string {
    if (status === 'queued') return 'в очереди';
    if (status === 'processing') return 'в обработке';
    if (status === 'completed') return 'завершено';
    if (status === 'failed') return 'ошибка';

    return status;
  }
}
