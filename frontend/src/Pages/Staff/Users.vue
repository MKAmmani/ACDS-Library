<script setup lang="ts">
import { reactive, ref, onMounted, watch } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPost, apiPatch, apiDelete } from '@/api/http'

const auth = useAuthStore()

interface Member {
  id: number
  name: string
  email: string
  role: 'admin' | 'staff' | 'user'
  is_active: boolean
  member_number: string | null
  phone: string | null
  address: string | null
  membership_type: 'student' | 'staff' | 'faculty' | 'public' | null
  membership_expires_at: string | null
  created_at: string
  loans_active_count?: number
  loans_limit?: number | null
  fines_unpaid_total?: number | string | null
}

interface MemberPage {
  data: Member[]
  total: number
  current_page: number
  last_page: number
}

interface Stats {
  total: number
  new_this_week: number
  suspended: number
  with_fines: number
}

// ── List state ──────────────────────────────────────────────────────────────
const members     = ref<Member[]>([])
const total        = ref(0)
const currentPage  = ref(1)
const lastPage     = ref(1)
const loading      = ref(true)
const fetchError   = ref('')
const stats        = ref<Stats>({ total: 0, new_this_week: 0, suspended: 0, with_fines: 0 })

// ── Filters ─────────────────────────────────────────────────────────────────
const searchQuery  = ref('')
const activeFilter = ref('all')
const filters = ref([
  { key: 'all',       label: 'All' },
  { key: 'standing',  label: 'Good Standing' },
  { key: 'fines',     label: 'With Fines' },
  { key: 'suspended', label: 'Suspended' },
])

// ── Register / Edit drawer ────────────────────────────────────────────────
const drawerOpen  = ref(false)
const editingUser = ref<Member | null>(null)
const saving      = ref(false)
const saveError   = ref('')

function emptyForm() {
  return { name: '', email: '', phone: '', address: '', membership_type: 'pg_student', membership_expires_at: '' }
}
const form = reactive(emptyForm())

function openAdd() {
  editingUser.value = null
  saveError.value   = ''
  Object.assign(form, emptyForm())
  drawerOpen.value  = true
}

function openEdit(user: Member) {
  editingUser.value = user
  Object.assign(form, {
    name: user.name,
    email: user.email,
    phone: user.phone ?? '',
    address: user.address ?? '',
    membership_type: user.membership_type ?? 'pg_student',
    membership_expires_at: user.membership_expires_at ?? '',
  })
  saveError.value  = ''
  drawerOpen.value = true
}

// ── View modal ────────────────────────────────────────────────────────────
const viewingUser = ref<Member | null>(null)

// ── Credentials modal (shown once after registering a member) ─────────────
const showCredsModal = ref(false)
const newCreds = ref<{ name: string; email: string; member_number: string | null; password: string } | null>(null)
const copied = ref(false)

function copyPassword() {
  if (!newCreds.value) return
  navigator.clipboard?.writeText(newCreds.value.password)
  copied.value = true
  setTimeout(() => { copied.value = false }, 1600)
}

// ── API ─────────────────────────────────────────────────────────────────────
async function fetchMembers() {
  loading.value    = true
  fetchError.value = ''
  try {
    const params = new URLSearchParams({ page: String(currentPage.value), role: 'user' })
    if (searchQuery.value)             params.set('search', searchQuery.value)
    if (activeFilter.value !== 'all')  params.set('status', activeFilter.value)

    const res = await apiGet<MemberPage>(`/admin/users?${params}`, auth.token ?? undefined)
    members.value     = res.data
    total.value        = res.total
    currentPage.value  = res.current_page
    lastPage.value     = res.last_page
  } catch (e: any) {
    fetchError.value = e.message
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    stats.value = await apiGet<Stats>('/admin/users/stats?role=user', auth.token ?? undefined)
  } catch { /* non-blocking */ }
}

