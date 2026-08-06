<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPost, apiPatch, apiDelete } from '@/api/http'
import LucideIcon from '@/components/LucideIcon.vue'

const auth = useAuthStore()

type TabKey = 'news' | 'events' | 'hours'

const TABS: { key: TabKey; label: string; icon: string }[] = [
  { key: 'news',   label: 'News',          icon: 'newspaper' },
  { key: 'events', label: 'Events',        icon: 'calendar' },
  { key: 'hours',  label: 'Opening Hours', icon: 'clock' },
]

const ENDPOINTS: Record<TabKey, { list: string; base: string; idKey: string }> = {
  news:   { list: '/news?limit=100',            base: '/admin/news',         idKey: 'id' },
  events: { list: '/events?limit=100&upcoming=0', base: '/admin/events',      idKey: 'id' },
  hours:  { list: '/opening-hours',             base: '/admin/opening-hours', idKey: 'id' },
}

const activeTab = ref<TabKey>('news')
const items    = ref<any[]>([])
const loading  = ref(true)
const error    = ref('')

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const res = await apiGet<any>(ENDPOINTS[activeTab.value].list, auth.token ?? undefined)
    items.value = res?.data ?? []
  } catch (e: any) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function switchTab(tab: TabKey) {
  activeTab.value = tab
  load()
}

// ── Add / Edit drawer ──────────────────────────────────────────────────────
const drawerOpen = ref(false)
const editing    = ref<any>(null)
const saving     = ref(false)
const saveError  = ref('')

const NEWS_TAGS  = ['Notice', 'Update', 'Announcement']
const TONE_KEYS  = ['blue', 'sky', 'gold', 'purple', 'amber', 'green', 'red']

const emptyForm: Record<TabKey, Record<string, any>> = {
  news:   { tag: 'Notice', title: '', body: '', icon: 'bell', tone: 'gold', published_at: '' },
  events: { title: '', starts_at: '', time_label: '', place: '' },
  hours:  { day_label: '', time_label: '', is_closed: false, sort_order: 0 },
}

const form = ref<Record<string, any>>({})

function toDateInput(v: string | null) {
  return v ? new Date(v).toISOString().slice(0, 10) : ''
}
function toDateTimeLocal(v: string | null) {
  return v ? new Date(v).toISOString().slice(0, 16) : ''
}

function openAdd() {
  editing.value = null
  form.value    = { ...emptyForm[activeTab.value] }
  saveError.value = ''
  drawerOpen.value = true
}

function openEdit(row: any) {
  editing.value = row
  saveError.value = ''
  if (activeTab.value === 'events') {
    form.value = { ...row, starts_at: toDateTimeLocal(row.starts_at) }
  } else if (activeTab.value === 'news') {
    form.value = { ...row, published_at: toDateInput(row.published_at) }
  } else {
    form.value = { ...row }
  }
  drawerOpen.value = true
}

function closeDrawer() { drawerOpen.value = false }

async function save() {
  saving.value    = true
  saveError.value = ''
  try {
    const { list, base } = ENDPOINTS[activeTab.value]
    // An empty date/text input is '' rather than omitted — strip those so
    // optional fields (e.g. published_at left blank) don't fail 'nullable|date'.
    const payload: Record<string, any> = {}
    for (const [k, v] of Object.entries(form.value)) {
      if (v !== '') payload[k] = v
    }
    if (editing.value) {
      await apiPatch<any>(`${base}/${editing.value.id}`, payload, auth.token ?? undefined)
    } else {
      await apiPost<any>(base, payload, auth.token ?? undefined)
    }
    drawerOpen.value = false
    await load()
  } catch (e: any) {
    saveError.value = e.message
  } finally {
    saving.value = false
  }
}

// ── Delete ───────────────────────────────────────────────────────────────
const deleting = ref<any>(null)
function confirmDelete(row: any) { deleting.value = row }
async function doDelete() {
  if (!deleting.value) return
  try {
    await apiDelete<any>(`${ENDPOINTS[activeTab.value].base}/${deleting.value.id}`, auth.token ?? undefined)
    items.value = items.value.filter(i => i.id !== deleting.value.id)
  } catch (e: any) {
    alert(e.message)
  } finally {
    deleting.value = null
  }
}

// ── Display helpers ─────────────────────────────────────────────────────────
function fmtDate(d: string | null) {
  return d ? new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
}

const tabTitle = computed(() => TABS.find(t => t.key === activeTab.value)?.label ?? '')

