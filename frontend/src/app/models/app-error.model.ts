export type AppErrorCode =
  | 'UNAUTHORIZED'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'VALIDATION'
  | 'NETWORK'
  | 'SERVER'
  | 'UNKNOWN';

export interface AppError {
  code: AppErrorCode;
  message: string;
  status?: number;
  details?: string[];
}
