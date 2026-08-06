<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import LucideIcon from '@/components/LucideIcon.vue'
import NewsModal from '@/components/NewsModal.vue'
import { apiGet } from '@/api/http'

const router = useRouter()

// ── Design-system tone tokens — literal Tailwind arbitrary-value classes so
// the JIT scanner can pick them up (dynamic string concatenation would not) ──
const TONES: Record<string, { bg: string; text: string; hoverBg: string }> = {
  blue:   { bg: 'bg-[var(--blue-50)]',   text: 'text-[var(--blue)]',   hoverBg: 'group-hover:bg-[var(--blue)]' },
  sky:    { bg: 'bg-[var(--blue-50)]',   text: 'text-[var(--sky)]',    hoverBg: 'group-hover:bg-[var(--sky)]' },
  gold:   { bg: 'bg-[var(--gold-50)]',   text: 'text-[var(--amber)]',  hoverBg: 'group-hover:bg-[var(--amber)]' },
  purple: { bg: 'bg-[var(--purple-50)]', text: 'text-[var(--purple)]', hoverBg: 'group-hover:bg-[var(--purple)]' },
  amber:  { bg: 'bg-[var(--amber-50)]',  text: 'text-[var(--amber)]',  hoverBg: 'group-hover:bg-[var(--amber)]' },
  green:  { bg: 'bg-[var(--green-50)]',  text: 'text-[var(--green)]',  hoverBg: 'group-hover:bg-[var(--green)]' },
  red:    { bg: 'bg-[var(--red-50)]',    text: 'text-[var(--red)]',    hoverBg: 'group-hover:bg-[var(--red)]' },
}

// ── Mobile nav ──
const mobileMenuOpen = ref(false)
const navLinks = [
  { label: 'Home',           href: '#top' },
  { label: 'About',          href: '#about' },
  { label: 'Library',        href: '#top', active: true },
  { label: 'Collections',    href: '#collections' },
  { label: 'Multimedia',     href: '#videos' },
  { label: 'News & Events',  href: '#news' },
  { label: 'Services',       href: '#services' },
  { label: 'Contact',        href: '#contact' },
]

// ── Hero search ──
const searchScopes = [
  { key: 'all',      label: 'Everything', icon: 'layers' },
  { key: 'books',    label: 'Books',      icon: 'book-open' },
  { key: 'journals', label: 'Journals',   icon: 'newspaper' },
  { key: 'video',    label: 'Video',      icon: 'video' },
  { key: 'theses',   label: 'Theses',     icon: 'graduation-cap' },
  { key: 'archives', label: 'Archives',   icon: 'archive' },
]
const activeScope  = ref('all')
const searchQuery  = ref('')
const searchPlaceholder = computed(() => {
  const scope = searchScopes.find(s => s.key === activeScope.value)
  return scope && scope.key !== 'all'
    ? `Search ${scope.label.toLowerCase()}…`
    : 'Search by title, author, subject, ISBN or keyword…'
})
// Where each search scope actually lives — mirrors the sidebar rail's Find
// group so "Journals" / "Video" / "Archives" land on their real home instead
// of always dumping the visitor on the physical catalogue.
const SCOPE_ROUTES: Record<string, string> = {
  all:      '/user/catalog',
  books:    '/user/catalog',
  journals: '/user/journals',
  video:    '/user/media',
  theses:   '/user/catalog',
  archives: '/user/archives',
}
function runSearch() {
  const q = searchQuery.value.trim()
  const path = SCOPE_ROUTES[activeScope.value] ?? '/user/catalog'
  router.push({ path, query: q ? { search: q } : {} })
}

// ── Stat band — live counts pulled from the catalogue, repository & video library ──
const statsLoading = ref(true)
const counts = ref({ books: 0, repository: 0, journals: 0, theses: 0, videos: 0 })

function fmtCount(n: number) {
  return n.toLocaleString()
}

const stats = computed(() => [
  { n: fmtCount(counts.value.books),      label: 'Total Volumes',         icon: 'book-copy',      tone: 'blue',   pill: 'Catalogue growing', pillIcon: 'trending-up', good: true },
  { n: fmtCount(counts.value.repository), label: 'eBooks & e-resources',  icon: 'laptop',         tone: 'sky',    pill: 'Full-text access',  pillIcon: 'download',    good: false },
  { n: fmtCount(counts.value.journals),   label: 'Journal titles',        icon: 'newspaper',      tone: 'gold',   pill: 'Peer-reviewed',     pillIcon: 'layers',      good: false },
  { n: fmtCount(counts.value.videos),     label: 'Recorded lectures',     icon: 'video',           tone: 'purple', pill: 'Streaming available', pillIcon: 'trending-up', good: true },
  { n: fmtCount(counts.value.theses),     label: 'Theses & dissertations', icon: 'graduation-cap', tone: 'amber',  pill: 'Full text',         pillIcon: 'archive',     good: false },
])

async function fetchStats() {
  statsLoading.value = true
  try {
    const [books, repo, journals, theses, videos] = await Promise.all([
      apiGet<any>('/books?archived=0&per_page=1').catch(() => null),
      apiGet<any>('/repository/stats').catch(() => null),
      apiGet<any>('/repository?format=Journal&per_page=1').catch(() => null),
      apiGet<any>('/repository?format=Thesis&per_page=1').catch(() => null),
      apiGet<any>('/media?limit=100').catch(() => null),
    ])
    counts.value = {
      books:      books?.total ?? 0,
      repository: repo?.total ?? 0,
      journals:   journals?.total ?? 0,
      theses:     theses?.total ?? 0,
      videos:     videos?.data?.length ?? 0,
    }
  } finally {
    statsLoading.value = false
  }
}

// ── Sidebar rail ──
const activeRail = ref('library-home')
type RailItem = { key: string; label: string; icon: string; to: string; tag?: string }
const railGroups: { heading: string | null; items: RailItem[] }[] = [
  {
    heading: null,
    items: [
      { key: 'library-home', label: 'Library Home',      icon: 'home', to: '#top' },
      { key: 'about',        label: 'About the Library',  icon: 'info', to: '#about' },
    ],
  },
  {
    heading: 'Find',
    items: [
      { key: 'opac',       label: 'OPAC — Search Catalogue',   icon: 'search',       to: '/user/catalog' },
      { key: 'coll',       label: 'Collections',                icon: 'layers',       to: '#collections' },
      { key: 'new',        label: 'New Arrivals',                icon: 'sparkles',     to: '#arrivals', tag: '32' },
      { key: 'elib',       label: 'E-Library & E-Resources',     icon: 'laptop',       to: '/user/e-library' },
      { key: 'journals',   label: 'Journals & Databases',        icon: 'newspaper',    to: '/user/journals' },
      { key: 'theses',     label: 'Theses & Dissertations',      icon: 'graduation-cap', to: '/user/catalog' },
      { key: 'periodicals',label: 'Newspapers & Periodicals',    icon: 'scroll-text',  to: '/user/journals' },
      { key: 'news',       label: 'News & Events',                icon: 'bell',         to: '#news', tag: 'New' },
    ],
  },
  {
    heading: 'Multimedia',
    items: [
      { key: 'vids',    label: 'Video Library',              icon: 'video',   to: '#videos', tag: 'New' },
      { key: 'audio',   label: 'Audio & Oral Histories',     icon: 'mic',     to: '#videos' },
      { key: 'photos',  label: 'Photographic Archive',       icon: 'image',   to: '/user/archives' },
      { key: 'archive', label: 'Archives & Special Collections', icon: 'archive', to: '/user/archives' },
    ],
  },
  {
    heading: 'Services',
    items: [
      { key: 'ask',      label: 'Ask a Librarian',            icon: 'message-square-text', to: '/user/help' },
      { key: 'join',     label: 'Membership & Registration',  icon: 'user-plus',           to: '/auth/login' },
      { key: 'borrow',   label: 'Borrow, Renew & Reserve',    icon: 'refresh-cw',          to: '/auth/login' },
      { key: 'rooms',    label: 'Reading Rooms & Facilities', icon: 'building-2',          to: '#services' },
      { key: 'research', label: 'Research & Citation Support',icon: 'microscope',          to: '#services' },
      { key: 'print',    label: 'Printing & Reprographics',   icon: 'printer',             to: '#services' },
    ],
  },
  {
    heading: 'Help',
    items: [
      { key: 'guide',   label: 'User Guide',           icon: 'book-marked', to: '#contact' },
      { key: 'policies',label: 'Library Policies',     icon: 'file-text',   to: '#contact' },
      { key: 'faqs',    label: 'FAQs',                 icon: 'circle-help', to: '#contact' },
      { key: 'contact', label: 'Contact the Library',  icon: 'mail',        to: '#contact' },
    ],
  },
]

