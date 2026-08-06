<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPatch } from '@/api/http'
import LucideIcon from '@/components/LucideIcon.vue'

const router = useRouter()
const auth   = useAuthStore()

const loading  = ref(true)
const overview = ref<any>({})
const activity = ref<any[]>([])
const popular  = ref<any[]>([])
const recent   = ref<any[]>([])
const pending  = ref<any[]>([])

const processingRes   = ref<number | null>(null)
const showReadyModal  = ref(false)
const readyRes        = ref<any>(null)

const COLORS = ['cv-navy', 'cv-burgundy', 'cv-charcoal', 'cv-slate', 'cv-forest', 'cv-ochre']
const color  = (i: number) => COLORS[i % COLORS.length]

const maxActivity = computed(() => {
  const vals = activity.value.flatMap((d: any) => [d.issued, d.returned])
  return Math.max(1, ...vals)
})

function barPct(val: number) {
  return Math.max(4, Math.round((val / maxActivity.value) * 100))
}

function timeAgo(d: string) {
  const diff = (Date.now() - new Date(d).getTime()) / 1000
  if (diff < 60)   return 'just now'
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago'
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago'
  return Math.floor(diff / 86400) + 'd ago'
}

function loanAction(loan: any) {
  if (loan.status === 'returned') {
    return new Date(loan.returned_at) <= new Date(loan.due_date + 'T23:59:59') ? 'Returned' : 'Late Return'
  }
  return 'Issued'
}

function actionBadge(loan: any) {
  const a = loanAction(loan)
  if (a === 'Issued')      return { cls: 'b-green', icon: 'arrow-up-right' }
  if (a === 'Returned')    return { cls: 'b-blue',  icon: 'arrow-down-left' }
  return { cls: 'b-red', icon: 'alert-triangle' }
}

