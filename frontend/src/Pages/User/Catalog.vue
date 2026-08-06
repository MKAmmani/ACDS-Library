<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import LucideIcon from '@/components/LucideIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPost } from '@/api/http'

const auth   = useAuthStore()
const router = useRouter()
const route  = useRoute()

// ── UI state ─────────────────────────────────────────────────────────────────
const searchQuery  = ref('')
const viewMode     = ref<'grid' | 'list'>('grid')
const drawerOpen   = ref(false)
const modalOpen    = ref(false)
const reserveStep  = ref(1)
const selectedBook = ref<any>(null)
const activeColl   = ref('all')
const sortBy       = ref('Title A–Z')
const citeFmt      = ref('APA')
const copiedCite   = ref(false)
const showFilters  = ref(false)

// ── Data state ───────────────────────────────────────────────────────────────
const books       = ref<any[]>([])
const loading     = ref(false)
const loadError   = ref('')
const currentPage = ref(1)
const lastPage    = ref(1)
const total       = ref(0)

// ── Reserve ──────────────────────────────────────────────────────────────────
const reserving     = ref(false)
const reserveError  = ref('')
const reservationId = ref('')
const reservedIds   = ref<Set<number>>(new Set())   // books the current user has already reserved

const isLoggedIn = computed(() => !!auth.token)

// When a search has been run and produced results, hide Browse Collections
// and the filter sidebar so only the results show under the search bar.
const isSearchingResults = computed(() => !!searchQuery.value.trim() && !loading.value && books.value.length > 0)
function isReserved(book: any) { return reservedIds.value.has(book.id) }

function goSignIn() {
  router.push({ path: '/auth/login', query: { redirect: route.path } })
}

// ── Filters ──────────────────────────────────────────────────────────────────
const DEFAULT_YEAR_FROM = 1900
const DEFAULT_YEAR_TO   = new Date().getFullYear()

const filters = reactive({
  format:       [] as string[],
  subject:      [] as string[],
  availability: [] as string[],
  yearFrom: DEFAULT_YEAR_FROM,
  yearTo:   DEFAULT_YEAR_TO,
})

// Dynamic facets loaded from the backend (reflect what staff have catalogued)
const subjectFacets = ref<{ name: string; count: number }[]>([])
const shelfFacets    = ref<{ name: string; count: number }[]>([])

// Browse Collections — built from the books' shelf locations
const collections = computed(() => [
  { id: 'all', label: 'All Books', icon: 'book-copy', cls: 'text-[var(--blue)]', shelf: null as string | null },
  ...shelfFacets.value.map(s => ({
    id: 'shelf:' + s.name,
    label: s.name,
    icon: 'map-pin',
    cls: 'text-[var(--blue)]',
    shelf: s.name as string | null,
  })),
])

const filterGroups = computed(() => [
  { label: 'Format',       key: 'format'       as const, opts: ['Books', 'Journals', 'Theses'] },
  { label: 'Subject',      key: 'subject'      as const, opts: subjectFacets.value.map(s => s.name) },
  { label: 'Availability', key: 'availability' as const, opts: ['Available Now', 'On Loan'] },
])

// ── Helpers ──────────────────────────────────────────────────────────────────
const coverClasses = ['cv-navy','cv-burgundy','cv-forest','cv-charcoal','cv-slate','cv-ochre','cv-rust']
function coverCls(id: number) { return coverClasses[id % coverClasses.length] }

function bookStatus(book: any): 'available' | 'on-loan' | 'reference' | 'digital' {
  if (book.material_type === 'reference') return 'reference'
  if (book.format === 'digital' || book.format === 'ebook') return 'digital'
  return book.available_copies > 0 ? 'available' : 'on-loan'
}

function statusBadge(s: string) {
  return { available:'b-green', 'on-loan':'b-red', digital:'b-blue', reference:'b-gold' }[s] ?? 'b-gray'
}
function statusLabel(s: string) {
  return { available:'Available', 'on-loan':'On Loan', digital:'Digital', reference:'Reference' }[s] ?? s
}
function dotCls(s: string) {
  return { available:'bg-[var(--green)]', 'on-loan':'bg-[var(--red)]', digital:'bg-[var(--blue)]', reference:'bg-[var(--gold)]' }[s] ?? 'bg-[var(--faint)]'
}

const activeFilters = computed(() => [
  ...filters.format.map(v => ({ label: v, type: 'format' })),
  ...filters.subject.map(v => ({ label: v, type: 'subject' })),
  ...filters.availability.map(v => ({ label: v, type: 'availability' })),
])

function toggleFilter(type: keyof typeof filters, val: string) {
  const arr = filters[type] as string[]
  const i = arr.indexOf(val); i === -1 ? arr.push(val) : arr.splice(i, 1)
}
function removeFilter(type: string, label: string) {
  const arr = filters[type as keyof typeof filters] as string[]
  const i = arr.indexOf(label); if (i !== -1) arr.splice(i, 1)
}
function clearFilters() {
  filters.format = []; filters.subject = []; filters.availability = []
  filters.yearFrom = DEFAULT_YEAR_FROM; filters.yearTo = DEFAULT_YEAR_TO
  activeColl.value = 'all'
  fetchBooks()
}

// ── API ───────────────────────────────────────────────────────────────────────
let searchTimer: ReturnType<typeof setTimeout>

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    fetchBooks()
  }, 400)
}

async function fetchBooks() {
  loading.value   = true
  loadError.value = ''
  try {
    const params = new URLSearchParams()

    // Search query
    if (searchQuery.value.trim()) {
      params.set('search', searchQuery.value.trim())
    }

    // Browse Collections shortcut → shelf location
    const col = collections.value.find(c => c.id === activeColl.value)
    if (col?.shelf) params.set('shelf_location', col.shelf)

    // Subject filter (sidebar) — dynamic exact subjects, supports multi-select
    if (filters.subject.length) {
      params.set('subject_area', filters.subject.join(','))
    }

    // Format filter
    if (filters.format.length) {
      const fmtMap: Record<string,string> = { Books:'book', Journals:'journal', Theses:'thesis' }
      const [firstFormat] = filters.format
      if (firstFormat) params.set('format', fmtMap[firstFormat] ?? firstFormat.toLowerCase())
    }

    // Availability filter — only meaningful when exactly one is chosen
    if (filters.availability.length === 1) {
      params.set('availability', filters.availability[0] === 'Available Now' ? 'available' : 'on_loan')
    }

    // Year range filter — only sent once the user has moved off the full default range
    if (filters.yearFrom !== DEFAULT_YEAR_FROM) params.set('year_from', String(filters.yearFrom))
    if (filters.yearTo   !== DEFAULT_YEAR_TO)   params.set('year_to',   String(filters.yearTo))

    // Archived titles are in special storage, not on the open shelves
    params.set('archived', '0')

    params.set('page', String(currentPage.value))

    const data = await apiGet<any>(`/books?${params}`)
    books.value       = data.data      ?? []
    total.value       = data.total     ?? 0
    lastPage.value    = data.last_page ?? 1
    currentPage.value = data.current_page ?? 1
  } catch (e: any) {
    loadError.value = e.message
  }
  loading.value = false
}

