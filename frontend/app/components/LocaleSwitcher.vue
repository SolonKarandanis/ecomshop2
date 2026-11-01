<script setup lang="ts">
const { locale, locales, t } = useI18n()
const { switchTo } = useLocaleSwitch()

const items = computed(() => locales.value.map(item => ({ label: item.name ?? item.code, value: item.code })))
</script>

<template>
  <USelect
    :model-value="locale"
    :items="items"
    icon="i-lucide-languages"
    color="neutral"
    variant="ghost"
    :aria-label="t('nav.language')"
    class="w-36"
    @update:model-value="code => isLocale(code) && switchTo(code)"
  />
</template>
