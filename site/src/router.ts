import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'home',
    component: () => import('@/views/Home.vue'),
    meta: { title: 'Proofread — Laravel-native AI evals', layout: 'default' },
  },
  {
    path: '/docs',
    name: 'docs-index',
    component: () => import('@/pages/DocsRedirect.vue'),
    meta: { title: 'Documentation — Proofread', layout: 'docs' },
  },
  {
    path: '/docs/:slug(.*)+',
    name: 'docs-page',
    component: () => import('@/pages/DocsPage.vue'),
    meta: { title: 'Documentation — Proofread', layout: 'docs' },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/pages/NotFoundPage.vue'),
    meta: { title: 'Page not found — Proofread', layout: 'default' },
  },
]

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, _from, saved) {
    if (saved) return saved
    if (to.hash) return { el: to.hash, behavior: 'smooth' }
    return { top: 0 }
  },
})

