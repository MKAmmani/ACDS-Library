<script setup lang="ts">
import { reactive, ref, computed, onMounted, watch } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPost, apiPatch, apiDelete } from '@/api/http'

const auth = useAuthStore()

interface Account {
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

interface AccountPage {
  data: Account[]
  total: number
  current_page: number
  last_page: number
}

interface Stats {
  total: number
  admins: number
  staff: number
  users: number
  suspended: number
  new_this_week: number
}

// ── List state ──────────────────────────────────────────────────────────────
const accounts     = ref<Account[]>([])
const total         = ref(0)
const currentPage   = ref(1)
const lastPage      = ref(1)
const loading       = ref(true)
const fetchError    = ref('')
const stats         = ref<Stats>({ total: 0, admins: 0, staff: 0, users: 0, suspended: 0, new_this_week: 0 })

// ── Filters — the difference from the Members page: filter by role ────────
const searchQuery  = ref('')
const activeFilter = ref('all')
const filters = computed(() => [
  { key: 'all',   label: `All (${stats.value.total})` },
  { key: 'admin', label: `Admins (${stats.value.admins})` },
  { key: 'staff', label: `Staff (${stats.value.staff})` },
  { key: 'user',  label: `Users (${stats.value.users})` },
])

const roleBadge: Record<string, string> = { admin: 'b-purple', staff: 'b-blue', user: 'b-gray' }
const roleLabel: Record<string, string> = { admin: 'Admin', staff: 'Staff', user: 'User' }

// ── Register / Edit drawer ────────────────────────────────────────────────
const drawerOpen  = ref(false)
const editingUser = ref<Account | null>(null)
const saving      = ref(false)
const saveError   = ref('')

function emptyForm() {
  return { name: '', email: '', role: 'staff', phone: '', address: '', membership_type: 'pg_student', membership_expires_at: '' }
}
const form = reactive(emptyForm())

function openAdd() {
  editingUser.value = null
  saveError.value   = ''
  Object.assign(form, emptyForm())
  drawerOpen.value  = true
}

function openEdit(user: Account) {
  editingUser.value = user
  Object.assign(form, {
    name: user.name,
    email: user.email,
    role: user.role,
    phone: user.phone ?? '',
    address: user.address ?? '',
    membership_type: user.membership_type ?? 'pg_student',
    membership_expires_at: user.membership_expires_at ?? '',
  })
  saveError.value  = ''
  drawerOpen.value = true
}

// ── View modal ────────────────────────────────────────────────────────────
const viewingUser = ref<Account | null>(null)

// ── Credentials modal (shown once after registering an account) ───────────
const showCredsModal = ref(false)
const newCreds = ref<{ name: string; email: string; role: string; member_number: string | null; password: string } | null>(null)
const copied = ref(false)

function copyPassword() {
  if (!newCreds.value) return
  navigator.clipboard?.writeText(newCreds.value.password)
  copied.value = true
  setTimeout(() => { copied.value = false }, 1600)
}

// ── API ─────────────────────────────────────────────────────────────────────
async function fetchAccounts() {
  loading.value    = true
  fetchError.value = ''
  try {
    const params = new URLSearchParams({ page: String(currentPage.value) })
    if (activeFilter.value !== 'all') params.set('role', activeFilter.value)
    if (searchQuery.value)            params.set('search', searchQuery.value)

    const res = await apiGet<AccountPage>(`/admin/users?${params}`, auth.token ?? undefined)
    accounts.value      = res.data
    total.value          = res.total
    currentPage.value    = res.current_page
    lastPage.value       = res.last_page
  } catch (e: any) {
    fetchError.value = e.message
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    stats.value = await apiGet<Stats>('/admin/users/stats', auth.token ?? undefined)
  } catch { /* non-blocking */ }
}

async function save() {
  if (!form.name.trim() || !form.email.trim()) { saveError.value = 'Name and email are required.'; return }
  saving.value    = true
  saveError.value = ''
  try {
    if (editingUser.value) {
      const id = editingUser.value.id
      await apiPatch<Account>(`/admin/users/${id}`, {
        name: form.name, email: form.email, phone: form.phone || null, address: form.address || null,
      }, auth.token ?? undefined)

      if (form.role !== editingUser.value.role) {
        await apiPatch<Account>(`/admin/users/${id}/role`, { role: form.role }, auth.token ?? undefined)
      }

      if (form.role === 'user') {
        await apiPatch<Account>(`/admin/users/${id}/membership`, {
          membership_type: form.membership_type, membership_expires_at: form.membership_expires_at || null,
        }, auth.token ?? undefined)
      }

      drawerOpen.value = false
      await fetchAccounts()
      await fetchStats()
    } else {
      const res = await apiPost<{ user: Account; generated_password: string }>('/admin/users', {
        name: form.name, email: form.email, role: form.role,
        phone: form.phone || undefined, address: form.address || undefined,
        membership_type: form.role === 'user' ? form.membership_type : undefined,
      }, auth.token ?? undefined)
      drawerOpen.value = false
      newCreds.value = {
        name: res.user.name, email: res.user.email, role: res.user.role,
        member_number: res.user.member_number, password: res.generated_password,
      }
      showCredsModal.value = true
      await fetchAccounts()
      await fetchStats()
    }
  } catch (e: any) {
    saveError.value = e.message
  } finally {
    saving.value = false
  }
}

const busyId = ref<number | null>(null)

function isSelf(user: Account) { return user.id === auth.user?.id }

async function toggleActive(user: Account) {
  if (isSelf(user)) return
  if (user.is_active && !confirm(`Suspend ${user.name}'s account? They will be signed out and unable to log in.`)) return
  busyId.value = user.id
  try {
    const updated = await apiPatch<Account>(`/admin/users/${user.id}/active`, {}, auth.token ?? undefined)
    const idx = accounts.value.findIndex(a => a.id === user.id)
    if (idx >= 0) accounts.value[idx] = { ...accounts.value[idx], ...updated }
    fetchStats()
  } catch (e: any) {
    alert(e.message)
  } finally {
    busyId.value = null
  }
}

async function removeUser(user: Account) {
  if (isSelf(user)) return
  if (!confirm(`Delete ${user.name}'s account permanently? This cannot be undone.`)) return
  busyId.value = user.id
  try {
    await apiDelete(`/admin/users/${user.id}`, auth.token ?? undefined)
    accounts.value = accounts.value.filter(a => a.id !== user.id)
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
  debounce = setTimeout(fetchAccounts, 320)
})
watch(currentPage, fetchAccounts)

onMounted(() => { fetchAccounts(); fetchStats() })
</script>

<template>
  <div class="shead">
    <div><h2>Manage Accounts</h2><p>{{ stats.total.toLocaleString() }} accounts · {{ stats.admins }} admins · {{ stats.staff }} staff · {{ stats.users }} members</p></div>
    <button class="btn btn-primary" @click="openAdd"><LucideIcon name="user-plus" class="ic-sm" /> Register Member</button>
  </div>

