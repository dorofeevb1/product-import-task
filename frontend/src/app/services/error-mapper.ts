import { HttpErrorResponse } from '@angular/common/http';
import { AppError } from '../models/app-error.model';

const DEFAULT_MESSAGE = 'Непредвиденная ошибка. Попробуйте еще раз.';

export function mapHttpError(error: unknown): AppError {
  if (!(error instanceof HttpErrorResponse)) {
    return { code: 'UNKNOWN', message: DEFAULT_MESSAGE };
  }

  if (error.status === 0) {
    return { code: 'NETWORK', message: 'Ошибка сети. Проверьте подключение и попробуйте снова.', status: 0 };
  }
  if (error.status === 401) {
    return { code: 'UNAUTHORIZED', message: 'Сессия истекла. Войдите снова.', status: 401 };
  }
  if (error.status === 403) {
    return { code: 'FORBIDDEN', message: 'У вас нет доступа к этому ресурсу.', status: 403 };
  }
  if (error.status === 404) {
    return { code: 'NOT_FOUND', message: 'Запрошенный ресурс не найден.', status: 404 };
  }
  if (error.status >= 400 && error.status < 500) {
    return { code: 'VALIDATION', message: 'Ошибка валидации запроса.', status: error.status };
  }
  if (error.status >= 500) {
    return { code: 'SERVER', message: 'Ошибка сервера. Попробуйте позже.', status: error.status };
  }

  return { code: 'UNKNOWN', message: DEFAULT_MESSAGE, status: error.status };
}