// ── Quick access ──
const quickAccess = [
  { title: 'OPAC Catalogue',      desc: 'Search every title the library holds, check availability and reserve a copy online.',      icon: 'search',               tone: 'blue',   to: '/user/catalog', cta: 'Open catalogue' },
  { title: 'E-Library',            desc: 'Read eBooks, full-text databases and downloadable policy briefs from any device.',          icon: 'laptop',               tone: 'sky',    to: '/user/e-library', cta: 'Browse e-library' },
  { title: 'Video Library',        desc: 'Recorded lectures, documentaries, conference sessions and oral history interviews.',        icon: 'video',                tone: 'purple', to: '#videos', cta: 'Watch now' },
  { title: 'Aminu Kano Archive',   desc: "Papers, photographs and artefacts documenting Mallam Aminu Kano's political life.",          icon: 'archive',              tone: 'gold',   to: '/user/archives', cta: 'Explore archive' },
  { title: 'Theses & Research',    desc: 'Postgraduate theses, dissertations and working papers on governance and democracy.',        icon: 'graduation-cap',       tone: 'amber',  to: '/user/catalog', cta: 'Browse research' },
  { title: 'Ask a Librarian',      desc: 'Stuck on a search or a citation? Send a question and a librarian will reply.',               icon: 'message-square-text',  tone: 'green',  to: '/user/help', cta: 'Ask a question' },
]

// ── New arrivals — latest 5 catalogued titles ──
const arrivalsLoading = ref(true)
const arrivals = ref<any[]>([])
const coverPalette = ['cv-navy', 'cv-burgundy', 'cv-charcoal', 'cv-slate', 'cv-forest', 'cv-ochre', 'cv-rust']

function arrivalCover(book: any) { return book.cover_treatment || coverPalette[book.id % coverPalette.length] }

function arrivalStatus(book: any): 'avail' | 'loan' | 'new' {
  const addedRecently = book.created_at && (Date.now() - new Date(book.created_at).getTime()) < 30 * 24 * 60 * 60 * 1000
  if (addedRecently) return 'new'
  return (book.available_copies ?? 0) > 0 ? 'avail' : 'loan'
}

const statusChip: Record<string, { label: string; class: string }> = {
  avail: { label: 'Available', class: 'bg-[var(--green-50)] text-[var(--green-600)]' },
  loan:  { label: 'On loan',   class: 'bg-[var(--red-50)] text-[var(--red-600)]' },
  new:   { label: 'New',       class: 'bg-[var(--gold-50)] text-[var(--amber)]' },
}

async function fetchArrivals() {
  arrivalsLoading.value = true
  try {
    const res = await apiGet<any>('/books?archived=0&sort=newest&per_page=5')
    arrivals.value = res?.data ?? []
  } catch {
    arrivals.value = []
  } finally {
    arrivalsLoading.value = false
  }
}

// ── Video & multimedia — sourced from the video library ──
const videosLoading = ref(true)
const rawVideos = ref<any[]>([])

function fmtDate(d: string | null) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

const videos = computed(() => rawVideos.value.slice(0, 4).map(v => ({
  id: v.id,
  title: v.title,
  kind: v.kind || 'Video',
  duration: v.duration_label || '',
  date: fmtDate(v.published_at),
  views: `${fmtCount(v.views ?? 0)} views`,
  tag: v.tag || 'Library',
  thumb: v.thumbnail_display_url || v.thumbnail_url || null,
  isAudio: !!v.is_audio,
})))

// Send the visitor to the full media library with this item pre-selected —
// Media.vue reads the ?id= query on load and opens it straight in the player.
function openVideo(v: { id: number }) {
  router.push({ path: '/user/media', query: { id: v.id } })
}

// Multimedia strip — tallied by the "kind" each catalogued video was given
const multimediaIcon: Record<string, string> = {
  'Public lecture': 'video', 'Documentary': 'film', 'Training': 'graduation-cap',
  'Oral history': 'mic', 'Interview': 'mic',
}
const multimedia = computed(() => {
  const byKind = new Map<string, number>()
  for (const v of rawVideos.value) {
    const k = v.kind || 'Video'
    byKind.set(k, (byKind.get(k) ?? 0) + 1)
  }
  return [...byKind.entries()].map(([label, count]) => ({
    label, count: `${count} video${count === 1 ? '' : 's'}`, icon: multimediaIcon[label] ?? 'video',
  }))
})

async function fetchVideos() {
  videosLoading.value = true
  try {
    const res = await apiGet<any>('/media?limit=50')
    rawVideos.value = res?.data ?? []
  } catch {
    rawVideos.value = []
  } finally {
    videosLoading.value = false
  }
}

// ── Featured collections — built from the subjects actually catalogued ──
const collectionsLoading = ref(true)
const collectionIcons = ['landmark', 'bookmark', 'scroll-text', 'gavel', 'users-round', 'book-marked']
const collectionTones = ['blue', 'gold', 'amber', 'sky', 'purple', 'red']
const collections = ref<{ name: string; count: string; icon: string; tone: string; search: string }[]>([])

async function fetchCollections() {
  collectionsLoading.value = true
  try {
    const f = await apiGet<any>('/books/facets')
    const subjects = (f?.subjects ?? []).slice(0, 6)
    collections.value = subjects.map((s: any, i: number) => ({
      name: s.name,
      count: `${fmtCount(s.count)} title${s.count === 1 ? '' : 's'}`,
      icon: collectionIcons[i % collectionIcons.length],
      tone: collectionTones[i % collectionTones.length],
      search: s.name,
    }))
  } catch {
    collections.value = []
  } finally {
    collectionsLoading.value = false
  }
}

// ── News & events — from the news_posts and events tables ──
const newsLoading = ref(true)
const rawNews = ref<any[]>([])
const newsList = computed(() => rawNews.value.slice(0, 3).map(n => ({
  tag: n.tag || 'Notice',
  title: n.title,
  body: n.body || '',
  date: fmtDate(n.published_at),
  icon: n.icon || 'bell',
  tone: n.tone || 'gold',
})))

// Clicking a news card opens the full write-up in a modal — "View all news &
// events" is the one that actually navigates to the /user/news listing.
const newsModalOpen = ref(false)
const activeNews    = ref<(typeof newsList.value)[number] | null>(null)
function openNewsModal(n: (typeof newsList.value)[number]) {
  activeNews.value = n
  newsModalOpen.value = true
}

const eventsLoading = ref(true)
const rawEvents = ref<any[]>([])
const events = computed(() => rawEvents.value.slice(0, 4).map(e => {
  const d = e.starts_at ? new Date(e.starts_at) : null
  return {
    day:   d ? String(d.getDate()).padStart(2, '0') : '—',
    mon:   d ? d.toLocaleDateString('en-GB', { month: 'short' }) : '',
    title: e.title,
    time:  e.time_label || '',
    place: e.place || '',
  }
}))

async function fetchNewsAndEvents() {
  newsLoading.value = true
  eventsLoading.value = true
  try {
    const [news, ev] = await Promise.all([
      apiGet<any>('/news?limit=3').catch(() => null),
      apiGet<any>('/events?limit=4').catch(() => null),
    ])
    rawNews.value   = news?.data ?? []
    rawEvents.value = ev?.data ?? []
  } finally {
    newsLoading.value = false
    eventsLoading.value = false
  }
}

// ── Services ──
const services = [
  { title: 'Reference Service',      desc: 'Help finding the right sources for your research, from a librarian who knows the collection.', icon: 'info' },
  { title: 'Circulation',            desc: 'Borrow, renew and return books at the desk, or reserve a title online and collect it.',         icon: 'refresh-cw' },
  { title: 'Current Awareness',      desc: 'Alerts on new arrivals, journal issues and publications in your subject area.',                 icon: 'bell' },
  { title: 'Research Support',       desc: 'Literature searches, citation guidance and support at every stage of a research project.',      icon: 'microscope' },
  { title: 'ICT & Study Space',      desc: 'Computers, printing, Wi-Fi and quiet reading rooms available throughout opening hours.',         icon: 'wifi' },
  { title: 'Training & Tutorials',   desc: 'Information-literacy sessions and short tutorials for students, staff and visiting researchers.', icon: 'graduation-cap' },
]

