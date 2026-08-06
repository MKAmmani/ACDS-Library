<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPost, apiPatch, apiDelete } from '@/api/http'
import LucideIcon from '@/components/LucideIcon.vue'

const auth = useAuthStore()

const pending   = ref<any[]>([])
const fulfilled = ref<any[]>([])
const loading   = ref(true)
const error     = ref('')

const showReadyModal   = ref(false)
const readyReservation = ref<any>(null)
const processingId     = ref<number | null>(null)

const issuingId      = ref<number | null>(null)
const showIssuedModal = ref(false)
const issuedResult   = ref<any>(null)

const COLORS = ['cv-charcoal', 'cv-slate', 'cv-burgundy', 'cv-navy', 'cv-forest', 'cv-ochre']
const color  = (i: number) => COLORS[i % COLORS.length]

function timeAgo(dateStr: string) {
  const diff = (Date.now() - new Date(dateStr).getTime()) / 1000
  if (diff < 3600)  return Math.floor(diff / 60) + 'm ago'
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago'
  if (diff < 172800) return 'Yesterday'
  return Math.floor(diff / 86400) + 'd ago'
}

function heldUntil(r: any) {
  if (!r.expires_at) return '—'
  return new Date(r.expires_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

function initials(name: string) {
  return name.split(' ').map((w: string) => w[0]).join('').toUpperCase().slice(0, 2)
}

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const [pRes, fRes] = await Promise.all([
      apiGet<any>('/admin/reservations?status=pending',   auth.token ?? undefined),
      apiGet<any>('/admin/reservations?status=fulfilled', auth.token ?? undefined),
    ])
    pending.value   = pRes.data ?? []
    fulfilled.value = fRes.data ?? []
  } catch (e: any) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function markReady(r: any) {
  processingId.value = r.id
  try {
    const updated = await apiPatch<any>(`/admin/reservations/${r.id}/fulfill`, {}, auth.token ?? undefined)
    pending.value   = pending.value.filter((x: any) => x.id !== r.id)
    fulfilled.value.unshift(updated)
    readyReservation.value = updated
    showReadyModal.value   = true
  } catch (e: any) {
    alert(e.message)
  } finally {
    processingId.value = null
  }
}

async function cancelReservation(r: any) {
  if (!confirm(`Cancel reservation for "${r.book?.title}"?`)) return
  processingId.value = r.id
  try {
    await apiDelete<any>(`/reservations/${r.id}`, auth.token ?? undefined)
    pending.value   = pending.value.filter((x: any) => x.id !== r.id)
    fulfilled.value = fulfilled.value.filter((x: any) => x.id !== r.id)
  } catch (e: any) {
    alert(e.message)
  } finally {
    processingId.value = null
  }
}

function fmtDate(d: string) {
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

async function issueNow(r: any) {
  issuingId.value = r.id
  try {
    const loan = await apiPost<any>('/admin/loans', {
      book_id: r.book.id,
      user_id: r.user.id,
    }, auth.token ?? undefined)

    // Remove from the ready queue immediately
    fulfilled.value = fulfilled.value.filter((x: any) => x.id !== r.id)

    // Clean up the reservation record (best-effort)
    try { await apiDelete<any>(`/reservations/${r.id}`, auth.token ?? undefined) } catch {}

    issuedResult.value   = { loan, reservation: r }
    showIssuedModal.value = true
  } catch (e: any) {
    alert(e.message)
  } finally {
    issuingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="shead">
    <div>
      <h2>Reservations Queue</h2>
      <p v-if="!loading">{{ pending.length }} pending · {{ fulfilled.length }} ready for collection</p>
      <p v-else>Loading…</p>
    </div>
    <button class="btn btn-ghost btn-sm" @click="load"><LucideIcon name="refresh-cw" class="ic-sm" /> Refresh</button>
  </div>

  <div v-if="loading" style="padding:40px;text-align:center;color:var(--faint)">Loading reservations…</div>
  <div v-else-if="error" style="padding:20px;color:var(--red);background:var(--red-50);border-radius:var(--r2)">{{ error }}</div>

  <template v-else>
    <!-- Pending -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-h">
        <h3>
          <LucideIcon name="clock" class="ic" style="color:var(--gold-600)!important" />
          Pending — Action Required
        </h3>
        <span class="badge b-gold">{{ pending.length }}</span>
      </div>

      <div v-if="!pending.length" style="padding:28px;text-align:center;color:var(--faint);font-size:13px">
        No pending reservations.
      </div>

      <div v-for="(r, i) in pending" :key="r.id" class="qitem">
        <div :class="['cover', color(i)]" style="width:36px;height:46px;flex-shrink:0">
          <div class="cover-top"><div class="cover-rule"></div><div class="cover-t">{{ r.book?.title?.slice(0,4) }}</div></div>
        </div>
        <div class="q-info">
          <div class="q-t">{{ r.book?.title }}</div>
          <div class="q-m">
            <span><LucideIcon name="user" :size="12" /> {{ r.user?.name }} · {{ r.user?.member_number ?? r.user?.email }}</span>
            <span><LucideIcon name="clock" :size="12" /> {{ timeAgo(r.reserved_at) }}</span>
          </div>
        </div>
        <div class="q-acts">
          <button
            class="btn btn-green btn-sm"
            :disabled="processingId === r.id"
            @click="markReady(r)"
          >
            <LucideIcon name="check" class="ic-sm" />
            {{ processingId === r.id ? '…' : 'Mark Ready' }}
          </button>
          <button
            class="btn btn-danger btn-sm"
            :disabled="processingId === r.id"
            @click="cancelReservation(r)"
          >
            <LucideIcon name="x" class="ic-sm" />
          </button>
        </div>
      </div>
    </div>

    <!-- Ready for collection -->
    <div class="card">
      <div class="card-h">
        <h3>
          <LucideIcon name="package-check" class="ic" style="color:var(--green)!important" />
          Ready for Collection
        </h3>
        <span class="badge b-green">{{ fulfilled.length }}</span>
      </div>

      <div v-if="!fulfilled.length" style="padding:28px;text-align:center;color:var(--faint);font-size:13px">
        No items ready for collection.
      </div>

      <div v-for="(r, i) in fulfilled" :key="r.id" class="qitem">
        <div :class="['cover', color(i + pending.length)]" style="width:36px;height:46px;flex-shrink:0">
          <div class="cover-top"><div class="cover-rule"></div><div class="cover-t">{{ r.book?.title?.slice(0,4) }}</div></div>
        </div>
        <div class="q-info">
          <div class="q-t">{{ r.book?.title }}</div>
          <div class="q-m">
            <span><LucideIcon name="user" :size="12" /> {{ r.user?.name }}</span>
            <span v-if="r.expires_at"><LucideIcon name="calendar" :size="12" /> Held until {{ heldUntil(r) }}</span>
          </div>
        </div>
        <div class="q-acts">
          <span class="badge b-green"><span class="dot d-green"></span> Ready</span>
          <button
            class="btn btn-primary btn-sm"
            :disabled="issuingId === r.id"
            @click="issueNow(r)"
          >
            <LucideIcon name="hand-coins" class="ic-sm" />
            {{ issuingId === r.id ? 'Issuing…' : 'Issue Now' }}
          </button>
          <button
            class="btn btn-danger btn-sm"
            :disabled="processingId === r.id"
            @click="cancelReservation(r)"
          >
            <LucideIcon name="x" class="ic-sm" />
          </button>
        </div>
      </div>
    </div>
  </template>

  <!-- Marked Ready modal -->
  <div :class="['mscrim', { open: showReadyModal }]" @click.self="showReadyModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--green-50)">
          <LucideIcon name="package-check" :size="28" style="color:var(--green)" />
        </div>
        <div class="modal-t">Marked Ready</div>
        <div class="modal-s">
          "{{ readyReservation?.book?.title }}" is now ready for collection by {{ readyReservation?.user?.name }}.
          Hold expires {{ heldUntil(readyReservation ?? {}) }}.
        </div>
        <div class="modal-f">
          <button class="btn btn-primary btn-block" @click="showReadyModal = false">Done</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Issued modal -->
  <div :class="['mscrim', { open: showIssuedModal }]" @click.self="showIssuedModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--green-50)">
          <LucideIcon name="check-check" :size="28" style="color:var(--green)" />
        </div>
        <div class="modal-t">Book Issued</div>
        <div class="modal-s" v-if="issuedResult">
          "{{ issuedResult.reservation?.book?.title }}" issued to {{ issuedResult.reservation?.user?.name }}.
          <br />
          <span style="font-weight:600">Due: {{ issuedResult.loan?.due_date ? fmtDate(issuedResult.loan.due_date) : '—' }}</span>
        </div>
        <div class="modal-f">
          <button class="btn btn-primary btn-block" @click="showIssuedModal = false">Done</button>
        </div>
      </div>
    </div>
  </div>
</template>
