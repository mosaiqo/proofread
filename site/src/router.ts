import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'home',
    component: () => import('@/views/Home.vue'),
    meta: { title: 'Proofread — Laravel-native AI evals', layout: 'default' },
  },
  {
    path: '/primitives',
    name: 'primitives',
    component: () => import('@/views/Primitives.vue'),
    meta: { title: 'Primitives — Proofread', layout: 'default' },
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
    redirect: '/',
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

router.afterEach((to) => {
  const title = (to.meta.title as string | undefined) ?? 'Proofread'
  document.title = title
})
