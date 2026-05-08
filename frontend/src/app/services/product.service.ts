import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { Product, ProductListResponse } from '../models/product.model';

@Injectable({ providedIn: 'root' })
export class ProductService {
  private readonly baseUrl = '/api/products';

  constructor(private readonly http: HttpClient) {}

  getProducts(page: number, limit: number, name = '', priceMin?: number, priceMax?: number): Observable<ProductListResponse> {
    let params = new HttpParams().set('page', page).set('limit', limit);
    if (name) params = params.set('name', name);
    if (priceMin !== undefined) params = params.set('priceMin', priceMin);
    if (priceMax !== undefined) params = params.set('priceMax', priceMax);
    return this.http.get<ProductListResponse>(this.baseUrl, { params });
  }

  getProduct(id: number): Observable<Product> {
    return this.http.get<Product>(`${this.baseUrl}/${id}`);
  }
}
