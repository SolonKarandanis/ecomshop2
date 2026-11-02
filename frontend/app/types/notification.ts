// NotificationData. Named AppNotification so it doesn't shadow the browser's Notification.
export interface AppNotification {
  id: string
  event_type: string | null
  order_id: number | null
  // Where the Notification leads: a storefront page, or a Filament page for admins.
  url: string | null
  order_url: string | null
  message: string | null
  read_at: string | null
  created_at: string | null
}
