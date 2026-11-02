<script setup lang="ts">
// How often the unread count is refreshed while the tab is visible. Each check
// is one small count query, but it's per open tab on Hostinger's few PHP workers.
const POLL_MS = 30_000

const localePath = useLocalePath()
const { t } = useI18n()

const notifications = useNotificationsStore()
const { open: openNotification } = useOpenNotification()

const open = ref(false)
const loading = ref(false)
const markingAll = ref(false)

function refreshCount() {
  return notifications.fetchUnreadCount().catch(() => null)
}

// The header renders the bell only for a logged-in user, so polling runs from
// mount to unmount: logging out removes the bell and stops it.
let timer: ReturnType<typeof setInterval> | undefined

function onVisibilityChange() {
  // A hidden tab skips its checks; coming back to it checks straight away.
  if (!document.hidden) {
    refreshCount()
  }
}

onMounted(() => {
  refreshCount()
  timer = setInterval(() => {
    if (!document.hidden) {
      refreshCount()
    }
  }, POLL_MS)
  document.addEventListener('visibilitychange', onVisibilityChange)
})

onBeforeUnmount(() => {
  clearInterval(timer)
  document.removeEventListener('visibilitychange', onVisibilityChange)
})

watch(open, async (isOpen) => {
  if (!isOpen) {
    return
  }
  loading.value = true
  try {
    await notifications.fetchUnread()
  }
  catch {
    // The list stays as it was; the count still shows.
  }
  finally {
    loading.value = false
  }
})

async function select(notification: Parameters<typeof openNotification>[0]) {
  open.value = false
  await openNotification(notification)
}

async function markAllRead() {
  markingAll.value = true
  try {
    await notifications.markAllRead()
  }
  catch {
    useToast().add({ title: t('common.error'), color: 'error' })
  }
  finally {
    markingAll.value = false
  }
}
</script>

<template>
  <UPopover v-model:open="open">
    <UButton
      icon="i-lucide-bell"
      color="neutral"
      variant="ghost"
      class="cursor-pointer"
      :aria-label="t('notifications.bell_label', { count: notifications.unreadCount }, notifications.unreadCount)"
    >
      <UBadge
        v-if="notifications.unreadCount > 0"
        :label="notifications.unreadCount > 99 ? '99+' : String(notifications.unreadCount)"
        size="sm"
      />
    </UButton>

    <template #content>
      <div class="w-80 max-w-[calc(100vw-2rem)]">
        <div class="flex items-center justify-between gap-2 border-b border-default px-3 py-2">
          <span class="text-sm font-semibold">{{ t('notifications.title') }}</span>
          <UButton
            v-if="notifications.unreadCount > 0"
            :label="t('notifications.mark_all_read')"
            size="xs"
            color="neutral"
            variant="link"
            class="cursor-pointer"
            :loading="markingAll"
            @click="markAllRead"
          />
        </div>

        <p
          v-if="loading && notifications.unread.length === 0"
          class="px-3 py-4 text-sm text-muted"
        >
          {{ t('notifications.loading') }}
        </p>
        <p
          v-else-if="notifications.unread.length === 0"
          class="px-3 py-4 text-sm text-muted"
        >
          {{ t('notifications.no_unread') }}
        </p>
        <ul
          v-else
          class="max-h-96 divide-y divide-default overflow-y-auto"
        >
          <li
            v-for="notification in notifications.unread"
            :key="notification.id"
          >
            <button
              type="button"
              class="w-full cursor-pointer px-3 py-2 text-left hover:bg-elevated"
              @click="select(notification)"
            >
              <span class="block text-sm">{{ notification.message }}</span>
              <span class="block text-xs text-muted">{{ formatDateTime(notification.created_at) }}</span>
            </button>
          </li>
        </ul>

        <div class="border-t border-default px-3 py-2">
          <ULink
            :to="localePath('/notifications')"
            class="text-sm text-primary"
            @click="open = false"
          >
            {{ t('notifications.view_all') }}
          </ULink>
        </div>
      </div>
    </template>
  </UPopover>
</template>
