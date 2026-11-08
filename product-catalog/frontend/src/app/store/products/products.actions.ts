import { createAction, props } from '@ngrx/store';
import { PaginationMeta, Product, ProductFilters } from '../../models/product';

export const loadProducts = createAction(
  '[Products] Load Products',
  props<{ filters: ProductFilters }>()
);

export const loadProductsSuccess = createAction(
  '[Products] Load Products Success',
  props<{ products: Product[]; meta: PaginationMeta }>()
);

export const loadProductsFailure = createAction(
  '[Products] Load Products Failure',
  props<{ error: string }>()
);

export const setFilters = createAction(
  '[Products] Set Filters',
  props<{ filters: Partial<ProductFilters> }>()
);

export const resetFilters = createAction('[Products] Reset Filters');
