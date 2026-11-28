# Issue-Entwurf (local_adele): ESLint prüfte die Vue-3-Anwendung mit Vue-2-Regeln

> **Stand:** gefunden und behoben in `2026112300` (Re-Audit 2026-11-22:
> „Lint-Baseline gesondert beurteilen“).

## Befund (belegt, 2026-11-23)

`vue3/package.json` → `eslintConfig.extends` enthielt
`plugin:vue/essential`. Bei eslint-plugin-vue 9 ist das der Regelsatz für
**Vue 2**. Folgen:

- 4 Fehlalarme in der Baseline: `vue/no-multiple-template-root` (2) und
  `vue/no-reserved-props` (2). Vue 3 erlaubt mehrere Wurzelknoten.
- Ein echter Vue-3-Befund fehlte: `vue/no-deprecated-router-link-tag-prop`.
  `NotFound.vue` setzte `tag="button"` auf `router-link`, was Vue Router 4
  ignoriert.
- Widersprüchliche Vorgaben für `<template v-for>`: Vue 2 verbietet den Key
  dort, Vue 3 verlangt ihn.

## Behebung

`plugin:vue/vue3-essential`; `tag` in `NotFound.vue` entfernt. Der Link war
über `class="btn btn-primary"` schon vorher als Knopf gestaltet und sieht
unverändert aus. Baseline neu geschrieben: 92 → 88 Befunde (79
`no-unused-vars`, 6 `vue/no-mutating-props`, 2 `no-irregular-whitespace`, 1
`vue/return-in-computed-property`).

## Offen (eigene Arbeit)

Die 88 verbleibenden Befunde abbauen. Am wichtigsten sind die 6
`no-mutating-props` im Abschluss- und Restriktionseditor: Sie verlangen ein
Refactoring mit Tests.
