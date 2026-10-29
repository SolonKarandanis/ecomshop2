import type { CheckoutPayload } from '~/types/order'
import type { SavedAddress, SavedAddressPayload } from '~/types/profile'

type CheckoutAddressFields = Pick<CheckoutPayload, 'firstName' | 'lastName' | 'phone' | 'address' | 'city' | 'country' | 'zipCode'>

export const savedAddressFields: { name: keyof SavedAddressPayload, label: string, autocomplete: string }[] = [
  { name: 'first_name', label: 'First name', autocomplete: 'given-name' },
  { name: 'last_name', label: 'Last name', autocomplete: 'family-name' },
  { name: 'phone', label: 'Phone', autocomplete: 'tel' },
  { name: 'street_address', label: 'Street address', autocomplete: 'street-address' },
  { name: 'city', label: 'City', autocomplete: 'address-level2' },
  { name: 'postal_code', label: 'Postal code', autocomplete: 'postal-code' },
  { name: 'country', label: 'Country', autocomplete: 'country-name' },
]

/** The checkout form uses its own field names (CheckoutRequest's). */
export function savedAddressToCheckout(address: SavedAddress): CheckoutAddressFields {
  return {
    firstName: address.first_name,
    lastName: address.last_name,
    phone: address.phone,
    address: address.street_address,
    city: address.city,
    country: address.country,
    zipCode: address.postal_code,
  }
}

/** One line for a picker or a list, e.g. "Jane Doe, 1 Main St, 12345 Athens, Greece". */
export function formatSavedAddress(address: SavedAddress): string {
  return `${address.first_name} ${address.last_name}, ${address.street_address}, ${address.postal_code} ${address.city}, ${address.country}`
}