onMounted(load)
</script>

<template>
  <div class="shead">
    <div>
      <h2>Website Content</h2>
      <p>Manage the news, events &amp; opening hours shown on the public landing page — videos have their own page</p>
    </div>
  </div>

  <!-- Tabs -->
  <div class="fbtns" style="margin-bottom:18px">
    <div v-for="t in TABS" :key="t.key" :class="['fbtn', { on: activeTab === t.key }]" @click="switchTab(t.key)">
      <LucideIcon :name="t.icon" class="ic-sm" style="margin-right:5px" />{{ t.label }}
    </div>
  </div>

  <div class="toolbar" style="margin-bottom:16px">
    <button class="btn btn-primary btn-sm" @click="openAdd">
      <LucideIcon name="plus" class="ic-sm" /> Add {{ tabTitle.replace(/s$/, '') }}
    </button>
    <button class="btn btn-ghost btn-sm" @click="load">
      <LucideIcon name="refresh-cw" class="ic-sm" /> Refresh
    </button>
  </div>

  <div v-if="loading" style="padding:40px;text-align:center;color:var(--faint)">Loading…</div>
  <div v-else-if="error" style="padding:16px;color:var(--red);background:var(--red-50);border-radius:var(--r2)">{{ error }}</div>

  <div v-else-if="!items.length" class="tbl-wrap" style="padding:40px;text-align:center;color:var(--faint)">
    <LucideIcon name="inbox" style="width:36px;height:36px;margin:0 auto 10px;display:block;opacity:.35" />
    No {{ tabTitle.toLowerCase() }} yet.
  </div>

  <!-- News table -->
  <div v-else-if="activeTab === 'news'" class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Tag</th><th>Title</th><th>Published</th><th style="text-align:right">Action</th></tr></thead>
      <tbody>
        <tr v-for="row in items" :key="row.id">
          <td><span class="badge b-gold">{{ row.tag }}</span></td>
          <td><div class="bk-t">{{ row.title }}</div></td>
          <td>{{ fmtDate(row.published_at) }}</td>
          <td style="text-align:right">
            <div class="rowacts" style="justify-content:flex-end">
              <button class="btn btn-ghost btn-sm" @click="openEdit(row)"><LucideIcon name="pencil" class="ic-sm" /></button>
              <button class="btn btn-danger btn-sm" @click="confirmDelete(row)"><LucideIcon name="trash-2" class="ic-sm" /></button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Events table -->
  <div v-else-if="activeTab === 'events'" class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Date</th><th>Title</th><th>Time</th><th>Place</th><th style="text-align:right">Action</th></tr></thead>
      <tbody>
        <tr v-for="row in items" :key="row.id">
          <td>{{ fmtDate(row.starts_at) }}</td>
          <td><div class="bk-t">{{ row.title }}</div></td>
          <td>{{ row.time_label || '—' }}</td>
          <td>{{ row.place || '—' }}</td>
          <td style="text-align:right">
            <div class="rowacts" style="justify-content:flex-end">
              <button class="btn btn-ghost btn-sm" @click="openEdit(row)"><LucideIcon name="pencil" class="ic-sm" /></button>
              <button class="btn btn-danger btn-sm" @click="confirmDelete(row)"><LucideIcon name="trash-2" class="ic-sm" /></button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Opening hours table -->
  <div v-else-if="activeTab === 'hours'" class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Day</th><th>Hours</th><th>Status</th><th style="text-align:right">Action</th></tr></thead>
      <tbody>
        <tr v-for="row in items" :key="row.id">
          <td class="bk-t">{{ row.day_label }}</td>
          <td>{{ row.time_label }}</td>
          <td><span :class="['badge', row.is_closed ? 'b-red' : 'b-green']">{{ row.is_closed ? 'Closed' : 'Open' }}</span></td>
          <td style="text-align:right">
            <div class="rowacts" style="justify-content:flex-end">
              <button class="btn btn-ghost btn-sm" @click="openEdit(row)"><LucideIcon name="pencil" class="ic-sm" /></button>
              <button class="btn btn-danger btn-sm" @click="confirmDelete(row)"><LucideIcon name="trash-2" class="ic-sm" /></button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ── Add / Edit Drawer ── -->
  <div :class="['scrim', { open: drawerOpen }]" @click="closeDrawer"></div>
  <div :class="['drawer', { open: drawerOpen }]">
    <div class="dh">
      <div>
        <div class="dh-t">{{ editing ? 'Edit' : 'Add' }} {{ tabTitle.replace(/s$/, '') }}</div>
        <div class="dh-s">Content shown on the public landing page</div>
      </div>
      <div class="dh-x" @click="closeDrawer"><LucideIcon name="x" /></div>
    </div>

    <div class="db">
      <!-- News form -->
      <div v-if="activeTab === 'news'" class="db-sec">
        <div class="form-grid">
          <div class="fg">
            <label class="fl">Tag</label>
            <select class="fs" v-model="form.tag">
              <option v-for="tg in NEWS_TAGS" :key="tg">{{ tg }}</option>
            </select>
          </div>
          <div class="fg">
            <label class="fl">Published</label>
            <input class="fi" type="date" v-model="form.published_at" />
          </div>
          <div class="fg col2">
            <label class="fl">Title <span class="req">*</span></label>
            <input class="fi" v-model="form.title" placeholder="Headline" />
          </div>
          <div class="fg col2">
            <label class="fl">Body</label>
            <textarea class="ft" v-model="form.body" placeholder="Short summary…"></textarea>
          </div>
          <div class="fg">
            <label class="fl">Icon (Lucide name)</label>
            <input class="fi" v-model="form.icon" placeholder="e.g. bell" />
          </div>
          <div class="fg">
            <label class="fl">Tone</label>
            <select class="fs" v-model="form.tone">
              <option v-for="tn in TONE_KEYS" :key="tn">{{ tn }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Events form -->
      <div v-else-if="activeTab === 'events'" class="db-sec">
        <div class="form-grid">
          <div class="fg col2">
            <label class="fl">Title <span class="req">*</span></label>
            <input class="fi" v-model="form.title" placeholder="Event title" />
          </div>
          <div class="fg">
            <label class="fl">Date &amp; Time <span class="req">*</span></label>
            <input class="fi" type="datetime-local" v-model="form.starts_at" />
          </div>
          <div class="fg">
            <label class="fl">Time Label</label>
            <input class="fi" v-model="form.time_label" placeholder="e.g. 10:00 AM or All day" />
          </div>
          <div class="fg col2">
            <label class="fl">Place</label>
            <input class="fi" v-model="form.place" placeholder="e.g. Main Reading Hall" />
          </div>
        </div>
      </div>

      <!-- Opening hours form -->
      <div v-else-if="activeTab === 'hours'" class="db-sec">
        <div class="form-grid">
          <div class="fg col2">
            <label class="fl">Day Label <span class="req">*</span></label>
            <input class="fi" v-model="form.day_label" placeholder="e.g. Monday – Friday" />
          </div>
          <div class="fg">
            <label class="fl">Time Label <span class="req">*</span></label>
            <input class="fi" v-model="form.time_label" placeholder="e.g. 8:00 – 17:00 or Closed" />
          </div>
          <div class="fg">
            <label class="fl">Sort Order</label>
            <input class="fi" type="number" min="0" v-model.number="form.sort_order" />
          </div>
          <div class="fg col2">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ink);cursor:pointer">
              <input type="checkbox" v-model="form.is_closed" /> Library is closed this day
            </label>
          </div>
        </div>
      </div>

      <div v-if="saveError" style="color:var(--red);font-size:13px;margin-top:4px">
        <LucideIcon name="alert-triangle" class="ic-sm" /> {{ saveError }}
      </div>
    </div>

    <div class="df">
      <button class="btn btn-primary" style="flex:1;justify-content:center" :disabled="saving" @click="save">
        <LucideIcon :name="saving ? 'refresh-cw' : 'save'" class="ic-sm" />
        {{ saving ? 'Saving…' : 'Save' }}
      </button>
      <button class="btn btn-ghost" @click="closeDrawer">Cancel</button>
    </div>
  </div>

  <!-- ── Delete confirm ── -->
  <div :class="['mscrim', { open: !!deleting }]" @click.self="deleting = null">
    <div class="modal" v-if="deleting">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--red-50)"><LucideIcon name="trash-2" style="width:22px;height:22px;color:var(--red)" /></div>
        <div class="modal-t">Delete this item?</div>
        <div class="modal-s">
          "{{ deleting.title || deleting.day_label }}" will be removed from the public landing page. This can't be undone.
        </div>
        <div class="modal-f">
          <button class="btn btn-ghost" @click="deleting = null">Cancel</button>
          <button class="btn btn-danger" @click="doDelete">Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>