// ── Sign-in roles ──
const roles = [
  { title: 'Library Member',     desc: 'Borrow and reserve titles, renew loans, stream lectures and read the e-library.',            icon: 'user-round',  cta: 'Sign in' },
  { title: 'Staff & Researchers',desc: 'Extended loan limits, research support, database access and reading-room booking.',           icon: 'users-round', cta: 'Sign in' },
  { title: 'Librarian Console',  desc: 'Circulation desk, catalogue manager, institutional repository, reports and acquisitions.',     icon: 'shield-check', cta: 'Open console' },
]

// ── Opening hours — live from the library's configured schedule ──
const hoursLoading = ref(true)
const rawHours = ref<any[]>([])
const hours = computed(() => rawHours.value.map((h: any) => ({ d: h.day_label, t: h.time_label, closed: !!h.is_closed })))

const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

// "Monday – Friday" → [1,2,3,4,5]; "Sunday" → [0]
function dayRange(label: string): number[] {
  const parts = label.split(/[–-]/).map(p => p.trim())
  const idx = (name: string) => WEEKDAYS.findIndex(w => w.toLowerCase().startsWith(name.toLowerCase().slice(0, 3)))
  const start = idx(parts[0] ?? '')
  if (parts.length === 1 || start === -1) return start === -1 ? [] : [start]
  const end = idx(parts[1] ?? '')
  if (end === -1) return [start]
  const out: number[] = []
  for (let i = start; ; i = (i + 1) % 7) { out.push(i); if (i === end) break }
  return out
}

// "8:00 – 17:00" → [480, 1020] (minutes since midnight)
function timeRange(label: string): [number, number] | null {
  const matches = [...label.matchAll(/(\d{1,2}):(\d{2})/g)]
  if (matches.length < 2) return null
  const toMin = (m: RegExpMatchArray) => parseInt(m[1] ?? '0') * 60 + parseInt(m[2] ?? '0')
  return [toMin(matches[0]!), toMin(matches[1]!)]
}

const openStatus = computed(() => {
  if (!hours.value.length) return null
  const now = new Date()
  const today = hours.value.find(h => dayRange(h.d).includes(now.getDay()))
  if (!today || today.closed) return { open: false, closesAt: null }
  const range = timeRange(today.t)
  if (!range) return { open: false, closesAt: null }
  const nowMin = now.getHours() * 60 + now.getMinutes()
  const [start, end] = range
  return { open: nowMin >= start && nowMin < end, closesAt: today.t.split(/[–-]/)[1]?.trim() ?? null }
})

async function fetchHours() {
  hoursLoading.value = true
  try {
    const res = await apiGet<any>('/opening-hours')
    rawHours.value = res?.data ?? []
  } catch {
    rawHours.value = []
  } finally {
    hoursLoading.value = false
  }
}

function selectRail(item: { key: string; to: string }) {
  activeRail.value = item.key
  navigateTo(item.to)
}
function navigateTo(to: string) {
  if (to.startsWith('/')) router.push(to)
  else if (to.startsWith('#')) document.querySelector(to)?.scrollIntoView({ behavior: 'smooth' })
}

// Header search icon — jumps to the hero search bar and focuses it rather
// than sitting there as a dead button.
function focusHeroSearch() {
  const el = document.getElementById('q') as HTMLInputElement | null
  el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  el?.focus({ preventScroll: true })
}

onMounted(() => {
  fetchArrivals()
  fetchVideos()
  fetchCollections()
  fetchNewsAndEvents()
  fetchHours()
  fetchStats()
})
</script>

