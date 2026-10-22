import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mockNuxtImport, mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { getQuery, getRouterParam, readBody, readMultipartFormData, setResponseStatus } from 'h3'
import NewProductPage from '~/pages/supplier/products/new.vue'
import EditProductPage from '~/pages/supplier/products/[id]/edit.vue'
import SupplierProductsPage from '~/pages/supplier/products/index.vue'

// Toasts render in the app's UApp, which mountSuspended doesn't mount.
const { toastAdd } = vi.hoisted(() => ({ toastAdd: vi.fn() }))
mockNuxtImport('useToast', () => () => ({ add: toastAdd }))

type Wrapper = Awaited<ReturnType<typeof mountSuspended>>

function product(overrides: Record<string, unknown> = {}) {
  return {
    id: 7,
    name: 'Aurora Laptop',
    slug: 'aurora-laptop',
    description: 'Light.',
    price: 899,
    category_id: 1,
    brand_id: 2,
    is_active: true,
    is_featured: false,
    in_stock: true,
    on_sale: false,
    category: { id: 1, name: 'Laptops', slug: 'laptops', thumbnail: '' },
    brand: { id: 2, name: 'Acme', slug: 'acme' },
    attributes: [{ id: 30, attribute_id: 10, attribute_option_id: 100 }],
    images: [{ id: 50, url: '/storage/50/a.jpg', thumbnail: '/storage/50/a-thumb.jpg', small: '', large: '' }],
    ...overrides,
  }
}

let created: Record<string, unknown>[]
let updated: Record<string, unknown>[]
let imageUploads: string[][]
let imageDeletes: string[]
let deletes: string[]
let listQueries: Record<string, unknown>[]
// Laravel's 422 body for the next create, if any.
let createErrors: Record<string, string[]> | null
let deleteConflict: boolean
// GET /api/config's image limits.
let maxImages: number

beforeEach(() => {
  created = []
  updated = []
  imageUploads = []
  imageDeletes = []
  deletes = []
  listQueries = []
  createErrors = null
  deleteConflict = false
  maxImages = 5
  toastAdd.mockClear()
  // UFileUpload previews images with object URLs; Node's URL rejects happy-dom's File.
  vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:preview')
  document.cookie = 'XSRF-TOKEN=token; path=/'
  clearNuxtData()

  // Routes run the auth and supplier middleware.
  registerEndpoint('/api/user', () => ({ data: { id: 5, name: 'Sam', email: 'sam@example.com', status: 'user.status.active', roles: ['role.supplier'] } }))
  registerEndpoint('/api/config', () => ({ data: { suppliers_enabled: true, max_product_images: maxImages, max_product_image_kb: 1024 } }))
  registerEndpoint('/api/categories', () => ({ data: [{ id: 1, name: 'Laptops', slug: 'laptops', thumbnail: '' }] }))
  registerEndpoint('/api/brands', () => ({ data: [{ id: 2, name: 'Acme', slug: 'acme' }] }))
  registerEndpoint('/api/attributes', () => ({
    data: [
      { id: 10, name: 'attribute.color', label: 'Color', type: 'Image', options: [
        { id: 100, name: 'attribute.option.color.silver', label: 'Silver' },
        { id: 101, name: 'attribute.option.color.midnight', label: 'Midnight' },
      ] },
      { id: 11, name: 'attribute.ram', label: 'Ram', type: 'Select', options: [
        { id: 110, name: 'attribute.option.ram.16', label: '16 GB' },
      ] },
    ],
  }))
  registerEndpoint('/api/supplier-products', {
    method: 'GET',
    handler: (event) => {
      listQueries.push(getQuery(event))
      return {
        data: [product(), product({ id: 8, name: 'Old Mouse', is_active: false, in_stock: false, on_sale: true, images: [] })],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 2 },
      }
    },
  })
  registerEndpoint('/api/supplier-products', {
    method: 'POST',
    handler: async (event) => {
      const body = await readBody(event)
      created.push(body)
      if (createErrors) {
        setResponseStatus(event, 422)
        return { message: 'The given data was invalid.', errors: createErrors }
      }
      return { data: product({ id: 9, ...body, slug: body.slug || 'generated-slug', images: [] }) }
    },
  })
  registerEndpoint('/api/supplier-products/7', {
    method: 'GET',
    handler: () => ({ data: product() }),
  })
  registerEndpoint('/api/supplier-products/7', {
    method: 'PATCH',
    handler: async (event) => {
      const body = await readBody(event)
      updated.push(body)
      return { data: product(body) }
    },
  })
  for (const id of [7, 8]) {
    registerEndpoint(`/api/supplier-products/${id}`, {
      method: 'DELETE',
      handler: (event) => {
        deletes.push(getRouterParam(event, 'id') ?? String(id))
        if (deleteConflict) {
          setResponseStatus(event, 409)
          return { status: false, message: 'This product is part of existing orders and cannot be deleted.', data: null, error: 'ProductException' }
        }
        setResponseStatus(event, 204)
        return null
      },
    })
  }
  registerEndpoint('/api/supplier-products/99', (event) => {
    setResponseStatus(event, 403)
    return { message: 'This action is unauthorized.' }
  })
  for (const id of [7, 9]) {
    registerEndpoint(`/api/supplier-products/${id}/images`, {
      method: 'POST',
      handler: async (event) => {
        const parts = await readMultipartFormData(event) ?? []
        imageUploads.push(parts.map(part => `${part.name}:${part.filename}`))
        return { data: product({ id, images: [{ id: 60, url: '/storage/60/new.png', thumbnail: '', small: '', large: '' }] }) }
      },
    })
  }
  registerEndpoint('/api/supplier-products/7/images/50', {
    method: 'DELETE',
    handler: () => {
      imageDeletes.push('7/50')
      return { data: product({ images: [] }) }
    },
  })
})

