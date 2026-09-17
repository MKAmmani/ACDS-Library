<script setup lang="ts">
import { ref, computed, nextTick, onMounted } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet } from '@/api/http'
import { renderAsync } from 'docx-preview'

const auth = useAuthStore()

const BASE_URL    = import.meta.env.VITE_API_URL as string
const STORAGE_URL = BASE_URL.replace(/\/api$/, '') + '/storage/'

const searchQuery   = ref('')
const loading       = ref(false)
const resources     = ref<any[]>([])
const currentPage   = ref(1)
const lastPage      = ref(1)
const total         = ref(0)
const downloading   = ref<number | null>(null)
const opening       = ref<number | null>(null)
const showReader    = ref(false)
const readerUrl     = ref('')
const readerTitle   = ref('')
const readerMode    = ref<'pdf' | 'docx' | 'unsupported'>('pdf')
const readerLoading = ref(false)
const readerError   = ref('')
const readerBlobUrl = ref('')
const readerDoc     = ref<any>(null)
const docxHost      = ref<HTMLElement | null>(null)

function fileKind(item: any): 'pdf' | 'img' | 'docx' | 'other' {
  const t = (item.file_type ?? '').toLowerCase()
  if (t === 'pdf') return 'pdf'
  if (t === 'docx') return 'docx'
  if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(t)) return 'img'
  return 'other'
}

let searchTimer: ReturnType<typeof setTimeout>
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
    params.set('page', String(currentPage.value))

    const data = await apiGet<any>(`/e-resources?${params}`)
    resources.value    = data.data      ?? []
    total.value        = data.total     ?? 0
    lastPage.value      = data.last_page ?? 1
    currentPage.value  = data.current_page ?? 1
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

