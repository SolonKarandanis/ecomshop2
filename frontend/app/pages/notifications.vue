<script setup lang="ts">
const { t } = useI18n()

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: () => t('notifications.title') })

const route = useRoute()
const notifications = useNotificationsStore()
const { open: openNotification } = useOpenNotification()

// The URL holds the page, so a reload keeps it.
const page = computed(() => Number(route.query.page) || 1)

// Newest first, in the API's order. Kept in the store so marking one read
// here also updates the header bell.
const { error, status: loadStatus } = await useAsyncData(
  'notifications',
  () => notifications.fetchAll(page.value).then(() => true),
  { watch: [page] },
)

const markingAll = ref(false)
const markError = ref<string | null>(null)

async function markAllRead() {
  markingAll.value = true
  markError.value = null
  try {
    await notifications.markAllRead()
  }
  catch (e) {
    markError.value = apiErrorMessage(e, t('common.error'))
  }
  finally {
    markingAll.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <h1 class="text-3xl font-bold">
        {{ t('notifications.title') }}
      </h1>
      <UButton
        v-if="notifications.unreadCount > 0"
        :label="t('notifications.mark_all_read')"
        icon="i-lucide-check-check"
        color="neutral"
        variant="outline"
        class="cursor-pointer"
        :loading="markingAll"
        @click="markAllRead"
      />
    </div>

    <UAlert
      v-if="markError"
      color="error"
      variant="subtle"
      :title="markError"
    />

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      :title="apiErrorMessage(error, t('notifications.load_error'))"
    />

    <template v-else>
      <p
        v-if="loadStatus === 'pending' && notifications.items.length === 0"
        class="text-muted"
      >
        {{ t('notifications.loading') }}
      </p>
      <p
        v-else-if="notifications.items.length === 0"
        class="text-muted"
      >
        {{ t('notifications.empty') }}
      </p>

      <ul
        v-else
        class="divide-y divide-default rounded-md border border-default"
      >
        <li
          v-for="notification in notifications.items"
          :key="notification.id"
          :data-unread="!notification.read_at || undefined"
        >
          <!-- Opening one marks it read, then follows its link if it has one. -->
          <button
            type="button"
            class="flex w-full cursor-pointer items-start gap-3 px-4 py-3 text-left hover:bg-elevated"
            :class="notification.read_at ? 'text-muted' : 'bg-primary/5 font-medium'"
            @click="openNotification(notification)"
          >
            <span
              class="mt-1.5 size-2 shrink-0 rounded-full"
              :class="notification.read_at ? 'bg-transparent' : 'bg-primary'"
            />
            <span class="min-w-0 flex-1">
              <span class="block">{{ notification.message }}</span>
              <span class="block text-xs font-normal text-muted">
                {{ formatDateTime(notification.created_at) }}
                <template v-if="!notification.read_at"> · {{ t('notifications.unread') }}</template>
              </span>
            </span>
          </button>
        </li>
      </ul>

      <UPagination
        v-if="notifications.meta && notifications.meta.last_page > 1"
        :page="page"
        :total="notifications.meta.total"
        :items-per-page="notifications.meta.per_page"
        :to="(p: number) => ({ query: { ...route.query, page: p } })"
        class="flex justify-center"
      />
    </template>
  </div>
</template>