function selectCollection(col: any) {
  activeColl.value  = col.id
  currentPage.value = 1
  fetchBooks()
}

function goToPage(p: number) {
  if (p < 1 || p > lastPage.value) return
  currentPage.value = p
  fetchBooks()
}

const pagesArr = computed(() => {
  const p = currentPage.value, l = lastPage.value
  const set = new Set([1, l, p - 1, p, p + 1].filter(x => x >= 1 && x <= l))
  return [...set].sort((a, b) => a - b)
})

async function fetchFacets() {
  try {
    const f = await apiGet<any>('/books/facets')
    subjectFacets.value = f.subjects        ?? []
    shelfFacets.value   = f.shelf_locations ?? []
  } catch {}
}

// Which books the signed-in member already has an active reservation for —
// so we can show them as "Reserved" instead of erroring on a duplicate request.
async function fetchMyReservations() {
  if (!auth.token) return
  try {
    const res  = await apiGet<any>('/me/reservations', auth.token)
    const list = res.data ?? []
    reservedIds.value = new Set(
      list
        .filter((r: any) => ['pending', 'fulfilled'].includes(r.status))
        .map((r: any) => r.book?.id ?? r.book_id),
    )
  } catch {}
}

// Deep-linked search — e.g. /user/catalog?search=… from the landing page's
// New Arrivals, Featured Collections or hero search bar.
onMounted(() => {
  const q = route.query.search
  if (typeof q === 'string' && q.trim()) searchQuery.value = q
  fetchFacets(); fetchBooks(); fetchMyReservations()
})

// Same route, new query (e.g. clicking a different collection card while
// already on the catalog page) — Vue Router reuses the component, so this
// won't re-run via onMounted.
watch(() => route.query.search, (q) => {
  const next = typeof q === 'string' ? q : ''
  if (next === searchQuery.value) return
  searchQuery.value = next
  currentPage.value = 1
  fetchBooks()
})

// Re-fetch when filter sidebar changes
watch(() => [filters.format.join(), filters.subject.join(), filters.availability.join()], () => {
  currentPage.value = 1
  fetchBooks()
})

// ── Drawer ────────────────────────────────────────────────────────────────────
function openDrawer(book: any) { selectedBook.value = book; drawerOpen.value = true }
function closeDrawer()         { drawerOpen.value = false }

// ── Reserve modal ─────────────────────────────────────────────────────────────
function openReserve(book?: any) {
  if (book) selectedBook.value = book
  if (!selectedBook.value) return
  // Reserving requires an account — send guests to sign in first.
  if (!auth.token) { goSignIn(); return }
  // Already reserved this book → just show the reserved confirmation.
  if (isReserved(selectedBook.value)) {
    reservationId.value = ''
    reserveStep.value   = 2
    reserveError.value  = ''
    modalOpen.value     = true
    return
  }
  reserveStep.value  = 1
  reserveError.value = ''
  reservationId.value = ''
  modalOpen.value    = true
}
function closeReserve() { modalOpen.value = false }

async function confirmReserve() {
  if (!selectedBook.value) return

  if (!auth.token) {
    reserveError.value = 'Please sign in to reserve a book.'
    return
  }

  reserving.value    = true
  reserveError.value = ''
  try {
    const res = await apiPost<any>('/reservations', { book_id: selectedBook.value.id }, auth.token)
    reservationId.value = `RES-${String(res.id).padStart(4, '0')}`
    markReserved(selectedBook.value.id)
    reserveStep.value   = 2
  } catch (e: any) {
    // An existing reservation isn't an error — treat the book as already reserved.
    if (/already have an active reservation/i.test(e.message ?? '')) {
      markReserved(selectedBook.value.id)
      reservationId.value = ''
      reserveStep.value   = 2
    } else {
      reserveError.value = e.message
    }
  }
  reserving.value = false
}

function markReserved(id: number) {
  const next = new Set(reservedIds.value)
  next.add(id)
  reservedIds.value = next
}

// ── Cite ──────────────────────────────────────────────────────────────────────
function buildCite(book: any) {
  if (!book) return ''
  const a = book.authors ?? 'Unknown Author'
  const t = book.title ?? ''
  const y = book.year ?? ''
  const p = book.publisher ?? 'Publisher'
  if (citeFmt.value === 'APA')     return `${a} (${y}). ${t}. ${p}.`
  if (citeFmt.value === 'MLA')     return `${a}. "${t}." ${p}, ${y}.`
  if (citeFmt.value === 'Chicago') return `${a}. ${t}. ${p}, ${y}.`
  return `${a} (${y}) ${t}, ${p}.`
}

function copyCite() {
  if (!selectedBook.value) return
  navigator.clipboard.writeText(buildCite(selectedBook.value))
  copiedCite.value = true
  setTimeout(() => { copiedCite.value = false }, 2000)
}
</script>

