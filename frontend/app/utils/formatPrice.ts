// Prices are EUR across the backend (orders, exports, Filament widgets).
const formatter = new Intl.NumberFormat('en-IE', { style: 'currency', currency: 'EUR' })

export function formatPrice(price: number): string {
  return formatter.format(price)
}
