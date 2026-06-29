<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPost, apiPatch, apiDelete } from '@/api/http'
import LucideIcon from '@/components/LucideIcon.vue'

const router = useRouter()
const auth   = useAuthStore()

const acquisitions = ref<any[]>([])
const loading      = ref(true)
const error        = ref('')
const processingId = ref<number | null>(null)

const showDrawer   = ref(false)
const form = ref({ title: '', authors: '', requested_by: '', estimated_cost: '', copies: 1, notes: '' })
const formError   = ref('')
const formLoading = ref(false)

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

async function submit() {
  if (!form.value.title.trim()) { formError.value = 'Title is required'; return }
  formError.value  = ''
  formLoading.value = true
  try {
    const body: any = {
      title:          form.value.title,
      authors:        form.value.authors || undefined,
      requested_by:   form.value.requested_by || undefined,
      estimated_cost: form.value.estimated_cost ? parseFloat(form.value.estimated_cost) : undefined,
      copies:         form.value.copies,
      notes:          form.value.notes || undefined,
    }
    const created = await apiPost<any>('/admin/acquisitions', body, auth.token ?? undefined)
    acquisitions.value.unshift(created)
    showDrawer.value = false
    form.value = { title: '', authors: '', requested_by: '', estimated_cost: '', copies: 1, notes: '' }
  } catch (e: any) {
    formError.value = e.message
  } finally {
    formLoading.value = false
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
      <p>{{ loading ? 'Loading…' : acquisitions.length + ' requests' }}</p>
    </div>
    <button class="btn btn-primary" @click="showDrawer = true">
      <LucideIcon name="plus" class="ic-sm" /> New Request
    </button>
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
    No acquisition requests yet. Click "New Request" to add one.
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
                <button class="btn btn-primary btn-sm" @click="router.push('/staff/catalog')">Catalog It</button>
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

  <!-- New Acquisition Drawer -->
  <div :class="['scrim', { open: showDrawer }]" @click.self="showDrawer = false">
    <div :class="['drawer', { open: showDrawer }]">
      <div class="dh">
        <div>
          <div class="dh-t">New Acquisition Request</div>
          <div class="dh-s">Add a book purchase request for review</div>
        </div>
        <div class="dh-x" @click="showDrawer = false"><LucideIcon name="x" /></div>
      </div>

      <div class="db">
        <div v-if="formError" style="padding:10px 14px;background:var(--red-50);color:var(--red);border-radius:var(--r2);margin-bottom:16px;font-size:13px">
          {{ formError }}
        </div>

        <div class="db-sec">
          <div class="db-sec-h"><LucideIcon name="book-open" /> Book Details</div>
          <div class="form-grid">
            <div class="fg col2">
              <label class="fl">Title <span class="req">*</span></label>
              <input class="fi" v-model="form.title" placeholder="Book title" />
            </div>
            <div class="fg col2">
              <label class="fl">Author(s)</label>
              <input class="fi" v-model="form.authors" placeholder="Author name(s)" />
            </div>
          </div>
        </div>

        <div class="db-sec">
          <div class="db-sec-h"><LucideIcon name="truck" /> Order Details</div>
          <div class="form-grid">
            <div class="fg">
              <label class="fl">Requested By</label>
              <input class="fi" v-model="form.requested_by" placeholder="Name / department" />
            </div>
            <div class="fg">
              <label class="fl">Est. Cost (₦)</label>
              <input class="fi" v-model="form.estimated_cost" type="number" min="0" placeholder="0" />
            </div>
            <div class="fg">
              <label class="fl">Copies</label>
              <input class="fi" v-model.number="form.copies" type="number" min="1" />
            </div>
            <div class="fg col2">
              <label class="fl">Notes</label>
              <textarea class="ft" v-model="form.notes" placeholder="Additional notes…"></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="df">
        <button class="btn btn-primary" :disabled="formLoading" @click="submit">
          <LucideIcon name="plus" class="ic-sm" />
          {{ formLoading ? 'Saving…' : 'Submit Request' }}
        </button>
        <button class="btn btn-ghost" @click="showDrawer = false">Cancel</button>
      </div>
    </div>
  </div>
</template>
