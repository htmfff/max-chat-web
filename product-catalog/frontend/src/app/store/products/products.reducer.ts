import { createReducer, on } from '@ngrx/store';
import { PaginationMeta, Product, ProductFilters } from '../../models/product';
import {
  loadProducts,
  loadProductsFailure,
  loadProductsSuccess,
  resetFilters,
  setFilters,
} from './products.actions';

export const PRODUCTS_FEATURE_KEY = 'products';

export interface ProductsState {
  products: Product[];
  loading: boolean;
  error: string | null;
  meta: PaginationMeta;
  filters: ProductFilters;
}

export const initialFilters: ProductFilters = {
  page: 1,
  limit: 10,
  name: '',
  priceFrom: null,
  priceTo: null,
  sort: 'id',
  direction: 'asc',
};

export const initialMeta: PaginationMeta = {
  page: 1,
  limit: 10,
  total: 0,
  totalPages: 0,
};

export const initialState: ProductsState = {
  products: [],
  loading: false,
  error: null,
  meta: initialMeta,
  filters: initialFilters,
};

export const productsReducer = createReducer(
  initialState,

  on(loadProducts, (state, { filters }) => ({
    ...state,
    loading: true,
    error: null,
    filters,
  })),

  on(loadProductsSuccess, (state, { products, meta }) => ({
    ...state,
    loading: false,
    products,
    meta,
  })),

  on(loadProductsFailure, (state, { error }) => ({
    ...state,
    loading: false,
    error,
  })),

  on(setFilters, (state, { filters }) => {
    const nextFilters: ProductFilters = { ...state.filters, ...filters };

    if (
      filters.page === undefined &&
      (filters.name !== undefined ||
        filters.priceFrom !== undefined ||
        filters.priceTo !== undefined ||
        filters.limit !== undefined)
    ) {
      nextFilters.page = 1;
    }

    return { ...state, filters: nextFilters };
  }),

  on(resetFilters, (state) => ({
    ...state,
    filters: initialFilters,
    meta: initialMeta,
  }))
);
