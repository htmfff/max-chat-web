import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { catchError, map, of, switchMap } from 'rxjs';
import { ProductService } from '../../core/services/product.service';
import {
  loadProducts,
  loadProductsFailure,
  loadProductsSuccess,
} from './products.actions';

@Injectable()
export class ProductsEffects {
  private readonly actions$ = inject(Actions);
  private readonly productService = inject(ProductService);

  readonly loadProducts$ = createEffect(() =>
    this.actions$.pipe(
      ofType(loadProducts),
      switchMap(({ filters }) =>
        this.productService.getProducts(filters).pipe(
          map((response) =>
            loadProductsSuccess({
              products: response.data,
              meta: response.meta,
            })
          ),
          catchError((error: HttpErrorResponse) =>
            of(
              loadProductsFailure({
                error:
                  (error.error as { message?: string } | null)?.message ??
                  'Не удалось загрузить товары.',
              })
            )
          )
        )
      )
    )
  );
}
