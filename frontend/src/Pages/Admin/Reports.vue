<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet } from '@/api/http'
import LucideIcon from '@/components/LucideIcon.vue'

const auth    = useAuthStore()
const loading = ref(true)
const error   = ref('')

const overview  = ref<any>({})
const subjects  = ref<any[]>([])
const popular   = ref<any[]>([])

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const [ovRes, subRes, popRes] = await Promise.all([
      apiGet<any>('/admin/reports/overview', auth.token ?? undefined),
      apiGet<any>('/admin/reports/subjects', auth.token ?? undefined),
      apiGet<any>('/admin/reports/popular',  auth.token ?? undefined),
    ])
    overview.value = ovRes
    subjects.value = subRes ?? []
    popular.value  = popRes ?? []
  } catch (e: any) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function maxSubjectLoans() {
  return Math.max(1, ...subjects.value.map((s: any) => s.loan_count))
}

function subjectBarPct(s: any) {
  return Math.round((s.loan_count / maxSubjectLoans()) * 100)
}

function shortSubject(s: string) {
  if (!s) return '—'
  const parts = s.split(/[\s,&]+/)
  const [first] = parts
  return (first ?? '').slice(0, 6) + (parts.length > 1 ? '.' : '')
}

async function exportPdf() {
  window.print()
}

onMounted(load)
</script>

