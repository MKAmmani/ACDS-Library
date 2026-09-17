<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import LucideIcon from '@/components/LucideIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet } from '@/api/http'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

interface NavItem {
  section?: string
  id?: string
  label?: string
  icon?: string
  path?: string
  badge?: string | number
  badgeClass?: string
}

const overdueCount      = ref(0)
const reservationCount  = ref(0)
const inboxUnread       = ref(0)
const sidebarOpen       = ref(false)

function navigate(path: string) {
  router.push(path)
  sidebarOpen.value = false
}

const userInitials = computed(() => {
  const name = auth.user?.name ?? 'GS'
  return name.split(' ').map((w: string) => w[0]).join('').toUpperCase().slice(0, 2)
})

const navItems = computed<NavItem[]>(() => [
  { section: 'Operations' },
  { id: 'dashboard',   label: 'Dashboard',           icon: 'layout-dashboard',     path: '/admin/dashboard' },
  { id: 'circulation', label: 'Circulation Desk',    icon: 'scan-line',            path: '/admin/circulation' },
  { id: 'reservation', label: 'Reservations',        icon: 'bookmark-check',       path: '/admin/reservation', badge: reservationCount.value || undefined, badgeClass: 'bg-gold' },
  { id: 'overdue',     label: 'Overdue & Fines',     icon: 'alarm-clock',          path: '/admin/overdue',     badge: overdueCount.value || undefined,     badgeClass: 'bg-red' },
  { section: 'Catalog' },
  { id: 'catalog',     label: 'Catalog Manager',     icon: 'book-copy',            path: '/admin/catalog' },
  { id: 'repository',  label: 'Institutional Repository',  icon: 'upload',        path: '/admin/repository' },
  { id: 'journals',    label: 'Journals & Thesis',   icon: 'newspaper',            path: '/admin/journals' },
  { id: 'e-resources', label: 'E-Resources',         icon: 'link',                 path: '/admin/e-resources' },
  { id: 'media',       label: 'Media Library',       icon: 'video',                path: '/admin/media' },
  { id: 'acquisition', label: 'Acquisitions',        icon: 'truck',                path: '/admin/acquisition' },
  { section: 'People' },
  { id: 'accounts',    label: 'Manage Accounts',     icon: 'shield-check',         path: '/admin/accounts' },
  { id: 'inbox',       label: 'Inbox',               icon: 'message-square-text',  path: '/admin/inbox',       badge: inboxUnread.value || undefined, badgeClass: 'bg-red' },
  { section: 'Insights' },
  { id: 'reports',     label: 'Reports',             icon: 'chart-no-axes-column', path: '/admin/reports' },
  { id: 'website',     label: 'Website Content',     icon: 'globe',                path: '/admin/website' },
  { id: 'settings',    label: 'Settings',            icon: 'settings',             path: '/admin/settings' },
])

const pageTitle = computed(() => route.meta.title as string ?? 'Admin Panel')

function isActive(path: string) {
  return route.path === path
}

onMounted(async () => {
  try {
    const ov = await apiGet<any>('/admin/reports/overview', auth.token ?? undefined)
    overdueCount.value     = ov.loans?.overdue ?? 0
    reservationCount.value = ov.reservations?.pending ?? 0
    inboxUnread.value      = ov.inbox?.unread ?? 0
  } catch {}
})

async function handleLogout() {
  await auth.logout()
  router.push('/auth/login')
}
</script>

<template>
  <div class="shell">

    <!-- Mobile scrim -->
    <div :class="['sb-scrim', { open: sidebarOpen }]" @click="sidebarOpen = false"></div>

    <!-- ── Sidebar ── -->
    <aside :class="['sb', { open: sidebarOpen }]">
      <div class="sb-brand">
        <div class="sb-logo">
          <LucideIcon name="library" :size="20" />
        </div>
        <div>
          <div class="sb-bname">Mambayya House</div>
          <div class="sb-bsub">Library Back-Office</div>
        </div>
      </div>

      <div class="sb-role">
        <span class="dot"></span> Admin Console
      </div>

      <nav class="sb-nav">
        <template v-for="item in navItems" :key="item.id ?? item.section">
          <div v-if="item.section" class="sb-sec">{{ item.section }}</div>
          <div
            v-else
            :class="['sb-i', { active: isActive(item.path!) }]"
            @click="navigate(item.path!)"
          >
            <LucideIcon :name="item.icon!" />
            <span>{{ item.label }}</span>
            <span v-if="item.badge" :class="['bg', item.badgeClass]">{{ item.badge }}</span>
          </div>
        </template>
      </nav>

      <div class="sb-foot" style="cursor:pointer" title="My Profile" @click="navigate('/admin/profile')">
        <div class="sb-av">{{ userInitials }}</div>
        <div>
          <div class="sb-uname">{{ auth.user?.name ?? 'Librarian' }}</div>
          <div class="sb-urole">Chief Librarian</div>
        </div>
        <div class="sb-out" title="Sign out" @click.stop="handleLogout">
          <LucideIcon name="log-out" class="ic-sm" />
        </div>
      </div>
    </aside>

    <!-- ── Main ── -->
    <div class="main">
      <div class="top">
        <!-- Hamburger — visible only on mobile -->
        <button class="mob-menu-btn" @click="sidebarOpen = true">
          <LucideIcon name="menu" :size="20" />
        </button>
        <div>
          <div class="top-title">{{ pageTitle }}</div>
          <div class="top-crumb">Library Back-Office › <b>{{ pageTitle }}</b></div>
        </div>
        <div class="top-search">
          <LucideIcon name="search" class="ic-sm" />
          <input placeholder="Search catalog, members, transactions…" />
        </div>
        <div class="icbtn" title="Notifications">
          <LucideIcon name="bell" />
          <span class="nd"></span>
        </div>
        <div class="icbtn" title="Add new title" @click="router.push('/admin/catalog')">
          <LucideIcon name="plus" />
        </div>
      </div>

      <div class="content">
        <RouterView />
      </div>
    </div>

  </div>
</template>