function label(page: Wrapper, text: string) {
  const match = page.findAll('label').find(candidate => candidate.text().startsWith(text))
  if (!match) {
    throw new Error(`No field labelled ${text}`)
  }
  return match
}

// The control a UFormField's <label for> points at: an input, textarea, or select trigger.
function input(page: Wrapper, text: string) {
  return page.find(`[id="${label(page, text).attributes('for')}"]`)
}

function fieldText(page: Wrapper, text: string) {
  return label(page, text).element.closest('[data-slot="root"]')?.textContent ?? ''
}

// Reka's Select opens from the keyboard on its trigger and renders its options in a portal.
async function choose(trigger: ReturnType<Wrapper['find']>, optionLabel: string) {
  await trigger.trigger('keydown', { key: 'Enter' })
  await flushPromises()
  const option = [...document.body.querySelectorAll<HTMLElement>('[role="option"]')].find(item => item.textContent?.trim() === optionLabel)
  if (!option) {
    throw new Error(`No option labelled ${optionLabel}`)
  }
  option.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }))
  await flushPromises()
}

async function fillRequired(page: Wrapper) {
  await input(page, 'Name').setValue('Aurora Laptop 14')
  await input(page, 'Price').setValue('899.5')
  await choose(input(page, 'Category'), 'Laptops')
  await choose(input(page, 'Brand'), 'Acme')
}

async function submit(page: Wrapper) {
  await page.find('form').trigger('submit')
  await flushPromises()
  await flushPromises()
}

