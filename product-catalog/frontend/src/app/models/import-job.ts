export type ImportStatus = 'queued' | 'processing' | 'completed' | 'failed';

export interface ImportError {
  row: number;
  message: string;
  externalCode?: string | null;
}

export interface ImportJob {
  id: number;
  uuid: string;
  status: ImportStatus;
  originalFilename: string;
  totalRows: number;
  processedRows: number;
  successRows: number;
  errorRows: number;
  progress: number;
  errors: ImportError[];
  createdAt: string;
  startedAt: string | null;
  finishedAt: string | null;
}

export interface ImportJobResponse {
  data: ImportJob;
}

export interface ImportJobsResponse {
  data: ImportJob[];
}