function formatFileSize(bytes: number | null) {
  if (!bytes) return null
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

async function openResource(item: any) {
  const kind = fileKind(item)
  readerDoc.value   = item
  readerTitle.value = item.title
  readerError.value = ''
  revokeReaderBlob()
  readerUrl.value = ''

  const isDocx = kind === 'docx'
  const previewable = kind === 'pdf' || kind === 'img' || isDocx
  if (!previewable) {
    readerMode.value = 'unsupported'
    showReader.value = true
    return
  }

  readerMode.value    = isDocx ? 'docx' : 'pdf'
  readerLoading.value = true
  opening.value       = item.id
  showReader.value    = true

  try {
    const headers: Record<string, string> = {}
    if (auth.token) headers['Authorization'] = `Bearer ${auth.token}`
    // base64 JSON envelope — avoids download-manager (IDM) interception of PDFs.
    const res = await fetch(`${BASE_URL}/e-resources/${item.id}/inline`, { headers })
    if (!res.ok) throw new Error('Could not load the document.')
    const json = await res.json()
    const blob = await (await fetch(`data:${json.mime};base64,${json.data}`)).blob()

    if (isDocx) {
      const buffer = await blob.arrayBuffer()
      await nextTick()
      if (docxHost.value) {
        docxHost.value.innerHTML = ''
        await renderAsync(buffer, docxHost.value, undefined, {
          className: 'docx', inWrapper: true, ignoreWidth: false,
        })
      }
    } else {
      readerBlobUrl.value = URL.createObjectURL(blob)
      readerUrl.value     = readerBlobUrl.value
    }
  } catch (e: any) {
    readerError.value = e.message ?? 'Could not display the document.'
  } finally {
    readerLoading.value = false
    opening.value = null
  }
}

function revokeReaderBlob() {
  if (readerBlobUrl.value) {
    URL.revokeObjectURL(readerBlobUrl.value)
    readerBlobUrl.value = ''
  }
}

function closeReader() {
  showReader.value  = false
  readerUrl.value   = ''
  readerError.value = ''
  revokeReaderBlob()
  if (docxHost.value) docxHost.value.innerHTML = ''
}

async function downloadItem(item: any) {
  downloading.value = item.id
  try {
    const headers: Record<string, string> = { Accept: 'application/json' }
    if (auth.token) headers['Authorization'] = `Bearer ${auth.token}`
    const res = await fetch(`${BASE_URL}/e-resources/${item.id}/download`, { headers })
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
  <!-- Page header -->
  <div class="relative overflow-hidden" style="background:linear-gradient(115deg,#0B2E63 0%,var(--blue) 48%,var(--sky) 100%);padding:40px 0 0">
    <div class="relative z-[2] max-w-[1260px] mx-auto px-4 sm:px-7">
      <div class="inline-flex items-center gap-2 text-[11px] font-semibold tracking-[.18em] uppercase text-[var(--gold)] mb-3">
        <span class="w-6 h-px bg-[var(--gold)]"></span> External Resources
      </div>
      <h1 class="text-[clamp(24px,3.5vw,40px)] font-bold text-white leading-tight tracking-tight mb-2" style="font-family:var(--display)">
        E-Resources <em class="not-italic text-[var(--gold)]">Collection</em>
      </h1>
      <p class="hidden sm:block text-[14.5px] text-white/70 font-light max-w-[520px] leading-[1.7] mb-5">
        E-books, reports and papers curated from outside the institution — open them online or download for offline reading.
      </p>
      <!-- Search bar -->
      <div class="bg-white rounded-t-xl p-5" style="box-shadow:0 -10px 36px rgba(11,46,99,.18)">
        <div class="flex gap-2.5">
          <div class="flex-1 flex items-center gap-3 border-[1.5px] border-[var(--line)] rounded-[10px] px-4 transition-all focus-within:border-[var(--blue)]">
            <LucideIcon name="search" :size="18" class="text-[var(--faint)] flex-shrink-0" />
            <input v-model="searchQuery" @input="onSearchInput" type="text" placeholder="Search e-resources by title or author…"
              class="flex-1 border-none outline-none py-3 text-[15px] text-[var(--ink)] bg-transparent placeholder:text-[var(--faint)]" />
          </div>
          <button class="btn btn-primary btn-sm" @click="currentPage=1; fetchResources()"><LucideIcon name="search" :size="15" /> Search</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Body -->
  <div class="max-w-[1260px] mx-auto px-4 sm:px-7 py-6 pb-16">
    <div class="text-[13px] text-[var(--muted)] mb-4">
      <strong class="text-[var(--navy)]">{{ loading ? '…' : total }}</strong> e-resources
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
      <div v-for="r in resources" :key="r.id"
        class="bg-white border border-[var(--line)] rounded-xl overflow-hidden transition-all duration-200 hover:-translate-y-1 hover:border-[var(--blue-100)] hover:shadow-[var(--sh2)]"
        style="box-shadow:var(--sh1)">
        <!-- Cover strip -->
        <div class="h-[120px] flex items-center justify-center relative" style="background:linear-gradient(180deg,#F3F2EC,#E8E6DC)">
          <img v-if="coverSrc(r)" :src="coverSrc(r)!" :alt="r.title" class="h-full w-full object-cover absolute inset-0" />
          <div v-else :class="['cover w-[76px] h-[98px]', coverCls(r.id)]">
            <div class="cover-top" style="padding:6px 5px 0 7px"><div class="cover-t" style="font-size:7.5px">{{ r.title }}</div></div>
          </div>
          <span v-if="!r.file_path" class="absolute top-2.5 right-2.5 badge b-gray">No File</span>
        </div>
        <!-- Body -->
        <div class="p-4">
          <div class="flex items-center gap-2 mb-2">
            <span class="badge b-blue"><LucideIcon name="link" :size="11" /> {{ r.format || 'E-Resource' }}</span>
            <span v-if="r.year" class="text-[11px] text-[var(--faint)]">{{ r.year }}</span>
          </div>
          <div class="text-[14px] font-bold text-[var(--navy)] leading-snug mb-1 line-clamp-2" style="font-family:var(--display)">{{ r.title }}</div>
          <div class="text-[12px] text-[var(--muted)] mb-2">{{ r.authors || r.publisher || '—' }}</div>
          <div v-if="r.file_size || r.file_type || r.isbn" class="text-[11.5px] text-[var(--faint)] mb-3 flex items-center gap-2">
            <span v-if="r.file_type" class="uppercase font-semibold">{{ r.file_type }}</span>
            <span v-if="r.file_size">· {{ formatFileSize(r.file_size) }}</span>
            <span v-if="r.isbn">· {{ r.isbn }}</span>
          </div>
          <div class="flex gap-2">
            <button class="flex-1 btn btn-primary btn-sm"
              :disabled="!r.file_path || opening === r.id" @click="openResource(r)">
              <LucideIcon :name="opening===r.id ? 'refresh-cw' : 'book-open'" :size="14" :class="opening===r.id ? 'animate-spin' : ''" />
              {{ opening === r.id ? 'Opening…' : 'Open' }}
            </button>
            <button class="btn btn-ghost btn-sm" :disabled="!r.file_path || downloading === r.id"
              @click="downloadItem(r)">
              <LucideIcon :name="downloading===r.id ? 'refresh-cw' : 'download'" :size="14" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="flex flex-col items-center py-16 text-center">
      <LucideIcon name="link" :size="44" class="text-[var(--faint)] mb-3" />
      <div class="text-[15px] font-semibold text-[var(--navy)] mb-1">No e-resources found</div>
      <p class="text-[13px] text-[var(--muted)]">Try a different search term.</p>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="flex items-center justify-between mt-8 pt-6 border-t border-[var(--line)] flex-wrap gap-3">
      <div class="text-[12.5px] text-[var(--muted)]">Page {{ currentPage }} of {{ lastPage }} · {{ total }} e-resources</div>
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
          <a v-if="readerMode === 'pdf' && readerUrl" :href="readerUrl" target="_blank"
            class="btn btn-ghost btn-sm flex items-center gap-1.5 text-[12.5px]">
            <LucideIcon name="external-link" :size="14" /> Open in tab
          </a>
          <button v-if="readerDoc" class="btn btn-ghost btn-sm flex items-center gap-1.5 text-[12.5px]"
            :disabled="downloading === readerDoc.id" @click="downloadItem(readerDoc)">
            <LucideIcon name="download" :size="14" /> Download
          </button>
          <button class="w-8 h-8 flex items-center justify-center rounded-md border border-[var(--line)] text-[var(--muted)] hover:bg-[var(--bg)]"
            @click="closeReader">
            <LucideIcon name="x" :size="16" />
          </button>
        </div>
      </div>

      <!-- PDF / image -->
      <div v-if="readerMode === 'pdf'" class="flex-1 relative" style="background:#525659">
        <div v-if="readerLoading" class="absolute inset-0 flex items-center justify-center gap-2.5 text-white text-[14px]">
          <LucideIcon name="refresh-cw" :size="18" class="animate-spin" /> Loading document…
        </div>
        <div v-else-if="readerError" class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-white text-[14px] px-6 text-center">
          <LucideIcon name="alert-triangle" :size="22" /> {{ readerError }}
        </div>
        <iframe v-if="readerUrl" :src="readerUrl" class="w-full h-full border-none" allow="fullscreen"></iframe>
      </div>

      <!-- DOCX -->
      <div v-else-if="readerMode === 'docx'" class="flex-1 overflow-auto relative" style="background:#f3f3f3">
        <div v-if="readerLoading" class="absolute inset-0 flex items-center justify-center gap-2.5 text-[var(--muted)] text-[14px]">
          <LucideIcon name="refresh-cw" :size="18" class="animate-spin" /> Rendering document…
        </div>
        <div v-if="readerError" class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-[var(--red)] text-[14px] px-6 text-center">
          <LucideIcon name="alert-triangle" :size="22" /> {{ readerError }}
        </div>
        <div ref="docxHost" class="py-6"></div>
      </div>

      <!-- Unsupported -->
      <div v-else class="flex-1 flex flex-col items-center justify-center gap-3.5 bg-white px-8 text-center">
        <LucideIcon name="file-text" :size="46" class="text-[var(--faint)]" />
        <div class="text-[15px] font-semibold text-[var(--navy)]">This file type can't be previewed in the browser</div>
        <div class="text-[13px] text-[var(--muted)] max-w-[380px]">Legacy Word (.doc) and EPUB files have no built-in browser viewer. Download the file to open it on your device.</div>
        <button v-if="readerDoc" class="btn btn-primary btn-sm mt-1" :disabled="downloading === readerDoc.id" @click="downloadItem(readerDoc)">
          <LucideIcon name="download" :size="14" /> Download file
        </button>
      </div>
    </div>
  </Teleport>
</template>
