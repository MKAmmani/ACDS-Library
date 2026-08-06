<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import LucideIcon from '@/components/LucideIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPost } from '@/api/http'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

interface ArchivedBook {
  id: number
  title: string
  authors: string | null
  publisher: string | null
  year: number | null
  subject_area: string
  call_number: string | null
  shelf_location: string | null
  description: string | null
  archive_reason: string | null
}

const REASONS = [
  'Historical or Cultural Value', 'Preservation', 'Low Circulation',
  'Special Collections', 'Legal or Institutional Requirement',
  'Out of Print', 'Research Significance',
]

const searchQuery  = ref('')
const activeReason = ref('All')
const loading      = ref(false)
const loadError    = ref('')
const items        = ref<ArchivedBook[]>([])
const currentPage  = ref(1)
const lastPage     = ref(1)
const total        = ref(0)

function goSignIn() {
  router.push({ path: '/auth/login', query: { redirect: route.path } })
}

let searchTimer: ReturnType<typeof setTimeout>
function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { currentPage.value = 1; fetchArchives() }, 400)
}

watch(activeReason, () => { currentPage.value = 1; fetchArchives() })

async function fetchArchives() {
  loading.value   = true
  loadError.value = ''
  try {
    const params = new URLSearchParams()
    params.set('archived', '1')
    if (searchQuery.value.trim())     params.set('search', searchQuery.value.trim())
    if (activeReason.value !== 'All') params.set('archive_reason', activeReason.value)
    params.set('page', String(currentPage.value))

    const data = await apiGet<any>(`/books?${params}`)
    items.value        = data.data      ?? []
    total.value        = data.total     ?? 0
    lastPage.value     = data.last_page ?? 1
    currentPage.value  = data.current_page ?? 1
  } catch (e: any) {
    loadError.value = e.message ?? 'Could not load the archives.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchArchives)

function goToPage(p: number) {
  if (p < 1 || p > lastPage.value) return
  currentPage.value = p
  fetchArchives()
}

const reasonIcon = (r: string | null) => ({
  'Historical or Cultural Value':       'scroll',
  'Preservation':                       'shield',
  'Low Circulation':                    'trending-up',
  'Special Collections':                'archive',
  'Legal or Institutional Requirement': 'receipt-text',
  'Out of Print':                       'book',
  'Research Significance':              'sparkles',
}[r ?? ''] ?? 'archive')

// ── Request from Staff ──────────────────────────────────────────────────────
const requestOpen    = ref(false)
const requestBook    = ref<ArchivedBook | null>(null)
const requestBody    = ref('')
const requestSending = ref(false)
const requestError   = ref('')
const requestSent    = ref(false)

function openRequest(book: ArchivedBook) {
  if (!auth.token) { goSignIn(); return }
  requestBook.value  = book
  requestBody.value  = `I would like to request access to "${book.title}"${book.call_number ? ` (Call No. ${book.call_number})` : ''} from the archives. Please let me know how I can view or borrow this item.`
  requestError.value = ''
  requestSent.value  = false
  requestOpen.value  = true
}

async function sendRequest() {
  if (!requestBook.value || !requestBody.value.trim()) return
  requestSending.value = true
  requestError.value   = ''
  try {
    await apiPost('/inbox/submit', {
      subject:    `Archive Retrieval — ${requestBook.value.title}`,
      body:       requestBody.value,
      query_type: 'reference',
    }, auth.token ?? undefined)
    requestSent.value = true
  } catch (e: any) {
    requestError.value = e.message ?? 'Could not send your request.'
  } finally {
    requestSending.value = false
  }
}
</script>

<template>
  <!-- Page header -->
  <div class="relative overflow-hidden" style="background:linear-gradient(115deg,#2B2D31 0%,#33526E 60%,#22304F 100%);padding:40px 0 0">
    <div class="relative z-[2] max-w-[1260px] mx-auto px-4 sm:px-7">
      <div class="inline-flex items-center gap-2 text-[11px] font-semibold tracking-[.18em] uppercase text-[var(--gold)] mb-3">
        <span class="w-6 h-px bg-[var(--gold)]"></span> Special Collections
      </div>
      <h1 class="text-[clamp(24px,3.5vw,40px)] font-bold text-white leading-tight tracking-tight mb-2" style="font-family:var(--display)">
        Historical <em class="not-italic text-[var(--gold)]">Archives</em>
      </h1>
      <p class="hidden sm:block text-[14.5px] text-white/70 font-light max-w-[540px] leading-[1.7] mb-5">
        Titles moved out of open-shelf circulation into special storage for preservation, rarity, or historical significance. Request access through library staff.
      </p>
      <!-- Search bar -->
      <div class="bg-white rounded-t-xl p-5" style="box-shadow:0 -10px 36px rgba(11,46,99,.2)">
        <div class="flex gap-2.5">
          <div class="flex-1 flex items-center gap-3 border-[1.5px] border-[var(--line)] rounded-[10px] px-4 transition-all focus-within:border-[var(--blue)]">
            <LucideIcon name="search" :size="18" class="text-[var(--faint)] flex-shrink-0" />
            <input v-model="searchQuery" @input="onSearchInput" type="text" placeholder="Search archives by title, author, or call number…"
              class="flex-1 border-none outline-none py-3 text-[15px] text-[var(--ink)] bg-transparent placeholder:text-[var(--faint)]" />
          </div>
          <button class="btn btn-primary btn-sm" @click="currentPage=1; fetchArchives()"><LucideIcon name="search" :size="15" /> Search</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Body -->
  <div class="max-w-[1260px] mx-auto px-4 sm:px-7 py-6 pb-16">
    <!-- Reason filter -->
    <div class="flex gap-2 flex-wrap items-center mb-6">
      <span class="text-[12px] font-semibold text-[var(--muted)] mr-1">Reason:</span>
      <button :class="['chip', activeReason==='All' ? 'active' : '']" @click="activeReason='All'">All</button>
      <button v-for="r in REASONS" :key="r" :class="['chip', activeReason===r ? 'active' : '']" @click="activeReason=r">{{ r }}</button>
    </div>

    <div class="text-[13px] text-[var(--muted)] mb-4">
      <strong class="text-[var(--navy)]">{{ loading ? '…' : total }}</strong> archived items
    </div>

    <div v-if="loadError" class="text-[13px] mb-4" style="color:var(--red)">
      <LucideIcon name="alert-triangle" :size="14" /> {{ loadError }}
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="flex flex-col gap-3">
      <div v-for="i in 4" :key="i" class="bg-white border border-[var(--line)] rounded-xl p-5" style="box-shadow:var(--sh1)">
        <div class="h-4 bg-[var(--bg)] rounded animate-pulse w-1/2 mb-3"></div>
        <div class="h-3 bg-[var(--bg)] rounded animate-pulse w-1/3"></div>
      </div>
    </div>

    <!-- Archive cards -->
    <div v-else-if="items.length" class="flex flex-col gap-3">
      <div v-for="item in items" :key="item.id"
        class="bg-white border border-[var(--line)] rounded-xl p-5 transition-all duration-200 hover:border-[var(--blue-100)] hover:shadow-[var(--sh2)]"
        style="box-shadow:var(--sh1)">
        <div class="flex gap-3 sm:gap-4 items-start">
          <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-[9px] bg-[var(--bg)] flex items-center justify-center flex-shrink-0 mt-0.5">
            <LucideIcon :name="reasonIcon(item.archive_reason)" :size="17" class="text-[var(--blue)]" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2 mb-1 flex-wrap">
              <div class="text-[14px] sm:text-[15px] font-bold text-[var(--navy)] leading-snug" style="font-family:var(--display)">{{ item.title }}</div>
              <span v-if="item.archive_reason" class="badge b-blue flex-shrink-0">{{ item.archive_reason }}</span>
            </div>
            <div class="flex items-center gap-2 sm:gap-3 text-[11.5px] sm:text-[12px] text-[var(--muted)] mb-2 flex-wrap">
              <span v-if="item.authors" class="flex items-center gap-1"><LucideIcon name="user" :size="12" /> {{ item.authors }}</span>
              <span v-if="item.publisher" class="flex items-center gap-1">{{ item.publisher }}</span>
              <span v-if="item.year" class="flex items-center gap-1"><LucideIcon name="calendar" :size="12" /> {{ item.year }}</span>
              <span v-if="item.call_number" class="flex items-center gap-1 mono"><LucideIcon name="map-pin" :size="12" /> {{ item.call_number }}</span>
            </div>
            <p v-if="item.description" class="hidden sm:block text-[13px] text-[var(--muted)] font-light leading-snug mb-3">{{ item.description }}</p>
            <div class="flex items-center gap-2 flex-wrap mb-3 sm:mb-0">
              <span class="tag">{{ item.subject_area }}</span>
              <span v-if="item.shelf_location" class="tag">{{ item.shelf_location }}</span>
            </div>
          </div>
          <div class="flex flex-col gap-2 flex-shrink-0">
            <button class="btn btn-primary btn-sm" @click="openRequest(item)">
              <LucideIcon name="send" :size="13" /> <span class="hidden sm:inline">Request from Staff</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="flex flex-col items-center py-16 text-center">
      <LucideIcon name="archive" :size="44" class="text-[var(--faint)] mb-3" />
      <div class="text-[15px] font-semibold text-[var(--navy)] mb-1">No archived items found</div>
      <p class="text-[13px] text-[var(--muted)]">Try a different reason filter or search term.</p>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="flex items-center justify-between mt-8 pt-6 border-t border-[var(--line)] flex-wrap gap-3">
      <div class="text-[12.5px] text-[var(--muted)]">Page {{ currentPage }} of {{ lastPage }} · {{ total }} items</div>
      <div class="flex gap-1">
        <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1"
          :class="['min-w-[32px] h-[32px] px-1.5 flex items-center justify-center border rounded-md text-[12.5px] cursor-pointer transition-all',
            currentPage===1 ? 'bg-[var(--bg)] border-[var(--line)] text-[var(--faint)] cursor-not-allowed' : 'bg-white border-[var(--line)] text-[var(--muted)] hover:border-[var(--blue)] hover:text-[var(--blue)]']">
          ‹
        </button>
        <button @click="goToPage(currentPage + 1)" :disabled="currentPage === lastPage"
          :class="['min-w-[32px] h-[32px] px-1.5 flex items-center justify-center border rounded-md text-[12.5px] cursor-pointer transition-all',
            currentPage===lastPage ? 'bg-[var(--bg)] border-[var(--line)] text-[var(--faint)] cursor-not-allowed' : 'bg-white border-[var(--line)] text-[var(--muted)] hover:border-[var(--blue)] hover:text-[var(--blue)]']">
          ›
        </button>
      </div>
    </div>
  </div>

  <!-- ── Request from Staff modal ── -->
  <div v-if="requestOpen" class="fixed inset-0 z-[999] flex items-center justify-center p-4" style="background:rgba(11,20,38,.6)" @click.self="requestOpen = false">
    <div class="bg-white rounded-xl w-full max-w-[460px] p-6" style="box-shadow:var(--sh2)">
      <template v-if="!requestSent">
        <div class="text-[16px] font-bold text-[var(--navy)] mb-1" style="font-family:var(--display)">Request from Staff</div>
        <div class="text-[13px] text-[var(--muted)] mb-4">{{ requestBook?.title }}</div>
        <textarea v-model="requestBody" rows="5"
          class="w-full border border-[var(--line)] rounded-lg p-3 text-[13.5px] text-[var(--ink)] outline-none focus:border-[var(--blue)] mb-3"
        ></textarea>
        <div v-if="requestError" class="text-[12.5px] mb-3" style="color:var(--red)">{{ requestError }}</div>
        <div class="flex gap-2">
          <button class="btn btn-primary flex-1 justify-center" :disabled="requestSending" @click="sendRequest">
            <LucideIcon :name="requestSending ? 'refresh-cw' : 'send'" :size="14" :class="requestSending ? 'animate-spin' : ''" />
            {{ requestSending ? 'Sending…' : 'Send Request' }}
          </button>
          <button class="btn btn-ghost" @click="requestOpen = false">Cancel</button>
        </div>
      </template>
      <template v-else>
        <div class="w-12 h-12 rounded-full flex items-center justify-center mb-3" style="background:var(--green-50)">
          <LucideIcon name="circle-check" :size="24" style="color:var(--green)" />
        </div>
        <div class="text-[16px] font-bold text-[var(--navy)] mb-1" style="font-family:var(--display)">Request Sent</div>
        <p class="text-[13.5px] text-[var(--muted)] mb-4">Library staff will follow up with you via the Ask-Librarian inbox.</p>
        <button class="btn btn-primary w-full justify-center" @click="requestOpen = false">Done</button>
      </template>
    </div>
  </div>
</template>
