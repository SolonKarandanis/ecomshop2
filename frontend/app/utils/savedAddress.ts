import type { CheckoutPayload } from '~/types/order'
import type { SavedAddress, SavedAddressPayload } from '~/types/profile'

type CheckoutAddressFields = Pick<CheckoutPayload, 'firstName' | 'lastName' | 'phone' | 'address' | 'city' | 'country' | 'zipCode'>

// `labelKey` is a message key, translated where the fields are shown.
export const savedAddressFields: { name: keyof SavedAddressPayload, labelKey: string, autocomplete: string }[] = [
  { name: 'first_name', labelKey: 'address.field.first_name', autocomplete: 'given-name' },
  { name: 'last_name', labelKey: 'address.field.last_name', autocomplete: 'family-name' },
  { name: 'phone', labelKey: 'address.field.phone', autocomplete: 'tel' },
  { name: 'street_address', labelKey: 'address.field.street_address', autocomplete: 'street-address' },
  { name: 'city', labelKey: 'address.field.city', autocomplete: 'address-level2' },
  { name: 'postal_code', labelKey: 'address.field.postal_code', autocomplete: 'postal-code' },
  { name: 'country', labelKey: 'address.field.country', autocomplete: 'country-name' },
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
