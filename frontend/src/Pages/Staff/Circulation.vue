<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPost, apiPatch } from '@/api/http'
import LucideIcon from '@/components/LucideIcon.vue'
import Users from './Users.vue'

const auth      = useAuthStore()
const activeTab = ref<'issue' | 'return' | 'renew' | 'members'>('issue')

// ── Member search ──
const memberQuery   = ref('')
const memberLoading = ref(false)
const memberResults = ref<any[]>([])
const selectedMember = ref<any>(null)
const memberFines   = ref(0)
const memberActiveLoans = ref<any[]>([])
const memberLoansLoading = ref(false)

// ── Book search (Issue tab) ──
const bookQuery   = ref('')
const bookLoading = ref(false)
const bookResults = ref<any[]>([])
const selectedBook = ref<any>(null)
const issueLoading = ref(false)
const issueError   = ref('')
const showIssueModal = ref(false)
const issuedLoan   = ref<any>(null)

// ── Return / Renew ──
const processingLoan  = ref<number | null>(null)
const showResultModal = ref(false)
const resultAction    = ref<'returned' | 'renewed'>('returned')
const resultLoan      = ref<any>(null)
const resultFine      = ref<any>(null)

// ── Today's transactions ──
const todayLoans   = ref<any[]>([])
const todayLoading = ref(false)
const todayTotal   = ref(0)

const COLORS = ['cv-navy', 'cv-burgundy', 'cv-forest', 'cv-slate', 'cv-charcoal', 'cv-ochre']
const color  = (i: number) => COLORS[i % COLORS.length]

function initials(name: string) {
  return name.split(' ').map((w: string) => w[0]).join('').toUpperCase().slice(0, 2)
}

