<script setup lang="ts">
import type { FormError } from '@nuxt/ui'
import type { ProductImage, SupplierProduct, SupplierProductPayload } from '~/types/supplierProduct'

const localePath = useLocalePath()
const { t } = useI18n()

const props = defineProps<{
  // Omitted on the "new product" page.
  product?: SupplierProduct
}>()

const emit = defineEmits<{
  saved: [product: SupplierProduct]
}>()

// Public lookup lists and limits, read without credentials like the catalogue.
const [{ data: categories }, { data: brands }, { data: attributes }, { data: config }] = await Promise.all([
  useCategories(),
  useBrands(),
  useProductAttributes(),
  usePublicConfig(),
])

const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp']
// The API's image limits. If /config didn't load, the form skips these checks
// and leaves them to the API's 422.
const maxImages = computed(() => config.value?.max_product_images ?? Infinity)
const maxImageKb = computed(() => config.value?.max_product_image_kb ?? Infinity)

type AttributeRow = SupplierProductPayload['attributes'][number] & { key: number }
type FormState = Omit<SupplierProductPayload, 'attributes'> & { attributes: AttributeRow[] }

let nextRowKey = 0
function attributeRow(attributeId?: number, optionId?: number): AttributeRow {
  return { key: nextRowKey++, attribute_id: attributeId, attribute_option_id: optionId }
}

const state = reactive<FormState>({
  name: props.product?.name ?? '',
  slug: props.product?.slug ?? '',
  description: props.product?.description ?? '',
  price: props.product?.price,
  category_id: props.product?.category_id,
  brand_id: props.product?.brand_id,
  is_active: props.product?.is_active ?? true,
  in_stock: props.product?.in_stock ?? true,
  on_sale: props.product?.on_sale ?? false,
  attributes: (props.product?.attributes ?? []).map(value => attributeRow(value.attribute_id, value.attribute_option_id)),
})

// The slug follows the name until the Supplier types their own. An existing
// Product keeps its slug, since renaming it would change the product's URL.
const slugEdited = ref(props.product !== undefined)
watch(() => state.name, (name) => {
  if (!slugEdited.value) {
    state.slug = slugify(name)
  }
})
function onSlugInput(value: string) {
  state.slug = value
  // Clearing the slug hands it back to the name.
  slugEdited.value = value !== ''
}

const categoryItems = computed(() => (categories.value ?? []).map(category => ({ label: category.name, value: category.id })))
const brandItems = computed(() => (brands.value ?? []).map(brand => ({ label: brand.name, value: brand.id })))
const attributeItems = computed(() => (attributes.value ?? []).map(attribute => ({ label: attribute.label, value: attribute.id })))

function optionItems(row: AttributeRow) {
  const attribute = attributes.value?.find(candidate => candidate.id === row.attribute_id)
  return (attribute?.options ?? []).map(option => ({ label: option.label, value: option.id }))
}

function addAttributeRow() {
  state.attributes.push(attributeRow())
}

function removeAttributeRow(index: number) {
  state.attributes.splice(index, 1)
}

function onAttributeChange(row: AttributeRow, attributeId: number) {
  row.attribute_id = attributeId
  // An option from the previous attribute would no longer belong to this one.
  if (!optionItems(row).some(option => option.value === row.attribute_option_id)) {
    row.attribute_option_id = undefined
  }
}

// Images are sent to their own endpoints once the Product is saved: removals
// first, then uploads, so the 5-image limit counts what's really kept.
const existingImages = ref<ProductImage[]>(props.product?.images ?? [])
const removedImageIds = ref<number[]>([])
const newImages = ref<File[]>([])
const imageError = ref<string | null>(null)

const keptImages = computed(() => existingImages.value.filter(image => !removedImageIds.value.includes(image.id)))
const slotsLeft = computed(() => maxImages.value - keptImages.value.length - newImages.value.length)

const imageHint = computed(() => {
  if (!config.value) {
    return t('product_form.image_hint_basic')
  }
  const sizeMb = Number((config.value.max_product_image_kb / 1024).toFixed(1))
  return t('product_form.image_hint', { size: sizeMb, left: Math.max(slotsLeft.value, 0), max: config.value.max_product_images })
})

function removeExistingImage(id: number) {
  removedImageIds.value.push(id)
}

const fieldNames = () => [
  'name', 'slug', 'description', 'price', 'category_id', 'brand_id', 'is_active', 'in_stock', 'on_sale',
  ...state.attributes.flatMap((_, index) => [`attributes.${index}.attribute_id`, `attributes.${index}.attribute_option_id`]),
]
const { error, submit } = useApiForm(fieldNames)
const submitting = ref(false)

// Set once the Product exists, so a retry after a failed image upload updates
// the Product instead of creating a second one.
const productId = ref(props.product?.id)