describe('product form', () => {
  it('fills the slug from the name until the Supplier edits it, and again once it is cleared', async () => {
    const page = await mountSuspended(NewProductPage, { route: '/supplier/products/new' })
    const slug = () => (input(page, 'Slug').element as HTMLInputElement).value

    await input(page, 'Name').setValue('Café Laptop 14"')
    expect(slug()).toBe('cafe-laptop-14')

    await input(page, 'Slug').setValue('my-laptop')
    await input(page, 'Name').setValue('Something else')
    expect(slug()).toBe('my-laptop')

    await input(page, 'Slug').setValue('')
    await input(page, 'Name').setValue('Back to auto')
    expect(slug()).toBe('back-to-auto')
  })

  it('keeps an existing Product\'s slug when it is renamed', async () => {
    const page = await mountSuspended(EditProductPage, { route: '/supplier/products/7/edit' })

    expect((input(page, 'Name').element as HTMLInputElement).value).toBe('Aurora Laptop')
    await input(page, 'Name').setValue('Aurora Laptop Pro')

    expect((input(page, 'Slug').element as HTMLInputElement).value).toBe('aurora-laptop')
  })

  it('checks the required fields before calling the API', async () => {
    const page = await mountSuspended(NewProductPage, { route: '/supplier/products/new' })

    await page.findAll('button').find(b => b.text() === 'Add attribute')!.trigger('click')
    await submit(page)

    expect(created).toHaveLength(0)
    expect(page.text()).toContain('Enter a product name.')
    expect(page.text()).toContain('Enter a price of 0 or more.')
    expect(page.text()).toContain('Choose a category.')
    expect(page.text()).toContain('Choose a brand.')
    expect(page.text()).toContain('Choose an attribute.')
    expect(page.text()).toContain('Choose a value.')
  })

  it('adds and removes attribute rows, offering only the chosen attribute\'s options', async () => {
    const page = await mountSuspended(NewProductPage, { route: '/supplier/products/new' })
    const addRow = () => page.findAll('button').find(b => b.text() === 'Add attribute')!.trigger('click')
    const rows = () => page.findAll('[data-testid="attribute-row"]')

    await addRow()
    await addRow()
    expect(rows()).toHaveLength(2)

    await choose(rows()[0]!.find('[aria-label="Attribute"]'), 'Color')
    await choose(rows()[0]!.find('[aria-label="Value"]'), 'Midnight')
    await choose(rows()[1]!.find('[aria-label="Attribute"]'), 'Ram')

    // Only Ram's options are offered for the second row.
    await rows()[1]!.find('[aria-label="Value"]').trigger('keydown', { key: 'Enter' })
    await flushPromises()
    const offered = [...document.body.querySelectorAll('[role="option"]')].map(option => option.textContent?.trim())
    expect(offered).toEqual(['16 GB'])
    document.body.querySelector<HTMLElement>('[role="option"]')!.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }))
    await flushPromises()

    // Switching the first row to Ram drops Midnight, which isn't a Ram option.
    await choose(rows()[0]!.find('[aria-label="Attribute"]'), 'Ram')
    expect(rows()[0]!.find('[aria-label="Value"]').text()).not.toContain('Midnight')
    await choose(rows()[0]!.find('[aria-label="Attribute"]'), 'Color')
    await choose(rows()[0]!.find('[aria-label="Value"]'), 'Silver')

    await rows()[1]!.find('button[aria-label="Remove attribute"]').trigger('click')
    expect(rows()).toHaveLength(1)

    await fillRequired(page)
    await submit(page)

    expect(created).toHaveLength(1)
    expect(created[0]).toMatchObject({
      name: 'Aurora Laptop 14',
      slug: 'aurora-laptop-14',
      price: 899.5,
      category_id: 1,
      brand_id: 2,
      is_active: true,
      attributes: [{ attribute_id: 10, attribute_option_id: 100 }],
    })
    expect(created[0]).not.toHaveProperty('supplier_id')
    expect(created[0]).not.toHaveProperty('is_featured')
  })

  it('shows the API\'s 422s next to the matching fields, including attribute rows', async () => {
    createErrors = {
      'slug': ['The slug has already been taken.'],
      'attributes.0.attribute_option_id': ['The selected option does not belong to the selected attribute.'],
    }
    const page = await mountSuspended(NewProductPage, { route: '/supplier/products/new' })
    await page.findAll('button').find(b => b.text() === 'Add attribute')!.trigger('click')
    const row = page.find('[data-testid="attribute-row"]')
    await choose(row.find('[aria-label="Attribute"]'), 'Color')
    await choose(row.find('[aria-label="Value"]'), 'Silver')
    await fillRequired(page)

    await submit(page)

    expect(created).toHaveLength(1)
    expect(fieldText(page, 'Slug')).toContain('The slug has already been taken.')
    expect(page.find('[data-testid="attribute-row"]').text()).toContain('The selected option does not belong to the selected attribute.')
    expect(imageUploads).toHaveLength(0)
  })

  it('uploads the chosen images after creating the Product', async () => {
    const page = await mountSuspended(NewProductPage, { route: '/supplier/products/new' })
    await fillRequired(page)

    const fileInput = page.find('input[type="file"]')
    Object.defineProperty(fileInput.element, 'files', {
      value: [new File(['x'], 'front.png', { type: 'image/png' })],
      configurable: true,
    })
    await fileInput.trigger('change')
    await flushPromises()

    await submit(page)

    expect(created).toHaveLength(1)
    await vi.waitFor(() => expect(imageUploads).toEqual([['images[]:front.png']]))
    await vi.waitFor(() => expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ title: expect.stringContaining('was created.'), color: 'success' })))
  })

  it('rejects non-image files without calling the API', async () => {
    const page = await mountSuspended(NewProductPage, { route: '/supplier/products/new' })
    await fillRequired(page)

    const fileInput = page.find('input[type="file"]')
    Object.defineProperty(fileInput.element, 'files', {
      value: [new File(['x'], 'notes.pdf', { type: 'application/pdf' })],
      configurable: true,
    })
    await fileInput.trigger('change')
    await flushPromises()
    await submit(page)

    expect(created).toHaveLength(0)
    expect(page.text()).toContain('Images must be JPG, PNG or WebP files.')
  })

  it('checks images against the limits from /config', async () => {
    maxImages = 1
    const page = await mountSuspended(EditProductPage, { route: '/supplier/products/7/edit' })

    // The Product already has its one allowed image.
    expect(page.text()).toContain('up to 1 MB each. 0 of 1 left.')

    await page.find('button[aria-label="Remove image"]').trigger('click')
    expect(page.text()).toContain('1 of 1 left.')

    const fileInput = page.find('input[type="file"]')
    Object.defineProperty(fileInput.element, 'files', {
      value: [new File([new Uint8Array(2 * 1024 * 1024)], 'huge.png', { type: 'image/png' })],
      configurable: true,
    })
    await fileInput.trigger('change')
    await flushPromises()
    await submit(page)

    expect(updated).toHaveLength(0)
    expect(page.text()).toContain('Each image must be 1 MB or smaller.')
  })

  it('saves edits, removing an existing image after the Product is saved', async () => {
    const page = await mountSuspended(EditProductPage, { route: '/supplier/products/7/edit' })

    expect(page.findAll('[data-testid="existing-image"]')).toHaveLength(1)
    expect(page.findAll('[data-testid="attribute-row"]')).toHaveLength(1)

    await page.find('button[aria-label="Remove image"]').trigger('click')
    expect(page.findAll('[data-testid="existing-image"]')).toHaveLength(0)
    expect(imageDeletes).toEqual([])

    await input(page, 'Price').setValue('950')
    await submit(page)

    expect(updated).toHaveLength(1)
    expect(updated[0]).toMatchObject({ price: 950, slug: 'aurora-laptop', attributes: [{ attribute_id: 10, attribute_option_id: 100 }] })
    await vi.waitFor(() => expect(imageDeletes).toEqual(['7/50']))
    await vi.waitFor(() => expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ title: '"Aurora Laptop" was saved.' })))
  })

  it('shows a friendly state for another Supplier\'s Product', async () => {
    const page = await mountSuspended(EditProductPage, { route: '/supplier/products/99/edit' })

    expect(page.text()).toContain('Product unavailable')
    expect(page.find('form').exists()).toBe(false)
  })
})

