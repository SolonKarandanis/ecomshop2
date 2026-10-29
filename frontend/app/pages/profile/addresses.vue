<script setup lang="ts">
import type { SavedAddress } from '~/types/profile'

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'Your addresses' })

const toast = useToast()

// Default first, then most recently updated (the API's order).
const { data: addresses, error: loadError, refresh } = await useAsyncData(
  'saved-addresses',
  () => useApi()<{ data: SavedAddress[] }>('/profile/addresses').then(response => response.data),
)

// undefined: the form is closed; null: adding a new address; otherwise the address being edited.
const editing = ref<SavedAddress | null | undefined>(undefined)

async function onSaved() {
  const added = editing.value === null
  editing.value = undefined
  toast.add({ title: added ? 'Address added.' : 'Address saved.', color: 'success' })
  // Refetch rather than patch the list: adding the first address also makes it the default.
  await refresh()
}

const defaultPending = ref<number | null>(null)

async function makeDefault(address: SavedAddress) {
  defaultPending.value = address.id
  try {
    await useApi()(`/profile/addresses/${address.id}/default`, { method: 'POST' })
    toast.add({ title: 'Default address updated.', color: 'success' })
    await refresh()
  }
  catch (e) {
    toast.add({ title: apiErrorMessage(e, 'The default address couldn\'t be changed. Please try again.'), color: 'error' })
  }
  finally {
    defaultPending.value = null
  }
}

const deleting = ref<SavedAddress | null>(null)
const deletePending = ref(false)

async function confirmDelete() {
  const address = deleting.value
  if (!address) {
    return
  }

  deletePending.value = true
  try {
    // 204; deleting the default promotes another address, so refetch.
    await useApi()(`/profile/addresses/${address.id}`, { method: 'DELETE' })
    toast.add({ title: 'Address deleted.', color: 'success' })
    await refresh()
  }
  catch (e) {
    toast.add({ title: apiErrorMessage(e, 'The address couldn\'t be deleted. Please try again.'), color: 'error' })
  }
  finally {
    deleting.value = null
    deletePending.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-xl space-y-6">
    <h1 class="text-3xl font-bold">
      Your account
    </h1>
    <ProfileNav />

    <UAlert
      v-if="loadError"
      color="error"
      variant="subtle"
      :title="apiErrorMessage(loadError, 'We couldn\'t load your addresses. Reload the page to try again.')"
    />

    <template v-else>
      <div class="flex items-center justify-between gap-4">
        <h2 class="text-lg font-semibold">
          Saved addresses
        </h2>
        <UButton
          label="Add address"
          icon="i-lucide-plus"
          class="cursor-pointer"
          @click="editing = null"
        />
      </div>

      <p
        v-if="!addresses?.length"
        class="py-6 text-center text-muted"
      >
        You haven't saved any addresses yet. Add one here, or tick "Save this address" at checkout.
      </p>

      <ul
        v-else
        aria-label="Saved addresses"
        class="space-y-3"
      >
        <li
          v-for="address in addresses"
          :key="address.id"
        >
          <UCard>
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div class="min-w-0 space-y-1 text-sm">
                <p class="flex items-center gap-2 font-medium">
                  {{ address.first_name }} {{ address.last_name }}
                  <UBadge
                    v-if="address.is_default"
                    label="Default"
                    color="primary"
                    variant="subtle"
                    size="sm"
                  />
                </p>
                <p>{{ address.street_address }}</p>
                <p>{{ address.postal_code }} {{ address.city }}, {{ address.country }}</p>
                <p class="text-muted">
                  {{ address.phone }}
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <UButton
                  v-if="!address.is_default"
                  label="Set as default"
                  size="sm"
                  color="neutral"
                  variant="outline"
                  class="cursor-pointer"
                  :loading="defaultPending === address.id"
                  @click="makeDefault(address)"
                />
                <UButton
                  label="Edit"
                  size="sm"
                  color="neutral"
                  variant="outline"
                  class="cursor-pointer"
                  @click="editing = address"
                />
                <UButton
                  label="Delete"
                  size="sm"
                  color="error"
                  variant="outline"
                  class="cursor-pointer"
                  @click="deleting = address"
                />
              </div>
            </div>
          </UCard>
        </li>
      </ul>
    </template>

    <UModal
      :open="editing !== undefined"
      :title="editing ? 'Edit address' : 'Add address'"
      @update:open="(open: boolean) => { if (!open) editing = undefined }"
    >
      <template #body>
        <SavedAddressForm
          :key="editing?.id ?? 'new'"
          :address="editing"
          @saved="onSaved"
          @cancel="editing = undefined"
        />
      </template>
    </UModal>

    <UModal
      :open="deleting !== null"
      title="Delete this address?"
      :description="deleting ? formatSavedAddress(deleting) : undefined"
      @update:open="(open: boolean) => { if (!open) deleting = null }"
    >
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton
            label="Keep address"
            color="neutral"
            variant="ghost"
            class="cursor-pointer"
            @click="deleting = null"
          />
          <UButton
            label="Delete address"
            color="error"
            class="cursor-pointer"
            :loading="deletePending"
            @click="confirmDelete"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>
