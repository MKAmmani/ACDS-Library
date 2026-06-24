import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/staff',
      component: () => import('@/layouts/StaffLayout.vue'),
      redirect: '/staff/dashboard',
      children: [
        { path: 'dashboard',   component: () => import('@/Pages/Staff/Dashboard.vue'),   meta: { title: 'Dashboard' } },
        { path: 'circulation', component: () => import('@/Pages/Staff/Circulation.vue'), meta: { title: 'Circulation Desk' } },
        { path: 'reservation', component: () => import('@/Pages/Staff/Reservation.vue'), meta: { title: 'Reservations Queue' } },
        { path: 'overdue',     component: () => import('@/Pages/Staff/Overdue.vue'),     meta: { title: 'Overdue & Fines' } },
        { path: 'catalog',     component: () => import('@/Pages/Staff/Catalog.vue'),     meta: { title: 'Catalog Manager' } },
        { path: 'acquisition', component: () => import('@/Pages/Staff/Acquisition.vue'), meta: { title: 'Acquisitions' } },
        { path: 'users',       component: () => import('@/Pages/Staff/Users.vue'),       meta: { title: 'Members' } },
        { path: 'inbox',       component: () => import('@/Pages/Staff/Inbox.vue'),       meta: { title: 'Ask-Librarian Inbox' } },
        { path: 'reports',     component: () => import('@/Pages/Staff/Reports.vue'),     meta: { title: 'Reports & Analytics' } },
        { path: 'settings',    component: () => import('@/Pages/Staff/Settings.vue'),    meta: { title: 'Library Settings' } },
      ],
    },
    {
      path: '/auth',
      children: [
        { path: 'login',   component: () => import('@/Pages/Auth/Login.vue'),           meta: { title: 'Sign In' } },
        { path: 'recover', component: () => import('@/Pages/Auth/PasswordRecovery.vue'), meta: { title: 'Recover Password' } },
      ],
    },
    { path: '/', redirect: '/auth/login' },
  ],
})

export default router