describe('supplier products list', () => {
  function rows(page: Wrapper) {
    return page.findAll('tbody tr')
  }

  it('lists the Products with their status badges and an Edit link', async () => {
    const page = await mountSuspended(SupplierProductsPage, { route: '/supplier/products' })

    expect(rows(page)).toHaveLength(2)
    expect(rows(page)[0]!.text()).toContain('Aurora Laptop')
    expect(rows(page)[0]!.text()).toContain('Active')
    expect(rows(page)[0]!.text()).toContain('In stock')
    expect(rows(page)[1]!.text()).toContain('Inactive')
    expect(rows(page)[1]!.text()).toContain('Out of stock')
    expect(rows(page)[1]!.text()).toContain('On sale')
    expect(page.find('a[href="/supplier/products/7/edit"]').exists()).toBe(true)
    expect(listQueries[0]).toMatchObject({ page: '1', perPage: '20' })
  })

  it('asks for confirmation before deleting', async () => {
    const page = await mountSuspended(SupplierProductsPage, { route: '/supplier/products' })

    await rows(page)[1]!.findAll('button').find(b => b.text() === 'Delete')!.trigger('click')
    await flushPromises()

    expect(deletes).toEqual([])
    expect(document.body.textContent).toContain('Delete Old Mouse?')

    const confirm = [...document.body.querySelectorAll('button')].find(b => b.textContent?.trim() === 'Delete product')!
    confirm.click()

    await vi.waitFor(() => expect(deletes).toEqual(['8']))
    await vi.waitFor(() => expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ title: '"Old Mouse" was deleted.', color: 'success' })))
    expect(listQueries).toHaveLength(2)
  })

  it('shows why a Product in existing Orders can\'t be deleted', async () => {
    deleteConflict = true
    const page = await mountSuspended(SupplierProductsPage, { route: '/supplier/products' })

    await rows(page)[0]!.findAll('button').find(b => b.text() === 'Delete')!.trigger('click')
    await flushPromises()
    ;[...document.body.querySelectorAll('button')].find(b => b.textContent?.trim() === 'Delete product')!.click()

    await vi.waitFor(() => expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({
      title: 'This product is part of existing orders and cannot be deleted.',
      color: 'error',
    })))
    expect(deletes).toEqual(['7'])
  })
})
