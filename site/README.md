# Proofread marketing site

Static Vue 3 + Vite + Tailwind site deployed to GitHub Pages at
`https://mosaiqo.github.io/proofread/`.

## Stack

- Vite + Vue 3 + TypeScript (strict)
- Tailwind CSS v3 with shadcn-vue primitives (button, card, tabs, badge)
- `reka-ui` for headless component behavior
- `lucide-vue-next` for icons
- `shiki` for syntax highlighting
- `vue-router` for the `/primitives` showcase page

## Local development

```bash
cd site
npm install
npm run dev     # starts Vite on http://localhost:5173/proofread/
```

## Build

```bash
npm run build   # runs vue-tsc type-check, then builds to site/dist/
npm run preview # serves the built dist/ locally
```

## Visual language

The single source of truth for the design system lives in
`src/assets/styles/tokens.css` (CSS custom properties) and is
re-exported as typed data from `src/design/tokens.ts`. The
`/primitives` route renders the full catalog so every color,
type size, spacing step, radius, shadow, and motion token is
inspectable live.

See [`src/design/README.md`](src/design/README.md) for the full
spec.

## Deploy

Pushing to `main` with changes under `site/**` triggers the
`.github/workflows/deploy-site.yml` workflow, which builds the
site and publishes `site/dist/` to the `gh-pages` branch. GitHub
Pages serves that branch.

The Vite `base` is `/proofread/` so all assets resolve correctly
under the project-page URL.
