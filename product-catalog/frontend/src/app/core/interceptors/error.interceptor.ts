import {
  HttpErrorResponse,
  HttpInterceptorFn,
} from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../services/auth.service';

export const errorInterceptor: HttpInterceptorFn = (request, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  return next(request).pipe(
    catchError((error: HttpErrorResponse) => {
      const isLoginRequest = request.url.includes('/auth/login');

      if (error.status === 401 && !isLoginRequest) {
        auth.logout();
        void router.navigate(['/login'], {
          queryParams: { redirect: router.url },
        });
      }

      if (error.status === 500) {
        console.error('Server error', error.error);
      }

      return throwError(() => error);
    })
  );
};