  <div class="toolbar">
    <div class="search-in"><LucideIcon name="search" class="ic-sm" /><input v-model="searchQuery" placeholder="Search by name or email…" /></div>
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
      <thead><tr><th>Account</th><th>Role</th><th>Status</th><th>Joined</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
        <tr v-if="loading"><td colspan="5" style="text-align:center;padding:36px;color:var(--faint)">Loading accounts…</td></tr>
        <tr v-else-if="!accounts.length"><td colspan="5" style="text-align:center;padding:36px;color:var(--faint)">No accounts found.</td></tr>
        <tr v-for="a in accounts" v-else :key="a.id">
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="sb-av" style="width:32px;height:32px" :style="{ background: avatarBg(a.id) }">{{ initials(a.name) }}</div>
              <div><div class="bk-t">{{ a.name }} <span v-if="isSelf(a)" style="color:var(--faint);font-weight:400">(you)</span></div><div class="bk-a">{{ a.email }}</div></div>
            </div>
          </td>
          <td><span :class="['badge', roleBadge[a.role]]">{{ roleLabel[a.role] }}</span></td>
          <td>
            <span :class="['badge', a.is_active ? 'b-green' : 'b-red']">
              <span :class="['dot', a.is_active ? 'd-green' : 'd-red']"></span> {{ a.is_active ? 'Active' : 'Suspended' }}
            </span>
          </td>
          <td style="color:var(--muted)">{{ fmtDate(a.created_at) }}</td>
          <td>
            <div class="rowacts" style="justify-content:flex-end">
              <div class="ra" title="View" @click="viewingUser = a"><LucideIcon name="eye" /></div>
              <div class="ra" title="Edit" @click="openEdit(a)"><LucideIcon name="pencil" /></div>
              <div
                class="ra" :style="isSelf(a) ? 'opacity:.35;pointer-events:none' : ''"
                :title="a.is_active ? 'Suspend' : 'Reactivate'" @click="toggleActive(a)"
              ><LucideIcon :name="a.is_active ? 'lock' : 'unlock'" /></div>
              <div
                class="ra del" :style="isSelf(a) ? 'opacity:.35;pointer-events:none' : ''"
                title="Delete" @click="removeUser(a)"
              ><LucideIcon name="trash-2" /></div>
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
          <div class="dh-t">{{ editingUser ? 'Edit Account' : 'Register Member' }}</div>
          <div class="dh-s">{{ editingUser ? 'Update account details' : 'A login password is generated automatically' }}</div>
        </div>
        <div class="dh-x" @click="drawerOpen = false"><LucideIcon name="x" /></div>
      </div>

