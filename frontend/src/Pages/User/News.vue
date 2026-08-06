<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'
import NewsModal from '@/components/NewsModal.vue'
import { apiGet } from '@/api/http'

const TONES: Record<string, { bg: string; text: string }> = {
  blue:   { bg: 'bg-[var(--blue-50)]',   text: 'text-[var(--blue)]' },
  sky:    { bg: 'bg-[var(--blue-50)]',   text: 'text-[var(--sky)]' },
  gold:   { bg: 'bg-[var(--gold-50)]',   text: 'text-[var(--amber)]' },
  purple: { bg: 'bg-[var(--purple-50)]', text: 'text-[var(--purple)]' },
  amber:  { bg: 'bg-[var(--amber-50)]',  text: 'text-[var(--amber)]' },
  green:  { bg: 'bg-[var(--green-50)]',  text: 'text-[var(--green)]' },
  red:    { bg: 'bg-[var(--red-50)]',    text: 'text-[var(--red)]' },
}

function fmtDate(d: string | null) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

// ── News ──────────────────────────────────────────────────────────────────────
const newsLoading = ref(true)
const rawNews     = ref<any[]>([])
const news = computed(() => rawNews.value.map(n => ({
  tag: n.tag || 'Notice',
  title: n.title,
  body: n.body || '',
  date: fmtDate(n.published_at),
  icon: n.icon || 'bell',
  tone: n.tone || 'gold',
})))

const newsModalOpen = ref(false)
const activeNews    = ref<(typeof news.value)[number] | null>(null)
function openNewsModal(n: (typeof news.value)[number]) {
  activeNews.value = n
  newsModalOpen.value = true
}

// ── Events ────────────────────────────────────────────────────────────────────
const eventsLoading = ref(true)
const rawEvents     = ref<any[]>([])
const events = computed(() => rawEvents.value.map(e => {
  const d = e.starts_at ? new Date(e.starts_at) : null
  return {
    day:   d ? String(d.getDate()).padStart(2, '0') : '—',
    mon:   d ? d.toLocaleDateString('en-GB', { month: 'short' }) : '',
    title: e.title,
    time:  e.time_label || '',
    place: e.place || '',
  }
}))

async function fetchAll() {
  newsLoading.value   = true
  eventsLoading.value = true
  try {
    const [n, e] = await Promise.all([
      apiGet<any>('/news?limit=100').catch(() => null),
      apiGet<any>('/events?limit=100').catch(() => null),
    ])
    rawNews.value   = n?.data ?? []
    rawEvents.value = e?.data ?? []
  } finally {
    newsLoading.value   = false
    eventsLoading.value = false
  }
}

onMounted(fetchAll)
</script>

<template>
  <!-- Header -->
  <div class="shead">
    <div>
      <h2>News & Events</h2>
      <p>Announcements from the library and every upcoming event at Mambayya House</p>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-[1.6fr_1fr] gap-5 items-start mt-4">
    <!-- News -->
    <section>
      <div v-if="newsLoading" class="flex flex-col gap-3.5">
        <div v-for="i in 4" :key="i" class="flex gap-3.5 bg-white border border-[var(--line)] rounded-[20px] p-4 animate-pulse">
          <div class="w-[46px] h-[46px] rounded-xl bg-[var(--bg)] flex-shrink-0"></div>
          <div class="flex-1"><div class="h-3 bg-[var(--bg)] rounded w-4/5 mb-2"></div><div class="h-2.5 bg-[var(--bg)] rounded w-3/5"></div></div>
        </div>
      </div>
      <div v-else-if="!news.length" class="text-[13px] text-[var(--muted)] py-8 text-center border border-dashed border-[var(--line)] rounded-2xl">
        No news posted yet.
      </div>
      <div v-else class="flex flex-col gap-3.5">
        <div v-for="n in news" :key="n.title" @click="openNewsModal(n)"
             class="flex gap-3.5 bg-white border border-[var(--line)] rounded-[20px] p-4 shadow-[var(--sh1)] hover:shadow-[var(--sh2)] hover:-translate-y-0.5 hover:border-[var(--blue-100)] transition-all cursor-pointer">
          <div class="w-[46px] h-[46px] rounded-xl flex items-center justify-center flex-shrink-0" :class="TONES[n.tone]!.bg">
            <LucideIcon :name="n.icon" :size="21" :class="TONES[n.tone]!.text" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-[10px] font-bold tracking-[.07em] uppercase text-[var(--amber)]">{{ n.tag }}</div>
            <div class="font-[var(--display)] text-[14.5px] font-bold text-[var(--navy)] leading-snug mt-1">{{ n.title }}</div>
            <p class="text-xs text-[var(--muted)] mt-1.5 leading-relaxed line-clamp-2">{{ n.body }}</p>
            <div class="mt-2.5 text-[11px] text-[var(--faint)] flex items-center gap-1"><LucideIcon name="calendar" :size="12" /> {{ n.date }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Events -->
    <section id="events" class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-[var(--sh1)]">
      <div class="px-4 py-3.5 bg-[var(--sb)] text-white font-[var(--display)] text-[13px] font-bold flex items-center gap-2">
        <LucideIcon name="calendar" :size="16" class="text-[var(--gold)]" /> Upcoming events
      </div>
      <div v-if="eventsLoading" class="p-3 flex flex-col gap-2.5">
        <div v-for="i in 4" :key="i" class="h-[46px] bg-[var(--bg)] rounded-lg animate-pulse"></div>
      </div>
      <div v-else-if="!events.length" class="p-4 text-[12.5px] text-[var(--faint)] text-center">
        No upcoming events.
      </div>
      <div v-else class="p-1.5">
        <div v-for="ev in events" :key="ev.title" class="flex gap-3 px-2.5 py-2.5 rounded-lg hover:bg-[var(--blue-50)] transition-colors border-t border-dashed border-[var(--line-soft)] first:border-none">
          <div class="w-[46px] h-[46px] rounded-[10px] bg-[var(--blue-50)] flex flex-col items-center justify-center flex-shrink-0">
            <span class="font-[var(--display)] text-base font-extrabold text-[var(--blue)] leading-none">{{ ev.day }}</span>
            <span class="text-[9px] font-bold tracking-wide uppercase text-[var(--blue)] mt-0.5">{{ ev.mon }}</span>
          </div>
          <div class="min-w-0">
            <div class="text-[12.5px] font-bold text-[var(--navy)] leading-snug">{{ ev.title }}</div>
            <div class="text-[11px] text-[var(--muted)] mt-1 flex flex-col gap-0.5">
              <span class="flex items-center gap-1"><LucideIcon name="clock" :size="11" class="text-[var(--faint)]" /> {{ ev.time }}</span>
              <span class="flex items-center gap-1"><LucideIcon name="map-pin" :size="11" class="text-[var(--faint)]" /> {{ ev.place }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <NewsModal :open="newsModalOpen" :news="activeNews" @close="newsModalOpen = false" />
</template>
