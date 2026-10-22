// Mirrors the backend's App\Data\ProfileData (GET/PATCH /api/profile).
export interface Profile {
  name: string
  email: string
  // Only sent for an Admin's own profile.
  status?: string
}

// UpdateProfileDto: name and email are the only editable profile fields.
export interface UpdateProfilePayload {
  name: string
  email: string
}

// ChangePasswordRequest's field names.
export interface ChangePasswordPayload {
  currentPassword: string
  newPassword: string
  newPasswordConfirmation: string
}
