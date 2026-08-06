<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { apiGet } from '@/api/http'
import LucideIcon from '@/components/LucideIcon.vue'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

const profile     = ref<any>(null)
const helpUnread  = ref(0)
const sidebarOpen = ref(false)

function navigate(path: string) {
  router.push(path)
  sidebarOpen.value = false
}

const initials = computed(() => {
  const name = profile.value?.name ?? auth.user?.name ?? 'MB'
  return name.split(' ').map((w: string) => w[0]).join('').toUpperCase().slice(0, 2)
})

const memberLabel = computed(() => {
  const t = profile.value?.membership_type ?? ''
  return t ? t.charAt(0).toUpperCase() + t.slice(1) + ' Member' : 'Library Member'
})

const navItems = computed(() => [
  { section: 'Library' },
  { id: 'catalog',    label: 'Browse Library',    icon: 'book-copy',          path: '/user/catalog' },
  { id: 'e-library',  label: 'Institutional Repository', icon: 'monitor',       path: '/user/e-library' },
  { id: 'media',      label: 'Media Library',      icon: 'video',              path: '/user/media' },
  { id: 'journals',   label: 'Journals',           icon: 'newspaper',          path: '/user/journals' },
  { id: 'archives',   label: 'Archives',           icon: 'archive',            path: '/user/archives' },
  { id: 'news',       label: 'News & Events',      icon: 'bell',               path: '/user/news' },
  { section: 'My Space' },
  { id: 'my-account', label: 'My Account',         icon: 'layout-dashboard',   path: '/user/my-account' },
  { id: 'help',       label: 'Ask-Librarian',      icon: 'message-circle',     path: '/user/help',
    badge: helpUnread.value || undefined, badgeClass: 'bg-red' },
  { id: 'profile',    label: 'Profile & Settings', icon: 'circle-user-round',  path: '/user/profile' },
])

const pageTitle = computed(() => route.meta.title as string ?? 'Member Portal')

function isActive(path: string) { return route.path === path }

onMounted(async () => {
  if (!auth.token) return   // guests browse without loading account data
  try {
    profile.value = await apiGet<any>('/auth/me', auth.token ?? undefined)
    // Count threads where staff has replied (unread for user = last_sender_role is staff/admin)
    const res = await apiGet<any>('/inbox/threads', auth.token ?? undefined)
    const threads = res.data ?? []
    helpUnread.value = threads.filter((t: any) =>
      t.status === 'open' && (t.last_sender_role === 'staff' || t.last_sender_role === 'admin')
    ).length
  } catch {}
})

function goSignIn() {
  router.push({ path: '/auth/login', query: { redirect: route.path } })
}

async function handleLogout() {
  await auth.logout()
  router.push('/auth/login')
}
</script>

<template>
  <div class="shell user-portal">

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
          <div class="sb-bsub">Member Portal</div>
        </div>
      </div>

      <div v-if="auth.isLoggedIn" class="sb-role" style="background:rgba(14,159,110,.13);border-color:rgba(14,159,110,.25);color:var(--green)">
        <span class="dot" style="background:var(--green)"></span> Member Access
      </div>
      <div v-else class="sb-role" style="background:rgba(23,99,201,.1);border-color:rgba(23,99,201,.22);color:var(--blue)">
        <span class="dot" style="background:var(--blue)"></span> Browsing as Guest
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

      <div class="sb-foot">
        <template v-if="auth.isLoggedIn">
          <div class="sb-av" style="background:linear-gradient(140deg,var(--green-600),#0dd891)">{{ initials }}</div>
          <div style="min-width:0">
            <div class="sb-uname">{{ profile?.name ?? auth.user?.name ?? 'Member' }}</div>
            <div class="sb-urole">{{ memberLabel }}</div>
          </div>
          <div class="sb-out" title="Sign out" @click="handleLogout">
            <LucideIcon name="log-out" class="ic-sm" />
          </div>
        </template>
        <button v-else class="btn btn-primary btn-block" style="justify-content:center" @click="goSignIn">
          <LucideIcon name="log-in" class="ic-sm" /> Sign in
        </button>
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
          <div class="top-crumb">
            Member Portal ›
            <span v-if="profile?.member_number" class="mono" style="font-size:11px;color:var(--muted)">
              {{ profile.member_number }}
            </span>
            <b v-else>{{ pageTitle }}</b>
          </div>
        </div>
        <div class="top-search">
          <LucideIcon name="search" class="ic-sm" />
          <input placeholder="Search catalog, journals, e-books…" @keydown.enter="router.push('/user/catalog')" />
        </div>
        <div class="icbtn" title="My notifications" @click="router.push('/user/help')">
          <LucideIcon name="bell" />
          <span v-if="helpUnread" class="nd"></span>
        </div>
        <div class="icbtn" title="My account" @click="router.push('/user/my-account')">
          <LucideIcon name="user" />
        </div>
      </div>

      <div class="content">
        <RouterView />
      </div>
    </div>

  </div>
</template>

<style scoped>
/* Green active stripe for user portal to distinguish from staff panel */
:deep(.sb-i.active) { background: rgba(14,159,110,.25); }
:deep(.sb-i.active::before) { background: var(--green) !important; }
</style>
