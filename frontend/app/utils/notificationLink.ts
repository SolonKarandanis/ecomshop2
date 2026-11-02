import type { AppNotification } from '~/types/notification'

export interface NotificationLinkContext {
  // The storefront's origin: links on it navigate inside the app.
  siteOrigin: string
  apiBase: string
  isAdmin: boolean
  isSupplier: boolean
  // Adds the page's language prefix to a path built here.
  localize: (path: string) => string
}

export interface NotificationTarget {
  to: string
  external: boolean
}

/**
 * Where clicking a Notification goes. Usually its own `url`: a storefront page
 * (already in the recipient's language) or a Filament page for admins.
 * Notifications stored before #53 point their order link at `/api/...`, which
 * isn't a page, so for those the link is rebuilt from `order_id`.
 */
export function notificationLink(notification: AppNotification, context: NotificationLinkContext): NotificationTarget | null {
  const url = notification.url ?? notification.order_url

  if (url) {
    const target = new URL(url, context.siteOrigin)
    if (!target.pathname.startsWith('/api/')) {
      return target.origin === context.siteOrigin
        ? { to: target.pathname + target.search + target.hash, external: false }
        : { to: target.href, external: true }
    }
  }

  const orderId = notification.order_id
  if (orderId === null) {
    return null
  }
  if (context.isAdmin) {
    return { to: `${context.apiBase}/admin/orders/${orderId}`, external: true }
  }

  return { to: context.localize(context.isSupplier ? `/supplier/orders/${orderId}` : `/orders/${orderId}`), external: false }
}