<template>
  <div id="top" class="min-h-screen bg-[var(--bg)] text-[var(--ink)]">

    <!-- ══ UTILITY BAR ══ -->
    <div class="bg-[var(--sb)] text-white/65 text-xs">
      <div class="max-w-[1220px] mx-auto px-[26px] h-10 flex items-center justify-between gap-4">
        <div class="hidden md:flex items-center gap-5 min-w-0">
          <a href="#contact" class="flex items-center gap-1.5 whitespace-nowrap hover:text-white transition-colors">
            <LucideIcon name="map-pin" :size="13" class="text-[var(--gold)]" /> A69 Kofar Ruwa Road, Gwammaja, Kano
          </a>
          <a href="mailto:mambayyahouse@buk.edu.ng" class="flex items-center gap-1.5 whitespace-nowrap hover:text-white transition-colors">
            <LucideIcon name="mail" :size="13" class="text-[var(--gold)]" /> mambayyahouse@buk.edu.ng
          </a>
          <a href="#contact" class="flex items-center gap-1.5 whitespace-nowrap hover:text-white transition-colors">
            <LucideIcon name="clock" :size="13" class="text-[var(--gold)]" /> Mon–Fri, 8:00 – 17:00
          </a>
        </div>
        <div class="flex items-center gap-4 md:gap-5 whitespace-nowrap ml-auto">
          <router-link to="/user/help" class="hover:text-[var(--gold)] transition-colors">Ask a Librarian</router-link>
          <router-link to="/user/journals" class="hover:text-[var(--gold)] transition-colors">A–Z Databases</router-link>
          <router-link to="/auth/login" class="hover:text-[var(--gold)] transition-colors">Staff Portal</router-link>
        </div>
      </div>
    </div>

    <!-- ══ HEADER ══ -->
    <header class="bg-white border-b border-[var(--line)] sticky top-0 z-[60]">
      <div class="max-w-[1220px] mx-auto px-[26px] h-[70px] lg:h-20 flex items-center gap-5">
        <a href="#top" class="flex items-center gap-3 flex-shrink-0">
          <span class="w-11 h-11 rounded-[11px] bg-gradient-to-br from-[var(--blue)] to-[var(--sky)] flex items-center justify-center relative overflow-hidden flex-shrink-0">
            <span class="absolute left-0 right-0 bottom-0 h-1 bg-[var(--gold)]"></span>
            <LucideIcon name="library" :size="22" class="text-white" />
          </span>
          <span>
            <span class="block font-[var(--display)] text-sm lg:text-base font-bold text-[var(--navy)] leading-tight tracking-tight">Mudi Sipikin Library</span>
            <span class="block text-[9px] lg:text-[10px] font-semibold tracking-[.09em] uppercase text-[var(--faint)] mt-0.5">Mambayya House · AKCDS</span>
          </span>
        </a>

        <nav class="hidden lg:flex items-center gap-0.5 ml-auto">
          <a v-for="link in navLinks" :key="link.label" :href="link.href"
             class="flex items-center gap-1 px-3 py-2 text-[13px] font-semibold rounded-lg transition-all relative whitespace-nowrap"
             :class="link.active ? 'text-[var(--blue)]' : 'text-[var(--ink)] hover:text-[var(--blue)] hover:bg-[var(--blue-50)]'">
            {{ link.label }}
            <span v-if="link.active" class="absolute left-3 right-3 -bottom-px h-[3px] bg-[var(--gold)] rounded-t"></span>
          </a>
        </nav>

        <div class="flex items-center gap-2 flex-shrink-0 ml-auto lg:ml-0">
          <button title="Search" @click="focusHeroSearch" class="w-10 h-10 rounded-[10px] hidden sm:flex items-center justify-center text-[var(--muted)] border border-[var(--line)] bg-white hover:border-[var(--blue)] hover:text-[var(--blue)] hover:bg-[var(--blue-50)] transition-all">
            <LucideIcon name="search" :size="18" />
          </button>
          <router-link to="/auth/login"
             class="inline-flex items-center gap-2 bg-[var(--blue)] text-white font-bold text-[13.5px] px-4 py-2.5 rounded-[10px] shadow-[0_2px_10px_rgba(23,99,201,.26)] hover:bg-[var(--blue-700)] hover:-translate-y-px transition-all">
            <LucideIcon name="log-in" :size="15" /> Sign In
          </router-link>
          <button title="Menu" class="w-10 h-10 rounded-[10px] flex lg:hidden items-center justify-center text-[var(--muted)] border border-[var(--line)] bg-white"
                  @click="mobileMenuOpen = !mobileMenuOpen">
            <LucideIcon :name="mobileMenuOpen ? 'x' : 'menu'" :size="18" />
          </button>
        </div>
      </div>

      <!-- Mobile nav panel -->
      <nav v-if="mobileMenuOpen" class="lg:hidden border-t border-[var(--line)] px-[26px] py-2 flex flex-col">
        <a v-for="link in navLinks" :key="link.label" :href="link.href" @click="mobileMenuOpen = false"
           class="px-2 py-2.5 text-[13.5px] font-semibold rounded-lg"
           :class="link.active ? 'text-[var(--blue)]' : 'text-[var(--ink)]'">
          {{ link.label }}
        </a>
      </nav>
    </header>

    <!-- ══ HERO ══ -->
    <section class="relative text-white bg-cover bg-[position:right_center]"
             :style="{ backgroundImage: `linear-gradient(100deg, rgba(12,33,71,.62) 0%, rgba(12,33,71,.42) 28%, rgba(12,33,71,.22) 50%, rgba(12,33,71,.08) 72%, rgba(12,33,71,0) 100%), url('https://mambayya-library.vercel.app/library.jpeg')` }">
      <div class="relative z-[2] max-w-[1220px] mx-auto px-[22px] lg:px-[26px] pt-10 lg:pt-14 pb-12 lg:pb-16">
        <div class="flex items-center gap-2 text-[12.5px] text-white/60 mb-5">
          <a href="#top" class="hover:text-white">Home</a>
          <LucideIcon name="chevron-right" :size="13" />
          <span class="text-[var(--gold)]">Library</span>
        </div>
        <div class="inline-flex items-center gap-2 text-[11px] font-bold tracking-[.14em] uppercase text-[var(--gold)] mb-3 before:content-[''] before:w-6 before:h-[2.5px] before:bg-[var(--gold)] before:rounded-full">
          Mudi Sipikin Library
        </div>
        <h1 class="font-[var(--display)] text-[31px] sm:text-[38px] lg:text-[45px] font-extrabold tracking-[-.028em] leading-[1.05]">
          Read the record of<br />
          Nigerian <span class="font-[var(--serif)] italic font-medium text-[var(--gold)] tracking-normal">democracy</span>
        </h1>
        <p class="text-[15.5px] text-white/80 mt-4 max-w-[510px] leading-relaxed">
          Search the full catalogue — books, journals, theses, recorded lectures and the Aminu Kano archive — then sign in to borrow, reserve and stream.
        </p>

        <div class="mt-7 max-w-[700px]">
          <div class="flex gap-1.5 flex-wrap mb-2.5">
            <button v-for="scope in searchScopes" :key="scope.key" @click="activeScope = scope.key"
                    class="text-[12.5px] font-semibold px-3.5 py-1.5 rounded-full inline-flex items-center gap-1.5 transition-all"
                    :class="activeScope === scope.key
                      ? 'bg-white text-[var(--blue)] font-bold'
                      : 'text-white/80 bg-white/10 border border-white/[.17] hover:bg-white/20 hover:text-white'">
              <LucideIcon :name="scope.icon" :size="14" /> {{ scope.label }}
            </button>
          </div>
          <div class="flex flex-col sm:flex-row gap-2 bg-white p-2 rounded-[14px] shadow-[0_22px_52px_rgba(6,20,48,.44)]">
            <div class="flex-1 flex items-center gap-2.5 px-3.5 min-w-0">
              <LucideIcon name="search" :size="19" class="text-[var(--faint)]" />
              <label for="q" class="sr-only">Search the library</label>
              <input id="q" v-model="searchQuery" @keyup.enter="runSearch" :placeholder="searchPlaceholder"
                     class="flex-1 min-w-0 border-none outline-none text-[14.5px] py-3 sm:py-0 text-[var(--ink)]" />
            </div>
            <button @click="runSearch" class="inline-flex items-center justify-center gap-2 bg-[var(--blue)] text-white font-bold text-sm px-6 py-3 sm:py-0 rounded-[9px] hover:bg-[var(--blue-700)] transition-colors whitespace-nowrap">
              <LucideIcon name="search" :size="15" /> Search
            </button>
          </div>
          <div class="mt-4 flex gap-5 flex-wrap">
            <router-link to="/user/catalog" class="inline-flex items-center gap-2 text-[13.5px] font-semibold text-white hover:gap-3 transition-all">
              <span class="border-b-[1.5px] border-[var(--gold)] pb-0.5">Advanced Search</span> <LucideIcon name="arrow-right" :size="15" class="text-[var(--gold)]" />
            </router-link>
            <a href="#collections" class="inline-flex items-center gap-2 text-[13.5px] font-semibold text-white hover:gap-3 transition-all">
              <span class="border-b-[1.5px] border-[var(--gold)] pb-0.5">Browse by Subject</span> <LucideIcon name="arrow-right" :size="15" class="text-[var(--gold)]" />
            </a>
            <router-link to="/auth/login" class="inline-flex items-center gap-2 text-[13.5px] font-semibold text-white hover:gap-3 transition-all">
              <span class="border-b-[1.5px] border-[var(--gold)] pb-0.5">My Library Account</span> <LucideIcon name="arrow-right" :size="15" class="text-[var(--gold)]" />
            </router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ STAT BAND ══ -->
    <div class="pt-6">
      <div class="max-w-[1220px] mx-auto px-[22px] lg:px-[26px] grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
        <div v-for="s in stats" :key="s.label"
             class="bg-white border border-[var(--line)] rounded-[20px] p-4.5 p-[18px] shadow-[var(--sh1)] hover:shadow-[var(--sh2)] hover:-translate-y-[3px] hover:border-[var(--blue-100)] transition-all">
          <div class="flex items-center justify-between gap-2.5 mb-2.5">
            <div class="font-[var(--display)] text-2xl font-extrabold text-[var(--navy)] leading-none tracking-tight" :class="{ 'opacity-40': statsLoading }">{{ statsLoading ? '…' : s.n }}</div>
            <div class="w-10 h-10 rounded-[11px] flex items-center justify-center flex-shrink-0" :class="TONES[s.tone]!.bg">
              <LucideIcon :name="s.icon" :size="20" :class="TONES[s.tone]!.text" />
            </div>
          </div>
          <div class="text-[12.5px] text-[var(--muted)] font-medium">{{ s.label }}</div>
          <div class="mt-2.5 inline-flex items-center gap-1 text-[11.5px] font-bold px-2.5 py-1 rounded-lg"
               :class="s.good ? 'bg-[var(--green-100)] text-[var(--green-600)]' : 'bg-[#EEF1F6] text-[var(--muted)]'">
            <LucideIcon :name="s.pillIcon" :size="13" /> {{ s.pill }}
          </div>
        </div>
      </div>
    </div>

    <!-- ══ BODY ══ -->
    <div class="max-w-[1220px] mx-auto px-[22px] lg:px-[26px]">
      <div class="grid grid-cols-1 lg:grid-cols-[262px_1fr] gap-7 py-8 lg:py-9 items-start">

        <!-- ── RAIL ── -->
        <aside class="lg:sticky lg:top-24">
          <div class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-[var(--sh1)]">
            <div class="px-4 py-3.5 bg-[var(--sb)] text-white font-[var(--display)] text-[13px] font-bold flex items-center gap-2">
              <LucideIcon name="library" :size="16" class="text-[var(--gold)]" /> Library
            </div>
            <nav class="p-1.5">
              <template v-for="(group, gi) in railGroups" :key="gi">
                <div v-if="group.heading" class="text-[9.5px] font-bold tracking-[.13em] uppercase text-[var(--faint)] px-3 pt-3 pb-1.5">{{ group.heading }}</div>
                <div v-for="item in group.items" :key="item.key" @click="selectRail(item)"
                     class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-[13px] font-medium cursor-pointer transition-all relative"
                     :class="activeRail === item.key
                       ? 'bg-[var(--blue)] text-white font-semibold'
                       : 'text-[var(--ink)] hover:bg-[var(--blue-50)] hover:text-[var(--blue)] hover:translate-x-0.5'">
                  <span v-if="activeRail === item.key" class="absolute left-0 top-0 bottom-0 w-[3px] bg-[var(--gold)] rounded-r"></span>
                  <LucideIcon :name="item.icon" :size="16" :class="activeRail === item.key ? 'text-white' : 'text-[var(--faint)]'" />
                  {{ item.label }}
                  <span v-if="item.tag" class="ml-auto text-[9.5px] font-bold px-2 py-0.5 rounded-full bg-[var(--gold)] text-[var(--sb)] tracking-wide">{{ item.tag }}</span>
                </div>
              </template>
            </nav>
          </div>

          <div class="mt-4 bg-[var(--sb)] rounded-2xl p-5 text-white relative overflow-hidden">
            <div class="absolute right-[-34px] bottom-[-34px] w-[130px] h-[130px] rounded-full" style="background: radial-gradient(circle, rgba(242,165,12,.2), transparent 70%)"></div>
            <h4 class="font-[var(--display)] text-[15px] font-bold mb-1.5 relative">Not a member yet?</h4>
            <p class="text-xs text-white/70 leading-relaxed mb-3.5 relative">Register to borrow books, reserve titles, stream recorded lectures and use the e-library from anywhere.</p>
            <router-link to="/auth/login" class="relative inline-flex items-center gap-1.5 bg-[var(--gold)] text-[var(--sb)] font-bold text-[12.5px] px-3.5 py-2 rounded-lg hover:bg-white hover:gap-2.5 transition-all">
              Join the Library <LucideIcon name="arrow-right" :size="14" />
            </router-link>
          </div>

          <div class="mt-4 bg-white border border-[var(--line)] rounded-2xl p-4.5 p-[18px] shadow-[var(--sh1)]">
            <h4 class="font-[var(--display)] text-[13.5px] font-bold text-[var(--navy)] flex items-center gap-2 mb-3">
              <LucideIcon name="clock" :size="15" class="text-[var(--blue)]" /> Opening Hours
            </h4>
            <div v-for="h in hours" :key="h.d" class="flex justify-between text-[12.5px] py-1.5 border-b border-dashed border-[var(--line-soft)] last:border-none">
              <span class="text-[var(--muted)]">{{ h.d }}</span>
              <span class="font-semibold" :class="h.closed ? 'text-[var(--red)]' : 'text-[var(--navy)]'">{{ h.t }}</span>
            </div>
            <div v-if="openStatus" class="mt-3 inline-flex items-center gap-1.5 text-[11.5px] font-bold px-2.5 py-1 rounded-full"
                 :class="openStatus.open ? 'text-[var(--green-600)] bg-[var(--green-100)]' : 'text-[var(--red-600)] bg-[var(--red-50)]'">
              <span class="w-1.5 h-1.5 rounded-full" :class="openStatus.open ? 'bg-[var(--green)] shadow-[0_0_0_3px_rgba(14,159,110,.18)]' : 'bg-[var(--red)]'"></span>
              {{ openStatus.open ? `Open now · closes ${openStatus.closesAt}` : 'Closed now' }}
            </div>
          </div>
        </aside>

        <!-- ── MAIN ── -->
        <main class="min-w-0">

          <section>
            <div class="mb-4.5 mb-[18px]">
              <div class="inline-flex items-center gap-2 text-[11px] font-bold tracking-[.12em] uppercase text-[var(--amber)] mb-2 before:content-[''] before:w-[22px] before:h-[2.5px] before:bg-[var(--gold)] before:rounded-full">Start here</div>
              <h2 class="font-[var(--display)] text-[23px] font-bold text-[var(--navy)] tracking-tight">Quick access</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
              <component :is="qa.to.startsWith('/') ? 'router-link' : 'a'" v-for="qa in quickAccess" :key="qa.title"
                 :to="qa.to.startsWith('/') ? qa.to : undefined" :href="qa.to.startsWith('/') ? undefined : qa.to"
                 class="group block bg-white border border-[var(--line)] rounded-[20px] p-5 shadow-[var(--sh1)] relative overflow-hidden transition-all hover:shadow-[var(--sh2)] hover:-translate-y-[3px] hover:border-[var(--blue-100)]">
                <span class="absolute left-0 top-0 bottom-0 w-[3px] bg-[var(--gold)] scale-y-0 origin-top group-hover:scale-y-100 transition-transform"></span>
                <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-3.5 transition-all" :class="[TONES[qa.tone]!.bg, TONES[qa.tone]!.hoverBg]">
                  <LucideIcon :name="qa.icon" :size="21" class="transition-colors" :class="[TONES[qa.tone]!.text, 'group-hover:text-white']" />
                </div>
                <h3 class="font-[var(--display)] text-[15.5px] font-bold text-[var(--navy)] mb-1.5">{{ qa.title }}</h3>
                <p class="text-[12.5px] text-[var(--muted)] leading-relaxed">{{ qa.desc }}</p>
                <span class="mt-3 inline-flex items-center gap-1.5 text-[12.5px] font-bold text-[var(--blue)]">
                  {{ qa.cta }} <LucideIcon name="arrow-right" :size="14" class="transition-transform group-hover:translate-x-1" />
                </span>
              </component>
            </div>
          </section>

          <section class="mt-11" id="arrivals">
            <div class="flex items-end justify-between gap-3.5 flex-wrap mb-4.5 mb-[18px]">
              <div>
                <div class="inline-flex items-center gap-2 text-[11px] font-bold tracking-[.12em] uppercase text-[var(--amber)] mb-2 before:content-[''] before:w-[22px] before:h-[2.5px] before:bg-[var(--gold)] before:rounded-full">Just catalogued</div>
                <h2 class="font-[var(--display)] text-[23px] font-bold text-[var(--navy)] tracking-tight">New arrivals</h2>
              </div>
              <router-link to="/user/catalog" class="inline-flex items-center gap-1.5 text-[13px] font-bold text-[var(--blue)] hover:gap-2.5 transition-all whitespace-nowrap">
                View all new titles <LucideIcon name="arrow-right" :size="15" />
              </router-link>
            </div>
            <div v-if="arrivalsLoading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
              <div v-for="i in 5" :key="i" class="bg-white border border-[var(--line)] rounded-2xl p-3.5 animate-pulse">
                <div class="mb-3 rounded-lg bg-[var(--bg)]" style="aspect-ratio: 2 / 2.9"></div>
                <div class="h-3 bg-[var(--bg)] rounded w-4/5 mb-1.5"></div>
                <div class="h-2.5 bg-[var(--bg)] rounded w-3/5"></div>
              </div>
            </div>
            <div v-else-if="!arrivals.length" class="text-[13px] text-[var(--muted)] py-8 text-center border border-dashed border-[var(--line)] rounded-2xl">
              No titles catalogued yet.
            </div>
            <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
              <router-link v-for="b in arrivals" :key="b.id" :to="{ path: '/user/catalog', query: { search: b.title } }"
                   class="group bg-white border border-[var(--line)] rounded-2xl p-3.5 shadow-[var(--sh1)] hover:shadow-[var(--sh2)] hover:-translate-y-[3px] hover:border-[var(--blue-100)] transition-all flex flex-col">
                <div class="cover mb-3" :class="arrivalCover(b)" style="aspect-ratio: 2 / 2.9">
                  <div class="cover-top">
                    <div class="cover-rule"></div>
                    <div class="cover-t text-[11px]">{{ b.title }}</div>
                  </div>
                </div>
                <div class="text-[12.5px] font-bold text-[var(--navy)] leading-snug line-clamp-2">{{ b.title }}</div>
                <div class="text-[11px] text-[var(--muted)] mt-1">{{ b.authors || 'Unknown author' }} · {{ b.year || '—' }}</div>
                <div class="mt-auto pt-2.5 flex items-center justify-between gap-1.5">
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded-md whitespace-nowrap" :class="statusChip[arrivalStatus(b)]!.class">{{ statusChip[arrivalStatus(b)]!.label }}</span>
                  <span class="text-[11.5px] font-bold text-[var(--blue)] inline-flex items-center gap-1">View <LucideIcon name="arrow-right" :size="13" class="transition-transform group-hover:translate-x-0.5" /></span>
                </div>
              </router-link>
            </div>
          </section>

          <!-- VIDEO & MULTIMEDIA -->
          <section class="mt-11" id="videos">
            <div class="flex items-end justify-between gap-3.5 flex-wrap mb-4.5 mb-[18px]">
              <div>
                <div class="inline-flex items-center gap-2 text-[11px] font-bold tracking-[.12em] uppercase text-[var(--amber)] mb-2 before:content-[''] before:w-[22px] before:h-[2.5px] before:bg-[var(--gold)] before:rounded-full">Watch & listen</div>
                <h2 class="font-[var(--display)] text-[23px] font-bold text-[var(--navy)] tracking-tight">Video & multimedia library</h2>
              </div>
              <router-link to="/user/media" class="inline-flex items-center gap-1.5 text-[13px] font-bold text-[var(--blue)] hover:gap-2.5 transition-all whitespace-nowrap">
                Browse full video library <LucideIcon name="arrow-right" :size="15" />
              </router-link>
            </div>
            <div v-if="videosLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <div v-for="i in 4" :key="i" class="bg-white border border-[var(--line)] rounded-[20px] overflow-hidden animate-pulse">
                <div class="aspect-video bg-[var(--bg)]"></div>
                <div class="p-4 flex flex-col gap-2">
                  <div class="h-3.5 bg-[var(--bg)] rounded w-4/5"></div>
                  <div class="h-3 bg-[var(--bg)] rounded w-2/5"></div>
                </div>
              </div>
            </div>
            <div v-else-if="!videos.length" class="text-[13px] text-[var(--muted)] py-8 text-center border border-dashed border-[var(--line)] rounded-2xl">
              No videos published yet.
            </div>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <div v-for="v in videos" :key="v.title" @click="openVideo(v)" class="group bg-white border border-[var(--line)] rounded-[20px] overflow-hidden shadow-[var(--sh1)] hover:shadow-[var(--sh2)] hover:-translate-y-[3px] hover:border-[var(--blue-100)] transition-all flex flex-col cursor-pointer">
                <div class="relative aspect-video bg-cover bg-center" :class="!v.thumb && 'bg-gradient-to-br from-[#0B2E63] to-[var(--blue)]'" :style="v.thumb ? { backgroundImage: `url('${v.thumb}')` } : {}">
                  <LucideIcon v-if="!v.thumb" :name="v.isAudio ? 'music' : 'video'" :size="34" class="absolute inset-0 m-auto text-white/25" />
                  <div class="absolute inset-0 bg-gradient-to-b from-[rgba(12,33,71,.08)] to-[rgba(12,33,71,.6)] group-hover:to-[rgba(12,33,71,.68)] transition-colors"></div>
                  <span class="absolute left-2.5 top-2.5 z-[3] text-[9.5px] font-bold tracking-[.07em] uppercase text-[var(--sb)] bg-[var(--gold)] px-2.5 py-1 rounded-md">{{ v.kind }}</span>
                  <span class="absolute inset-0 m-auto w-[52px] h-[52px] rounded-full bg-white/95 flex items-center justify-center z-[3] shadow-[0_6px_20px_rgba(6,20,48,.34)] group-hover:scale-110 transition-transform">
                    <LucideIcon name="play" :size="22" class="text-[var(--blue)] ml-0.5" />
                  </span>
                  <span class="absolute right-2.5 bottom-2.5 z-[3] text-[10.5px] font-bold text-white bg-[rgba(12,33,71,.82)] px-2 py-1 rounded-md">{{ v.duration }}</span>
                </div>
                <div class="px-4 pt-3.5 pb-4 flex-1 flex flex-col">
                  <div class="font-[var(--display)] text-[14.5px] font-bold text-[var(--navy)] leading-snug line-clamp-2">{{ v.title }}</div>
                  <div class="text-[11.5px] text-[var(--muted)] mt-1.5 flex items-center gap-3 flex-wrap">
                    <span class="flex items-center gap-1"><LucideIcon name="calendar" :size="13" class="text-[var(--faint)]" /> {{ v.date }}</span>
                    <span class="flex items-center gap-1"><LucideIcon name="users-round" :size="13" class="text-[var(--faint)]" /> {{ v.views }}</span>
                  </div>
                  <div class="mt-auto pt-3 flex items-center justify-between gap-2">
                    <span class="text-[10.5px] font-semibold text-[var(--muted)] border border-[var(--line)] px-2.5 py-0.5 rounded-md">{{ v.tag }}</span>
                    <span class="text-xs font-bold text-[var(--blue)] inline-flex items-center gap-1">Watch <LucideIcon name="arrow-right" :size="13" class="transition-transform group-hover:translate-x-0.5" /></span>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="multimedia.length" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-4">
              <a v-for="m in multimedia" :key="m.label" href="#videos"
                 class="group flex items-center gap-2.5 bg-white border border-[var(--line)] rounded-xl px-3.5 py-3 shadow-[var(--sh1)] hover:border-[var(--blue)] hover:bg-[var(--blue-50)] hover:-translate-y-0.5 transition-all">
                <div class="w-9 h-9 rounded-[10px] bg-[var(--blue-50)] group-hover:bg-white flex items-center justify-center flex-shrink-0 transition-colors">
                  <LucideIcon :name="m.icon" :size="18" class="text-[var(--blue)]" />
                </div>
                <div>
                  <div class="text-[12.5px] font-bold text-[var(--navy)] leading-tight">{{ m.label }}</div>
                  <div class="text-[11px] text-[var(--muted)] mt-0.5">{{ m.count }}</div>
                </div>
              </a>
            </div>
          </section>

          <section class="mt-11" id="collections">
            <div class="flex items-end justify-between gap-3.5 flex-wrap mb-4.5 mb-[18px]">
              <div>
                <div class="inline-flex items-center gap-2 text-[11px] font-bold tracking-[.12em] uppercase text-[var(--amber)] mb-2 before:content-[''] before:w-[22px] before:h-[2.5px] before:bg-[var(--gold)] before:rounded-full">By subject</div>
                <h2 class="font-[var(--display)] text-[23px] font-bold text-[var(--navy)] tracking-tight">Featured collections</h2>
              </div>
              <router-link to="/user/catalog" class="inline-flex items-center gap-1.5 text-[13px] font-bold text-[var(--blue)] hover:gap-2.5 transition-all whitespace-nowrap">
                All collections <LucideIcon name="arrow-right" :size="15" />
              </router-link>
            </div>
            <div v-if="collectionsLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
              <div v-for="i in 6" :key="i" class="flex items-center gap-3.5 bg-white border border-[var(--line)] rounded-xl px-4 py-3.5 animate-pulse">
                <div class="w-[38px] h-[38px] rounded-[10px] bg-[var(--bg)] flex-shrink-0"></div>
                <div class="flex-1"><div class="h-3 bg-[var(--bg)] rounded w-3/5 mb-1.5"></div><div class="h-2.5 bg-[var(--bg)] rounded w-2/5"></div></div>
              </div>
            </div>
            <div v-else-if="!collections.length" class="text-[13px] text-[var(--muted)] py-8 text-center border border-dashed border-[var(--line)] rounded-2xl">
              No subjects catalogued yet.
            </div>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
              <router-link v-for="c in collections" :key="c.name" :to="{ path: '/user/catalog', query: { search: c.search } }"
                 class="flex items-center gap-3.5 bg-white border border-[var(--line)] rounded-xl px-4 py-3.5 shadow-[var(--sh1)] hover:border-[var(--blue-100)] hover:shadow-[var(--sh2)] hover:-translate-y-0.5 transition-all cursor-pointer">
                <div class="w-[38px] h-[38px] rounded-[10px] bg-[#F1F4F9] flex items-center justify-center flex-shrink-0">
                  <LucideIcon :name="c.icon" :size="19" :class="TONES[c.tone]!.text" />
                </div>
                <div>
                  <div class="text-[13px] font-bold text-[var(--navy)] leading-tight">{{ c.name }}</div>
                  <div class="text-[11px] text-[var(--faint)] mt-0.5">{{ c.count }}</div>
                </div>
              </router-link>
            </div>
          </section>

          <!-- NEWS & EVENTS -->
          <section class="mt-11" id="news">
            <div class="flex items-end justify-between gap-3.5 flex-wrap mb-4.5 mb-[18px]">
              <div>
                <div class="inline-flex items-center gap-2 text-[11px] font-bold tracking-[.12em] uppercase text-[var(--amber)] mb-2 before:content-[''] before:w-[22px] before:h-[2.5px] before:bg-[var(--gold)] before:rounded-full">Stay informed</div>
                <h2 class="font-[var(--display)] text-[23px] font-bold text-[var(--navy)] tracking-tight">News & events</h2>
              </div>
              <router-link to="/user/news" class="inline-flex items-center gap-1.5 text-[13px] font-bold text-[var(--blue)] hover:gap-2.5 transition-all whitespace-nowrap">
                View all news & events <LucideIcon name="arrow-right" :size="15" />
              </router-link>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-[1.6fr_1fr] gap-5 items-start">
              <div v-if="newsLoading" class="flex flex-col gap-3.5">
                <div v-for="i in 3" :key="i" class="flex gap-3.5 bg-white border border-[var(--line)] rounded-[20px] p-4 animate-pulse">
                  <div class="w-[46px] h-[46px] rounded-xl bg-[var(--bg)] flex-shrink-0"></div>
                  <div class="flex-1"><div class="h-3 bg-[var(--bg)] rounded w-4/5 mb-2"></div><div class="h-2.5 bg-[var(--bg)] rounded w-3/5"></div></div>
                </div>
              </div>
              <div v-else-if="!newsList.length" class="text-[13px] text-[var(--muted)] py-8 text-center border border-dashed border-[var(--line)] rounded-2xl">
                No news posted yet.
              </div>
              <div v-else class="flex flex-col gap-3.5">
                <div v-for="n in newsList" :key="n.title" @click="openNewsModal(n)"
                   class="group flex gap-3.5 bg-white border border-[var(--line)] rounded-[20px] p-4 shadow-[var(--sh1)] hover:shadow-[var(--sh2)] hover:-translate-y-0.5 hover:border-[var(--blue-100)] transition-all cursor-pointer">
                  <div class="w-[46px] h-[46px] rounded-xl flex items-center justify-center flex-shrink-0" :class="TONES[n.tone]!.bg">
                    <LucideIcon :name="n.icon" :size="21" :class="TONES[n.tone]!.text" />
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="text-[10px] font-bold tracking-[.07em] uppercase text-[var(--amber)]">{{ n.tag }}</div>
                    <div class="font-[var(--display)] text-[14.5px] font-bold text-[var(--navy)] leading-snug mt-1">{{ n.title }}</div>
                    <p class="text-xs text-[var(--muted)] mt-1.5 leading-relaxed line-clamp-2">{{ n.body }}</p>
                    <div class="mt-2.5 flex items-center justify-between gap-2.5 flex-wrap">
                      <span class="text-[11px] text-[var(--faint)] flex items-center gap-1"><LucideIcon name="calendar" :size="12" /> {{ n.date }}</span>
                      <span class="text-[11.5px] font-bold text-[var(--blue)] inline-flex items-center gap-1 flex-shrink-0 whitespace-nowrap">Read more <LucideIcon name="arrow-right" :size="12" class="transition-transform group-hover:translate-x-0.5" /></span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-[var(--sh1)] flex flex-col h-full">
                <div class="px-4 py-3.5 bg-[var(--sb)] text-white font-[var(--display)] text-[13px] font-bold flex items-center gap-2">
                  <LucideIcon name="calendar" :size="16" class="text-[var(--gold)]" /> Upcoming events
                </div>
                <div v-if="eventsLoading" class="p-3 flex-1 flex flex-col gap-2.5">
                  <div v-for="i in 4" :key="i" class="h-[46px] bg-[var(--bg)] rounded-lg animate-pulse"></div>
                </div>
                <div v-else-if="!events.length" class="p-4 flex-1 text-[12.5px] text-[var(--faint)] text-center">
                  No upcoming events.
                </div>
                <div v-else class="p-1.5 flex-1">
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
                <router-link to="/user/news" class="px-4 py-3 border-t border-[var(--line-soft)] text-center block hover:bg-[var(--blue-50)] transition-colors">
                  <span class="text-xs font-bold text-[var(--blue)] inline-flex items-center gap-1.5">See full events calendar <LucideIcon name="arrow-right" :size="13" /></span>
                </router-link>
              </div>
            </div>
          </section>

          <section class="mt-11" id="services">
            <div class="mb-4.5 mb-[18px]">
              <div class="inline-flex items-center gap-2 text-[11px] font-bold tracking-[.12em] uppercase text-[var(--amber)] mb-2 before:content-[''] before:w-[22px] before:h-[2.5px] before:bg-[var(--gold)] before:rounded-full">What we offer</div>
              <h2 class="font-[var(--display)] text-[23px] font-bold text-[var(--navy)] tracking-tight">Library services</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
              <div v-for="s in services" :key="s.title" class="group bg-white border border-[var(--line)] rounded-[20px] p-5 shadow-[var(--sh1)] hover:shadow-[var(--sh2)] hover:-translate-y-[3px] hover:border-[var(--blue-100)] transition-all">
                <div class="w-[42px] h-[42px] rounded-xl bg-[var(--blue-50)] group-hover:bg-[var(--blue)] flex items-center justify-center mb-3.5 transition-colors">
                  <LucideIcon :name="s.icon" :size="20" class="text-[var(--blue)] group-hover:text-white transition-colors" />
                </div>
                <h3 class="font-[var(--display)] text-[15px] font-bold text-[var(--navy)] mb-1.5">{{ s.title }}</h3>
                <p class="text-[12.5px] text-[var(--muted)] leading-relaxed">{{ s.desc }}</p>
              </div>
            </div>
          </section>

          <section class="mt-11" id="about">
            <div class="grid grid-cols-1 lg:grid-cols-[1.05fr_.95fr] gap-7 items-center bg-white border border-[var(--line)] rounded-[20px] p-6 lg:p-7 shadow-[var(--sh1)]">
              <div>
                <div class="inline-flex items-center gap-2 text-[11px] font-bold tracking-[.12em] uppercase text-[var(--amber)] mb-2 before:content-[''] before:w-[22px] before:h-[2.5px] before:bg-[var(--gold)] before:rounded-full">The library</div>
                <h2 class="font-[var(--display)] text-[23px] font-bold text-[var(--navy)] tracking-tight">About Mudi Sipikin Library</h2>
                <p class="text-sm text-[var(--muted)] mt-3.5 leading-[1.75]">
                  Mudi Sipikin Library is the information hub of the Aminu Kano Centre for Democratic Studies (Mambayya House), Bayero University, Kano. It supports teaching, learning and research with a collection built around democracy, governance, law and Nigerian political history — including the Centre's archive of the life and work of Mallam Aminu Kano.
                </p>
                <router-link to="/user/catalog" class="mt-5 inline-flex items-center gap-2 bg-[var(--blue)] text-white font-bold text-[13.5px] px-5 py-3 rounded-[10px] hover:bg-[var(--blue-700)] hover:-translate-y-px transition-all">
                  More about the library <LucideIcon name="arrow-right" :size="16" />
                </router-link>
              </div>
              <div class="rounded-2xl overflow-hidden h-[220px] lg:h-[258px] relative shadow-[var(--sh2)] bg-cover bg-center"
                   :style="{ backgroundImage: `linear-gradient(180deg, rgba(11,33,72,.06), rgba(11,33,72,.56)), url('https://mambayya-library.vercel.app/about.png')` }">
                <div class="absolute left-4.5 left-[18px] bottom-4 text-white z-[2]">
                  <div class="font-[var(--serif)] italic text-base font-semibold">A home for democratic scholarship</div>
                  <div class="text-[11.5px] text-white/80 mt-0.5">Reading & reference hall, Mambayya House</div>
                </div>
              </div>
            </div>
          </section>

          <!-- ══ SIGN-IN ROUTER ══ -->
          <section class="mt-11 bg-[var(--sb)] rounded-[20px] p-6 lg:p-8 text-white relative overflow-hidden">
            <div class="absolute right-[-70px] top-[-70px] w-[260px] h-[260px] rounded-full" style="background: radial-gradient(circle, rgba(242,165,12,.18), transparent 68%)"></div>
            <div class="relative mb-5.5 mb-[22px]">
              <div class="inline-flex items-center gap-2 text-[11px] font-bold tracking-[.12em] uppercase text-[var(--gold)] mb-2 before:content-[''] before:w-[22px] before:h-[2.5px] before:bg-[var(--gold)] before:rounded-full">Access</div>
              <h3 class="font-[var(--display)] text-[22px] font-bold tracking-tight text-white">Sign in to the library system</h3>
              <p class="text-[13.5px] text-white/75 mt-2 max-w-[560px] leading-relaxed">Borrowing, reservations, e-resources and the staff console all live behind one login. Choose how you use the library — every route opens the same secure sign-in.</p>
            </div>
            <div class="relative grid grid-cols-1 sm:grid-cols-3 gap-3.5">
              <router-link v-for="r in roles" :key="r.title" to="/auth/login"
                 class="group bg-white/[.07] border border-white/[.14] rounded-2xl p-4.5 p-[18px] hover:bg-white/[.13] hover:border-[var(--gold)] hover:-translate-y-[3px] transition-all block">
                <div class="w-10 h-10 rounded-[11px] bg-white/10 group-hover:bg-[var(--gold)] flex items-center justify-center mb-3 transition-colors">
                  <LucideIcon :name="r.icon" :size="20" class="text-[var(--gold)] group-hover:text-[var(--sb)] transition-colors" />
                </div>
                <h4 class="font-[var(--display)] text-[15px] font-bold text-white mb-1">{{ r.title }}</h4>
                <p class="text-xs text-white/70 leading-relaxed">{{ r.desc }}</p>
                <span class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-[var(--gold)]">
                  {{ r.cta }} <LucideIcon name="arrow-right" :size="13" class="transition-transform group-hover:translate-x-0.5" />
                </span>
              </router-link>
            </div>
          </section>

        </main>
      </div>
    </div>

    <!-- ══ FOOTER ══ -->
    <footer class="bg-[var(--sb)] text-white/70 mt-12" id="contact">
      <div class="max-w-[1220px] mx-auto px-[26px] grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1.7fr_1fr_1fr_1.15fr] gap-8 py-12 lg:py-[52px] lg:pb-[42px]">
        <div>
          <div class="flex items-center gap-3 mb-4">
            <span class="w-[38px] h-[38px] rounded-[10px] bg-gradient-to-br from-[var(--blue)] to-[var(--sky)] flex items-center justify-center relative overflow-hidden flex-shrink-0">
              <span class="absolute left-0 right-0 bottom-0 h-1 bg-[var(--gold)]"></span>
              <LucideIcon name="library" :size="19" class="text-white" />
            </span>
            <span>
              <span class="block font-[var(--display)] text-[15px] font-bold text-white leading-tight">Mudi Sipikin Library</span>
              <span class="block text-[10px] font-semibold tracking-[.09em] uppercase text-[var(--gold)] mt-1">Mambayya House · AKCDS</span>
            </span>
          </div>
          <p class="text-[12.5px] leading-relaxed max-w-[320px]">
            The information hub of the Aminu Kano Centre for Democratic Studies, Bayero University, Kano — advancing democratic scholarship, research and civic education.
          </p>
          <ul class="mt-4 flex flex-col gap-2.5">
            <li class="flex gap-2.5 text-[12.5px] leading-relaxed">
              <LucideIcon name="map-pin" :size="15" class="text-[var(--gold)] flex-shrink-0 mt-0.5" />
              <span>A69 Kofar Ruwa Road, Gwammaja, PMB 3011, Kano, Nigeria</span>
            </li>
            <li class="flex gap-2.5 text-[12.5px] leading-relaxed">
              <LucideIcon name="mail" :size="15" class="text-[var(--gold)] flex-shrink-0 mt-0.5" />
              <span>mambayyahouse@buk.edu.ng</span>
            </li>
            <li class="flex gap-2.5 text-[12.5px] leading-relaxed">
              <LucideIcon name="phone" :size="15" class="text-[var(--gold)] flex-shrink-0 mt-0.5" />
              <span>+234 803 630 3262</span>
            </li>
          </ul>
          <div class="flex gap-2 mt-4">
            <a href="#" aria-label="Facebook" class="w-9 h-9 rounded-[10px] bg-white/[.08] flex items-center justify-center text-white/80 hover:bg-[var(--gold)] hover:text-[var(--sb)] hover:-translate-y-0.5 transition-all">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
            </a>
            <a href="#" aria-label="Twitter / X" class="w-9 h-9 rounded-[10px] bg-white/[.08] flex items-center justify-center text-white/80 hover:bg-[var(--gold)] hover:text-[var(--sb)] hover:-translate-y-0.5 transition-all">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M18.9 1.2h3.7l-8 9.2 9.5 12.6h-7.5l-5.8-7.7-6.7 7.7H.4l8.6-9.9L0 1.2h7.7l5.3 7z"/></svg>
            </a>
            <a href="#" aria-label="LinkedIn" class="w-9 h-9 rounded-[10px] bg-white/[.08] flex items-center justify-center text-white/80 hover:bg-[var(--gold)] hover:text-[var(--sb)] hover:-translate-y-0.5 transition-all">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/></svg>
            </a>
            <a href="#" aria-label="YouTube" class="w-9 h-9 rounded-[10px] bg-white/[.08] flex items-center justify-center text-white/80 hover:bg-[var(--gold)] hover:text-[var(--sb)] hover:-translate-y-0.5 transition-all">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"/><path d="m10 15 5-3-5-3z"/></svg>
            </a>
          </div>
        </div>

        <div>
          <h4 class="font-[var(--display)] text-[11.5px] font-bold text-white mb-4 tracking-[.1em] uppercase">Library</h4>
          <ul class="flex flex-col gap-2.5">
            <li><router-link to="/user/catalog" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">OPAC — Search Catalogue</router-link></li>
            <li><router-link to="/user/e-library" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">E-Library & E-Resources</router-link></li>
            <li><router-link to="/user/journals" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Journals & Databases</router-link></li>
            <li><a href="#videos" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Video Library</a></li>
            <li><a href="#videos" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Audio & Oral Histories</a></li>
            <li><router-link to="/user/catalog" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Theses & Dissertations</router-link></li>
            <li><router-link to="/user/archives" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Archives & Special Collections</router-link></li>
            <li><a href="#news" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">News & Events</a></li>
          </ul>
        </div>

        <div>
          <h4 class="font-[var(--display)] text-[11.5px] font-bold text-white mb-4 tracking-[.1em] uppercase">Services</h4>
          <ul class="flex flex-col gap-2.5">
            <li><router-link to="/auth/login" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Membership & Registration</router-link></li>
            <li><router-link to="/auth/login" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Borrow, Renew & Reserve</router-link></li>
            <li><router-link to="/user/help" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Ask a Librarian</router-link></li>
            <li><router-link to="/user/help" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Request a Title</router-link></li>
            <li><a href="#services" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Research & Citation Support</a></li>
            <li><a href="#services" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Reading Rooms & Facilities</a></li>
            <li><a href="#contact" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Library Policies</a></li>
          </ul>
        </div>

        <div>
          <h4 class="font-[var(--display)] text-[11.5px] font-bold text-white mb-4 tracking-[.1em] uppercase">Opening Hours</h4>
          <div v-for="h in hours" :key="h.d" class="flex justify-between text-[12.5px] py-1.5 border-b border-dashed border-white/10 last:border-none">
            <span class="text-white/60">{{ h.d }}</span>
            <span class="font-semibold" :class="h.closed ? 'text-[#F3B4B4]' : 'text-white'">{{ h.t }}</span>
          </div>
          <h4 class="font-[var(--display)] text-[11.5px] font-bold text-white mt-6 mb-4 tracking-[.1em] uppercase">Access</h4>
          <ul class="flex flex-col gap-2.5">
            <li><router-link to="/auth/login" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Member Sign In</router-link></li>
            <li><router-link to="/auth/login" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Staff Portal</router-link></li>
            <li><router-link to="/auth/login" class="text-[12.5px] hover:text-[var(--gold)] transition-colors">Librarian Console</router-link></li>
          </ul>
        </div>
      </div>

      <div class="border-t border-white/10">
        <div class="max-w-[1220px] mx-auto px-[26px] flex items-center justify-between py-4.5 py-[18px] flex-wrap gap-2.5">
          <div class="text-xs leading-relaxed">
            © 2025 Mambayya House — Aminu Kano Centre for Democratic Studies. All rights reserved.<br />
            Built with institutional integrity by <b class="text-[var(--gold)] font-semibold">MasqIT Solutions</b>.
          </div>
          <div class="flex gap-5 text-xs">
            <a href="#" class="hover:text-[var(--gold)] transition-colors">Privacy Policy</a>
            <a href="#" class="hover:text-[var(--gold)] transition-colors">Terms of Use</a>
            <a href="#" class="hover:text-[var(--gold)] transition-colors">Accessibility</a>
            <a href="#" class="hover:text-[var(--gold)] transition-colors">Sitemap</a>
          </div>
        </div>
      </div>
    </footer>

    <NewsModal :open="newsModalOpen" :news="activeNews" @close="newsModalOpen = false" />

  </div>
</template>
