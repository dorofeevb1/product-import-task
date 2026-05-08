import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ImportTaskStatus } from '../models/product.model';

@Injectable({ providedIn: 'root' })
export class ImportService {
  private readonly lastTaskIdKey = 'last_import_task_id';

  constructor(private readonly http: HttpClient) {}

  upload(file: File): Observable<{ taskId: string; status: string }> {
    const formData = new FormData();
    formData.append('file', file);
    return this.http.post<{ taskId: string; status: string }>('/api/import', formData);
  }

  status(taskId: string): Observable<ImportTaskStatus> {
    return this.http.get<ImportTaskStatus>(`/api/import/${taskId}`);
  }

  saveLastTaskId(taskId: string): void {
    localStorage.setItem(this.lastTaskIdKey, taskId);
  }

  getLastTaskId(): string | null {
    return localStorage.getItem(this.lastTaskIdKey);
  }
}