      <div class="db">
        <div v-if="saveError" style="padding:10px 14px;background:var(--red-50);color:var(--red);border-radius:var(--r2);margin-bottom:16px;font-size:13px">
          {{ saveError }}
        </div>

        <div class="db-sec">
          <div class="db-sec-h"><LucideIcon name="user" /> Account Details</div>
          <div class="form-grid">
            <div class="fg col2">
              <label class="fl">Full Name <span class="req">*</span></label>
              <input class="fi" v-model="form.name" placeholder="Full name" />
            </div>
            <div class="fg col2">
              <label class="fl">Email <span class="req">*</span></label>
              <input class="fi" type="email" v-model="form.email" placeholder="name@example.com" />
            </div>
            <div class="fg">
              <label class="fl">Role <span class="req">*</span></label>
              <select class="fs" v-model="form.role" :disabled="!!editingUser && isSelf(editingUser)">
                <option value="admin">Admin</option>
                <option value="staff">Staff</option>
                <option value="user">User (Member)</option>
              </select>
            </div>
            <div class="fg">
              <label class="fl">Phone</label>
              <input class="fi" v-model="form.phone" placeholder="080…" />
            </div>
            <div class="fg col2">
              <label class="fl">Address</label>
              <textarea class="ft" v-model="form.address" placeholder="Residential / institutional address"></textarea>
            </div>
          </div>
        </div>

        <div class="db-sec" v-if="form.role === 'user'">
          <div class="db-sec-h"><LucideIcon name="book-open" /> Membership</div>
          <div class="form-grid">
            <div class="fg">
              <label class="fl">Membership Type</label>
              <select class="fs" v-model="form.membership_type">
                <option value="buk_staff">BUK Staff</option>
                <option value="pg_student">PG Student</option>
                <option value="independent_researcher">Independent Researcher</option>
                <option value="international_researcher">International Researcher</option>
              </select>
            </div>
            <div class="fg" v-if="editingUser">
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
          <div><b>Role</b><br />{{ roleLabel[viewingUser.role] }}</div>
          <div><b>Status</b><br />{{ viewingUser.is_active ? 'Active' : 'Suspended' }}</div>
          <div><b>Phone</b><br />{{ viewingUser.phone ?? '—' }}</div>
          <div><b>Joined</b><br />{{ fmtDate(viewingUser.created_at) }}</div>
          <template v-if="viewingUser.role === 'user'">
            <div><b>Member ID</b><br />{{ viewingUser.member_number ?? '—' }}</div>
            <div><b>Type</b><br />{{ typeLabel(viewingUser.membership_type) }}</div>
            <div><b>On Loan</b><br />{{ viewingUser.loans_active_count ?? 0 }} / {{ viewingUser.loans_limit ?? '—' }}</div>
            <div><b>Fines Owed</b><br />{{ fmtMoney(viewingUser.fines_unpaid_total) }}</div>
          </template>
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
        <div class="modal-t">Account Created</div>
        <div class="modal-s">Share these login credentials with <b>{{ newCreds.name }}</b> ({{ roleLabel[newCreds.role] }}). The password is shown only once.</div>
        <div style="text-align:left;font-size:12.5px;background:var(--bg);border-radius:var(--r2);padding:14px;margin-bottom:18px">
          <div v-if="newCreds.member_number" style="margin-bottom:8px"><b>Member ID:</b> {{ newCreds.member_number }}</div>
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