function fmtDate(d: string) {
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function fmtTime(d: string) {
  return new Date(d).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

function dueDate(loan: any) {
  return new Date(loan.due_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

function membershipOk(m: any) {
  if (!m.membership_expires_at) return true
  return new Date(m.membership_expires_at) > new Date()
}

function slotPct(member: any) {
  const maxBooks = 5
  const used = memberActiveLoans.value.length
  return Math.min(100, (used / maxBooks) * 100)
}

onMounted(() => loadToday())

async function searchMember() {
  if (!memberQuery.value.trim()) return
  memberLoading.value = true
  memberResults.value = []
  selectedMember.value = null
  memberActiveLoans.value = []
  memberFines.value = 0
  try {
    const res = await apiGet<any>(`/admin/users?search=${encodeURIComponent(memberQuery.value)}`, auth.token ?? undefined)
    memberResults.value = (res.data ?? []).filter((u: any) => u.role === 'user')
  } catch {}
  finally { memberLoading.value = false }
}

async function selectMember(m: any) {
  selectedMember.value = m
  memberResults.value  = []
  memberQuery.value    = m.name
  memberLoansLoading.value = true
  memberFines.value = 0
  memberActiveLoans.value = []
  try {
    const [fRes, lRes] = await Promise.all([
      apiGet<any>(`/admin/fines?user_id=${m.id}&status=unpaid`, auth.token ?? undefined),
      apiGet<any>(`/admin/loans?user_id=${m.id}&status=active`, auth.token ?? undefined),
    ])
    memberFines.value       = (fRes.data ?? []).reduce((s: number, f: any) => s + parseFloat(f.amount ?? 0), 0)
    memberActiveLoans.value = lRes.data ?? []
  } catch {}
  finally { memberLoansLoading.value = false }
}

function clearMember() {
  selectedMember.value    = null
  memberQuery.value       = ''
  memberResults.value     = []
  memberActiveLoans.value = []
  memberFines.value       = 0
  selectedBook.value      = null
  bookQuery.value         = ''
  issueError.value        = ''
}

async function searchBook() {
  if (!bookQuery.value.trim()) return
  bookLoading.value = true
  bookResults.value = []
  selectedBook.value = null
  try {
    const res = await apiGet<any>(`/books?search=${encodeURIComponent(bookQuery.value)}`, auth.token ?? undefined)
    bookResults.value = res.data ?? []
  } catch {}
  finally { bookLoading.value = false }
}

function selectBook(b: any) {
  selectedBook.value = b
  bookResults.value  = []
  bookQuery.value    = b.title
}

async function issueBook() {
  if (!selectedMember.value || !selectedBook.value) return
  issueError.value  = ''
  issueLoading.value = true
  try {
    const loan = await apiPost<any>('/admin/loans', {
      book_id: selectedBook.value.id,
      user_id: selectedMember.value.id,
    }, auth.token ?? undefined)
    issuedLoan.value    = loan
    showIssueModal.value = true
    memberActiveLoans.value.push(loan)
    selectedBook.value = null
    bookQuery.value    = ''
    await loadToday()
  } catch (e: any) {
    issueError.value = e.message
  } finally {
    issueLoading.value = false
  }
}

async function returnLoan(loan: any) {
  processingLoan.value = loan.id
  try {
    const res = await apiPatch<any>(`/admin/loans/${loan.id}/return`, {}, auth.token ?? undefined)
    resultAction.value = 'returned'
    resultLoan.value   = res.loan
    resultFine.value   = res.fine ?? null
    showResultModal.value = true
    memberActiveLoans.value = memberActiveLoans.value.filter((l: any) => l.id !== loan.id)
    await loadToday()
  } catch (e: any) { alert(e.message) }
  finally { processingLoan.value = null }
}

async function renewLoan(loan: any) {
  processingLoan.value = loan.id
  try {
    const updated = await apiPatch<any>(`/admin/loans/${loan.id}/renew`, {}, auth.token ?? undefined)
    resultAction.value = 'renewed'
    resultLoan.value   = updated
    resultFine.value   = null
    showResultModal.value = true
    const idx = memberActiveLoans.value.findIndex((l: any) => l.id === loan.id)
    if (idx >= 0) memberActiveLoans.value[idx] = updated
  } catch (e: any) { alert(e.message) }
  finally { processingLoan.value = null }
}

async function loadToday() {
  todayLoading.value = true
  try {
    const res = await apiGet<any>('/admin/loans?today=true', auth.token ?? undefined)
    todayLoans.value = res.data ?? []
    todayTotal.value = res.total ?? todayLoans.value.length
  } catch {}
  finally { todayLoading.value = false }
}

function loanAction(loan: any): string {
  if (loan.status === 'returned') {
    const dueEndOfDay = new Date(loan.due_date.slice(0, 10) + 'T23:59:59')
    return new Date(loan.returned_at) <= dueEndOfDay ? 'Returned' : 'Late Return'
  }
  return 'Issued'
}

function loanBadge(loan: any) {
  const a = loanAction(loan)
  if (a === 'Issued')      return 'b-green'
  if (a === 'Returned')    return 'b-blue'
  return 'b-red'
}

function switchTab(tab: 'issue' | 'return' | 'renew' | 'members') {
  activeTab.value = tab
}
</script>

<template>
  <div class="shead">
    <div>
      <h2>Circulation Desk</h2>
      <p>Issue, return, and renew books at the front desk</p>
    </div>
    <div class="fbtns">
      <div :class="['fbtn', { on: activeTab === 'issue' }]"   @click="switchTab('issue')">Issue</div>
      <div :class="['fbtn', { on: activeTab === 'return' }]"  @click="switchTab('return')">Return</div>
      <div :class="['fbtn', { on: activeTab === 'renew' }]"   @click="switchTab('renew')">Renew</div>
      <div :class="['fbtn', { on: activeTab === 'members' }]" @click="switchTab('members')">Members</div>
    </div>
  </div>

  <Users v-if="activeTab === 'members'" />

  <template v-else>
  <div class="circ-grid">
    <!-- Step 1: Member -->
    <div class="circ-panel">
      <div class="circ-ph">
        <div class="stp">1</div>
        <div><h3>Identify Member</h3><p>Scan member card or search by name / ID</p></div>
      </div>
      <div class="circ-b">
        <div class="scan">
          <div class="scan-in">
            <LucideIcon name="scan-line" />
            <input
              v-model="memberQuery"
              placeholder="Scan or type member ID / name…"
              @keydown.enter="searchMember"
            />
          </div>
          <button class="btn btn-primary" :disabled="memberLoading" @click="searchMember">
            <LucideIcon name="search" class="ic-sm" />
          </button>
        </div>

        <!-- Search results dropdown -->
        <div v-if="memberResults.length" style="border:1px solid var(--line);border-radius:var(--r2);overflow:hidden;margin-bottom:14px;">
          <div
            v-for="m in memberResults" :key="m.id"
            style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--line-soft);display:flex;align-items:center;gap:10px;"
            @click="selectMember(m)"
            @mouseenter="($event.currentTarget as HTMLElement).style.background='var(--blue-50)'"
            @mouseleave="($event.currentTarget as HTMLElement).style.background=''"
          >
            <div style="width:32px;height:32px;border-radius:50%;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;">{{ initials(m.name) }}</div>
            <div>
              <div style="font-weight:600;font-size:13px;color:var(--navy)">{{ m.name }}</div>
              <div style="font-size:11px;color:var(--muted)">{{ m.member_number ?? m.email }}</div>
            </div>
          </div>
        </div>

        <!-- Member card -->
        <div v-if="selectedMember" class="mcard">
          <div class="mcard-top">
            <div class="mcard-av">{{ initials(selectedMember.name) }}</div>
            <div style="flex:1">
              <div class="mcard-n">{{ selectedMember.name }}</div>
              <div class="mcard-id">{{ selectedMember.member_number ?? selectedMember.email }}</div>
            </div>
            <span :class="['badge', membershipOk(selectedMember) ? 'b-green' : 'b-red']">
              <span :class="['dot', membershipOk(selectedMember) ? 'd-green' : 'd-red']"></span>
              {{ membershipOk(selectedMember) ? 'Good Standing' : 'Expired' }}
            </span>
            <button class="btn btn-ghost btn-sm" style="margin-left:6px" @click="clearMember">
              <LucideIcon name="x" class="ic-sm" />
            </button>
          </div>
          <div class="mcard-body">
            <div class="mcard-row">
              <span class="k">Membership</span>
              <span class="v">{{ selectedMember.membership_type ?? 'Standard' }}</span>
            </div>
            <div class="mcard-row">
              <span class="k">Outstanding Fines</span>
              <span class="v" :style="memberFines > 0 ? 'color:var(--red)' : 'color:var(--green-600)'">
                ₦{{ memberFines.toFixed(2) }}
              </span>
            </div>
            <div class="mcard-row">
              <span class="k">Active Loans</span>
              <span class="v">
                <span v-if="memberLoansLoading" style="color:var(--faint)">loading…</span>
                <span v-else>{{ memberActiveLoans.length }} book{{ memberActiveLoans.length !== 1 ? 's' : '' }}</span>
              </span>
            </div>
            <div class="slot-bar"><div class="slot-fill" :style="`width:${slotPct(selectedMember)}%`"></div></div>
          </div>
        </div>

        <div v-else-if="!memberLoading" style="padding:28px 0;text-align:center;color:var(--faint);font-size:13px">
          <LucideIcon name="user-search" style="width:32px;height:32px;margin:0 auto 8px;display:block;opacity:.35" />
          Search for a member above
        </div>
      </div>
    </div>

    <!-- Step 2: varies by tab -->
    <div class="circ-panel">
      <!-- ── ISSUE TAB ── -->
      <template v-if="activeTab === 'issue'">
        <div class="circ-ph">
          <div class="stp">2</div>
          <div><h3>Scan Book</h3><p>Scan barcode or enter call number / title</p></div>
        </div>
        <div class="circ-b">
          <div class="scan">
            <div class="scan-in">
              <LucideIcon name="barcode" />
              <input
                v-model="bookQuery"
                placeholder="Scan barcode or call number…"
                @keydown.enter="searchBook"
              />
            </div>
            <button class="btn btn-primary" :disabled="bookLoading" @click="searchBook">
              <LucideIcon name="search" class="ic-sm" />
            </button>
          </div>

          <!-- Book results -->
          <div v-if="bookResults.length" style="border:1px solid var(--line);border-radius:var(--r2);overflow:hidden;margin-bottom:14px;">
            <div
              v-for="(b, i) in bookResults" :key="b.id"
              style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--line-soft);display:flex;align-items:center;gap:10px;"
              @click="selectBook(b)"
              @mouseenter="($event.currentTarget as HTMLElement).style.background='var(--blue-50)'"
              @mouseleave="($event.currentTarget as HTMLElement).style.background=''"
            >
              <div :class="['cover', color(i)]" style="width:28px;height:36px;flex-shrink:0">
                <div class="cover-top"><div class="cover-t">{{ b.title.slice(0,4) }}</div></div>
              </div>
              <div>
                <div style="font-weight:600;font-size:12.5px;color:var(--navy)">{{ b.title }}</div>
                <div style="font-size:11px;color:var(--muted)">{{ b.authors }} · {{ b.call_number }}</div>
              </div>
              <span :class="['badge', b.available_copies > 0 ? 'b-green' : 'b-red']" style="margin-left:auto">
                {{ b.available_copies > 0 ? 'Available' : 'All out' }}
              </span>
            </div>
          </div>

          <!-- Selected book card -->
          <template v-if="selectedBook">
            <div class="cbook">
              <div :class="['cover', color(0)]" style="width:42px;height:54px;flex-shrink:0">
                <div class="cover-top"><div class="cover-t" style="font-size:5px">{{ selectedBook.title.slice(0,8) }}</div></div>
              </div>
              <div style="flex:1">
                <div class="cbook-t">{{ selectedBook.title }}</div>
                <div class="cbook-m">{{ selectedBook.authors }} · {{ selectedBook.call_number }}</div>
              </div>
              <span :class="['badge', selectedBook.available_copies > 0 ? 'b-green' : 'b-red']">
                {{ selectedBook.available_copies > 0 ? 'Available' : 'No copies' }}
              </span>
            </div>
            <div class="issue-sum" style="margin-top:14px">
              <div class="issue-sum-row">
                <span class="k">Issue Date</span>
                <span class="v">{{ fmtDate(new Date().toISOString()) }}</span>
              </div>
              <div class="issue-sum-row">
                <span class="k">Due Date</span>
                <span class="v">{{ fmtDate(new Date(Date.now() + 14 * 864e5).toISOString()) }}</span>
              </div>
            </div>
            <div v-if="issueError" style="color:var(--red);font-size:12.5px;margin-top:10px;padding:8px 12px;background:var(--red-50);border-radius:var(--r)">
              {{ issueError }}
            </div>
            <button
              class="btn btn-green btn-block"
              style="margin-top:14px"
              :disabled="!selectedMember || issueLoading || selectedBook.available_copies < 1"
              @click="issueBook"
            >
              <LucideIcon name="check-check" class="ic-sm" />
              {{ issueLoading ? 'Issuing…' : 'Confirm Issue to Member' }}
            </button>
            <p v-if="!selectedMember" style="font-size:11.5px;color:var(--amber);margin-top:8px;text-align:center">
              ← Identify a member first
            </p>
          </template>

          <div v-else-if="!bookLoading" style="padding:28px 0;text-align:center;color:var(--faint);font-size:13px">
            <LucideIcon name="book-open" style="width:32px;height:32px;margin:0 auto 8px;display:block;opacity:.35" />
            Search for a book above
          </div>
        </div>
      </template>

      <!-- ── RETURN TAB ── -->
      <template v-else-if="activeTab === 'return'">
        <div class="circ-ph">
          <div class="stp">2</div>
          <div><h3>Select Loan to Return</h3><p>Member's active loans are listed below</p></div>
        </div>
        <div class="circ-b">
          <div v-if="!selectedMember" style="padding:28px 0;text-align:center;color:var(--faint);font-size:13px">
            <LucideIcon name="user-search" style="width:32px;height:32px;margin:0 auto 8px;display:block;opacity:.35" />
            Identify a member first (Step 1)
          </div>
          <div v-else-if="memberLoansLoading" style="padding:28px 0;text-align:center;color:var(--faint)">Loading loans…</div>
          <div v-else-if="!memberActiveLoans.length" style="padding:28px 0;text-align:center;color:var(--faint);font-size:13px">
            <LucideIcon name="check-circle" style="width:32px;height:32px;margin:0 auto 8px;display:block;opacity:.35" />
            No active loans for this member
          </div>
          <div v-else>
            <div
              v-for="(loan, i) in memberActiveLoans" :key="loan.id"
              style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--line-soft)"
            >
              <div :class="['cover', color(i)]" style="width:30px;height:38px;flex-shrink:0">
                <div class="cover-top"><div class="cover-t" style="font-size:5px">{{ loan.book?.title?.slice(0,6) }}</div></div>
              </div>
              <div style="flex:1;min-width:0">
                <div class="bk-t" style="font-size:12.5px">{{ loan.book?.title }}</div>
                <div class="bk-a">Due {{ dueDate(loan) }} <span v-if="new Date(loan.due_date) < new Date()" style="color:var(--red)">· Overdue</span></div>
              </div>
              <button
                class="btn btn-green btn-sm"
                :disabled="processingLoan === loan.id"
                @click="returnLoan(loan)"
              >
                <LucideIcon name="undo-2" class="ic-sm" />
                {{ processingLoan === loan.id ? '…' : 'Return' }}
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- ── RENEW TAB ── -->
      <template v-else>
        <div class="circ-ph">
          <div class="stp">2</div>
          <div><h3>Select Loan to Renew</h3><p>Extends the due date by the standard loan period</p></div>
        </div>
        <div class="circ-b">
          <div v-if="!selectedMember" style="padding:28px 0;text-align:center;color:var(--faint);font-size:13px">
            <LucideIcon name="user-search" style="width:32px;height:32px;margin:0 auto 8px;display:block;opacity:.35" />
            Identify a member first (Step 1)
          </div>
          <div v-else-if="memberLoansLoading" style="padding:28px 0;text-align:center;color:var(--faint)">Loading loans…</div>
          <div v-else-if="!memberActiveLoans.length" style="padding:28px 0;text-align:center;color:var(--faint);font-size:13px">
            No active loans for this member
          </div>
          <div v-else>
            <div
              v-for="(loan, i) in memberActiveLoans" :key="loan.id"
              style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--line-soft)"
            >
              <div :class="['cover', color(i)]" style="width:30px;height:38px;flex-shrink:0">
                <div class="cover-top"><div class="cover-t" style="font-size:5px">{{ loan.book?.title?.slice(0,6) }}</div></div>
              </div>
              <div style="flex:1;min-width:0">
                <div class="bk-t" style="font-size:12.5px">{{ loan.book?.title }}</div>
                <div class="bk-a">Due {{ dueDate(loan) }}</div>
              </div>
              <button
                class="btn btn-primary btn-sm"
                :disabled="processingLoan === loan.id"
                @click="renewLoan(loan)"
              >
                <LucideIcon name="refresh-cw" class="ic-sm" />
                {{ processingLoan === loan.id ? '…' : 'Renew' }}
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>

  <!-- Today's transactions -->
  <div class="card">
    <div class="card-h">
      <h3><LucideIcon name="receipt-text" class="ic" /> Today's Transactions</h3>
      <span class="badge b-gray">{{ todayLoading ? '…' : todayTotal + ' today' }}</span>
    </div>
    <div v-if="todayLoading" style="padding:28px;text-align:center;color:var(--faint)">Loading…</div>
    <div v-else-if="!todayLoans.length" style="padding:28px;text-align:center;color:var(--faint);font-size:13px">No transactions today yet.</div>
    <table v-else class="tbl">
      <thead>
        <tr><th>Time</th><th>Member</th><th>Action</th><th>Book</th><th>Due / Status</th></tr>
      </thead>
      <tbody>
        <tr v-for="(loan, i) in todayLoans" :key="loan.id">
          <td class="mono">{{ fmtTime(loan.status === 'returned' ? loan.returned_at : loan.borrowed_at) }}</td>
          <td>{{ loan.user?.name }}</td>
          <td><span :class="['badge', loanBadge(loan)]">{{ loanAction(loan) }}</span></td>
          <td>
            <div class="bookcell">
              <div :class="['cover', color(i)]" style="width:30px;height:38px">
                <div class="cover-top"><div class="cover-t" style="font-size:5px">{{ loan.book?.title?.slice(0,4) }}</div></div>
              </div>
              <span class="bk-t" style="font-size:12px">{{ loan.book?.title }}</span>
            </div>
          </td>
          <td class="mono">
            <span v-if="loan.status === 'active'">Due {{ dueDate(loan) }}</span>
            <span v-else-if="loanAction(loan) === 'Returned'" style="color:var(--green-600)">On time</span>
            <span v-else style="color:var(--red)">Late</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  </template>

  <!-- Issue success modal -->
  <div :class="['mscrim', { open: showIssueModal }]" @click.self="showIssueModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--green-50)">
          <LucideIcon name="check-check" :size="28" style="color:var(--green)" />
        </div>
        <div class="modal-t">Book Issued</div>
        <div class="modal-s" v-if="issuedLoan">
          "{{ issuedLoan.book?.title }}" issued to {{ issuedLoan.user?.name }}. Due {{ dueDate(issuedLoan) }}.
        </div>
        <div class="modal-f">
          <button class="btn btn-primary btn-block" @click="showIssueModal = false">Done</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Return / Renew result modal -->
  <div :class="['mscrim', { open: showResultModal }]" @click.self="showResultModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" :style="resultFine ? 'background:var(--amber-50)' : 'background:var(--green-50)'">
          <LucideIcon
            :name="resultAction === 'renewed' ? 'refresh-cw' : resultFine ? 'banknote' : 'undo-2'"
            :size="28"
            :style="resultFine ? 'color:var(--amber)' : 'color:var(--green)'"
          />
        </div>
        <div class="modal-t">
          {{ resultAction === 'renewed' ? 'Loan Renewed' : resultFine ? 'Late Return – Fine Created' : 'Book Returned' }}
        </div>
        <div class="modal-s" v-if="resultLoan">
          <template v-if="resultAction === 'renewed'">
            "{{ resultLoan.book?.title }}" renewed. New due date: {{ dueDate(resultLoan) }}.
          </template>
          <template v-else>
            "{{ resultLoan.book?.title }}" returned.
            <span v-if="resultFine"> Fine of ₦{{ parseFloat(resultFine.amount).toFixed(2) }} has been created.</span>
          </template>
        </div>
        <div class="modal-f">
          <button class="btn btn-primary btn-block" @click="showResultModal = false">Done</button>
        </div>
      </div>
    </div>
  </div>
</template>
