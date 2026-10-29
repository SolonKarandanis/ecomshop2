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

// Mirrors the backend's App\Data\SavedAddressData (/api/profile/addresses).
export interface SavedAddress {
  id: number
  first_name: string
  last_name: string
  phone: string
  street_address: string
  city: string
  country: string
  postal_code: string
  is_default: boolean
}

// StoreSavedAddressRequest's fields.
export type SavedAddressPayload = Omit<SavedAddress, 'id' | 'is_default'>
