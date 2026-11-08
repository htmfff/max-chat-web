import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../api.config';
import { ImportJob, ImportJobResponse, ImportJobsResponse } from '../../models/import-job';

@Injectable({ providedIn: 'root' })
export class ImportService {
  private readonly http = inject(HttpClient);

  upload(file: File): Observable<ImportJobResponse> {
    const formData = new FormData();
    formData.append('file', file, file.name);

    return this.http.post<ImportJobResponse>(`${API_URL}/imports`, formData);
  }

  getJob(id: number): Observable<ImportJobResponse> {
    return this.http.get<ImportJobResponse>(`${API_URL}/imports/${id}`);
  }

  getJobs(): Observable<ImportJobsResponse> {
    return this.http.get<ImportJobsResponse>(`${API_URL}/imports`);
  }

  isActive(job: ImportJob): boolean {
    return job.status === 'queued' || job.status === 'processing';
  }
}
