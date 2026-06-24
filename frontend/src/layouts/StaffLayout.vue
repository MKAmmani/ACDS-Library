<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import LucideIcon from '@/components/LucideIcon.vue'

const route  = useRoute()
const router = useRouter()

interface NavItem {
  section?: string
  id?: string
  label?: string
  icon?: string
  path?: string
  badge?: string
  badgeClass?: string
}

const navItems: NavItem[] = [
  { section: 'Operations' },
  { id: 'dashboard',   label: 'Dashboard',            icon: 'layout-dashboard',    path: '/staff/dashboard' },
  { id: 'circulation', label: 'Circulation Desk',     icon: 'scan-line',           path: '/staff/circulation' },
  { id: 'reservation', label: 'Reservations',         icon: 'bookmark-check',      path: '/staff/reservation', badge: '5',      badgeClass: 'bg-gold' },
  { id: 'overdue',     label: 'Overdue & Fines',      icon: 'alarm-clock',         path: '/staff/overdue',     badge: '3',      badgeClass: 'bg-red' },
  { section: 'Catalog' },
  { id: 'catalog',     label: 'Catalog Manager',      icon: 'book-copy',           path: '/staff/catalog',     badge: '12,480', badgeClass: 'bg-dim' },
  { id: 'acquisition', label: 'Acquisitions',         icon: 'truck',               path: '/staff/acquisition', badge: '8',      badgeClass: 'bg-dim' },
  { section: 'People' },
  { id: 'users',       label: 'Members',              icon: 'users-round',         path: '/staff/users',       badge: '840',    badgeClass: 'bg-dim' },
  { id: 'inbox',       label: 'Ask-Librarian Inbox',  icon: 'message-square-text', path: '/staff/inbox',       badge: '4',      badgeClass: 'bg-red' },
  { section: 'Insights' },
  { id: 'reports',     label: 'Reports',              icon: 'chart-no-axes-column',path: '/staff/reports' },
  { id: 'settings',    label: 'Settings',             icon: 'settings',            path: '/staff/settings' },
]

const pageTitle = computed(() => route.meta.title as string ?? 'Staff Panel')

function isActive(path: string) {
  return route.path === path
}
</script>

<template>
  <div class="shell">

    <!-- ── Sidebar ── -->
    <aside class="sb">
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
        <span class="dot"></span> Librarian Console
      </div>

      <nav class="sb-nav">
        <template v-for="item in navItems" :key="item.id ?? item.section">
          <div v-if="item.section" class="sb-sec">{{ item.section }}</div>
          <div
            v-else
            :class="['sb-i', { active: isActive(item.path!) }]"
            @click="router.push(item.path!)"
          >
            <LucideIcon :name="item.icon!" />
            <span>{{ item.label }}</span>
            <span v-if="item.badge" :class="['bg', item.badgeClass]">{{ item.badge }}</span>
          </div>
        </template>
      </nav>

      <div class="sb-foot">
        <div class="sb-av">GS</div>
        <div>
          <div class="sb-uname">Mallam Garba Sule</div>
          <div class="sb-urole">Chief Librarian</div>
        </div>
        <div class="sb-out" title="Sign out">
          <LucideIcon name="log-out" class="ic-sm" />
        </div>
      </div>
    </aside>

    <!-- ── Main ── -->
    <div class="main">
      <div class="top">
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
        <div class="icbtn" title="Add new title" @click="router.push('/staff/catalog')">
          <LucideIcon name="plus" />
        </div>
      </div>

      <div class="content">
        <RouterView />
      </div>
    </div>

  </div>
</template>