<template>
  <div class="shead">
    <div>
      <h2>Reports &amp; Analytics</h2>
      <p>Library performance · current snapshot</p>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-ghost btn-sm" @click="load">
        <LucideIcon name="refresh-cw" class="ic-sm" /> Refresh
      </button>
      <button class="btn btn-ghost" @click="exportPdf">
        <LucideIcon name="download" class="ic-sm" /> Export PDF
      </button>
    </div>
  </div>

  <div v-if="loading" style="padding:40px;text-align:center;color:var(--faint)">Loading reports…</div>
  <div v-else-if="error" style="padding:16px;color:var(--red);background:var(--red-50);border-radius:var(--r2)">{{ error }}</div>

  <template v-else>
    <!-- KPI row -->
    <div class="kpis">
      <div class="kpi">
        <div class="kpi-top">
          <div class="kpi-n">{{ overview.loans?.this_month?.toLocaleString() ?? 0 }}</div>
          <div class="kpi-ic t-blue"><LucideIcon name="book-open" /></div>
        </div>
        <div class="kpi-l">Loans This Month</div>
        <div class="kpi-tr tr-up" v-if="overview.loans?.active">
          <LucideIcon name="activity" class="ic-sm" /> {{ overview.loans.active }} active
        </div>
      </div>

      <div class="kpi">
        <div class="kpi-top">
          <div class="kpi-n">{{ overview.loans?.on_time_rate_pct ?? 0 }}%</div>
          <div class="kpi-ic t-green"><LucideIcon name="circle-check-big" /></div>
        </div>
        <div class="kpi-l">On-time Return Rate</div>
        <div :class="['kpi-tr', (overview.loans?.on_time_rate_pct ?? 0) >= 90 ? 'tr-up' : 'tr-dn']">
          <LucideIcon :name="(overview.loans?.on_time_rate_pct ?? 0) >= 90 ? 'trending-up' : 'trending-down'" class="ic-sm" />
          {{ (overview.loans?.on_time_rate_pct ?? 0) >= 90 ? 'Good' : 'Needs attention' }}
        </div>
      </div>

      <div class="kpi">
        <div class="kpi-top">
          <div class="kpi-n">{{ overview.books?.utilization_pct ?? 0 }}%</div>
          <div class="kpi-ic t-gold"><LucideIcon name="gauge" /></div>
        </div>
        <div class="kpi-l">Collection Utilisation</div>
        <div class="kpi-tr tr-fl">
          <LucideIcon name="book-copy" class="ic-sm" />
          {{ overview.books?.total?.toLocaleString() ?? 0 }} titles
        </div>
      </div>

      <div class="kpi">
        <div class="kpi-top">
          <div class="kpi-n">{{ overview.loans?.avg_per_member ?? 0 }}</div>
          <div class="kpi-ic t-wine"><LucideIcon name="star" /></div>
        </div>
        <div class="kpi-l">Avg Loans / Member</div>
        <div class="kpi-tr tr-fl">
          <LucideIcon name="users-round" class="ic-sm" />
          {{ overview.users?.members?.toLocaleString() ?? 0 }} members
        </div>
      </div>
    </div>

    <div class="grid2">
      <!-- Loans by Subject -->
      <div class="card">
        <div class="card-h">
          <h3><LucideIcon name="chart-no-axes-column" class="ic" /> Loans by Subject Area</h3>
          <span class="badge b-gray">{{ subjects.length }} subjects</span>
        </div>
        <div class="card-b">
          <div v-if="!subjects.length" style="text-align:center;color:var(--faint);padding:24px 0;font-size:13px">No loan data yet</div>
          <template v-else>
            <div class="bars">
              <div
                v-for="s in subjects"
                :key="s.subject_area"
                class="bar"
                :style="`height:${subjectBarPct(s)}%`"
                :title="`${s.subject_area}: ${s.loan_count} loans`"
              ></div>
            </div>
            <div class="barlabels">
              <span v-for="s in subjects" :key="s.subject_area" :title="s.subject_area">{{ shortSubject(s.subject_area) }}</span>
            </div>
          </template>
        </div>
      </div>

      <!-- Member Activity -->
      <div class="card">
        <div class="card-h"><h3><LucideIcon name="users-round" class="ic" /> Member Activity</h3></div>
        <div class="card-b" style="padding:8px 18px">
          <div class="poprow">
            <div class="pop-t">Active borrowers this month</div>
            <div class="pop-n" style="font-weight:700;color:var(--navy)">{{ overview.users?.active_borrowers_month ?? 0 }}</div>
          </div>
          <div class="poprow">
            <div class="pop-t">New registrations</div>
            <div class="pop-n" style="font-weight:700;color:var(--green-600)">+{{ overview.users?.new_this_month ?? 0 }}</div>
          </div>
          <div class="poprow">
            <div class="pop-t">Reservations pending</div>
            <div class="pop-n" style="font-weight:700;color:var(--navy)">{{ overview.reservations?.pending ?? 0 }}</div>
          </div>
          <div class="poprow">
            <div class="pop-t">Overdue loans</div>
            <div class="pop-n" :style="(overview.loans?.overdue ?? 0) > 0 ? 'font-weight:700;color:var(--red)' : 'font-weight:700;color:var(--green-600)'">
              {{ overview.loans?.overdue ?? 0 }}
            </div>
          </div>
          <div class="poprow">
            <div class="pop-t">Unpaid fines</div>
            <div class="pop-n" :style="(overview.fines?.unpaid_amount ?? 0) > 0 ? 'font-weight:700;color:var(--amber)' : 'font-weight:700;color:var(--green-600)'">
              ₦{{ (overview.fines?.unpaid_amount ?? 0).toFixed(2) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Popular Books -->
    <div class="card" style="margin-top:16px">
      <div class="card-h">
        <h3><LucideIcon name="trophy" class="ic" /> Most Borrowed Books</h3>
        <span class="badge b-gray">Top {{ Math.min(10, popular.length) }}</span>
      </div>
      <div v-if="!popular.length" style="padding:28px;text-align:center;color:var(--faint);font-size:13px">No loan data yet.</div>
      <div v-else style="padding:8px 18px">
        <div v-for="(b, i) in popular.slice(0, 10)" :key="b.id" class="poprow">
          <div class="poprank">{{ i + 1 }}</div>
          <div class="pop-t">
            {{ b.title }}
            <div class="bk-a">{{ b.authors }}</div>
          </div>
          <div class="pop-n">{{ b.total_loans }} loan{{ b.total_loans !== 1 ? 's' : '' }}</div>
        </div>
      </div>
    </div>
  </template>
</template>
