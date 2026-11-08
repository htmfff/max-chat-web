import { AsyncPipe, CurrencyPipe, DecimalPipe } from '@angular/common';
import { Component, OnInit, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { Store } from '@ngrx/store';
import { Product, ProductFilters } from '../../models/product';
import {
  loadProducts,
  resetFilters,
  setFilters,
} from '../../store/products/products.actions';
import {
  initialFilters,
} from '../../store/products/products.reducer';
import {
  selectAllProducts,
  selectFilters,
  selectPaginationMeta,
  selectProductsError,
  selectProductsLoading,
} from '../../store/products/products.selectors';

@Component({
  selector: 'app-products-list',
  standalone: true,
  imports: [
    AsyncPipe,
    CurrencyPipe,
    DecimalPipe,
    ReactiveFormsModule,
    RouterLink,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatProgressBarModule,
    MatTableModule,
  ],
  template: `
    <div class="page-container">
      <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px">
        <div>
          <h1 style="margin: 0">Товары</h1>
          <p class="muted" style="margin: 4px 0 0">
            Серверная пагинация, поиск по названию и фильтр по цене
          </p>
        </div>
        <span class="spacer"></span>
        <a mat-flat-button color="primary" routerLink="/import">Импорт .xlsx</a>
      </div>

      <mat-card style="margin-bottom: 16px">
        <mat-card-content>
          <form
            [formGroup]="filterForm"
            (ngSubmit)="applyFilters()"
            style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; padding-top: 8px"
          >
            <mat-form-field appearance="outline" style="min-width: 240px">
              <mat-label>Поиск по названию</mat-label>
              <input matInput formControlName="name" placeholder="Например, бермуды" />
            </mat-form-field>

            <mat-form-field appearance="outline" style="width: 150px">
              <mat-label>Цена от</mat-label>
              <input matInput type="number" min="0" formControlName="priceFrom" />
            </mat-form-field>

            <mat-form-field appearance="outline" style="width: 150px">
              <mat-label>Цена до</mat-label>
              <input matInput type="number" min="0" formControlName="priceTo" />
            </mat-form-field>

            <button mat-flat-button color="primary" type="submit">Найти</button>
            <button mat-stroked-button type="button" (click)="reset()">Сбросить</button>
          </form>
        </mat-card-content>
      </mat-card>

      <mat-card>
        @if (loading$ | async) {
          <mat-progress-bar mode="indeterminate" />
        }

        <mat-card-content>
          @if (error$ | async; as error) {
            <p class="error-text">{{ error }}</p>
          }

          <table mat-table [dataSource]="(products$ | async) ?? []" class="full-width">
            <ng-container matColumnDef="image">
              <th mat-header-cell *matHeaderCellDef></th>
              <td mat-cell *matCellDef="let product">
                @if (imageUrl(product); as src) {
                  <img class="thumb" [src]="src" [alt]="product.name" />
                }
              </td>
            </ng-container>

            <ng-container matColumnDef="name">
              <th mat-header-cell *matHeaderCellDef>Наименование</th>
              <td mat-cell *matCellDef="let product">
                <a [routerLink]="['/products', product.id]" style="font-weight: 500">
                  {{ product.name }}
                </a>
                <div class="muted" style="font-size: 12px">{{ product.externalCode }}</div>
              </td>
            </ng-container>

            <ng-container matColumnDef="price">
              <th mat-header-cell *matHeaderCellDef>Цена</th>
              <td mat-cell *matCellDef="let product">
                {{ product.price | currency: 'RUB' : 'symbol-narrow' : '1.0-2' }}
              </td>
            </ng-container>

            <ng-container matColumnDef="discount">
              <th mat-header-cell *matHeaderCellDef>Скидка</th>
              <td mat-cell *matCellDef="let product">
                @if (product.discount !== null) {
                  <span style="color: #b54708; font-weight: 500">
                    {{ product.discount | number: '1.0-2' }}%
                  </span>
                } @else {
                  <span class="muted">—</span>
                }
              </td>
            </ng-container>

            <ng-container matColumnDef="actions">
              <th mat-header-cell *matHeaderCellDef></th>
              <td mat-cell *matCellDef="let product" style="text-align: right">
                <a mat-stroked-button [routerLink]="['/products', product.id]">Карточка</a>
              </td>
            </ng-container>

            <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
            <tr mat-row *matRowDef="let row; columns: displayedColumns"></tr>
          </table>

          @if ((products$ | async)?.length === 0 && !(loading$ | async)) {
            <p class="muted" style="text-align: center; padding: 24px">
              Товары не найдены. Загрузите .xlsx на странице импорта.
            </p>
          }

          @if (meta$ | async; as meta) {
            <div
              style="display: flex; align-items: center; gap: 16px; margin-top: 20px; flex-wrap: wrap"
            >
              <button
                mat-stroked-button
                type="button"
                [disabled]="meta.page <= 1"
                (click)="goToPage(meta.page - 1)"
              >
                Назад
              </button>
              <span class="muted">
                Страница {{ meta.page }} из {{ meta.totalPages }} · всего {{ meta.total }}
              </span>
              <button
                mat-stroked-button
                type="button"
                [disabled]="meta.page >= meta.totalPages"
                (click)="goToPage(meta.page + 1)"
              >
                Вперёд
              </button>
            </div>
          }
        </mat-card-content>
      </mat-card>
    </div>
  `,
})
export class ProductsListComponent implements OnInit {
  private readonly store = inject(Store);
  private readonly fb = inject(FormBuilder);

  readonly products$ = this.store.select(selectAllProducts);
  readonly loading$ = this.store.select(selectProductsLoading);
  readonly error$ = this.store.select(selectProductsError);
  readonly meta$ = this.store.select(selectPaginationMeta);
  private readonly currentFilters = this.store.selectSignal(selectFilters);

  readonly displayedColumns = ['image', 'name', 'price', 'discount', 'actions'];

  readonly filterForm = this.fb.group({
    name: [''],
    priceFrom: [null as number | null],
    priceTo: [null as number | null],
  });

  ngOnInit(): void {
    this.reload(1);
  }

  applyFilters(): void {
    this.reload(1);
  }

  reset(): void {
    this.filterForm.reset({ name: '', priceFrom: null, priceTo: null });
    this.store.dispatch(resetFilters());
    this.store.dispatch(loadProducts({ filters: { ...initialFilters } }));
  }

  goToPage(page: number): void {
    this.reload(page);
  }

  imageUrl(product: Product): string | null {
    const image = product.images[0];

    if (image === undefined) {
      return null;
    }

    return image.path ?? image.url;
  }

  private reload(page: number): void {
    const current = this.currentFilters();
    const formValue = this.filterForm.getRawValue();

    const filters: ProductFilters = {
      ...current,
      page,
      name: formValue.name ?? '',
      priceFrom: formValue.priceFrom,
      priceTo: formValue.priceTo,
    };

    this.store.dispatch(setFilters({ filters }));
    this.store.dispatch(loadProducts({ filters }));
  }
}
