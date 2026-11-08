import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatProgressSpinnerModule,
  ],
  template: `
    <div class="page-container" style="max-width: 440px; margin-top: 72px">
      <mat-card>
        <mat-card-header>
          <mat-card-title>Вход в каталог</mat-card-title>
          <mat-card-subtitle>Демо: admin&#64;example.com / admin123</mat-card-subtitle>
        </mat-card-header>

        <mat-card-content>
          <form
            [formGroup]="loginForm"
            (ngSubmit)="submit()"
            style="display: flex; flex-direction: column; gap: 16px; margin-top: 20px"
          >
            <mat-form-field appearance="outline" class="full-width">
              <mat-label>Email</mat-label>
              <input matInput type="email" formControlName="email" autocomplete="username" />
              @if (loginForm.controls.email.invalid && loginForm.controls.email.touched) {
                <mat-error>Введите корректный email</mat-error>
              }
            </mat-form-field>

            <mat-form-field appearance="outline" class="full-width">
              <mat-label>Пароль</mat-label>
              <input matInput type="password" formControlName="password" autocomplete="current-password" />
              @if (loginForm.controls.password.invalid && loginForm.controls.password.touched) {
                <mat-error>Пароль обязателен</mat-error>
              }
            </mat-form-field>

            @if (error()) {
              <p class="error-text">{{ error() }}</p>
            }

            <button
              mat-flat-button
              color="primary"
              type="submit"
              [disabled]="loading() || loginForm.invalid"
            >
              @if (loading()) {
                <mat-spinner diameter="20" />
              } @else {
                Войти
              }
            </button>
          </form>
        </mat-card-content>
      </mat-card>
    </div>
  `,
})
export class LoginComponent {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  readonly loading = signal(false);
  readonly error = signal<string | null>(null);

  readonly loginForm = this.fb.nonNullable.group({
    email: ['admin@example.com', [Validators.required, Validators.email]],
    password: ['admin123', Validators.required],
  });

  submit(): void {
    if (this.loginForm.invalid || this.loading()) {
      this.loginForm.markAllAsTouched();
      return;
    }

    this.loading.set(true);
    this.error.set(null);

    const { email, password } = this.loginForm.getRawValue();

    this.auth.login(email, password).subscribe({
      next: () => {
        this.loading.set(false);
        const redirect = this.route.snapshot.queryParamMap.get('redirect');
        void this.router.navigateByUrl(redirect ?? '/products');
      },
      error: (error: HttpErrorResponse) => {
        this.loading.set(false);
        this.error.set(
          (error.error as { message?: string } | null)?.message ??
            'Не удалось выполнить вход.'
        );
      },
    });
  }
}