function validate(data: Partial<FormState>): FormError[] {
  const errors: FormError[] = []

  if (!data.name?.trim()) {
    errors.push({ name: 'name', message: t('product_form.validation.name') })
  }
  if (productId.value !== undefined && !data.slug?.trim()) {
    errors.push({ name: 'slug', message: t('product_form.validation.slug') })
  }
  if (typeof data.price !== 'number' || Number.isNaN(data.price) || data.price < 0) {
    errors.push({ name: 'price', message: t('product_form.validation.price') })
  }
  if (!data.category_id) {
    errors.push({ name: 'category_id', message: t('product_form.validation.category') })
  }
  if (!data.brand_id) {
    errors.push({ name: 'brand_id', message: t('product_form.validation.brand') })
  }
  data.attributes?.forEach((row, index) => {
    if (!row.attribute_id) {
      errors.push({ name: `attributes.${index}.attribute_id`, message: t('product_form.validation.attribute') })
    }
    if (!row.attribute_option_id) {
      errors.push({ name: `attributes.${index}.attribute_option_id`, message: t('product_form.validation.value') })
    }
  })

  return errors
}

function imageProblem(): string | null {
  if (slotsLeft.value < 0) {
    return t('product_form.image.too_many', { max: maxImages.value })
  }
  if (newImages.value.some(file => !IMAGE_TYPES.includes(file.type))) {
    return t('product_form.image.wrong_type')
  }
  if (newImages.value.some(file => file.size > maxImageKb.value * 1024)) {
    return t('product_form.image.too_big', { size: Number((maxImageKb.value / 1024).toFixed(1)) })
  }
  return null
}

function payload(): SupplierProductPayload {
  return {
    ...state,
    attributes: state.attributes.map(({ attribute_id, attribute_option_id }) => ({ attribute_id, attribute_option_id })),
  }
}

async function saveImages(id: number): Promise<SupplierProduct | null> {
  const api = useApi()
  let latest: SupplierProduct | null = null

  try {
    for (const imageId of [...removedImageIds.value]) {
      latest = (await api<{ data: SupplierProduct }>(`/supplier-products/${id}/images/${imageId}`, { method: 'DELETE' })).data
      removedImageIds.value = removedImageIds.value.filter(removed => removed !== imageId)
    }

    if (newImages.value.length > 0) {
      const body = new FormData()
      newImages.value.forEach(file => body.append('images[]', file))
      latest = (await api<{ data: SupplierProduct }>(`/supplier-products/${id}/images`, { method: 'POST', body })).data
      newImages.value = []
    }
  }
  catch (e) {
    imageError.value = apiValidationErrors(e)[0]?.message ?? apiErrorMessage(e, t('product_form.image.save_error'))
  }

  if (latest) {
    existingImages.value = latest.images ?? []
  }

  return latest
}

async function onSubmit() {
  imageError.value = imageProblem()
  if (imageError.value) {
    return
  }

  submitting.value = true
  // Assigned inside submit()'s callback; the cast stops TS narrowing it to null.
  let saved = null as SupplierProduct | null

  await submit(async () => {
    const api = useApi()
    const response = productId.value === undefined
      ? await api<{ data: SupplierProduct }>('/supplier-products', { method: 'POST', body: payload() })
      : await api<{ data: SupplierProduct }>(`/supplier-products/${productId.value}`, { method: 'PATCH', body: payload() })
    saved = response.data
  })

  if (saved !== null) {
    const product = saved
    productId.value = product.id
    slugEdited.value = true
    state.slug = product.slug

    const withImages = await saveImages(product.id)
    if (imageError.value === null) {
      emit('saved', withImages ?? product)
    }
  }

  submitting.value = false
}
</script>

