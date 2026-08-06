<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPatch } from '@/api/http'
import LucideIcon from '@/components/LucideIcon.vue'

const auth = useAuthStore()

const overdueLoans = ref<any[]>([])
const loading      = ref(true)
const error        = ref('')
const processingId = ref<number | null>(null)

const overdueCount     = ref(0)
const unpaidAmount     = ref(0)
const collectedMonth   = ref(0)

const reminderSent = ref<Set<number>>(new Set())
const showFineModal   = ref(false)
const fineModalTitle  = ref('')
const fineModalAmount = ref(0)

const COLORS = ['cv-slate', 'cv-forest', 'cv-burgundy', 'cv-navy', 'cv-charcoal', 'cv-ochre']
const color  = (i: number) => COLORS[i % COLORS.length]

function daysLate(loan: any) {
  const due  = new Date(loan.due_date).setHours(0, 0, 0, 0)
  const now  = new Date().setHours(0, 0, 0, 0)
  return Math.max(0, Math.floor((now - due) / 86400000))
}

function badgeClass(days: number) {
  if (days >= 14) return 'b-red'
  if (days >= 7)  return 'b-amber'
  return 'b-gold'
}

function fmtDate(d: string) {
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const [odRes, ovRes] = await Promise.all([
      apiGet<any>('/admin/reports/overdue',  auth.token ?? undefined),
      apiGet<any>('/admin/reports/overview', auth.token ?? undefined),
    ])
    overdueLoans.value = odRes.data ?? []
    overdueCount.value   = ovRes.loans?.overdue        ?? 0
    unpaidAmount.value   = ovRes.fines?.unpaid_amount  ?? 0
    collectedMonth.value = ovRes.fines?.collected_this_month ?? 0
  } catch (e: any) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function sendReminder(loan: any) {
  reminderSent.value.add(loan.id)
  // No email backend yet — optimistic UI only
}

async function collectFine(loan: any) {
  processingId.value = loan.id
  try {
    const returnRes = await apiPatch<any>(`/admin/loans/${loan.id}/return`, {}, auth.token ?? undefined)
    if (returnRes.fine?.id) {
      await apiPatch<any>(`/admin/fines/${returnRes.fine.id}/pay`, {}, auth.token ?? undefined)
      fineModalTitle.value  = returnRes.loan?.book?.title ?? loan.book?.title ?? 'Book'
      fineModalAmount.value = parseFloat(returnRes.fine.amount)
      showFineModal.value   = true
    }
    overdueLoans.value = overdueLoans.value.filter((l: any) => l.id !== loan.id)
    overdueCount.value  = Math.max(0, overdueCount.value - 1)
    if (returnRes.fine) {
      unpaidAmount.value   = Math.max(0, unpaidAmount.value - parseFloat(returnRes.fine.amount))
      collectedMonth.value += parseFloat(returnRes.fine.amount)
    }
  } catch (e: any) {
    alert(e.message)
  } finally {
    processingId.value = null
  }
}

async function waiveFine(loan: any) {
  if (!confirm(`Waive the fine for "${loan.book?.title}"? This will return the book and cancel the fine.`)) return
  processingId.value = loan.id
  try {
    const returnRes = await apiPatch<any>(`/admin/loans/${loan.id}/return`, {}, auth.token ?? undefined)
    if (returnRes.fine?.id) {
      await apiPatch<any>(`/admin/fines/${returnRes.fine.id}/waive`, {}, auth.token ?? undefined)
    }
    overdueLoans.value = overdueLoans.value.filter((l: any) => l.id !== loan.id)
    overdueCount.value = Math.max(0, overdueCount.value - 1)
    if (returnRes.fine) {
      unpaidAmount.value = Math.max(0, unpaidAmount.value - parseFloat(returnRes.fine.amount))
    }
  } catch (e: any) {
    alert(e.message)
  } finally {
    processingId.value = null
  }
}

async function sendAllReminders() {
  overdueLoans.value.forEach((l: any) => reminderSent.value.add(l.id))
}

onMounted(load)
</script>

