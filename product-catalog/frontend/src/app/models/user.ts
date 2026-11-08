export interface User {
  id: number;
  email: string;
  roles: string[];
}

export interface AuthResponse {
  token: string;
  expiresIn: number;
  user: User;
}

export interface MeResponse {
  data: User;
}
