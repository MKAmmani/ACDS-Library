import { createRouter, createWebHistory } from 'vue-router'
import { onUnauthorized } from '@/api/http'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // ── Staff panel ───────────────────────────────────────────────────────
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
        { path: 'repository',  component: () => import('@/Pages/Staff/Repository.vue'),  meta: { title: 'Institutional Repository' } },
        { path: 'media',       component: () => import('@/Pages/Staff/Media.vue'),       meta: { title: 'Media Library' } },
        { path: 'acquisition', component: () => import('@/Pages/Staff/Acquisition.vue'), meta: { title: 'Acquisitions' } },
        { path: 'users',       component: () => import('@/Pages/Staff/Users.vue'),       meta: { title: 'Members' } },
        { path: 'inbox',       component: () => import('@/Pages/Staff/Inbox.vue'),       meta: { title: 'Ask-Librarian Inbox' } },
        { path: 'reports',     component: () => import('@/Pages/Staff/Reports.vue'),     meta: { title: 'Reports & Analytics' } },
        { path: 'profile',     component: () => import('@/Pages/Staff/Profile.vue'),     meta: { title: 'My Profile' } },
      ],
    },

    // ── Admin panel ───────────────────────────────────────────────────────
    {
      path: '/admin',
      component: () => import('@/layouts/AdminLayout.vue'),
      redirect: '/admin/dashboard',
      children: [
        { path: 'dashboard',   component: () => import('@/Pages/Admin/Dashboard.vue'),   meta: { title: 'Dashboard' } },
        { path: 'circulation', component: () => import('@/Pages/Admin/Circulation.vue'), meta: { title: 'Circulation Desk' } },
        { path: 'reservation', component: () => import('@/Pages/Admin/Reservation.vue'), meta: { title: 'Reservations Queue' } },
        { path: 'overdue',     component: () => import('@/Pages/Admin/Overdue.vue'),     meta: { title: 'Overdue & Fines' } },
        { path: 'catalog',     component: () => import('@/Pages/Admin/Catalog.vue'),     meta: { title: 'Catalog Manager' } },
        { path: 'repository',  component: () => import('@/Pages/Admin/Repository.vue'),  meta: { title: 'Institutional Repository' } },
        { path: 'media',       component: () => import('@/Pages/Admin/Media.vue'),       meta: { title: 'Media Library' } },
        { path: 'acquisition', component: () => import('@/Pages/Admin/Acquisition.vue'), meta: { title: 'Acquisitions' } },
        { path: 'accounts',    component: () => import('@/Pages/Admin/StaffsView.vue'),  meta: { title: 'Staff Accounts' } },
        { path: 'inbox',       component: () => import('@/Pages/Admin/Inbox.vue'),       meta: { title: 'Ask-Librarian Inbox' } },
        { path: 'reports',     component: () => import('@/Pages/Admin/Reports.vue'),     meta: { title: 'Reports & Analytics' } },
        { path: 'settings',    component: () => import('@/Pages/Admin/Settings.vue'),    meta: { title: 'Library Settings' } },
        { path: 'website',     component: () => import('@/Pages/Admin/Website.vue'),     meta: { title: 'Website Content' } },
        { path: 'profile',     component: () => import('@/Pages/Admin/Profile.vue'),     meta: { title: 'My Profile' } },
      ],
    },

    // ── Member portal ────────────────────────────────────────────────────
    {
      path: '/user',
      component: () => import('@/layouts/UserLayout.vue'),
      redirect: '/user/catalog',
      children: [
        { path: 'my-account', component: () => import('@/Pages/User/MyAccount.vue'), meta: { title: 'My Account' } },
        { path: 'catalog',    component: () => import('@/Pages/User/Catalog.vue'),   meta: { title: 'Library Catalog' } },
        { path: 'e-library',  component: () => import('@/Pages/User/ELibrary.vue'),  meta: { title: 'Institutional Repository' } },
        { path: 'media',      component: () => import('@/Pages/User/Media.vue'),     meta: { title: 'Media Library' } },
        { path: 'journals',   component: () => import('@/Pages/User/Journals.vue'),  meta: { title: 'Journals' } },
        { path: 'archives',   component: () => import('@/Pages/User/Archives.vue'),  meta: { title: 'Archives' } },
        { path: 'news',       component: () => import('@/Pages/User/News.vue'),      meta: { title: 'News & Events' } },
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

    // ── Landing page ─────────────────────────────────────────────────────
    { path: '/', component: () => import('@/Pages/Welcome.vue'), meta: { title: 'Welcome' } },
  ],
})

// Member-portal pages that still require a logged-in account
const PROTECTED_USER_PATHS = ['/user/profile', '/user/my-account']

router.beforeEach((to) => {
  const token = localStorage.getItem('auth_token')
  const role  = JSON.parse(localStorage.getItem('auth_user') ?? 'null')?.role ?? null

  const wantsStaffPanel   = to.path.startsWith('/staff')
  const wantsAdminPanel   = to.path.startsWith('/admin')
  const wantsAuth         = to.path.startsWith('/auth')
  const wantsProtectedUser = PROTECTED_USER_PATHS.includes(to.path)

  // Staff/admin panel and the private member pages (profile, my-account) require login.
  // All other /user pages (catalog, repository, journals, archives, help) are public.
  if ((wantsStaffPanel || wantsAdminPanel || wantsProtectedUser) && !token) {
    return { path: '/auth/login', query: { redirect: to.path } }
  }

  // Logged-in users leave the auth pages, routed to their own panel
  if (wantsAuth && token) {
    if (role === 'admin') return '/admin/dashboard'
    if (role === 'staff') return '/staff/dashboard'
    return '/user/catalog'
  }

  // Each role has its own fully separate panel — no cross-access
  if (wantsStaffPanel && role !== 'staff') {
    return role === 'admin' ? '/admin/dashboard' : '/user/catalog'
  }
  if (wantsAdminPanel && role !== 'admin') {
    return role === 'staff' ? '/staff/dashboard' : '/user/catalog'
  }
})

// When any API call returns 401, the token is stale — clear session and go to login.
// Guests (no token) browsing public pages may hit auth-only endpoints; ignore those
// so they aren't bounced off the public catalog/repository.
onUnauthorized(() => {
  const auth = useAuthStore()
  if (!auth.token) return
  auth.logout()
  router.push('/auth/login')
})

export default router
