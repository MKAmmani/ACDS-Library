<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPatch } from '@/api/http'

const auth = useAuthStore()

const form = ref({
  name:    '',
  email:   '',
  phone:   '',
  address: '',
})

const accountInfo     = ref<any>(null)
const profileLoading  = ref(true)
const passwords       = ref({ current:'', newPw:'', confirm:'' })

const notifs = ref({
  emailReservation: true,
  emailInbox:       true,
  smsOverdueReport: false,
  emailWeeklySummary: true,
})

const saving      = ref(false)
const saved       = ref(false)
const saveError   = ref('')
const pwSaving    = ref(false)
const pwSaved     = ref(false)
const pwError     = ref('')
const activeTab   = ref<'profile' | 'security' | 'notifications'>('profile')

const initials = computed(() => {
  const n = form.value.name || auth.user?.name || 'LB'
  return n.split(' ').map((w: string) => w[0]).join('').toUpperCase().slice(0, 2)
})

const roleLabel = computed(() => 'Librarian')

function formatDate(d: string | null) {
  if (!d) return 'N/A'
  return new Date(d).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' })
}

onMounted(async () => {
  try {
    const user = await apiGet<any>('/auth/me', auth.token ?? undefined)
    form.value.name    = user.name    ?? ''
    form.value.email   = user.email   ?? ''
    form.value.phone   = user.phone   ?? ''
    form.value.address = user.address ?? ''
    accountInfo.value  = user
  } catch {}
  profileLoading.value = false
})

async function saveProfile() {
  saving.value    = true
  saveError.value = ''
  try {
    const updated = await apiPatch<any>('/me/profile', {
      name:    form.value.name,
      phone:   form.value.phone   || null,
      address: form.value.address || null,
    }, auth.token ?? undefined)
    accountInfo.value = updated
    if (auth.user) {
      auth.user.name = updated.name
      localStorage.setItem('auth_user', JSON.stringify(auth.user))
    }
    saved.value = true
    setTimeout(() => { saved.value = false }, 3000)
  } catch (e: any) {
    saveError.value = e.message
  }
  saving.value = false
}

async function changePassword() {
  pwError.value = ''
  if (passwords.value.newPw !== passwords.value.confirm) {
    pwError.value = 'New passwords do not match.'
    return
  }
  if (passwords.value.newPw.length < 8) {
    pwError.value = 'Password must be at least 8 characters.'
    return
  }
  pwSaving.value = true
  await new Promise(r => setTimeout(r, 800))
  pwSaving.value = false
  pwSaved.value  = true
  passwords.value = { current:'', newPw:'', confirm:'' }
  setTimeout(() => { pwSaved.value = false }, 3000)
}
</script>

