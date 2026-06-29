import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // ── Staff / Admin panel ──────────────────────────────────────────────
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
        { path: 'repository',  component: () => import('@/Pages/Staff/Repository.vue'),  meta: { title: 'Digital Repository' } },
        { path: 'acquisition', component: () => import('@/Pages/Staff/Acquisition.vue'), meta: { title: 'Acquisitions' } },
        { path: 'users',       component: () => import('@/Pages/Staff/Users.vue'),       meta: { title: 'Members' } },
        { path: 'inbox',       component: () => import('@/Pages/Staff/Inbox.vue'),       meta: { title: 'Ask-Librarian Inbox' } },
        { path: 'reports',     component: () => import('@/Pages/Staff/Reports.vue'),     meta: { title: 'Reports & Analytics' } },
        { path: 'settings',    component: () => import('@/Pages/Staff/Settings.vue'),    meta: { title: 'Library Settings' } },
      ],
    },

    // ── Member portal ────────────────────────────────────────────────────
    {
      path: '/user',
      component: () => import('@/layouts/UserLayout.vue'),
      redirect: '/user/my-account',
      children: [
        { path: 'my-account', component: () => import('@/Pages/User/MyAccount.vue'), meta: { title: 'My Account' } },
        { path: 'catalog',    component: () => import('@/Pages/User/Catalog.vue'),   meta: { title: 'Library Catalog' } },
        { path: 'e-library',  component: () => import('@/Pages/User/ELibrary.vue'),  meta: { title: 'E-Library' } },
        { path: 'journals',   component: () => import('@/Pages/User/Journals.vue'),  meta: { title: 'Journals' } },
        { path: 'archives',   component: () => import('@/Pages/User/Archives.vue'),  meta: { title: 'Archives' } },
        { path: 'help',       component: () => import('@/Pages/User/Help.vue'),      meta: { title: 'Ask-Librarian' } },
        { path: 'profile',    component: () => import('@/Pages/User/Profile.vue'),   meta: { title: 'Profile' } },
      ],
    },

    // ── Auth ─────────────────────────────────────────────────────────────
    {
      path: '/auth',
      children: [
        { path: 'login',   component: () => import('@/Pages/Auth/Login.vue'),            meta: { title: 'Sign In' } },
        { path: 'recover', component: () => import('@/Pages/Auth/PasswordRecovery.vue'), meta: { title: 'Recover Password' } },
      ],
    },
    { path: '/', redirect: '/auth/login' },
  ],
})

router.beforeEach((to) => {
  const token = localStorage.getItem('auth_token')
  const role  = JSON.parse(localStorage.getItem('auth_user') ?? 'null')?.role ?? null

  const wantsStaff = to.path.startsWith('/staff')
  const wantsUser  = to.path.startsWith('/user')
  const wantsAuth  = to.path.startsWith('/auth')

  // Must be logged in to access any panel
  if ((wantsStaff || wantsUser) && !token) return '/auth/login'

  // Logged-in users leave the auth pages
  if (wantsAuth && token) {
    return role === 'user' ? '/user/my-account' : '/staff/dashboard'
  }

  // Regular members cannot access the staff panel
  if (wantsStaff && token && role === 'user') return '/user/my-account'
})

export default router