<template>
  <UForm
    ref="form"
    :state="state"
    :validate="validate"
    class="space-y-8"
    @submit="onSubmit"
  >
    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      :title="error"
    />
    <UAlert
      v-if="productId !== undefined && imageError && !product"
      color="warning"
      variant="subtle"
      :title="t('product_form.images_not_saved')"
      :description="t('product_form.images_not_saved_hint')"
    />

    <section class="space-y-4">
      <h2 class="text-lg font-semibold">
        {{ t('product_form.details') }}
      </h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <UFormField
          :label="t('product_form.name')"
          name="name"
          required
        >
          <UInput
            v-model="state.name"
            class="w-full"
          />
        </UFormField>
        <UFormField
          :label="t('product_form.slug')"
          name="slug"
          :required="productId !== undefined"
          :help="productId === undefined ? t('product_form.slug_help_new') : t('product_form.slug_help_existing')"
        >
          <UInput
            :model-value="state.slug"
            class="w-full"
            @update:model-value="onSlugInput(String($event ?? ''))"
          />
        </UFormField>
        <UFormField
          :label="t('product_form.price')"
          name="price"
          required
        >
          <UInput
            v-model.number="state.price"
            type="number"
            min="0"
            step="0.01"
            class="w-full"
          />
        </UFormField>
        <UFormField
          :label="t('product_form.category')"
          name="category_id"
          required
        >
          <USelect
            v-model="state.category_id"
            :items="categoryItems"
            :placeholder="t('product_form.choose_category')"
            class="w-full"
          />
        </UFormField>
        <UFormField
          :label="t('product_form.brand')"
          name="brand_id"
          required
        >
          <USelect
            v-model="state.brand_id"
            :items="brandItems"
            :placeholder="t('product_form.choose_brand')"
            class="w-full"
          />
        </UFormField>
        <UFormField
          :label="t('product_form.description')"
          name="description"
          class="sm:col-span-2"
        >
          <UTextarea
            v-model="state.description"
            :rows="5"
            class="w-full"
          />
        </UFormField>
      </div>
    </section>

    <section class="space-y-4">
      <h2 class="text-lg font-semibold">
        {{ t('product_form.status') }}
      </h2>
      <div class="flex flex-wrap gap-6">
        <UFormField name="is_active">
          <USwitch
            v-model="state.is_active"
            :label="t('product_form.active')"
            :description="t('product_form.active_help')"
          />
        </UFormField>
        <UFormField name="in_stock">
          <USwitch
            v-model="state.in_stock"
            :label="t('product_form.in_stock')"
          />
        </UFormField>
        <UFormField name="on_sale">
          <USwitch
            v-model="state.on_sale"
            :label="t('product_form.on_sale')"
          />
        </UFormField>
      </div>
    </section>

    <section class="space-y-4">
      <div class="flex items-center justify-between gap-4">
        <h2 class="text-lg font-semibold">
          {{ t('product_form.attributes') }}
        </h2>
        <UButton
          :label="t('product_form.add_attribute')"
          icon="i-lucide-plus"
          color="neutral"
          variant="outline"
          size="sm"
          class="cursor-pointer"
          @click="addAttributeRow"
        />
      </div>
      <p
        v-if="state.attributes.length === 0"
        class="text-sm text-muted"
      >
        {{ t('product_form.no_attributes') }}
      </p>
      <ul
        v-else
        class="space-y-3"
      >
        <li
          v-for="(row, index) in state.attributes"
          :key="row.key"
          data-testid="attribute-row"
          class="grid items-start gap-3 sm:grid-cols-[1fr_1fr_auto]"
        >
          <UFormField
            :name="`attributes.${index}.attribute_id`"
            :label="index === 0 ? t('product_form.attribute') : undefined"
          >
            <USelect
              :model-value="row.attribute_id"
              :items="attributeItems"
              :placeholder="t('product_form.attribute')"
              :aria-label="t('product_form.attribute')"
              class="w-full"
              @update:model-value="onAttributeChange(row, $event as number)"
            />
          </UFormField>
          <UFormField
            :name="`attributes.${index}.attribute_option_id`"
            :label="index === 0 ? t('product_form.value') : undefined"
          >
            <USelect
              v-model="row.attribute_option_id"
              :items="optionItems(row)"
              :disabled="!row.attribute_id"
              :placeholder="t('product_form.value')"
              :aria-label="t('product_form.value')"
              class="w-full"
            />
          </UFormField>
          <UButton
            icon="i-lucide-trash-2"
            color="error"
            variant="ghost"
            :aria-label="t('product_form.remove_attribute')"
            class="cursor-pointer"
            :class="index === 0 ? 'sm:mt-6' : undefined"
            @click="removeAttributeRow(index)"
          />
        </li>
      </ul>
    </section>

    <section class="space-y-4">
      <h2 class="text-lg font-semibold">
        {{ t('product_form.images') }}
      </h2>
      <ul
        v-if="keptImages.length"
        class="flex flex-wrap gap-3"
      >
        <li
          v-for="image in keptImages"
          :key="image.id"
          data-testid="existing-image"
          class="relative"
        >
          <img
            :src="image.thumbnail || image.url"
            alt=""
            class="size-24 rounded-md border border-default object-cover"
          >
          <UButton
            icon="i-lucide-x"
            color="neutral"
            size="xs"
            :aria-label="t('product_form.remove_image')"
            class="absolute -right-2 -top-2 cursor-pointer rounded-full"
            @click="removeExistingImage(image.id)"
          />
        </li>
      </ul>
      <UFileUpload
        v-model="newImages"
        multiple
        accept="image/jpeg,image/png,image/webp"
        layout="grid"
        :label="t('product_form.drop_images')"
        :description="imageHint"
        :disabled="slotsLeft <= 0 && newImages.length === 0"
        class="w-full"
      />
      <p
        v-if="imageError"
        role="alert"
        class="text-sm text-error"
      >
        {{ imageError }}
      </p>
    </section>

    <div class="flex justify-end gap-2">
      <UButton
        :to="localePath('/supplier/products')"
        :label="t('common.cancel')"
        color="neutral"
        variant="ghost"
      />
      <UButton
        type="submit"
        :label="product ? t('product_form.save') : t('product_form.create')"
        :loading="submitting"
        class="cursor-pointer"
      />
    </div>
  </UForm>
</template>
