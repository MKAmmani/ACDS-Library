<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet } from '@/api/http'

const auth = useAuthStore()

const BASE_URL    = import.meta.env.VITE_API_URL as string
const STORAGE_URL = BASE_URL.replace(/\/api$/, '') + '/storage/'

const searchQuery   = ref('')
const activeFormat  = ref('All')
const activeTopic   = ref('All')
const loading       = ref(false)
const resources     = ref<any[]>([])
const currentPage   = ref(1)
const lastPage      = ref(1)
const total         = ref(0)
const downloading    = ref<number | null>(null)
const showReader     = ref(false)
const readerUrl      = ref('')
const readerTitle    = ref('')

const formats = ['All', 'eBook', 'Report', 'Thesis', 'Policy Brief']
const topics  = ['All', 'Democracy', 'Governance', 'Electoral', 'History', 'Law', 'Civil Society']

let searchTimer: ReturnType<typeof setTimeout>

watch([activeFormat, activeTopic], () => {
  currentPage.value = 1
  fetchResources()
})

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    fetchResources()
  }, 400)
}

async function fetchResources() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (searchQuery.value.trim()) params.set('search', searchQuery.value.trim())
    if (activeFormat.value !== 'All')  params.set('format', activeFormat.value)
    if (activeTopic.value !== 'All')   params.set('subject_area', activeTopic.value)
    params.set('page', String(currentPage.value))

    const data = await apiGet<any>(`/repository?${params}`)
    resources.value   = data.data      ?? []
    total.value       = data.total     ?? 0
    lastPage.value    = data.last_page ?? 1
    currentPage.value = data.current_page ?? 1
  } catch {}
  loading.value = false
}

onMounted(fetchResources)

function goToPage(p: number) {
  if (p < 1 || p > lastPage.value) return
  currentPage.value = p
  fetchResources()
}

function coverSrc(item: any) {
  return item.cover_image ? STORAGE_URL + item.cover_image : null
}

const coverClasses = ['cv-navy','cv-burgundy','cv-forest','cv-charcoal','cv-slate','cv-ochre']
function coverCls(id: number) { return coverClasses[id % coverClasses.length] }

const formatIcon  = (f: string) => ({ eBook:'book-open', Report:'receipt-text', Thesis:'scroll', 'Policy Brief':'newspaper' }[f] ?? 'book')
const formatBadge = (f: string) => ({ eBook:'b-blue', Report:'b-green', Thesis:'b-purple', 'Policy Brief':'b-gold' }[f] ?? 'b-gray')