<template>
  <!-- Hero -->
  <div class="relative overflow-hidden" style="background:linear-gradient(115deg,#0B2E63 0%,var(--blue) 48%,var(--sky) 100%);padding:36px 0 0">
    <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(circle at 88% 10%,rgba(242,165,12,.16),transparent 45%)"></div>
    <div class="relative z-[2] max-w-[1260px] mx-auto px-4 sm:px-7">
      <div class="inline-flex items-center gap-2 text-[11px] font-semibold tracking-[.18em] uppercase text-[var(--gold)] mb-3">
        <span class="w-5 h-px bg-[var(--gold)]"></span> Online Public Access Catalog
      </div>
      <h1 class="text-[clamp(22px,5vw,44px)] font-bold text-white leading-tight tracking-tight mb-2" style="font-family:var(--display)">
        Search the <em class="not-italic text-[var(--gold)]">Library Catalog</em>
      </h1>
      <p class="hidden sm:block text-[14.5px] text-white/70 font-light max-w-[560px] leading-[1.7] mb-6">
        Browse volumes, eBooks, journals, and government documents.
      </p>
      <!-- Search card -->
      <div class="bg-white rounded-t-xl p-4 sm:p-6 sm:pb-5 mt-4" style="box-shadow:0 -10px 36px rgba(11,46,99,.18)">
        <div class="flex gap-2 mb-3">
          <div class="flex-1 flex items-center gap-2 border-[1.5px] border-[var(--line)] rounded-[10px] px-3 sm:px-4 transition-all focus-within:border-[var(--blue)] focus-within:shadow-[0_0_0_4px_rgba(23,99,201,.09)]">
            <LucideIcon name="search" :size="18" class="text-[var(--faint)] flex-shrink-0" />
            <input v-model="searchQuery" @input="onSearchInput" type="text"
              placeholder="Search by title, author, subject…"
              class="flex-1 border-none outline-none py-3 text-[15px] sm:text-[17px] text-[var(--ink)] bg-transparent placeholder:text-[var(--faint)]"
              style="font-family:var(--display)" />
            <button v-if="searchQuery" @click="searchQuery=''; onSearchInput()" class="p-1 text-[var(--faint)] hover:text-[var(--muted)] rounded">
              <LucideIcon name="x" :size="15" />
            </button>
          </div>
          <button class="btn btn-primary px-3 sm:px-6 text-[13px] sm:text-[14px]" @click="currentPage=1; fetchBooks()">
            <LucideIcon name="search" :size="15" />
            <span class="hidden sm:inline">Search</span>
          </button>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-[11.5px] font-semibold text-[var(--muted)]">In:</span>
          <button v-for="s in ['All Fields','Title','Author','Subject','ISBN']" :key="s"
            class="chip" :class="s==='All Fields' ? 'active' : ''">{{ s }}</button>
        </div>
        <div class="hidden sm:flex gap-4 sm:gap-6 mt-3.5 pt-3.5 border-t border-[var(--line-soft)] overflow-x-auto">
          <div v-for="[icon,val,lbl] in [['package', loading ? '…' : String(total),'items'],['circle-check-big','—','available'],['refresh-cw','today','updated']]"
               :key="icon" class="flex items-center gap-1.5 text-[12px] text-[var(--muted)] flex-shrink-0">
            <LucideIcon :name="icon" :size="14" class="text-[var(--blue)]" />
            <strong class="text-[var(--navy)]">{{ val }}</strong> {{ lbl }}
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Body -->
  <div class="max-w-[1260px] mx-auto px-4 sm:px-7 py-5 pb-16">

    <!-- Error -->
    <div v-if="loadError" class="flex items-center gap-2 text-[13px] text-[var(--red)] bg-[var(--red-50)] border border-[#f6c9cf] rounded-[10px] px-4 py-3 mb-4">
      <LucideIcon name="alert-triangle" :size="15" /> {{ loadError }}
    </div>

    <!-- Mobile filter toggle -->
    <button v-if="!isSearchingResults" class="lg:hidden flex items-center justify-between w-full btn btn-ghost mb-4" @click="showFilters = !showFilters">
      <span class="flex items-center gap-2">
        <LucideIcon name="sliders-horizontal" :size="15" /> Filters
        <span v-if="activeFilters.length" class="text-[11px] bg-[var(--blue)] text-white rounded-full w-5 h-5 flex items-center justify-center">{{ activeFilters.length }}</span>
      </span>
      <LucideIcon :name="showFilters ? 'chevron-up' : 'chevron-down'" :size="15" />
    </button>

    <div :class="['catalog-grid', isSearchingResults ? 'no-sidebar' : '']">

      <!-- Filter sidebar -->
      <aside v-if="!isSearchingResults" :class="['bg-white border border-[var(--line)] rounded-xl overflow-hidden lg:sticky lg:top-[6rem]', showFilters ? 'block' : 'hidden lg:block']"
        style="box-shadow:var(--sh1)">
        <div class="px-4 py-3.5 border-b border-[var(--line)] flex items-center justify-between">
          <h3 class="text-[12px] font-bold tracking-[.08em] uppercase text-[var(--navy)] flex items-center gap-2">
            <LucideIcon name="sliders-horizontal" :size="14" class="text-[var(--blue)]" /> Refine
          </h3>
          <button class="text-[11.5px] font-semibold text-[var(--blue)] hover:underline" @click="clearFilters">Clear all</button>
        </div>
        <div v-for="g in filterGroups" :key="g.label" class="px-4 py-4 border-b border-[var(--line-soft)] last:border-0">
          <div class="flex items-center justify-between text-[11px] font-bold tracking-[.07em] uppercase text-[var(--ink)] mb-3">
            {{ g.label }} <LucideIcon name="chevron-down" :size="13" class="text-[var(--faint)]" />
          </div>
          <div class="flex flex-col gap-2">
            <label v-for="opt in g.opts" :key="opt" class="flex items-center gap-2 cursor-pointer group">
              <span :class="['w-4 h-4 border-[1.75px] rounded border-[var(--line)] flex items-center justify-center transition-all flex-shrink-0 group-hover:border-[var(--blue)]',
                (filters[g.key] as string[]).includes(opt) ? '!bg-[var(--blue)] !border-[var(--blue)]' : '']"
                @click="toggleFilter(g.key, opt)">
                <LucideIcon v-if="(filters[g.key] as string[]).includes(opt)" name="check" :size="10" class="text-white" style="stroke-width:3" />
              </span>
              <span class="text-[13px] text-[var(--ink)] flex-1">{{ opt }}</span>
            </label>
          </div>
        </div>
        <div class="px-4 py-4">
          <div class="text-[11px] font-bold tracking-[.07em] uppercase text-[var(--ink)] mb-3">Year</div>
          <div class="flex items-center gap-1.5">
            <input v-model.number="filters.yearFrom" type="number" inputmode="numeric"
              @change="currentPage=1; fetchBooks()"
              class="year-input min-w-0 flex-1 text-[12.5px] px-1.5 py-2 border-[1.5px] border-[var(--line)] rounded-md outline-none text-[var(--ink)] focus:border-[var(--blue)]" />
            <span class="text-[var(--faint)] flex-shrink-0">–</span>
            <input v-model.number="filters.yearTo" type="number" inputmode="numeric"
              @change="currentPage=1; fetchBooks()"
              class="year-input min-w-0 flex-1 text-[12.5px] px-1.5 py-2 border-[1.5px] border-[var(--line)] rounded-md outline-none text-[var(--ink)] focus:border-[var(--blue)]" />
          </div>
        </div>
      </aside>

      <!-- Main content -->
      <div class="min-w-0">
        <!-- Collections -->
        <div v-if="!isSearchingResults" class="mb-5">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-[15px] sm:text-[16px] font-bold text-[var(--navy)] flex items-center gap-2" style="font-family:var(--display)">
              <LucideIcon name="sparkles" :size="17" class="text-[var(--gold)]" /> Browse Collections
            </h2>
          </div>
          <div class="flex gap-2.5 overflow-x-auto pb-1 snap-x">
            <button v-for="col in collections" :key="col.id"
              :class="['flex items-center gap-2.5 px-3 sm:px-4 py-2.5 bg-white border rounded-xl cursor-pointer flex-shrink-0 transition-all duration-200 snap-start',
                activeColl===col.id ? 'border-[var(--blue)] shadow-[0_0_0_3px_rgba(23,99,201,.08)]' : 'border-[var(--line)] hover:border-[var(--blue-100)]']"
              @click="selectCollection(col)">
              <div class="w-8 h-8 rounded-[8px] bg-[var(--bg)] flex items-center justify-center flex-shrink-0">
                <LucideIcon :name="col.icon" :size="17" :class="col.cls" />
              </div>
              <div class="text-left">
                <div class="text-[12.5px] font-semibold text-[var(--navy)] leading-snug whitespace-nowrap">{{ col.label }}</div>
              </div>
            </button>
          </div>
        </div>

        <!-- Active filters -->
        <div v-if="activeFilters.length" class="flex gap-2 flex-wrap items-center mb-4">
          <span class="text-[11px] font-bold tracking-[.06em] uppercase text-[var(--muted)]">Active:</span>
          <button v-for="f in activeFilters" :key="f.label"
            class="inline-flex items-center gap-1 text-[12px] bg-[var(--blue-50)] border border-[var(--blue-100)] rounded-full px-3 py-1 pr-1.5 text-[var(--blue-700)] hover:bg-[var(--red-50)] hover:border-[#f6c9cf] hover:text-[var(--red-600)] transition-all"
            @click="removeFilter(f.type, f.label)">
            {{ f.label }} <LucideIcon name="x" :size="12" />
          </button>
        </div>

        <!-- Results header -->
        <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
          <div class="text-[13px] text-[var(--muted)]">
            <strong class="text-[var(--navy)]">{{ loading ? '…' : total }}</strong> results
            <span v-if="searchQuery" class="hidden sm:inline"> for <em class="not-italic font-semibold text-[var(--blue)]">"{{ searchQuery }}"</em></span>
          </div>
          <div class="flex items-center gap-2">
            <select v-model="sortBy" class="text-[12.5px] px-2.5 py-[7px] border border-[var(--line)] rounded-md outline-none text-[var(--ink)] bg-white cursor-pointer focus:border-[var(--blue)]">
              <option>Title A–Z</option><option>Year (Newest)</option><option>Most Available</option>
            </select>
            <div class="flex gap-1 bg-[var(--bg)] p-1 rounded-md border border-[var(--line)]">
              <button v-for="[mode,icon] in [['grid','layout-grid'],['list','list']]" :key="mode"
                :class="['w-8 h-[30px] rounded flex items-center justify-center cursor-pointer transition-all',
                  viewMode===mode ? 'bg-white text-[var(--blue)] shadow-[var(--sh1)]' : 'text-[var(--faint)] hover:text-[var(--blue)]']"
                @click="viewMode = mode as any">
                <LucideIcon :name="icon" :size="14" />
              </button>
            </div>
          </div>
        </div>

        <!-- Loading skeleton — grid -->
        <div v-if="loading && viewMode==='grid'" class="book-grid">
          <div v-for="i in 8" :key="i" class="bg-white border border-[var(--line)] rounded-[10px] overflow-hidden" style="box-shadow:var(--sh1)">
            <div class="h-[160px] bg-[var(--bg)] animate-pulse"></div>
            <div class="p-3 flex flex-col gap-2">
              <div class="h-4 bg-[var(--bg)] rounded animate-pulse"></div>
              <div class="h-3 bg-[var(--bg)] rounded animate-pulse w-2/3"></div>
              <div class="h-7 bg-[var(--bg)] rounded animate-pulse mt-1"></div>
            </div>
          </div>
        </div>

        <!-- Grid view -->
        <div v-else-if="viewMode==='grid' && books.length" class="book-grid">
          <div v-for="book in books" :key="book.id"
            class="bg-white border border-[var(--line)] rounded-[10px] overflow-hidden cursor-pointer flex flex-col transition-all duration-200 hover:-translate-y-1 hover:border-[var(--blue-100)] hover:shadow-[var(--sh2)]"
            style="box-shadow:var(--sh1)" @click="openDrawer(book)">
            <div class="h-[160px] sm:h-[188px] flex items-center justify-center relative py-4 sm:py-5"
              style="background:linear-gradient(180deg,#F3F2EC,#E8E6DC)">
              <span :class="['absolute top-3 right-3 w-2.5 h-2.5 rounded-full border-2 border-white z-[4]', dotCls(bookStatus(book))]"
                style="box-shadow:0 1px 3px rgba(0,0,0,.2)"></span>
              <div :class="['cover w-[100px] h-[130px] sm:w-[118px] sm:h-[152px]', coverCls(book.id)]">
                <div class="cover-top"><div class="cover-rule"></div><div class="cover-t" style="font-size:11px">{{ book.title }}</div></div>
              </div>
            </div>
            <div class="p-3 sm:p-[13px_14px] flex-1">
              <div class="text-[13px] font-bold text-[var(--navy)] leading-snug mb-1 line-clamp-2" style="font-family:var(--display)">{{ book.title }}</div>
              <div class="text-[11px] text-[var(--muted)] mb-2">{{ book.authors }}</div>
              <div class="flex items-center justify-between">
                <span :class="['badge', statusBadge(bookStatus(book))]">{{ statusLabel(bookStatus(book)) }}</span>
                <span class="text-[11px] text-[var(--faint)]">{{ book.year }}</span>
              </div>
            </div>
            <div class="px-3 py-2.5 border-t border-[var(--line-soft)] flex gap-2">
              <button v-if="isReserved(book)" disabled
                class="flex-1 flex items-center justify-center gap-1.5 text-[11px] font-semibold py-2 rounded-md border-[1.5px] border-[var(--green)] bg-[var(--green-50)] text-[var(--green-600)] cursor-default">
                <LucideIcon name="bookmark-check" :size="13" /> Reserved
              </button>
              <button v-else-if="bookStatus(book)==='available'"
                class="flex-1 flex items-center justify-center gap-1.5 text-[11px] font-semibold py-2 rounded-md border-[1.5px] border-[var(--blue)] bg-[var(--blue)] text-white hover:bg-[var(--blue-700)] transition-all"
                @click.stop="openReserve(book)">
                <LucideIcon name="bookmark" :size="13" /> Reserve
              </button>
              <button v-else-if="bookStatus(book)==='digital'"
                class="flex-1 flex items-center justify-center gap-1.5 text-[11px] font-semibold py-2 rounded-md border-[1.5px] border-[var(--blue)] bg-[var(--blue)] text-white hover:bg-[var(--blue-700)] transition-all"
                @click.stop>
                <LucideIcon name="book-open" :size="13" /> Read
              </button>
              <button v-else
                class="flex-1 flex items-center justify-center gap-1.5 text-[11px] font-semibold py-2 rounded-md border-[1.5px] border-[var(--line)] text-[var(--muted)] hover:border-[var(--blue)] hover:text-[var(--blue)] transition-all"
                @click.stop="openReserve(book)">
                <LucideIcon name="bell" :size="13" /> Join Queue
              </button>
              <button class="w-9 flex items-center justify-center rounded-md border-[1.5px] border-[var(--blue-100)] bg-[var(--blue-50)] text-[var(--blue)] hover:bg-[var(--blue)] hover:text-white transition-all"
                @click.stop="openDrawer(book)">
                <LucideIcon name="eye" :size="13" />
              </button>
            </div>
          </div>
        </div>

        <!-- Loading skeleton — list -->
        <div v-else-if="loading && viewMode==='list'" class="overflow-x-auto rounded-xl border border-[var(--line)]" style="box-shadow:var(--sh1)">
          <div class="min-w-[640px] bg-white">
            <div v-for="i in 5" :key="i" class="px-5 py-4 border-b border-[var(--line-soft)] last:border-0 flex gap-3 items-center">
              <div class="w-[30px] h-[38px] bg-[var(--bg)] rounded animate-pulse flex-shrink-0"></div>
              <div class="flex-1 flex flex-col gap-2">
                <div class="h-3.5 bg-[var(--bg)] rounded animate-pulse w-1/2"></div>
                <div class="h-3 bg-[var(--bg)] rounded animate-pulse w-1/3"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- List view -->
        <div v-else-if="viewMode==='list' && books.length" class="overflow-x-auto rounded-xl border border-[var(--line)]" style="box-shadow:var(--sh1)">
          <div class="min-w-[640px]">
            <div class="grid px-5 py-[11px] bg-[var(--bg)] border-b border-[var(--line)]" style="grid-template-columns:1fr 140px 90px 60px 160px">
              <span v-for="h in ['Title / Author','Subject','Call No.','Year','Actions']" :key="h"
                class="text-[10.5px] font-bold tracking-[.07em] uppercase text-[var(--muted)]">{{ h }}</span>
            </div>
            <div class="bg-white">
              <div v-for="book in books" :key="book.id"
                class="grid px-5 py-3.5 border-b border-[var(--line-soft)] last:border-0 items-center cursor-pointer hover:bg-[#FAFCFF] transition-colors"
                style="grid-template-columns:1fr 140px 90px 60px 160px" @click="openDrawer(book)">
                <div class="flex items-center gap-3">
                  <div :class="['cover flex-shrink-0 w-[30px] h-[38px]', coverCls(book.id)]">
                    <div class="cover-top" style="padding:5px 4px 0 5px"><div class="cover-t" style="font-size:6px;line-height:1.1">{{ book.title.slice(0,16) }}</div></div>
                  </div>
                  <div class="min-w-0">
                    <div class="text-[13px] font-bold text-[var(--navy)] truncate" style="font-family:var(--display)">{{ book.title }}</div>
                    <div class="text-[11px] text-[var(--muted)] flex items-center gap-1.5 mt-px flex-wrap">
                      {{ book.authors }} <span :class="['badge', statusBadge(bookStatus(book))]">{{ statusLabel(bookStatus(book)) }}</span>
                    </div>
                  </div>
                </div>
                <div><span class="tag">{{ book.subject_area }}</span></div>
                <div class="text-[11.5px] text-[var(--muted)] font-mono">{{ book.call_number }}</div>
                <div class="text-[12px] text-[var(--muted)]">{{ book.year }}</div>
                <div class="flex gap-1.5">
                  <button v-if="isReserved(book)" disabled
                    class="text-[11px] font-semibold px-2.5 py-[5px] rounded-md border-[1.5px] border-[var(--green)] bg-[var(--green-50)] text-[var(--green-600)] cursor-default">
                    Reserved
                  </button>
                  <button v-else :class="['text-[11px] font-semibold px-2.5 py-[5px] rounded-md border-[1.5px] transition-all',
                    bookStatus(book)==='available' ? 'bg-[var(--blue)] text-white border-[var(--blue)] hover:bg-[var(--blue-700)]' : 'border-[var(--line)] text-[var(--muted)] hover:border-[var(--blue)] hover:text-[var(--blue)]']"
                    @click.stop="openReserve(book)">
                    {{ bookStatus(book)==='available' ? 'Reserve' : bookStatus(book)==='digital' ? 'Read' : 'Queue' }}
                  </button>
                  <button class="px-2 text-[var(--blue)] border-[1.5px] border-[var(--blue-100)] bg-[var(--blue-50)] rounded-md hover:bg-[var(--blue)] hover:text-white transition-all"
                    @click.stop="openDrawer(book)"><LucideIcon name="eye" :size="13" /></button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-else-if="!loading && !books.length" class="flex flex-col items-center py-20 text-center">
          <LucideIcon name="book-x" :size="48" class="text-[var(--faint)] mb-4" />
          <div class="text-[16px] font-semibold text-[var(--navy)] mb-1">No books found</div>
          <p class="text-[13.5px] text-[var(--muted)] mb-4">Try a different search term or clear your filters.</p>
          <button class="btn btn-outline btn-sm" @click="searchQuery=''; clearFilters()">Clear Search</button>
        </div>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="flex items-center justify-between mt-6 pt-5 border-t border-[var(--line)] flex-wrap gap-3">
          <div class="text-[12.5px] text-[var(--muted)]">
            Page {{ currentPage }} of {{ lastPage }} · {{ total }} results
          </div>
          <div class="flex gap-1">
            <button @click="goToPage(currentPage - 1)" :disabled="currentPage===1"
              :class="['min-w-[32px] h-[32px] px-1.5 flex items-center justify-center border rounded-md text-[12.5px] transition-all',
                currentPage===1 ? 'bg-[var(--bg)] border-[var(--line)] text-[var(--faint)] cursor-not-allowed' : 'bg-white border-[var(--line)] text-[var(--muted)] hover:border-[var(--blue)] hover:text-[var(--blue)] cursor-pointer']">‹</button>
            <template v-for="(pg, i) in pagesArr" :key="pg">
              <span v-if="i > 0 && pg - (pagesArr[i-1] ?? 0) > 1"
                class="min-w-[32px] h-[32px] flex items-center justify-center text-[12.5px] text-[var(--faint)]">…</span>
              <button @click="goToPage(pg)"
                :class="['min-w-[32px] h-[32px] px-1.5 flex items-center justify-center border rounded-md text-[12.5px] cursor-pointer transition-all',
                  pg===currentPage ? 'bg-[var(--blue)] text-white border-[var(--blue)]' : 'bg-white border-[var(--line)] text-[var(--muted)] hover:border-[var(--blue)] hover:text-[var(--blue)]']">{{ pg }}</button>
            </template>
            <button @click="goToPage(currentPage + 1)" :disabled="currentPage===lastPage"
              :class="['min-w-[32px] h-[32px] px-1.5 flex items-center justify-center border rounded-md text-[12.5px] transition-all',
                currentPage===lastPage ? 'bg-[var(--bg)] border-[var(--line)] text-[var(--faint)] cursor-not-allowed' : 'bg-white border-[var(--line)] text-[var(--muted)] hover:border-[var(--blue)] hover:text-[var(--blue)] cursor-pointer']">›</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Drawer scrim -->
  <div :class="['fixed inset-0 z-[80] transition-all duration-300', drawerOpen ? 'opacity-100 visible' : 'opacity-0 invisible']"
    style="background:rgba(14,42,92,.45);backdrop-filter:blur(3px)" @click="closeDrawer"></div>

  <!-- Book detail drawer -->
  <div :class="['fixed top-0 right-0 bottom-0 bg-white z-[81] flex flex-col overflow-y-auto transition-transform duration-300 w-full sm:w-[560px]', drawerOpen ? 'translate-x-0' : 'translate-x-full']"
    style="box-shadow:-12px 0 48px rgba(14,42,92,.14)">
    <div class="px-4 sm:px-6 py-4 border-b border-[var(--line)] flex items-center justify-between sticky top-0 bg-white z-[2]">
      <div class="text-[12px] text-[var(--muted)] flex items-center gap-1.5">
        <LucideIcon name="book-marked" :size="14" /> Catalog <LucideIcon name="chevron-right" :size="14" />
        <b class="text-[var(--blue)] font-semibold">Book Detail</b>
      </div>
      <button class="w-[34px] h-[34px] rounded-md border border-[var(--line)] flex items-center justify-center text-[var(--muted)] hover:bg-[var(--bg)]" @click="closeDrawer">
        <LucideIcon name="x" :size="16" />
      </button>
    </div>
    <div v-if="selectedBook" class="p-4 sm:p-6 flex-1">
      <!-- Book hero -->
      <div class="flex gap-4 sm:gap-5 mb-6">
        <div :class="['cover flex-shrink-0', coverCls(selectedBook.id)]" style="width:90px;height:120px">
          <div class="cover-top"><div class="cover-rule"></div><div class="cover-t" style="font-size:10px">{{ selectedBook.title }}</div></div>
          <div class="cover-bot"><div class="cover-rule2"></div><div class="cover-a" style="font-size:7px">{{ selectedBook.authors }}</div></div>
        </div>
        <div class="min-w-0">
          <div class="text-[17px] sm:text-[21px] font-bold text-[var(--navy)] leading-snug mb-1.5" style="font-family:var(--display)">{{ selectedBook.title }}</div>
          <div class="text-[13px] text-[var(--blue)] mb-2.5 font-medium">{{ selectedBook.authors }}</div>
          <div class="flex gap-1.5 flex-wrap mb-2.5">
            <span v-if="selectedBook.subject_area" class="tag">{{ selectedBook.subject_area }}</span>
            <span v-if="selectedBook.language" class="tag">{{ selectedBook.language }}</span>
          </div>
          <div :class="['flex items-center gap-2 px-3 py-2 rounded-[8px]',
            bookStatus(selectedBook)==='available' ? 'bg-[var(--green-50)]' : 'bg-[var(--red-50)]']">
            <LucideIcon :name="bookStatus(selectedBook)==='available' ? 'circle-check-big' : 'clock'" :size="15"
              :class="bookStatus(selectedBook)==='available' ? 'text-[var(--green-600)] flex-shrink-0' : 'text-[var(--red-600)] flex-shrink-0'" />
            <span :class="['text-[12px] font-semibold',
              bookStatus(selectedBook)==='available' ? 'text-[var(--green-600)]' : 'text-[var(--red-600)]']">
              {{ bookStatus(selectedBook)==='available'
                ? `${selectedBook.available_copies} of ${selectedBook.number_of_copies} copies available`
                : 'Currently on loan — join the waitlist' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Description -->
      <div v-if="selectedBook.description" class="mb-5 pb-5 border-b border-[var(--line-soft)]">
        <div class="text-[10.5px] font-bold tracking-[.09em] uppercase text-[var(--muted)] mb-3 flex items-center gap-2">
          <LucideIcon name="align-left" :size="13" class="text-[var(--blue)]" /> Description
        </div>
        <p class="text-[13.5px] text-[var(--muted)] leading-[1.75] font-light">{{ selectedBook.description }}</p>
      </div>

      <!-- Bibliographic details -->
      <div class="mb-5 pb-5 border-b border-[var(--line-soft)]">
        <div class="text-[10.5px] font-bold tracking-[.09em] uppercase text-[var(--muted)] mb-3 flex items-center gap-2">
          <LucideIcon name="info" :size="13" class="text-[var(--blue)]" /> Bibliographic Details
        </div>
        <div class="grid grid-cols-2">
          <div v-for="[k,v] in [
            ['Publisher', selectedBook.publisher ?? 'N/A'],
            ['Year', selectedBook.year ? String(selectedBook.year) + (selectedBook.edition ? ` (${selectedBook.edition})` : '') : 'N/A'],
            ['ISBN', selectedBook.isbn ?? 'N/A'],
            ['Format', selectedBook.format ?? 'N/A'],
            ['Call No.', selectedBook.call_number ?? 'N/A'],
            ['Shelf', selectedBook.shelf_location ?? 'N/A'],
          ]" :key="k" class="py-2 border-b border-[var(--line-soft)] flex flex-col gap-0.5 even:pl-3">
            <span class="text-[10px] font-semibold tracking-[.06em] uppercase text-[var(--faint)]">{{ k }}</span>
            <span class="text-[12.5px] text-[var(--ink)] font-medium">{{ v }}</span>
          </div>
        </div>
      </div>

      <!-- Copies -->
      <div class="mb-5 pb-5 border-b border-[var(--line-soft)]">
        <div class="text-[10.5px] font-bold tracking-[.09em] uppercase text-[var(--muted)] mb-3 flex items-center gap-2">
          <LucideIcon name="layers" :size="13" class="text-[var(--blue)]" /> Copies &amp; Availability
        </div>
        <div class="flex items-center gap-3 text-[13px] text-[var(--ink)]">
          <span class="font-bold text-[var(--navy)] text-[22px]">{{ selectedBook.available_copies ?? 0 }}</span>
          <span class="text-[var(--muted)]">of {{ selectedBook.number_of_copies ?? 0 }} copies available</span>
        </div>
        <div v-if="selectedBook.call_number" class="text-[12px] text-[var(--muted)] mt-1">
          Call Number: <span class="font-mono text-[var(--ink)]">{{ selectedBook.call_number }}</span>
          <span v-if="selectedBook.shelf_location"> · {{ selectedBook.shelf_location }}</span>
        </div>
      </div>

      <!-- Citation -->
      <div>
        <div class="text-[10.5px] font-bold tracking-[.09em] uppercase text-[var(--muted)] mb-3 flex items-center gap-2">
          <LucideIcon name="quote" :size="13" class="text-[var(--blue)]" /> Cite This Item
        </div>
        <div class="flex gap-1.5 mb-2.5 flex-wrap">
          <button v-for="fmt in ['APA','MLA','Chicago','Harvard']" :key="fmt"
            :class="['chip', citeFmt===fmt ? 'active' : '']" @click="citeFmt=fmt">{{ fmt }}</button>
        </div>
        <div class="relative bg-[var(--bg)] border border-[var(--line)] rounded-[10px] px-4 py-3.5 pr-24 text-[12px] text-[var(--muted)] leading-[1.65]">
          <button class="absolute top-2.5 right-2.5 flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-[5px] bg-white border border-[var(--line)] rounded-md text-[var(--muted)] hover:border-[var(--blue)] hover:text-[var(--blue)] transition-all whitespace-nowrap"
            @click="copyCite">
            <LucideIcon :name="copiedCite ? 'check' : 'copy'" :size="11" /> {{ copiedCite ? 'Copied!' : 'Copy' }}
          </button>
          {{ buildCite(selectedBook) }}
        </div>
      </div>
    </div>

    <div class="px-4 sm:px-6 py-4 border-t border-[var(--line)] bg-[var(--bg)] sticky bottom-0 flex gap-2 flex-wrap">
      <button class="btn btn-primary" @click="openReserve()">
        <LucideIcon name="bookmark" :size="14" />
        {{ selectedBook && bookStatus(selectedBook) === 'available' ? 'Reserve' : 'Join Queue' }}
      </button>
      <button class="btn btn-outline" @click="copyCite"><LucideIcon name="quote" :size="14" /> Cite</button>
    </div>
  </div>

  <!-- Reserve / Waitlist modal -->
  <Teleport to="body">
    <div v-if="modalOpen"
      class="fixed inset-0 z-[90] flex items-end sm:items-center justify-center sm:p-5"
      style="background:rgba(14,42,92,.55);backdrop-filter:blur(4px)"
      @click.self="closeReserve">
      <div class="bg-white rounded-t-[20px] sm:rounded-[20px] w-full sm:max-w-[480px] overflow-hidden max-h-[90vh] overflow-y-auto" style="box-shadow:var(--sh3)">
        <div class="px-5 sm:px-7 pt-5 sm:pt-6 pb-0 flex items-start justify-between">
          <div>
            <div class="text-[19px] sm:text-[21px] font-bold text-[var(--navy)]" style="font-family:var(--display)">
              {{ reserveStep===1
                  ? (selectedBook && bookStatus(selectedBook)==='available' ? 'Reserve a Book' : 'Join the Waitlist')
                  : 'Reservation Confirmed' }}
            </div>
            <div class="text-[13px] text-[var(--muted)] font-light mt-1">
              {{ reserveStep===1
                  ? (selectedBook && bookStatus(selectedBook)==='available' ? 'Place a hold — pick up at the library desk within 48 hours.' : 'You will be notified when this book becomes available.')
                  : '' }}
            </div>
          </div>
          <button v-if="reserveStep===1" class="w-8 h-8 rounded-md border border-[var(--line)] flex items-center justify-center text-[var(--muted)] hover:bg-[var(--bg)] flex-shrink-0" @click="closeReserve">
            <LucideIcon name="x" :size="16" />
          </button>
        </div>
        <div class="px-5 sm:px-7 py-5 sm:py-6">
          <template v-if="reserveStep===1 && selectedBook">
            <!-- Book info -->
            <div class="flex items-center gap-3 p-3 bg-[var(--bg)] border border-[var(--line)] rounded-[10px] mb-4">
              <div :class="['cover flex-shrink-0', coverCls(selectedBook.id)]" style="width:38px;height:48px">
                <div class="cover-top" style="padding:5px 4px 0 5px"><div class="cover-t" style="font-size:6px">{{ selectedBook.title.slice(0,14) }}</div></div>
              </div>
              <div>
                <div class="text-[13px] font-bold text-[var(--navy)]" style="font-family:var(--display)">{{ selectedBook.title }}</div>
                <div class="text-[11.5px] text-[var(--muted)] mt-0.5">{{ selectedBook.authors }}</div>
                <span :class="['badge mt-1', statusBadge(bookStatus(selectedBook))]">{{ statusLabel(bookStatus(selectedBook)) }}</span>
              </div>
            </div>

            <!-- Available book info -->
            <div v-if="bookStatus(selectedBook)==='available'" class="bg-[var(--green-50)] border border-[var(--green)] border-opacity-30 rounded-[10px] p-3.5 mb-4 flex items-start gap-3">
              <LucideIcon name="info" :size="16" class="text-[var(--green-600)] flex-shrink-0 mt-0.5" />
              <div class="text-[12.5px] text-[var(--green-600)]">
                <strong>{{ selectedBook.available_copies }} copies</strong> are available on the shelf. Visit the library desk to borrow this book directly, or confirm below to request a hold.
              </div>
            </div>

            <!-- Waitlist info -->
            <div v-else class="grid grid-cols-2 gap-2 mb-4">
              <div v-for="[l,v] in [['Borrow Period','14 Days'],['Renewals','2×'],['Fine / Day','₦50'],['Queue Position','Next']]" :key="l"
                class="bg-[var(--bg)] border border-[var(--line)] rounded-[10px] p-2.5 text-center">
                <div class="text-[10px] font-bold tracking-[.06em] uppercase text-[var(--faint)] mb-1">{{ l }}</div>
                <div class="text-[15px] font-bold text-[var(--navy)]" style="font-family:var(--display)">{{ v }}</div>
              </div>
            </div>

            <div class="mb-3">
              <label class="block text-[11px] font-bold tracking-[.05em] uppercase text-[var(--muted)] mb-1.5">Member ID</label>
              <input class="w-full text-[14px] px-3.5 py-2.5 border-[1.5px] border-[var(--line)] rounded-[10px] outline-none bg-[var(--bg)] text-[var(--muted)]"
                :value="auth.user?.member_number ?? 'N/A'" readonly />
            </div>

            <div v-if="reserveError" class="flex items-center gap-2 text-[12.5px] text-[var(--red)] bg-[var(--red-50)] rounded-[10px] px-3 py-2.5 mb-3">
              <LucideIcon name="alert-triangle" :size="14" /> {{ reserveError }}
            </div>
          </template>

          <!-- Step 2: confirmation -->
          <template v-else>
            <div class="w-14 h-14 rounded-full bg-[var(--green-50)] flex items-center justify-center mx-auto mb-4">
              <LucideIcon name="check" :size="28" class="text-[var(--green)]" />
            </div>
            <div class="text-[19px] font-bold text-[var(--navy)] text-center mb-2" style="font-family:var(--display)">
              {{ reservationId ? 'Reservation Confirmed' : 'Already Reserved' }}
            </div>
            <p class="text-[13.5px] text-[var(--muted)] text-center font-light mb-5">
              {{ !reservationId
                  ? 'You already have an active reservation for this book. Check “My Account” to view it.'
                  : (selectedBook && bookStatus(selectedBook) === 'available'
                      ? 'Your hold has been placed. Visit the library desk within 48 hours to collect this book.'
                      : 'You are on the waitlist. You will be notified when this book becomes available.') }}
            </p>
            <div v-if="reservationId" class="bg-[var(--bg)] border border-[var(--line)] rounded-[10px] p-4 flex flex-col gap-2.5">
              <div class="flex justify-between items-center">
                <span class="text-[12px] text-[var(--muted)]">Reservation ID</span>
                <span class="text-[13px] font-bold text-[var(--blue)]">{{ reservationId }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-[12px] text-[var(--muted)]">Status</span>
                <span class="badge" :class="selectedBook && bookStatus(selectedBook)==='available' ? 'b-green' : 'b-gold'">
                  {{ selectedBook && bookStatus(selectedBook)==='available' ? 'Ready for pickup' : 'Waitlisted' }}
                </span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-[12px] text-[var(--muted)]">Book</span>
                <span class="text-[12px] font-semibold text-[var(--navy)] text-right max-w-[60%]">{{ selectedBook?.title?.slice(0, 30) }}{{ (selectedBook?.title?.length ?? 0) > 30 ? '…' : '' }}</span>
              </div>
            </div>
          </template>
        </div>
        <div class="px-5 sm:px-7 pb-5 sm:pb-6 flex gap-2">
          <template v-if="reserveStep===1">
            <button class="btn btn-primary btn-block" :disabled="reserving" @click="confirmReserve">
              <LucideIcon :name="reserving ? 'refresh-cw' : 'check'" :size="14" />
              {{ reserving ? 'Processing…' : (selectedBook && bookStatus(selectedBook)==='available' ? 'Confirm Hold Request' : 'Join Waitlist') }}
            </button>
            <button class="btn btn-outline" @click="closeReserve">Cancel</button>
          </template>
          <template v-else>
            <button class="btn btn-primary btn-block" @click="closeReserve">Done</button>
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.year-input {
  -moz-appearance: textfield;
}
.year-input::-webkit-outer-spin-button,
.year-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.catalog-grid {
  display: grid;
  gap: 1.5rem;
  align-items: start;
  grid-template-columns: 1fr;
}
@media (min-width: 1024px) {
  .catalog-grid { grid-template-columns: 248px 1fr; }
  .catalog-grid.no-sidebar { grid-template-columns: 1fr; }
}
.book-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
}
@media (min-width: 480px) {
  .book-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
}
@media (min-width: 768px) {
  .book-grid { grid-template-columns: repeat(auto-fill, minmax(216px, 1fr)); }
}
</style>
