<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { apiGet, apiPost, apiDelete } from '@/api/http'

const auth   = useAuthStore()
const router = useRouter()

const loading      = ref(true)
const loans        = ref<any[]>([])
const reservations = ref<any[]>([])
const fines        = ref<any[]>([])
const totalLoans   = ref(0)
const loadError    = ref('')

const askMessage = ref('')
const askSending = ref(false)
const askSent    = ref(false)

const renewingId  = ref<number | null>(null)
const renewDoneId = ref<number | null>(null)

const cancellingId = ref<number | null>(null)

// Computed stats
const activeLoans        = computed(() => loans.value)
const activeReservations = computed(() => reservations.value.filter(r => r.status === 'pending' || r.status === 'fulfilled'))
const unpaidFinesTotal   = computed(() => fines.value.filter(f => f.status === 'unpaid').reduce((s: number, f: any) => s + parseFloat(f.amount ?? 0), 0))

const stats = computed(() => [
  { icon:'book-open', label:'Books on Loan (of 5)', value: String(activeLoans.value.length),        gradient:'linear-gradient(140deg,var(--blue),var(--sky))' },
  { icon:'bookmark',  label:'Active Reservations',  value: String(activeReservations.value.length),  gradient:'linear-gradient(140deg,#6E5630,var(--gold-600))' },
  { icon:'wallet',    label:'Outstanding Fines',     value: unpaidFinesTotal.value > 0 ? `₦${unpaidFinesTotal.value.toFixed(0)}` : '₦0', gradient:'linear-gradient(140deg,#234034,var(--green))' },
  { icon:'history',   label:'Lifetime Borrowings',   value: String(totalLoans.value),                gradient:'linear-gradient(140deg,#33526E,var(--purple))' },
])

const quickActions = [
  { icon:'search',         label:'Search the Catalog',  path:'/user/catalog' },
  { icon:'monitor',        label:'Browse E-Library',    path:'/user/e-library' },
  { icon:'message-circle', label:'Ask the Librarian',   path:'/user/help' },
  { icon:'settings',       label:'Account Settings',    path:'/user/profile' },
]

function dueStatus(loan: any) {
  const due  = new Date(loan.due_date)
  const now  = new Date()
  const days = Math.ceil((due.getTime() - now.getTime()) / 86400000)
  if (days < 0) return 'over'
  if (days <= 3) return 'warn'
  return 'ok'
}

const dueClass = (s: string) => ({
  ok:   'text-[var(--muted)]',
  warn: 'text-[var(--gold-600)] font-semibold',
  over: 'text-[var(--red)] font-semibold',
}[s] ?? '')

const dueIcon = (s: string) => s === 'ok' ? 'calendar' : 'alert-triangle'

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' })
}

function reservationBadge(res: any) {
  if (res.status === 'fulfilled') return { label:'Ready for Collection', cls:'b-green', icon:'circle-check' }
  if (res.status === 'pending')   return { label:'Pending',              cls:'b-gold',  icon:'clock' }
  return { label: res.status, cls:'b-gray', icon:'info' }
}

const coverClasses = ['cv-navy','cv-burgundy','cv-forest','cv-charcoal','cv-slate','cv-ochre']
function coverCls(id: number) { return coverClasses[id % coverClasses.length] }

onMounted(async () => {
  try {
    const token = auth.token ?? undefined
    const [loansRes, resRes, finesRes, allLoansRes] = await Promise.all([
      apiGet<any>('/me/loans?status=active', token),
      apiGet<any>('/me/reservations', token),
      apiGet<any>('/me/fines', token),
      apiGet<any>('/me/loans', token),
    ])
    loans.value        = loansRes.data  ?? []
    reservations.value = resRes.data    ?? []
    fines.value        = finesRes.data  ?? []
    totalLoans.value   = allLoansRes.total ?? 0
  } catch (e: any) {
    loadError.value = e.message
  }
  loading.value = false
})

async function requestRenew(loan: any) {
  renewingId.value = loan.id
  try {
    await apiPost('/inbox/submit', {
      body:       `I would like to request a renewal for: "${loan.book?.title ?? 'my book'}" (Due: ${formatDate(loan.due_date)}).`,
      query_type: 'support',
    }, auth.token ?? undefined)
    renewDoneId.value = loan.id
    setTimeout(() => { renewDoneId.value = null }, 3500)
  } catch {}
  renewingId.value = null
}

