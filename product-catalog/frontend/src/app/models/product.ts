export interface ProductAttribute {
  id?: number;
  key: string;
  value: string | null;
}

export interface ProductImage {
  id?: number;
  url: string;
  path: string | null;
  position: number;
}

export interface Product {
  id: number;
  externalCode: string;
  name: string;
  description: string | null;
  price: number;
  purchasePrice: number | null;
  discount: number | null;
  attributes: ProductAttribute[];
  images: ProductImage[];
  createdAt: string;
  updatedAt: string;
}

export interface ProductInput {
  externalCode: string;
  name: string;
  description?: string | null;
  price: number;
  purchasePrice?: number | null;
  attributes?: Array<Pick<ProductAttribute, 'key' | 'value'>>;
  images?: Array<Pick<ProductImage, 'url' | 'path'>>;
}

export interface PaginationMeta {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginationMeta;
}

export interface ProductFilters {
  page: number;
  limit: number;
  name: string;
  priceFrom: number | null;
  priceTo: number | null;
  sort: 'id' | 'name' | 'price' | 'discount' | 'createdAt' | 'updatedAt';
  direction: 'asc' | 'desc';
}
