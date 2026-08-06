<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPatch } from '@/api/http'
import LucideIcon from '@/components/LucideIcon.vue'

const auth    = useAuthStore()
const loading = ref(true)
const error   = ref('')

const policies = ref<any[]>([])
const activeType = ref('pg_student')
const saving  = ref(false)
const saved   = ref(false)

const TYPES = [
  { key: 'buk_staff',                label: 'BUK Staff' },
  { key: 'pg_student',               label: 'PG Student' },
  { key: 'independent_researcher',   label: 'Independent Researcher' },
  { key: 'international_researcher', label: 'International Researcher' },
]

// Editable fields per policy (keyed by membership_type)
const edits = ref<Record<string, any>>({})

function currentPolicy() {
  return policies.value.find((p: any) => p.membership_type === activeType.value)
}

function editFor(type: string) {
  return edits.value[type] ?? {}
}

function typeLabel(type: string) {
  return TYPES.find(t => t.key === type)?.label ?? type
}

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const res = await apiGet<any[]>('/admin/settings/policies', auth.token ?? undefined)
    policies.value = res ?? []
    // Prime editable copies
    for (const p of policies.value) {
      edits.value[p.membership_type] = {
        max_books:    p.max_books,
        loan_days:    p.loan_days,
        fine_per_day: p.fine_per_day,
        max_fine:     p.max_fine ?? '',
      }
    }
  } catch (e: any) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function save() {
  const policy = currentPolicy()
  if (!policy) return
  saving.value = true
  saved.value  = false
  try {
    const updated = await apiPatch<any>(`/admin/settings/policies/${policy.id}`, {
      max_books:    parseInt(edits.value[activeType.value].max_books),
      loan_days:    parseInt(edits.value[activeType.value].loan_days),
      fine_per_day: parseFloat(edits.value[activeType.value].fine_per_day),
      max_fine:     edits.value[activeType.value].max_fine !== '' ? parseFloat(edits.value[activeType.value].max_fine) : null,
    }, auth.token ?? undefined)
    const idx = policies.value.findIndex((p: any) => p.id === policy.id)
    if (idx >= 0) policies.value[idx] = updated
    saved.value = true
    setTimeout(() => saved.value = false, 2500)
  } catch (e: any) {
    alert(e.message)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="shead">
    <div><h2>Library Settings</h2><p>Circulation rules &amp; fine policies per membership type</p></div>
  </div>

  <div v-if="loading" style="padding:40px;text-align:center;color:var(--faint)">Loading settings…</div>
  <div v-else-if="error" style="padding:16px;color:var(--red);background:var(--red-50);border-radius:var(--r2)">{{ error }}</div>

  <template v-else>
    <!-- Membership type tabs -->
    <div class="fbtns" style="margin-bottom:18px">
      <div
        v-for="t in TYPES" :key="t.key"
        :class="['fbtn', { on: activeType === t.key }]"
        @click="activeType = t.key"
      >
        {{ t.label }}
      </div>
    </div>

    <div v-if="!currentPolicy()" style="padding:28px;text-align:center;color:var(--faint)">
      No policy found for this type. Run the database seeder to initialise policies.
    </div>

    <template v-else>
      <div class="grid2">
        <!-- Borrowing Rules -->
        <div class="card">
          <div class="card-h">
            <h3><LucideIcon name="book-open" class="ic" /> Borrowing Rules</h3>
            <span class="badge b-blue">{{ TYPES.find(t => t.key === activeType)?.label }}</span>
          </div>
          <div class="card-b">
            <div class="form-grid">
              <div class="fg">
                <label class="fl">Loan Period (days)</label>
                <input class="fi" type="number" min="1" v-model.number="editFor(activeType).loan_days" />
              </div>
              <div class="fg">
                <label class="fl">Max Books / Member</label>
                <input class="fi" type="number" min="1" v-model.number="editFor(activeType).max_books" />
              </div>
            </div>
          </div>
        </div>

        <!-- Fine Policy -->
        <div class="card">
          <div class="card-h">
            <h3><LucideIcon name="banknote" class="ic" /> Fine Policy</h3>
            <span class="badge b-blue">{{ TYPES.find(t => t.key === activeType)?.label }}</span>
          </div>
          <div class="card-b">
            <div class="form-grid">
              <div class="fg">
                <label class="fl">Overdue Fine / Day (₦)</label>
                <input class="fi" type="number" min="0" step="0.01" v-model.number="editFor(activeType).fine_per_day" />
              </div>
              <div class="fg">
                <label class="fl">Max Fine (₦) — leave blank for none</label>
                <input class="fi" type="number" min="0" step="0.01" v-model="editFor(activeType).max_fine" placeholder="No limit" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div style="margin-top:16px;display:flex;align-items:center;gap:12px">
        <button class="btn btn-primary" :disabled="saving" @click="save">
          <LucideIcon name="save" class="ic-sm" />
          {{ saving ? 'Saving…' : 'Save Changes' }}
        </button>
        <span v-if="saved" style="color:var(--green-600);font-size:13px;display:flex;align-items:center;gap:5px">
          <LucideIcon name="check-circle" class="ic-sm" /> Saved successfully
        </span>
      </div>

      <!-- All policies summary -->
      <div class="card" style="margin-top:20px">
        <div class="card-h"><h3><LucideIcon name="table" class="ic" /> All Policies Summary</h3></div>
        <table class="tbl">
          <thead>
            <tr>
              <th>Membership Type</th>
              <th>Loan Days</th>
              <th>Max Books</th>
              <th>Fine / Day (₦)</th>
              <th>Max Fine (₦)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in policies" :key="p.id" :style="p.membership_type === activeType ? 'background:var(--blue-50)' : ''">
              <td style="font-weight:600">{{ typeLabel(p.membership_type) }}</td>
              <td>{{ p.loan_days }}</td>
              <td>{{ p.max_books }}</td>
              <td>{{ parseFloat(p.fine_per_day).toFixed(2) }}</td>
              <td>{{ p.max_fine !== null ? parseFloat(p.max_fine).toFixed(2) : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </template>
</template>