async function cancelReservation(res: any) {
  cancellingId.value = res.id
  try {
    await apiDelete(`/reservations/${res.id}`, auth.token ?? undefined)
    reservations.value = reservations.value.filter(rv => rv.id !== res.id)
  } catch {}
  cancellingId.value = null
}

async function sendAsk() {
  if (!askMessage.value.trim()) return
  askSending.value = true
  try {
    await apiPost('/inbox/submit', {
      body:       askMessage.value,
      query_type: 'reference',
    }, auth.token ?? undefined)
    askSent.value    = true
    askMessage.value = ''
    setTimeout(() => { askSent.value = false }, 4000)
  } catch {}
  askSending.value = false
}
</script>

<template>
  <!-- Hero -->
  <div class="relative" style="background:linear-gradient(115deg,#0B2E63 0%,var(--blue) 48%,var(--sky) 100%);padding:40px 0 36px">
    <div class="max-w-[1260px] mx-auto px-4 sm:px-7">
      <div class="inline-flex items-center gap-2 text-[11px] font-semibold tracking-[.18em] uppercase text-[var(--gold)] mb-3">
        <span class="w-6 h-px bg-[var(--gold)]"></span> Member Portal
      </div>
      <h1 class="text-[clamp(22px,3vw,34px)] font-bold text-white leading-tight tracking-tight mb-1" style="font-family:var(--display)">
        My Library Account
      </h1>
      <p class="text-[13px] sm:text-[15px] text-white/70 line-clamp-2 sm:line-clamp-none">
        {{ auth.user?.name ?? 'Member' }} · {{ auth.user?.member_number ?? 'N/A' }} · Good Standing
      </p>
    </div>
  </div>

  <!-- Body -->
  <div class="max-w-[1260px] mx-auto px-4 sm:px-7 py-6 pb-16">

    <!-- Error -->
    <div v-if="loadError" class="flex items-center gap-2 text-[13px] text-[var(--red)] bg-[var(--red-50)] border border-[#f6c9cf] rounded-[10px] px-4 py-3 mb-5">
      <LucideIcon name="alert-triangle" :size="16" /> {{ loadError }}
    </div>

    <div class="grid gap-6 items-start account-grid">

      <!-- Left column -->
      <div>
        <!-- Stats grid -->
        <div class="grid grid-cols-2 gap-3 mb-5">
          <div v-if="loading" v-for="i in 4" :key="i" class="h-[100px] bg-white border border-[var(--line)] rounded-[10px] animate-pulse" style="box-shadow:var(--sh1)"></div>
          <div v-else v-for="s in stats" :key="s.label" class="bg-white border border-[var(--line)] rounded-[10px] p-4" style="box-shadow:var(--sh1)">
            <div class="w-[34px] h-[34px] rounded-[9px] flex items-center justify-center mb-2.5" :style="{ background: s.gradient }">
              <LucideIcon :name="s.icon" :size="17" class="text-white" />
            </div>
            <div class="text-[24px] font-bold text-[var(--navy)] leading-none" style="font-family:var(--display)">{{ s.value }}</div>
            <div class="text-[11.5px] text-[var(--muted)] mt-1">{{ s.label }}</div>
          </div>
        </div>

        <!-- Current borrowings -->
        <div class="bg-white border border-[var(--line)] rounded-xl overflow-hidden mb-5" style="box-shadow:var(--sh1)">
          <div class="px-5 py-[15px] border-b border-[var(--line)] flex items-center justify-between">
            <h3 class="text-[12.5px] font-bold tracking-[.05em] uppercase text-[var(--navy)] flex items-center gap-2">
              <LucideIcon name="book-open" :size="16" class="text-[var(--blue)]" /> Current Borrowings
            </h3>
            <span class="badge b-blue">{{ activeLoans.length }} of 5 slots</span>
          </div>

          <!-- Loading skeleton -->
          <div v-if="loading" class="px-5 py-4 flex flex-col gap-4">
            <div v-for="i in 2" :key="i" class="h-[56px] bg-[var(--bg)] rounded-[8px] animate-pulse"></div>
          </div>

          <!-- Empty state -->
          <div v-else-if="!activeLoans.length" class="px-5 py-10 text-center text-[13px] text-[var(--faint)]">
            <LucideIcon name="book" :size="32" class="mx-auto mb-2 opacity-30" />
            No books currently on loan.
          </div>

          <!-- Loan rows -->
          <template v-else>
            <div v-for="loan in activeLoans" :key="loan.id" class="flex items-center gap-3 px-5 py-[15px] border-b border-[var(--line-soft)] last:border-0">
              <div :class="['cover flex-shrink-0', coverCls(loan.book?.id ?? loan.id)]" style="width:38px;height:48px">
                <div class="cover-top" style="padding:5px 4px 0 5px"><div class="cover-t" style="font-size:6px">{{ (loan.book?.title ?? '').slice(0,14) }}</div></div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-[14px] font-bold text-[var(--navy)] truncate" style="font-family:var(--display)">{{ loan.book?.title ?? 'Unknown Title' }}</div>
                <div :class="['text-[12px] mt-1 flex items-center gap-1.5', dueClass(dueStatus(loan))]">
                  <LucideIcon :name="dueIcon(dueStatus(loan))" :size="13" />
                  Due {{ formatDate(loan.due_date) }}
                  <span v-if="loan.book?.call_number" class="text-[var(--faint)]">· {{ loan.book.call_number }}</span>
                </div>
              </div>
              <div class="flex gap-1.5 ml-2 flex-shrink-0">
                <div v-if="renewDoneId === loan.id" class="text-[11.5px] text-[var(--green)] flex items-center gap-1">
                  <LucideIcon name="check" :size="12" /> Request sent
                </div>
                <button v-else class="btn btn-ghost btn-sm" :disabled="renewingId === loan.id" @click="requestRenew(loan)">
                  <LucideIcon :name="renewingId===loan.id ? 'refresh-cw' : 'refresh-cw'" :size="14" />
                  {{ renewingId === loan.id ? '…' : 'Renew' }}
                </button>
              </div>
            </div>
            <div class="px-5 py-[15px] bg-[var(--bg)] border-t border-[var(--line)] flex flex-wrap justify-between gap-2.5 text-[12.5px] text-[var(--muted)]">
              <span>Borrow limit: <strong class="text-[var(--navy)]">5 / 14 days</strong></span>
              <span>Renewals: <strong class="text-[var(--navy)]">2 per item</strong></span>
              <span>Overdue: <strong class="text-[var(--red)]">₦50 / day</strong></span>
            </div>
          </template>
        </div>

        <!-- Active reservations -->
        <div class="bg-white border border-[var(--line)] rounded-xl overflow-hidden" style="box-shadow:var(--sh1)">
          <div class="px-5 py-[15px] border-b border-[var(--line)]">
            <h3 class="text-[12.5px] font-bold tracking-[.05em] uppercase text-[var(--navy)] flex items-center gap-2">
              <LucideIcon name="bookmark" :size="16" class="text-[var(--blue)]" /> Active Reservations
            </h3>
          </div>

          <div v-if="loading" class="px-5 py-4">
            <div class="h-[56px] bg-[var(--bg)] rounded-[8px] animate-pulse"></div>
          </div>

          <template v-else-if="activeReservations.length">
            <div v-for="res in activeReservations" :key="res.id" class="flex items-center gap-3 px-5 py-[15px] border-b border-[var(--line-soft)] last:border-0">
              <div :class="['cover flex-shrink-0', coverCls(res.book?.id ?? res.id)]" style="width:38px;height:48px">
                <div class="cover-top" style="padding:5px 4px 0 5px"><div class="cover-t" style="font-size:6px">{{ (res.book?.title ?? '').slice(0,14) }}</div></div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-[14px] font-bold text-[var(--navy)] truncate" style="font-family:var(--display)">{{ res.book?.title ?? 'Unknown Title' }}</div>
                <div class="text-[12px] text-[var(--muted)] mt-1 flex items-center gap-1.5">
                  <LucideIcon name="map-pin" :size="13" />
                  {{ res.status === 'fulfilled' ? `Ready · Library Front Desk · Held until ${formatDate(res.expires_at)}` : 'Pending — you will be notified when ready' }}
                </div>
              </div>
              <div class="flex items-center gap-2 flex-shrink-0">
                <span :class="['badge', reservationBadge(res).cls]">
                  <LucideIcon :name="reservationBadge(res).icon" :size="12" /> {{ reservationBadge(res).label }}
                </span>
                <button v-if="res.status === 'pending'" class="btn btn-ghost btn-sm text-[var(--red)]"
                  :disabled="cancellingId === res.id" @click="cancelReservation(res)">
                  <LucideIcon name="x" :size="13" />
                </button>
              </div>
            </div>
          </template>

          <div v-else class="px-5 py-8 text-center text-[13px] text-[var(--faint)]">
            No active reservations
          </div>
        </div>
      </div>

      <!-- Right column -->
      <div>
        <!-- Ask the librarian -->
        <div class="bg-white border border-[var(--line)] rounded-xl overflow-hidden mb-5" style="box-shadow:var(--sh1)">
          <div class="p-5">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-11 h-11 rounded-full flex items-center justify-center text-white flex-shrink-0" style="background:linear-gradient(140deg,var(--blue),var(--sky))">
                <LucideIcon name="user-round" :size="22" />
              </div>
              <div>
                <div class="text-[14.5px] font-bold text-[var(--navy)]" style="font-family:var(--display)">Ask the Librarian</div>
                <div class="text-[11.5px] text-[var(--green)] flex items-center gap-1.5 mt-px">
                  <span class="w-1.5 h-1.5 rounded-full bg-[var(--green)]"></span> Online
                </div>
              </div>
            </div>
            <div v-if="askSent" class="flex items-center gap-2 text-[13px] text-[var(--green)] bg-[var(--green-50)] rounded-[10px] px-4 py-3 mb-3">
              <LucideIcon name="check" :size="16" /> Message sent! Reply within 1 business day.
            </div>
            <textarea v-model="askMessage" rows="4"
              class="w-full border-[1.5px] border-[var(--line)] rounded-[10px] px-3.5 py-3 text-[13.5px] text-[var(--ink)] resize-y min-h-[88px] leading-relaxed outline-none transition-colors focus:border-[var(--blue)]"
              placeholder="Ask a research question, request a citation, or get help finding a resource…"></textarea>
            <div class="flex items-center justify-between mt-3">
              <button class="text-[11.5px] text-[var(--faint)] flex items-center gap-1.5" @click="router.push('/user/help')">
                <LucideIcon name="message-circle" :size="15" /> View conversations
              </button>
              <button class="btn btn-primary btn-sm" :disabled="askSending || !askMessage.trim()" @click="sendAsk">
                <LucideIcon :name="askSending ? 'refresh-cw' : 'send'" :size="14" />
                {{ askSending ? 'Sending…' : 'Send' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Quick actions -->
        <div class="bg-white border border-[var(--line)] rounded-xl overflow-hidden" style="box-shadow:var(--sh1)">
          <div class="px-5 py-[15px] border-b border-[var(--line)]">
            <h3 class="text-[12.5px] font-bold tracking-[.05em] uppercase text-[var(--navy)] flex items-center gap-2">
              <LucideIcon name="zap" :size="16" class="text-[var(--blue)]" /> Quick Actions
            </h3>
          </div>
          <div class="px-5 py-3.5 flex flex-col gap-2">
            <button v-for="a in quickActions" :key="a.label"
              class="btn btn-ghost btn-block justify-start"
              @click="router.push(a.path)">
              <LucideIcon :name="a.icon" :size="15" /> {{ a.label }}
            </button>
          </div>
        </div>

        <!-- Outstanding fines (if any) -->
        <div v-if="!loading && unpaidFinesTotal > 0" class="mt-5 bg-[var(--red-50)] border border-[#f6c9cf] rounded-xl p-4" style="box-shadow:var(--sh1)">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-9 h-9 rounded-[9px] bg-[var(--red)] flex items-center justify-center flex-shrink-0">
              <LucideIcon name="alert-triangle" :size="16" class="text-white" />
            </div>
            <div>
              <div class="text-[13.5px] font-bold text-[var(--red)]">Outstanding Fines</div>
              <div class="text-[12px] text-[var(--red)] opacity-80">Please settle to continue borrowing.</div>
            </div>
          </div>
          <div v-for="fine in fines.filter(f => f.status === 'unpaid')" :key="fine.id"
            class="flex justify-between items-center text-[12.5px] py-1.5 border-b border-[#f6c9cf] last:border-0">
            <span class="text-[var(--ink)] truncate mr-2">{{ fine.loan?.book?.title ?? 'Loan #'+fine.loan_id }}</span>
            <span class="font-bold text-[var(--red)] flex-shrink-0">₦{{ parseFloat(fine.amount).toFixed(0) }}</span>
          </div>
          <button class="btn btn-sm w-full mt-3 text-[var(--red)] border-[var(--red)] bg-white hover:bg-[var(--red)] hover:text-white" style="border:1.5px solid" @click="router.push('/user/help')">
            <LucideIcon name="message-circle" :size="14" /> Contact Librarian to Pay
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.account-grid {
  grid-template-columns: 1fr;
}
@media (min-width: 900px) {
  .account-grid { grid-template-columns: 1fr 320px; }
}
</style>
