import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterOutlet } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatToolbarModule } from '@angular/material/toolbar';
import { AuthService } from './core/services/auth.service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, RouterLink, MatToolbarModule, MatButtonModule, MatIconModule],
  template: `
    <mat-toolbar color="primary">
      <mat-icon>inventory_2</mat-icon>
      <span style="margin-left: 8px; font-weight: 500">Каталог товаров</span>
      <span class="spacer"></span>

      @if (auth.isAuthenticated()) {
        <a mat-button routerLink="/products">Товары</a>
        <a mat-button routerLink="/import">Импорт</a>
        <span class="muted" style="margin: 0 12px; color: #e8eaf6">
          {{ auth.currentUser()?.email }}
        </span>
        <button mat-stroked-button color="accent" type="button" (click)="logout()">
          Выйти
        </button>
      }
    </mat-toolbar>

    <router-outlet />
  `,
})
export class AppComponent {
  readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  logout(): void {
    this.auth.logout();
    void this.router.navigate(['/login']);
  }
}
