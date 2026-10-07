// Mirrors the backend's App\Data\UserData, as returned by /api/user, /api/login
// and /api/register (wrapped in `data`).
export interface User {
  id: number
  name: string
  email: string
  status: string
  roles?: string[]
  email_verified_at: string | null
  created_at: string | null
}

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
}

export interface ForgotPasswordPayload {
  email: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}