<template>
  <div class="shead">
    <div>
      <h2>Overdue &amp; Fines</h2>
      <p v-if="!loading">{{ overdueCount }} overdue items · ₦{{ unpaidAmount.toFixed(0) }} in outstanding fines</p>
      <p v-else>Loading…</p>
    </div>
    <button class="btn btn-primary" :disabled="loading" @click="sendAllReminders">
      <LucideIcon name="send" class="ic-sm" /> Send All Reminders
    </button>
  </div>

  <!-- KPIs -->
  <div class="kpis" style="grid-template-columns:repeat(3,1fr)">
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n" style="color:var(--red)">{{ loading ? '—' : overdueCount }}</div>
        <div class="kpi-ic t-red"><LucideIcon name="alarm-clock" /></div>
      </div>
      <div class="kpi-l">Overdue Items</div>
    </div>
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n" style="color:var(--amber)">{{ loading ? '—' : '₦' + unpaidAmount.toFixed(0) }}</div>
        <div class="kpi-ic t-amber"><LucideIcon name="banknote" /></div>
      </div>
      <div class="kpi-l">Outstanding Fines</div>
    </div>
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n">{{ loading ? '—' : '₦' + collectedMonth.toFixed(0) }}</div>
        <div class="kpi-ic t-green"><LucideIcon name="hand-coins" /></div>
      </div>
      <div class="kpi-l">Collected This Month</div>
    </div>
  </div>

  <div v-if="loading" style="padding:40px;text-align:center;color:var(--faint)">Loading overdue items…</div>
  <div v-else-if="error" style="padding:16px;color:var(--red);background:var(--red-50);border-radius:var(--r2)">{{ error }}</div>

  <div v-else-if="!overdueLoans.length" class="tbl-wrap" style="padding:40px;text-align:center;color:var(--faint)">
    <LucideIcon name="check-circle" style="width:36px;height:36px;margin:0 auto 10px;display:block;opacity:.35" />
    No overdue items — great news!
  </div>

  <div v-else class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Member</th>
          <th>Book</th>
          <th>Due Date</th>
          <th>Days Late</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(loan, i) in overdueLoans" :key="loan.id">
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <span :class="['dot', daysLate(loan) >= 7 ? 'd-red' : 'd-amber']"></span>
              <div>
                <div style="font-weight:600">{{ loan.user?.name }}</div>
                <div style="font-size:11px;color:var(--muted)">{{ loan.user?.member_number ?? loan.user?.email }}</div>
              </div>
            </div>
          </td>
          <td>
            <div class="bookcell">
              <div :class="['cover', color(i)]" style="width:30px;height:38px">
                <div class="cover-top"><div class="cover-t" style="font-size:5px">{{ loan.book?.title?.slice(0,4) }}</div></div>
              </div>
              <div>
                <div class="bk-t" style="font-size:12px">{{ loan.book?.title }}</div>
                <div class="bk-a">{{ loan.book?.call_number }}</div>
              </div>
            </div>
          </td>
          <td class="mono">{{ fmtDate(loan.due_date) }}</td>
          <td>
            <span :class="['badge', badgeClass(daysLate(loan))]">{{ daysLate(loan) }} days</span>
          </td>
          <td>
            <div class="rowacts" style="justify-content:flex-end">
              <button
                class="btn btn-ghost btn-sm"
                :style="reminderSent.has(loan.id) ? 'color:var(--green-600)' : ''"
                :disabled="processingId === loan.id"
                @click="sendReminder(loan)"
              >
                <LucideIcon :name="reminderSent.has(loan.id) ? 'check' : 'send'" class="ic-sm" />
                {{ reminderSent.has(loan.id) ? 'Sent' : 'Remind' }}
              </button>
              <button
                class="btn btn-green btn-sm"
                :disabled="processingId === loan.id"
                @click="collectFine(loan)"
              >
                <LucideIcon name="hand-coins" class="ic-sm" />
                {{ processingId === loan.id ? '…' : 'Collect' }}
              </button>
              <button
                class="btn btn-danger btn-sm"
                :disabled="processingId === loan.id"
                @click="waiveFine(loan)"
              >
                Waive
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Fine collected modal -->
  <div :class="['mscrim', { open: showFineModal }]" @click.self="showFineModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--green-50)">
          <LucideIcon name="hand-coins" :size="28" style="color:var(--green)" />
        </div>
        <div class="modal-t">Fine Collected</div>
        <div class="modal-s">
          "{{ fineModalTitle }}" returned. Fine of ₦{{ fineModalAmount.toFixed(2) }} has been collected.
        </div>
        <div class="modal-f">
          <button class="btn btn-primary btn-block" @click="showFineModal = false">Done</button>
        </div>
      </div>
    </div>
  </div>
</template>
