import type { FormError } from '@nuxt/ui'

interface FormHandle {
  setErrors: (errors: FormError[]) => void
}

/**
 * Submits a `UForm` against the API: 422 field errors are shown inline on the
 * matching fields, anything else (or errors for fields the form doesn't have,
 * e.g. a reset `token`) becomes the form-level `error` message.
 */
export function useApiForm(fields: () => string[], formRef = 'form') {
  const form = useTemplateRef<FormHandle>(formRef)
  const error = ref<string | null>(null)

  async function submit(action: () => Promise<unknown>) {
    error.value = null

    try {
      await action()
    }
    catch (e) {
      const known = new Set(fields())
      const fieldErrors = apiValidationErrors(e)
      const inline = fieldErrors.filter(err => err.name && known.has(err.name))
      const orphaned = fieldErrors.filter(err => !inline.includes(err))

      form.value?.setErrors(inline)

      if (inline.length === 0 || orphaned.length > 0) {
        error.value = orphaned[0]?.message ?? apiErrorMessage(e)
      }
    }
  }

  return { error, submit }
}
