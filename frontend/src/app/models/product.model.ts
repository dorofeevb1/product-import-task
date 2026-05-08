export interface ProductAttribute {
  key: string;
  value: string;
}

export interface ProductImage {
  url: string;
  path: string;
}

export interface Product {
  id: number;
  externalCode: string;
  name: string;
  description: string;
  price: number;
  discount: number;
  attributes?: ProductAttribute[];
  images?: ProductImage[];
}

export interface ProductListResponse {
  items: Product[];
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

export interface ImportTaskStatus {
  id: string;
  status: 'queued' | 'processing' | 'completed' | 'failed';
  processedRows: number;
  failedRows: number;
  errors: string[];
}
