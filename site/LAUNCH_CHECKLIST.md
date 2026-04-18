# Launch verification checklist

Pre-announcement walk-through. Do this before linking the docs anywhere public.

## Build
- [ ] `npm install` clean from scratch
- [ ] `npm run build` passes, no warnings about chunks > 1MB that aren't bundled deps
- [ ] `dist/` size reasonable (under 5MB gzipped)
- [ ] `dist/sitemap.xml` exists with entries for every docs page
- [ ] `dist/robots.txt` points at correct sitemap URL
- [ ] `dist/search-index.json` has >100 entries
- [ ] `dist/404.html` exists (SPA fallback)
- [ ] `dist/og-image.png` exists and is 1200x630

## Navigation
- [ ] Landing loads at `/` and renders correctly
- [ ] `/docs` redirects to `/docs/getting-started`
- [ ] All sidebar links resolve (no 404s)
- [ ] TOC on each doc page highlights active heading on scroll
- [ ] Pagination prev/next works through the nav order
- [ ] Cmd+K opens search, typing returns results, Enter navigates
- [ ] Hitting a totally unknown URL (e.g. `/nope`) renders the 404 page

## Theming
- [ ] Light mode legible
- [ ] Dark mode legible, no black-on-black or white-on-white
- [ ] System mode respects OS preference (toggle cycles light → dark → system)
- [ ] Toggle persists across reload
- [ ] No FOUC (flash of unstyled content) on load
- [ ] Shiki code blocks respect the active theme

## Content
- [ ] All code snippets have working syntax highlighting
- [ ] Copy button works on hover of code blocks, shows "Copied!"
- [ ] All internal links (relative `/docs/...`) resolve
- [ ] All external GitHub source links resolve (spot-check 5)
- [ ] Callouts render with correct variants (info/warn/danger/success)
- [ ] No "TODO", "FIXME", or lorem ipsum text

## Mobile
- [ ] Sidebar collapses into a hamburger on viewport <768px
- [ ] Cmd+K search accessible without keyboard
- [ ] Code blocks horizontally scrollable, don't break layout
- [ ] TOC rail hides appropriately on narrow viewports

## SEO
- [ ] `<title>` varies per page (verify view-source on 3 docs pages)
- [ ] `<meta name="description">` present on each page
- [ ] Open Graph image (`og-image.png`) returns 200 at `/proofread/og-image.png`
- [ ] Canonical links correct (check Home, Primitives, each docs page)
- [ ] Sitemap submitted to Google Search Console (post-launch manual step)

## Accessibility
- [ ] Tab order skip-link → logo → nav links → theme toggle → content
- [ ] Icon-only buttons have `aria-label` (theme toggle, search trigger, copy)
- [ ] Heading hierarchy clean (no h2 → h4 jumps)
- [ ] Focus rings visible on keyboard nav in both themes

## Performance
- [ ] Lighthouse score >= 90 on landing (desktop)
- [ ] Lighthouse score >= 85 on a docs page (desktop)
- [ ] First contentful paint <1s on fast connection

## Deploy
- [ ] GitHub Pages workflow passes on main
- [ ] Live URL `https://mosaiqo.github.io/proofread/` loads
- [ ] Deep link `/docs/getting-started` loads (SPA fallback works)
