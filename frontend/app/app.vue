<script setup lang="ts">
import { el, en, tr } from '@nuxt/ui/locale'

const { locale } = useI18n()

// Nuxt UI's own texts (close buttons, empty tables, pagination) in the page's language.
const uiLocales = { en, el, tr }
const uiLocale = computed(() => uiLocales[locale.value as keyof typeof uiLocales] ?? en)

// <html lang>, hreflang alternates for every language, canonical and og:locale.
const localeHead = useLocaleHead()

useHead(() => ({
  htmlAttrs: {
    lang: localeHead.value.htmlAttrs.lang,
    dir: localeHead.value.htmlAttrs.dir,
  },
  link: [...(localeHead.value.link ?? [])],
  meta: [...(localeHead.value.meta ?? [])],
}))
</script>

<template>
  <UApp :locale="uiLocale">
    <NuxtRouteAnnouncer />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>