async function save() {
  if (!form.name.trim() || !form.email.trim()) { saveError.value = 'Name and email are required.'; return }
  saving.value    = true
  saveError.value = ''
  try {
    if (editingUser.value) {
      await apiPatch<Member>(`/admin/users/${editingUser.value.id}`, {
        name: form.name, email: form.email, phone: form.phone || null, address: form.address || null,
      }, auth.token ?? undefined)
      await apiPatch<Member>(`/admin/users/${editingUser.value.id}/membership`, {
        membership_type: form.membership_type, membership_expires_at: form.membership_expires_at || null,
      }, auth.token ?? undefined)
      drawerOpen.value = false
      await fetchMembers()
    } else {
      const res = await apiPost<{ user: Member; generated_password: string }>('/admin/users', {
        name: form.name, email: form.email, role: 'user',
        phone: form.phone || undefined, address: form.address || undefined,
        membership_type: form.membership_type,
      }, auth.token ?? undefined)
      drawerOpen.value = false
      newCreds.value = {
        name: res.user.name, email: res.user.email,
        member_number: res.user.member_number, password: res.generated_password,
      }
      showCredsModal.value = true
      await fetchMembers()
      await fetchStats()
    }
  } catch (e: any) {
    saveError.value = e.message
  } finally {
    saving.value = false
  }
}

const busyId = ref<number | null>(null)

async function toggleActive(user: Member) {
  if (user.is_active && !confirm(`Suspend ${user.name}'s account? They will be signed out and unable to log in.`)) return
  busyId.value = user.id
  try {
    const updated = await apiPatch<Member>(`/admin/users/${user.id}/active`, {}, auth.token ?? undefined)
    const idx = members.value.findIndex(m => m.id === user.id)
    if (idx >= 0) members.value[idx] = { ...members.value[idx], ...updated }
    fetchStats()
  } catch (e: any) {
    alert(e.message)
  } finally {
    busyId.value = null
  }
}

async function removeUser(user: Member) {
  if (!confirm(`Delete ${user.name}'s account permanently? This cannot be undone.`)) return
  busyId.value = user.id
  try {
    await apiDelete(`/admin/users/${user.id}`, auth.token ?? undefined)
    members.value = members.value.filter(m => m.id !== user.id)
    total.value--
    fetchStats()
  } catch (e: any) {
    alert(e.message)
  } finally {
    busyId.value = null
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────
const avatarGradients = [
  'linear-gradient(140deg,var(--blue),var(--sky))',
  'linear-gradient(140deg,#234034,var(--green))',
  'linear-gradient(140deg,#9b2230,var(--red))',
  'linear-gradient(140deg,#5E2A35,#8a4250)',
]
function avatarBg(id: number) { return avatarGradients[id % avatarGradients.length] }
function initials(name: string) { return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2) }

const typeLabels: Record<string, string> = {
  buk_staff: 'BUK Staff',
  pg_student: 'PG Student',
  independent_researcher: 'Independent Researcher',
  international_researcher: 'International Researcher',
}
function typeLabel(t: string | null) { return t ? (typeLabels[t] ?? t) : '—' }

function fmtDate(d: string) { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) }
function fmtMoney(n: number | string | null | undefined) { return '₦' + Number(n || 0).toLocaleString() }

// ── Watchers ──────────────────────────────────────────────────────────────
let debounce: ReturnType<typeof setTimeout>
watch([searchQuery, activeFilter], () => {
  currentPage.value = 1
  clearTimeout(debounce)
  debounce = setTimeout(fetchMembers, 320)
})
watch(currentPage, fetchMembers)

onMounted(() => { fetchMembers(); fetchStats() })
</script>

