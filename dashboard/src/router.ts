import { createRouter, createWebHistory } from 'vue-router'
import { getToken } from './session'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/applications' },
    { path: '/login', component: () => import('./views/LoginView.vue'), meta: { public: true } },
    { path: '/applications', component: () => import('./views/ApplicationsView.vue') },
    { path: '/applications/new', component: () => import('./views/NewApplicationView.vue') },
    { path: '/applications/:id', component: () => import('./views/ApplicationView.vue'), props: true },
  ],
})

router.beforeEach((to) => (to.meta.public || getToken() ? true : '/login'))
