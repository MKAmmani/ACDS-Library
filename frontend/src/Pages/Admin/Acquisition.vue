<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPatch, apiDelete } from '@/api/http'
import LucideIcon from '@/components/LucideIcon.vue'

const router = useRouter()
const auth   = useAuthStore()

const acquisitions = ref<any[]>([])
const loading      = ref(true)
const error        = ref('')
const processingId = ref<number | null>(null)

const statusFilter = ref('')

const statusLabel: Record<string, string> = {
  awaiting: 'Awaiting Approval',
  ordered:  'Ordered',
  received: 'Received',
  declined: 'Declined',
}

const statusBadge: Record<string, string> = {
  awaiting: 'b-gold',
  ordered:  'b-blue',
  received: 'b-green',
  declined: 'b-red',
}

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const q   = statusFilter.value ? `?status=${statusFilter.value}` : ''
    const res = await apiGet<any>(`/admin/acquisitions${q}`, auth.token ?? undefined)
    acquisitions.value = res.data ?? []
  } catch (e: any) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function updateStatus(acq: any, status: string) {
  processingId.value = acq.id
  try {
    const updated = await apiPatch<any>(`/admin/acquisitions/${acq.id}`, { status }, auth.token ?? undefined)
    const idx = acquisitions.value.findIndex((a: any) => a.id === acq.id)
    if (idx >= 0) acquisitions.value[idx] = updated
  } catch (e: any) {
    alert(e.message)
  } finally {
    processingId.value = null
  }
}

async function deleteAcq(acq: any) {
  if (!confirm(`Delete acquisition request for "${acq.title}"?`)) return
  processingId.value = acq.id
  try {
    await apiDelete<any>(`/admin/acquisitions/${acq.id}`, auth.token ?? undefined)
    acquisitions.value = acquisitions.value.filter((a: any) => a.id !== acq.id)
  } catch (e: any) {
    alert(e.message)
  } finally {
    processingId.value = null
  }
}

function fmtCost(c: any) {
  if (!c) return '—'
  return '₦' + parseFloat(c).toLocaleString()
}

onMounted(load)
</script>

<template>
  <div class="shead">
    <div>
      <h2>Acquisitions</h2>
      <p>{{ loading ? 'Loading…' : acquisitions.length + ' requests' }} · review and approve requests submitted by staff</p>
    </div>
  </div>

  <!-- Filter strip -->
  <div class="toolbar" style="margin-bottom:16px">
    <select class="sel" v-model="statusFilter" @change="load">
      <option value="">All Statuses</option>
      <option value="awaiting">Awaiting Approval</option>
      <option value="ordered">Ordered</option>
      <option value="received">Received</option>
      <option value="declined">Declined</option>
    </select>
    <button class="btn btn-ghost btn-sm" @click="load">
      <LucideIcon name="refresh-cw" class="ic-sm" /> Refresh
    </button>
  </div>

  <div v-if="loading" style="padding:40px;text-align:center;color:var(--faint)">Loading…</div>
  <div v-else-if="error" style="padding:16px;color:var(--red);background:var(--red-50);border-radius:var(--r2)">{{ error }}</div>

  <div v-else-if="!acquisitions.length" class="tbl-wrap" style="padding:40px;text-align:center;color:var(--faint)">
    <LucideIcon name="truck" style="width:36px;height:36px;margin:0 auto 10px;display:block;opacity:.35" />
    No acquisition requests yet.
  </div>

  <div v-else class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Title / Author</th>
          <th>Requested By</th>
          <th>Est. Cost</th>
          <th>Copies</th>
          <th>Status</th>
          <th style="text-align:right">Action</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="acq in acquisitions" :key="acq.id">
          <td>
            <div>
              <div class="bk-t">{{ acq.title }}</div>
              <div class="bk-a">{{ acq.authors || '—' }}</div>
            </div>
          </td>
          <td style="color:var(--muted)">{{ acq.requested_by || acq.user?.name || '—' }}</td>
          <td>{{ fmtCost(acq.estimated_cost) }}</td>
          <td>{{ acq.copies }}</td>
          <td><span :class="['badge', statusBadge[acq.status] || 'b-gray']">{{ statusLabel[acq.status] || acq.status }}</span></td>
          <td style="text-align:right">
            <div class="rowacts" style="justify-content:flex-end">
              <!-- Awaiting -->
              <template v-if="acq.status === 'awaiting'">
                <button class="btn btn-green btn-sm" :disabled="processingId === acq.id" @click="updateStatus(acq, 'ordered')">Approve</button>
                <button class="btn btn-danger btn-sm" :disabled="processingId === acq.id" @click="updateStatus(acq, 'declined')">Decline</button>
              </template>
              <!-- Ordered -->
              <template v-else-if="acq.status === 'ordered'">
                <button class="btn btn-ghost btn-sm" :disabled="processingId === acq.id" @click="updateStatus(acq, 'received')">Mark Received</button>
              </template>
              <!-- Received -->
              <template v-else-if="acq.status === 'received'">
                <span v-if="acq.book_id" class="badge b-green" style="cursor:pointer" title="View in Catalog" @click="router.push('/admin/catalog')">
                  <LucideIcon name="check" class="ic-sm" /> Logged
                </span>
              </template>
              <!-- Declined -->
              <template v-else>
                <button class="btn btn-ghost btn-sm" :disabled="processingId === acq.id" @click="updateStatus(acq, 'awaiting')">Reopen</button>
              </template>
              <button class="btn btn-danger btn-sm" :disabled="processingId === acq.id" @click="deleteAcq(acq)" title="Delete">
                <LucideIcon name="trash-2" class="ic-sm" />
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
