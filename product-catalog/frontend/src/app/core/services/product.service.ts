import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../api.config';
import {
  PaginatedResponse,
  Product,
  ProductFilters,
  ProductInput,
} from '../../models/product';

interface ProductResponse {
  data: Product;
}

@Injectable({ providedIn: 'root' })
export class ProductService {
  private readonly http = inject(HttpClient);

  getProducts(filters: ProductFilters): Observable<PaginatedResponse<Product>> {
    let params = new HttpParams()
      .set('page', filters.page)
      .set('limit', filters.limit)
      .set('sort', filters.sort)
      .set('direction', filters.direction);

    if (filters.name.trim() !== '') {
      params = params.set('name', filters.name.trim());
    }

    if (filters.priceFrom !== null) {
      params = params.set('priceFrom', filters.priceFrom);
    }

    if (filters.priceTo !== null) {
      params = params.set('priceTo', filters.priceTo);
    }

    return this.http.get<PaginatedResponse<Product>>(`${API_URL}/products`, { params });
  }

  getProduct(id: number): Observable<ProductResponse> {
    return this.http.get<ProductResponse>(`${API_URL}/products/${id}`);
  }

  createProduct(input: ProductInput): Observable<ProductResponse> {
    return this.http.post<ProductResponse>(`${API_URL}/products`, input);
  }

  updateProduct(id: number, input: ProductInput): Observable<ProductResponse> {
    return this.http.put<ProductResponse>(`${API_URL}/products/${id}`, input);
  }

  deleteProduct(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/products/${id}`);
  }
}
