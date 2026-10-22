import type { ApplicationConfig } from '~/types/auth'

/** The API's public feature flags and limits (`GET /config`), shared under one key. */
export function usePublicConfig() {
  return useCatalogFetch<{ data: ApplicationConfig }, ApplicationConfig>('/config', {
    key: 'public-config',
    transform: response => response.data,
  })
}