function formatFileSize(bytes: number | null) {
  if (!bytes) return null
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function readOnline(item: any) {
  // Point the iframe directly at the public /read endpoint. The backend serves
  // the file inline (Content-Disposition: inline), so the browser's native PDF
  // viewer renders it. No fetch/blob — that would require CORS read access; an
  // iframe just displaying a cross-origin PDF does not.
  readerUrl.value   = `${BASE_URL}/repository/${item.id}/read`
  readerTitle.value = item.title
  showReader.value  = true
}

function closeReader() {
  showReader.value = false
  readerUrl.value  = ''
}

async function downloadItem(item: any) {
  if (!auth.token) return
  downloading.value = item.id
  try {
    const res = await fetch(`${BASE_URL}/repository/${item.id}/download`, {
      headers: {
        Authorization: `Bearer ${auth.token}`,
        Accept: 'application/json',
      },
    })
    if (!res.ok) throw new Error('Download failed.')
    const blob = await res.blob()
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    a.href     = url
    a.download = `${item.title}.${item.file_type ?? 'pdf'}`
    a.click()
    URL.revokeObjectURL(url)
  } catch {}
  downloading.value = null
}

const pages = computed(() => {
  const p = currentPage.value, l = lastPage.value
  const set = new Set([1, l, p - 1, p, p + 1].filter(x => x >= 1 && x <= l))
  return [...set].sort((a, b) => a - b)
})
</script>

<template>
  <!-- Hero -->
  <div class="relative overflow-hidden" style="background:linear-gradient(115deg,#0B2E63 0%,var(--blue) 48%,var(--sky) 100%);padding:46px 0 0">
    <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(circle at 80% 20%,rgba(242,165,12,.14),transparent 50%)"></div>
    <div class="relative z-[2] max-w-[1260px] mx-auto px-4 sm:px-7">
      <div class="inline-flex items-center gap-2 text-[11px] font-semibold tracking-[.18em] uppercase text-[var(--gold)] mb-3.5">
        <span class="w-6 h-px bg-[var(--gold)]"></span> Digital Collection
      </div>
      <h1 class="text-[clamp(28px,4vw,44px)] font-bold text-white leading-[1.08] tracking-tight mb-2.5" style="font-family:var(--display)">
        E-Library <em class="not-italic text-[var(--gold)]">Resources</em>
      </h1>
      <p class="hidden sm:block text-[14.5px] text-white/70 font-light max-w-[560px] leading-[1.7] mb-6">
        Access digital resources — eBooks, research reports, policy briefs, and theses — from any device, 24/7.
      </p>
      <!-- Search -->
      <div class="bg-white rounded-t-xl p-6 pb-5" style="box-shadow:0 -10px 36px rgba(11,46,99,.18)">
        <div class="flex gap-2.5">
          <div class="flex-1 flex items-center gap-3 border-[1.5px] border-[var(--line)] rounded-[10px] px-4 transition-all focus-within:border-[var(--blue)] focus-within:shadow-[0_0_0_4px_rgba(23,99,201,.09)]">
            <LucideIcon name="search" :size="20" class="text-[var(--faint)] flex-shrink-0" />
            <input v-model="searchQuery" @input="onSearchInput" type="text"
              placeholder="Search digital resources by title or author…"
              class="flex-1 border-none outline-none py-3.5 text-[16px] text-[var(--ink)] bg-transparent placeholder:text-[var(--faint)]" />
            <button v-if="searchQuery" @click="searchQuery=''; onSearchInput()" class="p-1 text-[var(--faint)] hover:text-[var(--muted)] rounded">
              <LucideIcon name="x" :size="15" />
            </button>
          </div>
          <button class="btn btn-primary" @click="currentPage=1; fetchResources()">
            <LucideIcon name="search" :size="15" /> Search
          </button>
        </div>
        <div class="hidden sm:flex gap-5 mt-4 pt-4 border-t border-[var(--line-soft)]">
          <div v-for="[icon,val,lbl] in [['monitor', String(total || '…'),'Digital resources'],['download','Open','Open access'],['globe','24/7','Remote access']]"
               :key="lbl" class="flex items-center gap-1.5 text-[12.5px] text-[var(--muted)]">
            <LucideIcon :name="icon" :size="15" class="text-[var(--blue)]" />
            <strong class="text-[var(--navy)]">{{ val }}</strong> {{ lbl }}
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Body -->
  <div class="max-w-[1260px] mx-auto px-4 sm:px-7 py-6 pb-16">
    <!-- Format filter -->
    <div class="flex gap-2 flex-wrap mb-3">
      <button v-for="f in formats" :key="f"
        :class="['chip', activeFormat===f ? 'active' : '']" @click="activeFormat=f">{{ f }}</button>
    </div>
    <!-- Topic filter -->
    <div class="flex gap-2 flex-wrap items-center mb-6">
      <span class="text-[12px] font-semibold text-[var(--muted)]">Topic:</span>
      <button v-for="t in topics" :key="t"
        :class="['chip', activeTopic===t ? 'active' : '']" @click="activeTopic=t">{{ t }}</button>
    </div>

    <!-- Results header -->
    <div class="flex items-center justify-between mb-4">
      <div class="text-[13.5px] text-[var(--muted)]">
        <strong class="text-[var(--navy)]">{{ loading ? '…' : total }}</strong> resources found
      </div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr))">
      <div v-for="i in 8" :key="i" class="bg-white border border-[var(--line)] rounded-xl overflow-hidden" style="box-shadow:var(--sh1)">
        <div class="h-[120px] bg-[var(--bg)] animate-pulse"></div>
        <div class="p-4 flex flex-col gap-3">
          <div class="h-4 bg-[var(--bg)] rounded animate-pulse"></div>
          <div class="h-3 bg-[var(--bg)] rounded animate-pulse w-2/3"></div>
          <div class="h-8 bg-[var(--bg)] rounded animate-pulse mt-2"></div>
        </div>
      </div>
    </div>

    <!-- Resource cards -->
    <div v-else-if="resources.length" class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr))">
      <div v-for="res in resources" :key="res.id"
        class="bg-white border border-[var(--line)] rounded-xl overflow-hidden cursor-pointer transition-all duration-200 hover:-translate-y-1 hover:border-[var(--blue-100)] hover:shadow-[var(--sh2)]"
        style="box-shadow:var(--sh1)">
        <!-- Cover strip -->
        <div class="h-[120px] flex items-center justify-center relative" style="background:linear-gradient(180deg,#F3F2EC,#E8E6DC)">
          <img v-if="coverSrc(res)" :src="coverSrc(res)!" :alt="res.title"
            class="h-full w-full object-cover absolute inset-0" />
          <div v-else :class="['cover w-[76px] h-[98px]', coverCls(res.id)]">
            <div class="cover-top" style="padding:6px 5px 0 7px"><div class="cover-t" style="font-size:7.5px">{{ res.title }}</div></div>
          </div>
          <span v-if="!res.file_path" class="absolute top-2.5 right-2.5 badge b-gray">No File</span>
        </div>
        <!-- Body -->
        <div class="p-4">
          <div class="flex items-center gap-2 mb-2">
            <span v-if="res.format" :class="['badge', formatBadge(res.format)]">
              <LucideIcon :name="formatIcon(res.format)" :size="11" /> {{ res.format }}
            </span>
            <span v-if="res.year" class="text-[11px] text-[var(--faint)]">{{ res.year }}</span>
          </div>
          <div class="text-[14px] font-bold text-[var(--navy)] leading-snug mb-1 line-clamp-2" style="font-family:var(--display)">{{ res.title }}</div>
          <div class="text-[12px] text-[var(--muted)] mb-2">{{ res.authors }}</div>
          <div v-if="res.file_size || res.file_type" class="text-[11.5px] text-[var(--faint)] mb-3 flex items-center gap-2">
            <span v-if="res.file_type" class="uppercase font-semibold">{{ res.file_type }}</span>
            <span v-if="res.file_size">· {{ formatFileSize(res.file_size) }}</span>
          </div>
          <div class="flex gap-2">
            <button class="flex-1 btn btn-primary btn-sm" :disabled="!res.file_path" @click="readOnline(res)">
              <LucideIcon name="book-open" :size="14" /> Read Online
            </button>
            <button class="btn btn-ghost btn-sm" :disabled="!res.file_path || !auth.token || downloading === res.id"
              @click="downloadItem(res)">
              <LucideIcon :name="downloading===res.id ? 'refresh-cw' : 'download'" :size="14" />
            </button>
            <button class="btn btn-ghost btn-sm">
              <LucideIcon name="bookmark" :size="14" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="flex flex-col items-center py-20 text-center">
      <LucideIcon name="monitor" :size="48" class="text-[var(--faint)] mb-4" />
      <div class="text-[16px] font-semibold text-[var(--navy)] mb-1">No resources found</div>
      <p class="text-[13.5px] text-[var(--muted)]">Try adjusting your filters or search term.</p>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="flex items-center justify-between mt-8 pt-6 border-t border-[var(--line)] flex-wrap gap-3">
      <div class="text-[12.5px] text-[var(--muted)]">
        Page {{ currentPage }} of {{ lastPage }} · {{ total }} resources
      </div>
      <div class="flex gap-1">
        <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1"
          :class="['min-w-[32px] h-[32px] px-1.5 flex items-center justify-center border rounded-md text-[12.5px] cursor-pointer transition-all',
            currentPage===1 ? 'bg-[var(--bg)] border-[var(--line)] text-[var(--faint)] cursor-not-allowed' : 'bg-white border-[var(--line)] text-[var(--muted)] hover:border-[var(--blue)] hover:text-[var(--blue)]']">
          ‹
        </button>
        <template v-for="(pg, i) in pages" :key="pg">
          <span v-if="i > 0 && pg - (pages[i-1] ?? 0) > 1"
            class="min-w-[32px] h-[32px] flex items-center justify-center text-[12.5px] text-[var(--faint)]">…</span>
          <button @click="goToPage(pg)"
            :class="['min-w-[32px] h-[32px] px-1.5 flex items-center justify-center border rounded-md text-[12.5px] cursor-pointer transition-all',
              pg===currentPage ? 'bg-[var(--blue)] text-white border-[var(--blue)]' : 'bg-white border-[var(--line)] text-[var(--muted)] hover:border-[var(--blue)] hover:text-[var(--blue)]']">
            {{ pg }}
          </button>
        </template>
        <button @click="goToPage(currentPage + 1)" :disabled="currentPage === lastPage"
          :class="['min-w-[32px] h-[32px] px-1.5 flex items-center justify-center border rounded-md text-[12.5px] cursor-pointer transition-all',
            currentPage===lastPage ? 'bg-[var(--bg)] border-[var(--line)] text-[var(--faint)] cursor-not-allowed' : 'bg-white border-[var(--line)] text-[var(--muted)] hover:border-[var(--blue)] hover:text-[var(--blue)]']">
          ›
        </button>
      </div>
    </div>
  </div>

  <!-- ── Document Reader Modal ── -->
  <Teleport to="body">
    <div v-if="showReader" class="fixed inset-0 z-[999] flex flex-col" style="background:rgba(0,0,0,.72)">
      <div class="flex items-center justify-between gap-4 px-5 py-3 bg-white border-b border-[var(--line)] flex-shrink-0">
        <div class="text-[13.5px] font-semibold text-[var(--navy)] truncate">{{ readerTitle }}</div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <a :href="readerUrl" target="_blank"
            class="btn btn-ghost btn-sm flex items-center gap-1.5 text-[12.5px]">
            <LucideIcon name="external-link" :size="14" /> Open in tab
          </a>
          <button class="w-8 h-8 flex items-center justify-center rounded-md border border-[var(--line)] text-[var(--muted)] hover:bg-[var(--bg)]"
            @click="closeReader">
            <LucideIcon name="x" :size="16" />
          </button>
        </div>
      </div>
      <iframe :src="readerUrl" class="flex-1 w-full border-none bg-white" allow="fullscreen"></iframe>
    </div>
  </Teleport>
</template>