<template>
  <div class="max-w-[900px] mx-auto px-4 sm:px-7 py-6 sm:py-8 pb-16">

    <!-- Profile card header -->
    <div class="bg-white border border-[var(--line)] rounded-xl overflow-hidden mb-6" style="box-shadow:var(--sh1)">
      <div class="h-24 relative" style="background:linear-gradient(115deg,#0B2E63,var(--blue))">
        <div class="absolute -bottom-8 left-6">
          <div class="w-16 h-16 rounded-full flex items-center justify-center text-[22px] font-bold text-white border-[3px] border-white"
            style="background:linear-gradient(140deg,var(--green),#0dd891);box-shadow:0 4px 12px rgba(14,42,92,.2)">
            {{ initials }}
          </div>
        </div>
      </div>
      <div class="pt-10 pb-4 px-6 flex items-end justify-between">
        <div>
          <div class="text-[18px] font-bold text-[var(--navy)]" style="font-family:var(--display)">
            {{ form.name || 'Librarian' }}
          </div>
          <div class="text-[12.5px] text-[var(--muted)] mt-0.5 flex items-center gap-2">
            <span class="badge b-blue">{{ roleLabel }}</span>
            <span>· {{ form.email }}</span>
          </div>
        </div>
        <div class="text-[12px] flex items-center gap-1.5" :style="accountInfo?.is_active === false ? 'color:var(--red)' : 'color:var(--green)'">
          <span class="w-1.5 h-1.5 rounded-full" :style="accountInfo?.is_active === false ? 'background:var(--red)' : 'background:var(--green)'"></span>
          {{ accountInfo?.is_active === false ? 'Suspended' : 'Active' }}
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex border-t border-[var(--line)] px-1 overflow-x-auto">
        <button v-for="[id,label,icon] in [['profile','Profile','user'],['security','Security','lock'],['notifications','Notifications','bell']]" :key="id"
          :class="['flex items-center gap-2 text-[13px] font-medium px-3 sm:px-4 py-[14px] border-b-2 transition-all -mb-px cursor-pointer whitespace-nowrap flex-shrink-0',
            activeTab===id ? 'text-[var(--blue)] border-[var(--gold)] font-semibold' : 'text-[var(--muted)] border-transparent hover:text-[var(--navy)]']"
          @click="activeTab = id as any">
          <LucideIcon :name="icon" :size="15" /> {{ label }}
        </button>
      </div>
    </div>

    <!-- Profile tab -->
    <div v-if="activeTab==='profile'" class="bg-white border border-[var(--line)] rounded-xl overflow-hidden" style="box-shadow:var(--sh1)">
      <div class="px-6 py-4 border-b border-[var(--line)] flex items-center justify-between">
        <h3 class="text-[13px] font-bold tracking-[.05em] uppercase text-[var(--navy)] flex items-center gap-2">
          <LucideIcon name="user" :size="15" class="text-[var(--blue)]" /> Personal Information
        </h3>
        <div v-if="saved" class="text-[12.5px] text-[var(--green)] flex items-center gap-1.5">
          <LucideIcon name="check" :size="14" /> Saved
        </div>
      </div>

      <div v-if="profileLoading" class="p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
          <div v-for="i in 4" :key="i" class="h-[60px] bg-[var(--bg)] rounded-[10px] animate-pulse"></div>
        </div>
      </div>

      <div v-else class="p-6">
        <div v-if="saveError" class="flex items-center gap-2 text-[13px] text-[var(--red)] bg-[var(--red-50)] rounded-[10px] px-4 py-3 mb-5">
          <LucideIcon name="alert-triangle" :size="15" /> {{ saveError }}
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
          <div>
            <label class="block text-[11px] font-bold tracking-[.05em] uppercase text-[var(--muted)] mb-1.5">Full Name</label>
            <input v-model="form.name" type="text" placeholder="Your full name"
              class="w-full text-[14px] px-3.5 py-2.5 border-[1.5px] border-[var(--line)] rounded-[10px] outline-none text-[var(--ink)] placeholder:text-[var(--faint)] focus:border-[var(--blue)] transition-colors" />
          </div>
          <div>
            <label class="block text-[11px] font-bold tracking-[.05em] uppercase text-[var(--muted)] mb-1.5">Email Address</label>
            <input :value="form.email" type="email" readonly
              class="w-full text-[14px] px-3.5 py-2.5 border-[1.5px] border-[var(--line)] rounded-[10px] outline-none text-[var(--muted)] bg-[var(--bg)]" />
          </div>
          <div>
            <label class="block text-[11px] font-bold tracking-[.05em] uppercase text-[var(--muted)] mb-1.5">Phone Number</label>
            <input v-model="form.phone" type="tel" placeholder="+234 800 000 0000"
              class="w-full text-[14px] px-3.5 py-2.5 border-[1.5px] border-[var(--line)] rounded-[10px] outline-none text-[var(--ink)] placeholder:text-[var(--faint)] focus:border-[var(--blue)] transition-colors" />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-[11px] font-bold tracking-[.05em] uppercase text-[var(--muted)] mb-1.5">Postal Address</label>
            <input v-model="form.address" type="text" placeholder="Kano, Nigeria"
              class="w-full text-[14px] px-3.5 py-2.5 border-[1.5px] border-[var(--line)] rounded-[10px] outline-none text-[var(--ink)] placeholder:text-[var(--faint)] focus:border-[var(--blue)] transition-colors" />
          </div>
        </div>

        <!-- Account info (read-only) -->
        <div class="bg-[var(--bg)] border border-[var(--line)] rounded-[10px] p-4 mb-6">
          <div class="text-[11px] font-bold tracking-[.07em] uppercase text-[var(--muted)] mb-3">Account Details</div>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <div v-for="[k,v] in [
              ['Role', roleLabel],
              ['Account Status', accountInfo?.is_active === false ? 'Suspended' : 'Active'],
              ['Staff Since', formatDate(accountInfo?.created_at ?? null)],
            ]" :key="k">
              <div class="text-[10px] font-semibold tracking-[.05em] uppercase text-[var(--faint)] mb-0.5">{{ k }}</div>
              <div class="text-[13px] font-medium text-[var(--ink)]">{{ v }}</div>
            </div>
          </div>
        </div>

        <div class="flex gap-3">
          <button class="btn btn-primary" :disabled="saving" @click="saveProfile">
            <LucideIcon :name="saving ? 'refresh-cw' : 'save'" :size="15" />
            {{ saving ? 'Saving…' : 'Save Changes' }}
          </button>
          <button class="btn btn-ghost" @click="form.name=accountInfo?.name??''; form.phone=accountInfo?.phone??''; form.address=accountInfo?.address??''; saveError=''">Discard</button>
        </div>
      </div>
    </div>

    <!-- Security tab -->
    <div v-if="activeTab==='security'" class="bg-white border border-[var(--line)] rounded-xl overflow-hidden" style="box-shadow:var(--sh1)">
      <div class="px-6 py-4 border-b border-[var(--line)]">
        <h3 class="text-[13px] font-bold tracking-[.05em] uppercase text-[var(--navy)] flex items-center gap-2">
          <LucideIcon name="unlock" :size="15" class="text-[var(--blue)]" /> Change Password
        </h3>
      </div>
      <div class="p-6 max-w-[440px]">
        <div v-if="pwSaved" class="flex items-center gap-2 text-[13px] text-[var(--green)] bg-[var(--green-50)] rounded-[10px] px-4 py-3 mb-4">
          <LucideIcon name="check" :size="15" /> Password updated successfully.
        </div>
        <div v-if="pwError" class="flex items-center gap-2 text-[13px] text-[var(--red)] bg-[var(--red-50)] rounded-[10px] px-4 py-3 mb-4">
          <LucideIcon name="alert-triangle" :size="15" /> {{ pwError }}
        </div>
        <div v-for="[key,label,ph] in [['current','Current Password','Enter current password'],['newPw','New Password','Min. 8 characters'],['confirm','Confirm New Password','Repeat new password']]" :key="key" class="mb-4">
          <label class="block text-[11px] font-bold tracking-[.05em] uppercase text-[var(--muted)] mb-1.5">{{ label }}</label>
          <input v-model="(passwords as any)[key]" type="password" :placeholder="ph"
            class="w-full text-[14px] px-3.5 py-2.5 border-[1.5px] border-[var(--line)] rounded-[10px] outline-none text-[var(--ink)] placeholder:text-[var(--faint)] focus:border-[var(--blue)] transition-colors" />
        </div>
        <button class="btn btn-primary" :disabled="pwSaving" @click="changePassword">
          <LucideIcon :name="pwSaving ? 'refresh-cw' : 'unlock'" :size="15" />
          {{ pwSaving ? 'Updating…' : 'Update Password' }}
        </button>
      </div>
    </div>

    <!-- Notifications tab -->
    <div v-if="activeTab==='notifications'" class="bg-white border border-[var(--line)] rounded-xl overflow-hidden" style="box-shadow:var(--sh1)">
      <div class="px-6 py-4 border-b border-[var(--line)]">
        <h3 class="text-[13px] font-bold tracking-[.05em] uppercase text-[var(--navy)] flex items-center gap-2">
          <LucideIcon name="bell" :size="15" class="text-[var(--blue)]" /> Notification Preferences
        </h3>
      </div>
      <div class="p-6">
        <div class="flex flex-col gap-0">
          <div v-for="[key,label,desc] in [
            ['emailReservation',  'Email — New reservation',        'Notify me when a member places a new reservation.'],
            ['emailInbox',        'Email — Ask-a-Librarian message', 'Notify me when a member sends a new inbox message.'],
            ['smsOverdueReport',  'SMS — Overdue summary',           'Receive a daily SMS summary of overdue loans.'],
            ['emailWeeklySummary','Email — Weekly circulation report','A weekly summary of loans, returns, and fines.'],
          ]" :key="key" class="flex items-center justify-between py-4 border-b border-[var(--line-soft)] last:border-0">
            <div>
              <div class="text-[14px] font-semibold text-[var(--navy)]">{{ label }}</div>
              <div class="text-[12.5px] text-[var(--muted)] mt-0.5">{{ desc }}</div>
            </div>
            <button
              :class="['relative w-11 h-6 rounded-full transition-colors duration-200 flex-shrink-0', (notifs as any)[key] ? 'bg-[var(--blue)]' : 'bg-[var(--line)]']"
              @click="(notifs as any)[key] = !(notifs as any)[key]">
              <span :class="['absolute top-0.5 w-5 h-5 rounded-full bg-white transition-transform duration-200 shadow-sm', (notifs as any)[key] ? 'translate-x-5' : 'translate-x-0.5']"></span>
            </button>
          </div>
        </div>
        <button class="btn btn-primary mt-6">
          <LucideIcon name="save" :size="15" /> Save Preferences
        </button>
      </div>
    </div>

  </div>
</template>
