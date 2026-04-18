import { copyFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

// GitHub Pages has no server-side rewrites; a 404.html that contains
// the SPA shell lets vue-router pick up deep-links like /primitives.
const here = dirname(fileURLToPath(import.meta.url))
const dist = resolve(here, '..', 'dist')

await copyFile(resolve(dist, 'index.html'), resolve(dist, '404.html'))
console.log('Wrote dist/404.html for SPA deep-link fallback.')
