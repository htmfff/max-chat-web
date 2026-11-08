import { AsyncPipe, CurrencyPipe, DatePipe, DecimalPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { Observable, catchError, map, of, switchMap } from 'rxjs';
import { ImportService } from '../../core/services/import.service';
import { ProductService } from '../../core/services/product.service';
import { ImportJob } from '../../models/import-job';
import { Product } from '../../models/product';

@Component({
  selector: 'app-product-detail',
  standalone: true,
  imports: [
    AsyncPipe,
    CurrencyPipe,
    DatePipe,
    DecimalPipe,
    RouterLink,
    MatCardModule,
    MatButtonModule,
    MatDividerModule,
    MatProgressBarModule,
    MatTableModule,
  ],
  template: `
    <div class="page-container" style="max-width: 1080px">
      <div style="margin-bottom: 16px">
        <a mat-stroked-button routerLink="/products">← Назад к списку</a>
      </div>

      @if (error(); as message) {
        <mat-card>
          <mat-card-content>
            <p class="error-text">{{ message }}</p>
          </mat-card-content>
        </mat-card>
      }

      @if (product$ | async; as product) {
        <mat-card style="margin-bottom: 16px">
          <mat-card-header>
            <mat-card-title>{{ product.name }}</mat-card-title>
            <mat-card-subtitle>
              Внешний код: {{ product.externalCode }} · ID: {{ product.id }}
            </mat-card-subtitle>
          </mat-card-header>

          <mat-card-content>
            <div style="display: flex; gap: 32px; flex-wrap: wrap; margin-top: 16px">
              <div>
                <div class="muted">Цена продажи</div>
                <div style="font-size: 28px; font-weight: 600">
                  {{ product.price | currency: 'RUB' : 'symbol-narrow' : '1.0-2' }}
                </div>
              </div>

              <div>
                <div class="muted">Закупочная цена</div>
                <div style="font-size: 20px">
                  @if (product.purchasePrice !== null) {
                    {{ product.purchasePrice | currency: 'RUB' : 'symbol-narrow' : '1.0-2' }}
                  } @else {
                    —
                  }
                </div>
              </div>

              <div>
                <div class="muted">Скидка</div>
                <div style="font-size: 20px; color: #b54708; font-weight: 600">
                  @if (product.discount !== null) {
                    {{ product.discount | number: '1.0-2' }}%
                  } @else {
                    —
                  }
                </div>
              </div>
            </div>

            <mat-divider style="margin: 24px 0" />

            <h3 style="margin-top: 0">Описание</h3>
            <p style="white-space: pre-line">{{ product.description ?? 'Описание отсутствует.' }}</p>

            <p class="muted" style="font-size: 12px">
              Создан: {{ product.createdAt | date: 'dd.MM.yyyy HH:mm' }} ·
              Обновлён: {{ product.updatedAt | date: 'dd.MM.yyyy HH:mm' }}
            </p>
          </mat-card-content>
        </mat-card>

        <mat-card style="margin-bottom: 16px">
          <mat-card-header>
            <mat-card-title>Дополнительные атрибуты</mat-card-title>
            <mat-card-subtitle>{{ product.attributes.length }} шт.</mat-card-subtitle>
          </mat-card-header>

          <mat-card-content>
            @if (product.attributes.length === 0) {
              <p class="muted">Атрибуты не заданы.</p>
            } @else {
              <table mat-table [dataSource]="product.attributes" class="full-width">
                <ng-container matColumnDef="key">
                  <th mat-header-cell *matHeaderCellDef>Ключ</th>
                  <td mat-cell *matCellDef="let attribute">{{ attribute.key }}</td>
                </ng-container>

                <ng-container matColumnDef="value">
                  <th mat-header-cell *matHeaderCellDef>Значение</th>
                  <td mat-cell *matCellDef="let attribute">{{ attribute.value ?? '—' }}</td>
                </ng-container>

                <tr mat-header-row *matHeaderRowDef="attributeColumns"></tr>
                <tr mat-row *matRowDef="let row; columns: attributeColumns"></tr>
              </table>
            }
          </mat-card-content>
        </mat-card>

        <mat-card style="margin-bottom: 16px">
          <mat-card-header>
            <mat-card-title>Изображения</mat-card-title>
            <mat-card-subtitle>{{ product.images.length }} шт.</mat-card-subtitle>
          </mat-card-header>

          <mat-card-content>
            @if (product.images.length === 0) {
              <p class="muted">Изображения не заданы.</p>
            } @else {
              <div class="image-grid">
                @for (image of product.images; track image.id ?? image.url) {
                  <figure>
                    <img [src]="image.path ?? image.url" [alt]="product.name" loading="lazy" />
                    <figcaption [title]="image.url">{{ image.url }}</figcaption>
                  </figure>
                }
              </div>
            }
          </mat-card-content>
        </mat-card>

        @if (latestJob$ | async; as job) {
          <mat-card>
            <mat-card-header>
              <mat-card-title>Последний импорт</mat-card-title>
              <mat-card-subtitle>
                Задача #{{ job.id }} · {{ job.status }} · {{ job.successRows }} / {{ job.totalRows }}
              </mat-card-subtitle>
            </mat-card-header>
            <mat-card-content>
              <mat-progress-bar [value]="job.progress" style="margin: 12px 0" />
              @if (job.errors.length > 0) {
                <p class="error-text">Ошибок в последнем импорте: {{ job.errors.length }}</p>
                <ul>
                  @for (error of job.errors.slice(0, 5); track $index) {
                    <li>Строка {{ error.row }}: {{ error.message }}</li>
                  }
                </ul>
              } @else {
                <p class="muted">Ошибок нет.</p>
              }
              <a mat-stroked-button routerLink="/import">Открыть импорт</a>
            </mat-card-content>
          </mat-card>
        }
      }
    </div>
  `,
})
export class ProductDetailComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly productService = inject(ProductService);
  private readonly importService = inject(ImportService);

  readonly error = signal<string | null>(null);
  readonly attributeColumns = ['key', 'value'];

  readonly product$: Observable<Product | null> = this.route.paramMap.pipe(
    map((params) => Number(params.get('id'))),
    switchMap((id) =>
      this.productService.getProduct(id).pipe(
        map((response) => response.data),
        catchError((error: HttpErrorResponse) => {
          this.error.set(
            (error.error as { message?: string } | null)?.message ??
              'Не удалось загрузить товар.'
          );
          return of(null);
        })
      )
    )
  );

  readonly latestJob$: Observable<ImportJob | null> = this.importService.getJobs().pipe(
    map((response) => response.data[0] ?? null),
    catchError(() => of(null))
  );
}
