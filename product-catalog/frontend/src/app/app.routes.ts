import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () =>
      import('./pages/login/login.component').then((m) => m.LoginComponent),
  },
  {
    path: 'products',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./pages/products/products-list.component').then(
        (m) => m.ProductsListComponent
      ),
  },
  {
    path: 'products/:id',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./pages/product-detail/product-detail.component').then(
        (m) => m.ProductDetailComponent
      ),
  },
  {
    path: 'import',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./pages/import/import.component').then((m) => m.ImportComponent),
  },
  { path: '', pathMatch: 'full', redirectTo: 'products' },
  { path: '**', redirectTo: 'products' },
];
