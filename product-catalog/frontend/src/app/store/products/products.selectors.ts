import { createFeatureSelector, createSelector } from '@ngrx/store';
import { PRODUCTS_FEATURE_KEY, ProductsState } from './products.reducer';

export const selectProductsState =
  createFeatureSelector<ProductsState>(PRODUCTS_FEATURE_KEY);

export const selectAllProducts = createSelector(
  selectProductsState,
  (state) => state.products
);

export const selectProductsLoading = createSelector(
  selectProductsState,
  (state) => state.loading
);

export const selectProductsError = createSelector(
  selectProductsState,
  (state) => state.error
);

export const selectPaginationMeta = createSelector(
  selectProductsState,
  (state) => state.meta
);

export const selectTotalPages = createSelector(
  selectPaginationMeta,
  (meta) => meta.totalPages
);

export const selectTotalProducts = createSelector(
  selectPaginationMeta,
  (meta) => meta.total
);

export const selectPage = createSelector(
  selectPaginationMeta,
  (meta) => meta.page
);

export const selectFilters = createSelector(
  selectProductsState,
  (state) => state.filters
);
