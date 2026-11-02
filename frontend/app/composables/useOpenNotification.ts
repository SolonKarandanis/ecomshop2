import type { AppNotification } from '~/types/notification'

/**
 * Opening a Notification marks it read, then follows its link. A failed
 * mark-read doesn't keep the user from where they were going: the count is
 * refreshed by the next poll either way.
 */
export function useOpenNotification() {
  const notifications = useNotificationsStore()
  const auth = useAuthStore()
  const localePath = useLocalePath()
  const siteOrigin = useRequestURL().origin
  const apiBase = useRuntimeConfig().public.apiBase

  function linkFor(notification: AppNotification) {
    return notificationLink(notification, {
      siteOrigin,
      apiBase,
      isAdmin: auth.isAdmin,
      isSupplier: auth.isSupplier,
      localize: path => localePath(path),
    })
  }

  async function open(notification: AppNotification) {
    if (!notification.read_at) {
      await notifications.markRead([notification.id]).catch(() => null)
    }

    const target = linkFor(notification)
    if (target) {
      await navigateTo(target.to, { external: target.external })
    }
  }

  return { open, linkFor }
}
