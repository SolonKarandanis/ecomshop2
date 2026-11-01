import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

type Messages = { [key: string]: string | Messages }

function flatten(messages: Messages, prefix = ''): Record<string, string> {
  return Object.entries(messages).reduce<Record<string, string>>((flat, [key, value]) => {
    return typeof value === 'string'
      ? { ...flat, [prefix + key]: value }
      : { ...flat, ...flatten(value, `${prefix}${key}.`) }
  }, {})
}

// Read as plain JSON: imported, @nuxtjs/i18n's build step would compile them.
function load(locale: string): Record<string, string> {
  return flatten(JSON.parse(readFileSync(join(__dirname, `../../i18n/locales/${locale}.json`), 'utf8')))
}

const english = load('en')
const translations = { el: load('el'), tr: load('tr') }

const placeholders = (message: string) => [...message.matchAll(/\{(\w+)\}/g)].map(match => match[1]).sort()
const pluralForms = (message: string) => message.split('|').length

function sourceFiles(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    return statSync(path).isDirectory() ? sourceFiles(path) : /\.(vue|ts)$/.test(name) ? [path] : []
  })
}

// Every literal key passed to t(), $t(), translate() or translateOr(), and every
// labelKey; keys built at runtime, like the API's status values, are skipped.
function literalKeys(): Record<string, string> {
  const keys: Record<string, string> = {}
  for (const file of sourceFiles(join(__dirname, '../../app'))) {
    const source = readFileSync(file, 'utf8')
    for (const match of source.matchAll(/(?:\bt|\$t|translate|translateOr)\(\s*'([\w.]+)'|labelKey: '([\w.]+)'/g)) {
      keys[match[1] ?? match[2]!] ??= file
    }
  }
  return keys
}

describe('message files', () => {
  it.each(Object.entries(translations))('has every English key in %s, and no others', (_, messages) => {
    expect(Object.keys(messages).sort()).toEqual(Object.keys(english).sort())
  })

  it.each(Object.entries(translations))('keeps the placeholders and plural forms of each English message in %s', (_, messages) => {
    for (const [key, message] of Object.entries(english)) {
      expect(placeholders(messages[key] ?? ''), key).toEqual(placeholders(message))
      expect(pluralForms(messages[key] ?? ''), key).toBe(pluralForms(message))
    }
  })

  it('has no stray vue-i18n syntax characters, which would break compiling a message', () => {
    for (const [locale, messages] of Object.entries({ en: english, ...translations })) {
      for (const [key, message] of Object.entries(messages)) {
        expect(message, `${locale}: ${key}`).not.toMatch(/[@$]/)
      }
    }
  })

  it('has an English message for every key the app uses', () => {
    const keys = literalKeys()
    expect(Object.keys(keys).length).toBeGreaterThan(100)

    const missing = Object.entries(keys).filter(([key]) => !(key in english)).map(([key, file]) => `${key} (${file})`)
    expect(missing).toEqual([])
  })
})