function fmtDate(d: string) {
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

function timeOfLoan(loan: any) {
  const d = loan.status === 'returned' ? loan.returned_at : loan.borrowed_at
  return timeAgo(d)
}

function reservedAgo(r: any) {
  return timeAgo(r.reserved_at)
}

async function load() {
  loading.value = true
  try {
    const [ovRes, actRes, popRes, loanRes, resRes] = await Promise.all([
      apiGet<any>('/admin/reports/overview',      auth.token ?? undefined),
      apiGet<any>('/admin/reports/activity?days=12', auth.token ?? undefined),
      apiGet<any>('/admin/reports/popular',       auth.token ?? undefined),
      apiGet<any>('/admin/loans',                 auth.token ?? undefined),
      apiGet<any>('/admin/reservations?status=pending', auth.token ?? undefined),
    ])
    overview.value = ovRes
    activity.value = actRes ?? []
    popular.value  = (popRes ?? []).slice(0, 5)
    recent.value   = (loanRes.data ?? []).slice(0, 6)
    pending.value  = (resRes.data ?? []).slice(0, 3)
  } catch {}
  finally { loading.value = false }
}

async function markReady(r: any) {
  processingRes.value = r.id
  try {
    const updated = await apiPatch<any>(`/admin/reservations/${r.id}/fulfill`, {}, auth.token ?? undefined)
    pending.value    = pending.value.filter((x: any) => x.id !== r.id)
    readyRes.value   = updated
    showReadyModal.value = true
  } catch (e: any) {
    alert(e.message)
  } finally {
    processingRes.value = null
  }
}

onMounted(load)
</script>

<template>
  <!-- KPI row -->
  <div class="kpis">
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n">{{ loading ? '—' : (overview.books?.total_copies ?? 0).toLocaleString() }}</div>
        <div class="kpi-ic t-blue"><LucideIcon name="book-copy" /></div>
      </div>
      <div class="kpi-l">Total Volumes</div>
      <div class="kpi-tr tr-up">
        <LucideIcon name="trending-up" class="ic-sm" />
        +{{ loading ? '…' : (overview.loans?.this_month ?? 0) }} this month
      </div>
    </div>

    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n">{{ loading ? '—' : (overview.loans?.active ?? 0) }}</div>
        <div class="kpi-ic t-slate"><LucideIcon name="book-open" /></div>
      </div>
      <div class="kpi-l">Currently on Loan</div>
      <div class="kpi-tr tr-fl">
        <LucideIcon name="minus" class="ic-sm" />
        {{ loading ? '…' : (overview.reservations?.pending ?? 0) }} reservations pending
      </div>
    </div>

    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n" :style="(overview.loans?.overdue ?? 0) > 0 ? 'color:var(--red)' : ''">
          {{ loading ? '—' : (overview.loans?.overdue ?? 0) }}
        </div>
        <div class="kpi-ic t-red"><LucideIcon name="alarm-clock" /></div>
      </div>
      <div class="kpi-l">Overdue Returns</div>
      <div :class="['kpi-tr', (overview.fines?.unpaid_amount ?? 0) > 0 ? 'tr-dn' : 'tr-fl']">
        <LucideIcon name="banknote" class="ic-sm" />
        ₦{{ loading ? '…' : (overview.fines?.unpaid_amount ?? 0).toFixed(0) }} fines due
      </div>
    </div>

    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n">{{ loading ? '—' : (overview.users?.members ?? 0).toLocaleString() }}</div>
        <div class="kpi-ic t-green"><LucideIcon name="users-round" /></div>
      </div>
      <div class="kpi-l">Active Members</div>
      <div class="kpi-tr tr-up">
        <LucideIcon name="trending-up" class="ic-sm" />
        +{{ loading ? '…' : (overview.users?.new_this_month ?? 0) }} this month
      </div>
    </div>
  </div>

  <!-- Chart + Most Borrowed -->
  <div class="grid3" style="margin-bottom:18px">
    <div class="card" style="grid-column:span 2">
      <div class="card-h">
        <h3><LucideIcon name="activity" class="ic" /> Circulation — Last 12 Days</h3>
        <div style="display:flex;gap:12px">
          <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted)">
            <span class="dot" style="background:var(--blue)"></span> Issued
          </span>
          <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted)">
            <span class="dot" style="background:var(--green)"></span> Returned
          </span>
        </div>
      </div>
      <div class="card-b">
        <div v-if="loading || !activity.length" style="height:130px;display:flex;align-items:center;justify-content:center;color:var(--faint);font-size:13px">
          {{ loading ? 'Loading…' : 'No activity data yet.' }}
        </div>
        <template v-else>
          <div class="bars">
            <template v-for="d in activity" :key="d.date">
              <div class="bar" :style="`height:${barPct(d.issued)}%`" :title="`${d.date}: ${d.issued} issued`"></div>
              <div class="bar g" :style="`height:${barPct(d.returned)}%`" :title="`${d.date}: ${d.returned} returned`"></div>
            </template>
          </div>
          <div class="barlabels">
            <template v-for="d in activity" :key="d.date + 'l'">
              <span>{{ d.label }}</span>
              <span></span>
            </template>
          </div>
        </template>
      </div>
    </div>

    <!-- Most Borrowed -->
    <div class="card">
      <div class="card-h">
        <h3><LucideIcon name="flame" class="ic" /> Most Borrowed</h3>
      </div>
      <div class="card-b" style="padding:8px 18px">
        <div v-if="loading" style="padding:20px 0;text-align:center;color:var(--faint);font-size:13px">Loading…</div>
        <div v-else-if="!popular.length" style="padding:20px 0;text-align:center;color:var(--faint);font-size:13px">No loan data yet.</div>
        <div v-else class="poprow" v-for="(b, i) in popular" :key="b.id">
          <div class="poprank">{{ i + 1 }}</div>
          <div :class="['cover', color(i)]" style="width:24px;height:30px;flex-shrink:0">
            <div class="cover-top"><div class="cover-t" style="font-size:4px">{{ b.title.slice(0,6) }}</div></div>
          </div>
          <div style="flex:1;min-width:0">
            <div class="pop-t" style="font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ b.title }}</div>
            <div class="pop-n">{{ b.total_loans }} loan{{ b.total_loans !== 1 ? 's' : '' }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Transactions + Pending Reservations -->
  <div class="grid2">
    <!-- Recent transactions -->
    <div class="card">
      <div class="card-h">
        <h3><LucideIcon name="history" class="ic" /> Recent Transactions</h3>
        <button class="btn btn-ghost btn-sm" @click="router.push('/admin/circulation')">Open Desk</button>
      </div>
      <div v-if="loading" style="padding:24px;text-align:center;color:var(--faint)">Loading…</div>
      <div v-else-if="!recent.length" style="padding:24px;text-align:center;color:var(--faint);font-size:13px">No transactions recorded yet.</div>
      <table v-else class="tbl">
        <thead>
          <tr><th>Member</th><th>Action</th><th>Book</th><th>When</th></tr>
        </thead>
        <tbody>
          <tr v-for="loan in recent" :key="loan.id">
            <td style="font-size:12.5px">{{ loan.user?.name }}</td>
            <td>
              <span :class="['badge', actionBadge(loan).cls]">
                <LucideIcon :name="actionBadge(loan).icon" :size="11" />
                {{ loanAction(loan) }}
              </span>
            </td>
            <td class="mono" style="font-size:11.5px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              {{ loan.book?.title }}
            </td>
            <td class="mono">{{ timeOfLoan(loan) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pending reservations -->
    <div class="card">
      <div class="card-h">
        <h3><LucideIcon name="bookmark-check" class="ic" /> Reservations to Fulfil</h3>
        <span class="badge b-gold">{{ loading ? '…' : (overview.reservations?.pending ?? 0) }} pending</span>
      </div>
      <div v-if="loading" style="padding:24px;text-align:center;color:var(--faint)">Loading…</div>
      <div v-else-if="!pending.length" style="padding:24px;text-align:center;color:var(--faint);font-size:13px">
        <LucideIcon name="check-circle" style="width:28px;height:28px;margin:0 auto 8px;display:block;opacity:.35" />
        All reservations are up to date.
      </div>
      <template v-else>
        <div v-for="(r, i) in pending" :key="r.id" class="qitem">
          <div :class="['cover', color(i)]" style="width:36px;height:46px">
            <div class="cover-top"><div class="cover-rule"></div><div class="cover-t">{{ r.book?.title?.slice(0,4) }}</div></div>
          </div>
          <div class="q-info">
            <div class="q-t">{{ r.book?.title }}</div>
            <div class="q-m">
              <span><LucideIcon name="user" :size="12" /> {{ r.user?.name }}</span>
              <span><LucideIcon name="clock" :size="12" /> Reserved {{ reservedAgo(r) }}</span>
            </div>
          </div>
          <div class="q-acts">
            <button
              class="btn btn-green btn-sm"
              :disabled="processingRes === r.id"
              @click="markReady(r)"
            >
              <LucideIcon name="check" class="ic-sm" />
              {{ processingRes === r.id ? '…' : 'Mark Ready' }}
            </button>
          </div>
        </div>
        <div
          v-if="(overview.reservations?.pending ?? 0) > pending.length"
          style="padding:10px 18px;font-size:12px;color:var(--muted);border-top:1px solid var(--line-soft)"
        >
          <button class="btn btn-ghost btn-sm" @click="router.push('/admin/reservation')">
            View all {{ overview.reservations?.pending }} →
          </button>
        </div>
      </template>
    </div>
  </div>

  <!-- Quick stats row -->
  <div class="grid3" style="margin-top:18px">
    <div class="card">
      <div class="card-h"><h3><LucideIcon name="percent" class="ic" /> On-time Return Rate</h3></div>
      <div class="card-b" style="display:flex;align-items:center;gap:16px">
        <div style="font-family:var(--display);font-size:38px;font-weight:700;color:var(--navy)">
          {{ loading ? '—' : (overview.loans?.on_time_rate_pct ?? 0) }}%
        </div>
        <div style="flex:1">
          <div style="height:8px;background:var(--line);border-radius:4px;overflow:hidden">
            <div
              style="height:100%;border-radius:4px;background:var(--green);transition:width .5s"
              :style="`width:${loading ? 0 : (overview.loans?.on_time_rate_pct ?? 0)}%`"
            ></div>
          </div>
          <div style="font-size:11px;color:var(--muted);margin-top:5px">of all returned loans</div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-h"><h3><LucideIcon name="gauge" class="ic" /> Collection Utilisation</h3></div>
      <div class="card-b" style="display:flex;align-items:center;gap:16px">
        <div style="font-family:var(--display);font-size:38px;font-weight:700;color:var(--navy)">
          {{ loading ? '—' : (overview.books?.utilization_pct ?? 0) }}%
        </div>
        <div style="flex:1">
          <div style="height:8px;background:var(--line);border-radius:4px;overflow:hidden">
            <div
              style="height:100%;border-radius:4px;background:var(--blue);transition:width .5s"
              :style="`width:${loading ? 0 : (overview.books?.utilization_pct ?? 0)}%`"
            ></div>
          </div>
          <div style="font-size:11px;color:var(--muted);margin-top:5px">
            {{ loading ? '—' : (overview.books?.total_copies ?? 0) - (overview.books?.available_copies ?? 0) }} of {{ loading ? '—' : (overview.books?.total_copies ?? 0) }} copies on loan
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-h"><h3><LucideIcon name="zap" class="ic" /> Quick Actions</h3></div>
      <div class="card-b" style="display:flex;flex-direction:column;gap:8px">
        <button class="btn btn-primary btn-sm" @click="router.push('/admin/circulation')">
          <LucideIcon name="scan-line" class="ic-sm" /> Open Circulation Desk
        </button>
        <button class="btn btn-ghost btn-sm" @click="router.push('/admin/overdue')">
          <LucideIcon name="alarm-clock" class="ic-sm" /> View Overdue Items
        </button>
        <button class="btn btn-ghost btn-sm" @click="router.push('/admin/reports')">
          <LucideIcon name="chart-no-axes-column" class="ic-sm" /> Full Reports
        </button>
      </div>
    </div>
  </div>

  <!-- Mark Ready modal -->
  <div :class="['mscrim', { open: showReadyModal }]" @click.self="showReadyModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--green-50)">
          <LucideIcon name="package-check" :size="28" style="color:var(--green)" />
        </div>
        <div class="modal-t">Marked Ready</div>
        <div class="modal-s">
          "{{ readyRes?.book?.title }}" is now ready for collection by {{ readyRes?.user?.name }}.
        </div>
        <div class="modal-f">
          <button class="btn btn-primary btn-block" @click="showReadyModal = false">Done</button>
        </div>
      </div>
    </div>
  </div>
</template>
