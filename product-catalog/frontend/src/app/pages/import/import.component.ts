import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnDestroy, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatDividerModule } from '@angular/material/divider';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { Subscription, switchMap, takeWhile, timer } from 'rxjs';
import { ImportService } from '../../core/services/import.service';
import { ImportJob, ImportStatus } from '../../models/import-job';

@Component({
  selector: 'app-import',
  standalone: true,
  imports: [
    RouterLink,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatProgressBarModule,
    MatTableModule,
    MatDividerModule,
  ],
  template: `
    <div class="page-container" style="max-width: 980px">
      <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px">
        <div>
          <h1 style="margin: 0">Импорт товаров</h1>
          <p class="muted" style="margin: 4px 0 0">
            Загрузите .xlsx — файл будет обработан в фоновой очереди RabbitMQ
          </p>
        </div>
        <span class="spacer"></span>
        <a mat-stroked-button routerLink="/products">К товарам</a>
      </div>

      <mat-card style="margin-bottom: 16px">
        <mat-card-content>
          <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap; padding: 8px 0">
            <input
              #fileInput
              type="file"
              accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
              hidden
              (change)="onFileSelected($event)"
            />

            <button mat-stroked-button type="button" (click)="fileInput.click()">
              <mat-icon>attach_file</mat-icon>
              Выбрать .xlsx
            </button>

            <span class="muted">
              {{ selectedFileName() ?? 'Файл не выбран (максимум 10 МБ)' }}
            </span>

            <span class="spacer"></span>

            <button
              mat-flat-button
              color="primary"
              type="button"
              [disabled]="selectedFileName() === null || uploading()"
              (click)="upload()"
            >
              {{ uploading() ? 'Импортируем…' : 'Загрузить и импортировать' }}
            </button>
          </div>

          @if (error(); as message) {
            <p class="error-text">{{ message }}</p>
          }
        </mat-card-content>
      </mat-card>

      @if (job(); as currentJob) {
        <mat-card>
          <mat-card-header>
            <mat-card-title>Задача #{{ currentJob.id }}</mat-card-title>
            <mat-card-subtitle>
              {{ statusLabel(currentJob.status) }} · {{ currentJob.originalFilename }}
            </mat-card-subtitle>
          </mat-card-header>

          <mat-card-content>
            <mat-progress-bar
              [mode]="currentJob.status === 'queued' ? 'indeterminate' : 'determinate'"
              [value]="currentJob.progress"
              style="margin: 16px 0"
            />

            <div style="display: flex; gap: 24px; flex-wrap: wrap">
              <span>Обработано: <strong>{{ currentJob.processedRows }}</strong> / {{ currentJob.totalRows }}</span>
              <span>Успешно: <strong>{{ currentJob.successRows }}</strong></span>
              <span [class.error-text]="currentJob.errorRows > 0">
                Ошибок: <strong>{{ currentJob.errorRows }}</strong>
              </span>
              <span class="muted">Прогресс: {{ currentJob.progress }}%</span>
            </div>

            @if (currentJob.status === 'completed') {
              <p style="color: #067647; margin-top: 16px">
                Импорт завершён. Товары доступны в каталоге.
              </p>
              <a mat-flat-button color="primary" routerLink="/products">Открыть список товаров</a>
            }

            @if (currentJob.status === 'failed') {
              <p class="error-text" style="margin-top: 16px">
                Импорт завершился с критической ошибкой. Проверьте отчёт ниже.
              </p>
            }

            @if (currentJob.errors.length > 0) {
              <mat-divider style="margin: 24px 0 16px" />
              <h3 style="margin: 0 0 12px">Отчёт об ошибках</h3>

              <table mat-table [dataSource]="currentJob.errors" class="full-width">
                <ng-container matColumnDef="row">
                  <th mat-header-cell *matHeaderCellDef>Строка</th>
                  <td mat-cell *matCellDef="let error">{{ error.row }}</td>
                </ng-container>

                <ng-container matColumnDef="externalCode">
                  <th mat-header-cell *matHeaderCellDef>Внешний код</th>
                  <td mat-cell *matCellDef="let error">{{ error.externalCode ?? '—' }}</td>
                </ng-container>

                <ng-container matColumnDef="message">
                  <th mat-header-cell *matHeaderCellDef>Сообщение</th>
                  <td mat-cell *matCellDef="let error" class="error-text">{{ error.message }}</td>
                </ng-container>

                <tr mat-header-row *matHeaderRowDef="errorColumns"></tr>
                <tr mat-row *matRowDef="let row; columns: errorColumns"></tr>
              </table>
            }
          </mat-card-content>
        </mat-card>
      }
    </div>
  `,
})
export class ImportComponent implements OnDestroy {
  private readonly importService = inject(ImportService);

  readonly selectedFileName = signal<string | null>(null);
  readonly uploading = signal(false);
  readonly error = signal<string | null>(null);
  readonly job = signal<ImportJob | null>(null);

  readonly errorColumns = ['row', 'externalCode', 'message'];

  private selectedFile: File | null = null;
  private pollSubscription?: Subscription;

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    this.selectedFile = file;
    this.selectedFileName.set(file?.name ?? null);
    this.error.set(null);
  }

  upload(): void {
    const file = this.selectedFile;

    if (file === null || this.uploading()) {
      return;
    }

    this.uploading.set(true);
    this.error.set(null);
    this.job.set(null);
    this.pollSubscription?.unsubscribe();

    this.importService.upload(file).subscribe({
      next: ({ data }) => {
        this.job.set(data);
        this.startPolling(data.id);
      },
      error: (error: HttpErrorResponse) => {
        this.uploading.set(false);
        this.error.set(
          (error.error as { message?: string } | null)?.message ??
            'Не удалось загрузить файл.'
        );
      },
    });
  }

  statusLabel(status: ImportStatus): string {
    switch (status) {
      case 'queued':
        return 'В очереди';
      case 'processing':
        return 'Обрабатывается';
      case 'completed':
        return 'Завершено';
      case 'failed':
        return 'Ошибка';
    }
  }

  ngOnDestroy(): void {
    this.pollSubscription?.unsubscribe();
  }

  private startPolling(jobId: number): void {
    this.pollSubscription = timer(0, 1000)
      .pipe(
        switchMap(() => this.importService.getJob(jobId)),
        takeWhile((response) => this.importService.isActive(response.data), true)
      )
      .subscribe({
        next: ({ data }) => {
          this.job.set(data);

          if (!this.importService.isActive(data)) {
            this.uploading.set(false);
          }
        },
        error: (error: HttpErrorResponse) => {
          this.uploading.set(false);
          this.error.set(
            (error.error as { message?: string } | null)?.message ??
              'Не удалось получить статус импорта.'
          );
        },
      });
  }
}