<template>
  <div class="shead">
    <div><h2>Members</h2><p>{{ stats.total.toLocaleString() }} active members · {{ stats.new_this_week }} new this week</p></div>
    <button class="btn btn-primary" @click="openAdd"><LucideIcon name="user-plus" class="ic-sm" /> Register Member</button>
  </div>

  <div class="toolbar">
    <div class="search-in"><LucideIcon name="search" class="ic-sm" /><input v-model="searchQuery" placeholder="Search by name, member ID, or email…" /></div>
    <div class="fbtns">
      <div
        v-for="f in filters"
        :key="f.key"
        :class="['fbtn', { on: activeFilter === f.key }]"
        @click="activeFilter = f.key"
      >{{ f.label }}</div>
    </div>
  </div>

  <div v-if="fetchError" style="color:var(--red);font-size:13px;margin-bottom:12px;display:flex;align-items:center;gap:6px">
    <LucideIcon name="alert-triangle" class="ic-sm" /> {{ fetchError }}
  </div>

  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Member</th><th>Member ID</th><th>Type</th><th>On Loan</th><th>Fines</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
        <tr v-if="loading"><td colspan="7" style="text-align:center;padding:36px;color:var(--faint)">Loading members…</td></tr>
        <tr v-else-if="!members.length"><td colspan="7" style="text-align:center;padding:36px;color:var(--faint)">No members found.</td></tr>
        <tr v-for="m in members" v-else :key="m.id">
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="sb-av" style="width:32px;height:32px" :style="{ background: avatarBg(m.id) }">{{ initials(m.name) }}</div>
              <div><div class="bk-t">{{ m.name }}</div><div class="bk-a">{{ m.email }}</div></div>
            </div>
          </td>
          <td class="mono">{{ m.member_number ?? '—' }}</td>
          <td><span class="tag">{{ typeLabel(m.membership_type) }}</span></td>
          <td>{{ m.loans_active_count ?? 0 }} / {{ m.loans_limit ?? '—' }}</td>
          <td :style="{ color: Number(m.fines_unpaid_total) > 0 ? 'var(--red)' : 'var(--green-600)', fontWeight: Number(m.fines_unpaid_total) > 0 ? 700 : 400 }">
            {{ fmtMoney(m.fines_unpaid_total) }}
          </td>
          <td>
            <span :class="['badge', m.is_active ? 'b-green' : 'b-red']">
              <span :class="['dot', m.is_active ? 'd-green' : 'd-red']"></span> {{ m.is_active ? 'Active' : 'Suspended' }}
            </span>
          </td>
          <td>
            <div class="rowacts" style="justify-content:flex-end">
              <div class="ra" title="View" @click="viewingUser = m"><LucideIcon name="eye" /></div>
              <div class="ra" title="Edit" @click="openEdit(m)"><LucideIcon name="pencil" /></div>
              <div class="ra" :title="m.is_active ? 'Suspend' : 'Reactivate'" @click="toggleActive(m)"><LucideIcon :name="m.is_active ? 'lock' : 'unlock'" /></div>
              <div class="ra del" title="Delete" @click="removeUser(m)"><LucideIcon name="trash-2" /></div>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div v-if="lastPage > 1" style="display:flex;align-items:center;justify-content:space-between;padding:16px 0">
    <span style="font-size:12px;color:var(--muted)">Page {{ currentPage }} of {{ lastPage }}</span>
    <div style="display:flex;gap:6px">
      <button class="btn btn-ghost btn-sm" :disabled="currentPage === 1" @click="currentPage--"><LucideIcon name="chevron-left" class="ic-sm" /> Prev</button>
      <button class="btn btn-ghost btn-sm" :disabled="currentPage === lastPage" @click="currentPage++">Next <LucideIcon name="chevron-right" class="ic-sm" /></button>
    </div>
  </div>

  <!-- ── Register / Edit Drawer ── -->
  <div :class="['scrim', { open: drawerOpen }]" @click.self="drawerOpen = false">
    <div :class="['drawer', { open: drawerOpen }]">
      <div class="dh">
        <div>
          <div class="dh-t">{{ editingUser ? 'Edit Member' : 'Register Member' }}</div>
          <div class="dh-s">{{ editingUser ? 'Update member details' : 'A login password is generated automatically' }}</div>
        </div>
        <div class="dh-x" @click="drawerOpen = false"><LucideIcon name="x" /></div>
      </div>

      <div class="db">
        <div v-if="saveError" style="padding:10px 14px;background:var(--red-50);color:var(--red);border-radius:var(--r2);margin-bottom:16px;font-size:13px">
          {{ saveError }}
        </div>

        <div class="db-sec">
          <div class="db-sec-h"><LucideIcon name="user" /> Personal Details</div>
          <div class="form-grid">
            <div class="fg col2">
              <label class="fl">Full Name <span class="req">*</span></label>
              <input class="fi" v-model="form.name" placeholder="Full name" />
            </div>
            <div class="fg col2">
              <label class="fl">Email <span class="req">*</span></label>
              <input class="fi" type="email" v-model="form.email" placeholder="member@example.com" />
            </div>
            <div class="fg">
              <label class="fl">Phone</label>
              <input class="fi" v-model="form.phone" placeholder="080…" />
            </div>
            <div class="fg">
              <label class="fl">Membership Type</label>
              <select class="fs" v-model="form.membership_type">
                <option value="buk_staff">BUK Staff</option>
                <option value="pg_student">PG Student</option>
                <option value="independent_researcher">Independent Researcher</option>
                <option value="international_researcher">International Researcher</option>
              </select>
            </div>
            <div class="fg col2">
              <label class="fl">Address</label>
              <textarea class="ft" v-model="form.address" placeholder="Residential / institutional address"></textarea>
            </div>
            <div v-if="editingUser" class="fg col2">
              <label class="fl">Membership Expires</label>
              <input class="fi" type="date" v-model="form.membership_expires_at" />
            </div>
          </div>
        </div>
      </div>

      <div class="df">
        <button class="btn btn-primary" style="flex:1;justify-content:center" :disabled="saving" @click="save">
          <LucideIcon :name="saving ? 'refresh-cw' : 'save'" class="ic-sm" />
          {{ saving ? 'Saving…' : editingUser ? 'Save Changes' : 'Register Member' }}
        </button>
        <button class="btn btn-ghost" @click="drawerOpen = false">Cancel</button>
      </div>
    </div>
  </div>

  <!-- ── View Modal ── -->
  <div :class="['mscrim', { open: !!viewingUser }]" @click.self="viewingUser = null">
    <div class="modal" v-if="viewingUser">
      <div class="modal-b">
        <div class="sb-av" style="width:56px;height:56px;font-size:18px;margin:0 auto 14px" :style="{ background: avatarBg(viewingUser.id) }">{{ initials(viewingUser.name) }}</div>
        <div class="modal-t">{{ viewingUser.name }}</div>
        <div class="modal-s">{{ viewingUser.email }}</div>
        <div style="text-align:left;font-size:12.5px;color:var(--ink);display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0;padding:14px;background:var(--bg);border-radius:var(--r2)">
          <div><b>Member ID</b><br />{{ viewingUser.member_number ?? '—' }}</div>
          <div><b>Type</b><br />{{ typeLabel(viewingUser.membership_type) }}</div>
          <div><b>Phone</b><br />{{ viewingUser.phone ?? '—' }}</div>
          <div><b>Status</b><br />{{ viewingUser.is_active ? 'Active' : 'Suspended' }}</div>
          <div><b>On Loan</b><br />{{ viewingUser.loans_active_count ?? 0 }} / {{ viewingUser.loans_limit ?? '—' }}</div>
          <div><b>Fines Owed</b><br />{{ fmtMoney(viewingUser.fines_unpaid_total) }}</div>
          <div><b>Joined</b><br />{{ fmtDate(viewingUser.created_at) }}</div>
          <div style="grid-column:span 2"><b>Address</b><br />{{ viewingUser.address ?? '—' }}</div>
        </div>
        <div class="modal-f">
          <button class="btn btn-primary btn-block" @click="viewingUser = null">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Credentials Modal ── -->
  <div :class="['mscrim', { open: showCredsModal }]" @click.self="showCredsModal = false">
    <div class="modal" v-if="newCreds">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--green-50)"><LucideIcon name="user-check" style="width:28px;height:28px;color:var(--green-600)" /></div>
        <div class="modal-t">Member Registered</div>
        <div class="modal-s">Share these login credentials with <b>{{ newCreds.name }}</b>. The password is shown only once.</div>
        <div style="text-align:left;font-size:12.5px;background:var(--bg);border-radius:var(--r2);padding:14px;margin-bottom:18px">
          <div style="margin-bottom:8px"><b>Member ID:</b> {{ newCreds.member_number }}</div>
          <div style="margin-bottom:8px"><b>Email:</b> {{ newCreds.email }}</div>
          <div style="display:flex;align-items:center;gap:8px">
            <b>Password:</b>
            <span style="background:#fff;padding:3px 7px;border-radius:4px;border:1px solid var(--line);font-family:ui-monospace,monospace">{{ newCreds.password }}</span>
            <div class="ra" style="width:26px;height:26px" @click="copyPassword" title="Copy password">
              <LucideIcon :name="copied ? 'check' : 'copy'" style="width:13px;height:13px" />
            </div>
          </div>
        </div>
        <div class="modal-f">
          <button class="btn btn-primary btn-block" @click="showCredsModal = false; newCreds = null">Done</button>
        </div>
      </div>
    </div>
  </div>
</template>
