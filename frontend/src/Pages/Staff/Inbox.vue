<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPost, apiPatch, apiDelete } from '@/api/http'
import LucideIcon from '@/components/LucideIcon.vue'

const auth = useAuthStore()

// ── Tab state ────────────────────────────────────────────────────────────────
const activeTab = ref<'user_to_staff' | 'staff_to_admin'>('user_to_staff')

// ── Thread lists ─────────────────────────────────────────────────────────────
const memberThreads = ref<any[]>([])
const staffThreads  = ref<any[]>([])
const loadingM      = ref(true)
const loadingS      = ref(true)
const statusFilter  = ref('')
const typeFilter    = ref('')

// ── Thread modal (conversation view) ─────────────────────────────────────────
const showThread   = ref(false)
const activeThread = ref<any>(null)
const threadMsgs   = ref<any[]>([])
const loadingMsgs  = ref(false)
const replyBody    = ref('')
const sending      = ref(false)
const sendError    = ref('')
const msgListRef   = ref<HTMLElement | null>(null)

// ── Compose drawer (staff → admin) ───────────────────────────────────────────
const showCompose    = ref(false)
const composeSubject = ref('')
const composeBody    = ref('')
const composeError   = ref('')
const composing      = ref(false)

// ── Log Query drawer (walk-in / phone) ───────────────────────────────────────
const showLog  = ref(false)
const logForm  = ref({ from_name: '', from_email: '', body: '', query_type: 'reference', subject: '' })
const logError = ref('')
const logging  = ref(false)

const processingId = ref<number | null>(null)

let listPollTimer: ReturnType<typeof setInterval> | null = null
let msgPollTimer:  ReturnType<typeof setInterval> | null = null

// ── Helpers ──────────────────────────────────────────────────────────────────
const TYPES: Record<string, string> = {
  reference: 'Reference', acquisition: 'Acquisition',
  citation: 'Citation',   support: 'Support', other: 'Other',
}

const unreadMemberCount = computed(() =>
  memberThreads.value.filter((t: any) => t.last_sender_role === 'user' && t.status === 'open').length
)

function threadSenderName(t: any): string {
  return t.from_user?.name ?? t.from_name ?? 'Unknown'
}

function isUnread(t: any): boolean {
  if (activeTab.value === 'user_to_staff') return t.last_sender_role === 'user' && t.status === 'open'
  return t.last_sender_role === 'admin' && t.status === 'open'
}

function dotStyle(t: any) {
  if (t.status === 'closed') return 'background:var(--faint)'
  if (isUnread(t))           return 'background:var(--blue)'
  return 'background:var(--green)'
}

function timeAgo(d: string | null) {
  if (!d) return '—'
  const diff = (Date.now() - new Date(d).getTime()) / 1000
  if (diff < 60)     return 'just now'
  if (diff < 3600)   return Math.floor(diff / 60) + 'm ago'
  if (diff < 86400)  return Math.floor(diff / 3600) + 'h ago'
  if (diff < 172800) return 'Yesterday'
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

function msgSide(msg: any): 'mine' | 'theirs' {
  return msg.sender_id === auth.user?.id ? 'mine' : 'theirs'
}

// ── Load lists ────────────────────────────────────────────────────────────────
async function loadMemberThreads(bustCache = false) {
  if (!bustCache) loadingM.value = true
  try {
    let q = '?channel=user_to_staff'
    if (statusFilter.value) q += `&status=${statusFilter.value}`
    if (typeFilter.value)   q += `&type=${typeFilter.value}`
    if (bustCache)          q += `&_t=${Date.now()}`
    const res = await apiGet<any>(`/admin/inbox${q}`, auth.token ?? undefined)
    memberThreads.value = res.data ?? []
  } catch {} finally { loadingM.value = false }
}

async function loadStaffThreads(bustCache = false) {
  if (!bustCache) loadingS.value = true
  try {
    const ts = bustCache ? `&_t=${Date.now()}` : ''
    const res = await apiGet<any>(`/admin/inbox?channel=staff_to_admin${ts}`, auth.token ?? undefined)
    staffThreads.value = res.data ?? []
  } catch {} finally { loadingS.value = false }
}

function switchTab(tab: 'user_to_staff' | 'staff_to_admin') {
  activeTab.value = tab
  if (tab === 'user_to_staff' && !memberThreads.value.length) loadMemberThreads()
  if (tab === 'staff_to_admin' && !staffThreads.value.length) loadStaffThreads()
}

// ── Thread modal ──────────────────────────────────────────────────────────────
async function openThread(t: any) {
  activeThread.value = t
  showThread.value   = true
  replyBody.value    = ''
  sendError.value    = ''
  loadingMsgs.value  = true
  try {
    threadMsgs.value = await apiGet<any[]>(`/admin/inbox/${t.id}/messages`, auth.token ?? undefined)
    scrollToBottom()
  } catch {} finally { loadingMsgs.value = false }

  if (msgPollTimer) clearInterval(msgPollTimer)
  msgPollTimer = setInterval(async () => {
    if (document.hidden || !showThread.value) return
    try {
      const fresh = await apiGet<any[]>(`/admin/inbox/${t.id}/messages?_t=${Date.now()}`, auth.token ?? undefined)
      if (fresh.length !== threadMsgs.value.length) {
        threadMsgs.value = fresh
        scrollToBottom()
      }
    } catch {}
  }, 5_000)
}

async function scrollToBottom() {
  await nextTick()
  if (msgListRef.value) msgListRef.value.scrollTop = msgListRef.value.scrollHeight
}

async function sendReply() {
  if (!replyBody.value.trim()) return
  sending.value   = true
  sendError.value = ''
  try {
    const msg = await apiPost<any>(
      `/admin/inbox/${activeThread.value.id}/reply`,
      { body: replyBody.value },
      auth.token ?? undefined
    )
    threadMsgs.value.push(msg)
    replyBody.value = ''
    scrollToBottom()
    const list = activeTab.value === 'user_to_staff' ? memberThreads : staffThreads
    const idx  = list.value.findIndex((x: any) => x.id === activeThread.value.id)
    if (idx >= 0) {
      list.value[idx].last_sender_role = auth.user?.role ?? 'staff'
      list.value[idx].last_message_at  = new Date().toISOString()
    }
  } catch (e: any) {
    sendError.value = e.message
  } finally { sending.value = false }
}

async function closeThread(t: any) {
  processingId.value = t.id
  try {
    await apiPatch<any>(`/admin/inbox/${t.id}/close`, {}, auth.token ?? undefined)
    const list = activeTab.value === 'user_to_staff' ? memberThreads : staffThreads
    const idx  = list.value.findIndex((x: any) => x.id === t.id)
    if (idx >= 0) list.value[idx].status = 'closed'
    if (activeThread.value?.id === t.id) activeThread.value.status = 'closed'
  } catch {} finally { processingId.value = null }
}

async function reopenThread(t: any) {
  processingId.value = t.id
  try {
    await apiPatch<any>(`/admin/inbox/${t.id}/open`, {}, auth.token ?? undefined)
    const list = activeTab.value === 'user_to_staff' ? memberThreads : staffThreads
    const idx  = list.value.findIndex((x: any) => x.id === t.id)
    if (idx >= 0) list.value[idx].status = 'open'
    if (activeThread.value?.id === t.id) activeThread.value.status = 'open'
  } catch {} finally { processingId.value = null }
}

async function deleteThread(t: any) {
  if (!confirm('Delete this thread permanently?')) return
  processingId.value = t.id
  try {
    await apiDelete<any>(`/admin/inbox/${t.id}`, auth.token ?? undefined)
    const list = activeTab.value === 'user_to_staff' ? memberThreads : staffThreads
    list.value = list.value.filter((x: any) => x.id !== t.id)
    if (activeThread.value?.id === t.id) showThread.value = false
  } catch (e: any) {
    alert(e.message)
  } finally { processingId.value = null }
}

// ── Compose staff → admin ─────────────────────────────────────────────────────
async function submitCompose() {
  if (!composeSubject.value.trim() || !composeBody.value.trim()) {
    composeError.value = 'Subject and message are required.'
    return
  }
  composing.value    = true
  composeError.value = ''
  try {
    const t = await apiPost<any>('/admin/inbox/compose', {
      subject: composeSubject.value, body: composeBody.value,
    }, auth.token ?? undefined)
    staffThreads.value.unshift(t)
    showCompose.value    = false
    composeSubject.value = ''
    composeBody.value    = ''
  } catch (e: any) {
    composeError.value = e.message
  } finally { composing.value = false }
}

// ── Log walk-in query ─────────────────────────────────────────────────────────
async function submitLog() {
  if (!logForm.value.from_name.trim() || !logForm.value.body.trim()) {
    logError.value = 'Name and query are required.'
    return
  }
  logging.value  = true
  logError.value = ''
  try {
    const t = await apiPost<any>('/admin/inbox/log-query', {
      from_name:  logForm.value.from_name,
      from_email: logForm.value.from_email || undefined,
      body:       logForm.value.body,
      query_type: logForm.value.query_type,
      subject:    logForm.value.subject || undefined,
    }, auth.token ?? undefined)
    memberThreads.value.unshift(t)
    showLog.value = false
    logForm.value = { from_name: '', from_email: '', body: '', query_type: 'reference', subject: '' }
  } catch (e: any) {
    logError.value = e.message
  } finally { logging.value = false }
}

onMounted(() => {
  loadMemberThreads()
  listPollTimer = setInterval(() => {
    if (document.hidden) return
    if (activeTab.value === 'user_to_staff') loadMemberThreads(true)
    else loadStaffThreads(true)
  }, 30_000)
})

onUnmounted(() => {
  if (listPollTimer) clearInterval(listPollTimer)
  if (msgPollTimer)  clearInterval(msgPollTimer)
})

watch(showThread, (open) => {
  if (!open && msgPollTimer) {
    clearInterval(msgPollTimer)
    msgPollTimer = null
  }
})
</script>

<template>
  <!-- Header -->
  <div class="shead">
    <div>
      <h2>Inbox</h2>
      <p>{{ unreadMemberCount }} unread member quer{{ unreadMemberCount !== 1 ? 'ies' : 'y' }}</p>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-ghost btn-sm" @click="activeTab === 'user_to_staff' ? loadMemberThreads() : loadStaffThreads()">
        <LucideIcon name="refresh-cw" class="ic-sm" />
      </button>
      <button v-if="activeTab === 'user_to_staff'" class="btn btn-ghost" @click="showLog = true">
        <LucideIcon name="pen-line" class="ic-sm" /> Log Query
      </button>
      <button v-else class="btn btn-primary" @click="showCompose = true">
        <LucideIcon name="send" class="ic-sm" /> Message Admin
      </button>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tab-bar">
    <button
      :class="['tab-btn', { active: activeTab === 'user_to_staff' }]"
      @click="switchTab('user_to_staff')"
    >
      <LucideIcon name="users-round" class="ic-sm" /> Members → Staff
      <span v-if="unreadMemberCount" class="bg bg-red" style="margin-left:6px">{{ unreadMemberCount }}</span>
    </button>
    <button
      :class="['tab-btn', { active: activeTab === 'staff_to_admin' }]"
      @click="switchTab('staff_to_admin')"
    >
      <LucideIcon name="shield" class="ic-sm" /> Staff → Admin
    </button>
  </div>

  <!-- Members → Staff -->
  <template v-if="activeTab === 'user_to_staff'">
    <div class="toolbar" style="margin:14px 0 10px">
      <select class="sel" v-model="statusFilter" @change="loadMemberThreads">
        <option value="">All Statuses</option>
        <option value="open">Open</option>
        <option value="closed">Closed</option>
      </select>
      <select class="sel" v-model="typeFilter" @change="loadMemberThreads">
        <option value="">All Types</option>
        <option value="reference">Reference</option>
        <option value="acquisition">Acquisition</option>
        <option value="citation">Citation</option>
        <option value="support">Support</option>
        <option value="other">Other</option>
      </select>
    </div>

    <div v-if="loadingM" style="padding:40px;text-align:center;color:var(--faint)">Loading…</div>
    <div v-else-if="!memberThreads.length" class="empty-state">
      <LucideIcon name="inbox" style="width:38px;height:38px;opacity:.3;margin:0 auto 12px;display:block" />
      <div style="font-weight:600;margin-bottom:4px">No queries yet</div>
      <div style="font-size:12.5px">Use "Log Query" to record a walk-in or phone enquiry.</div>
    </div>
    <div v-else class="thread-list">
      <div
        v-for="t in memberThreads" :key="t.id"
        :class="['thread-row', { unread: isUnread(t), closed: t.status === 'closed' }]"
        @click="openThread(t)"
      >
        <span class="dot" :style="dotStyle(t)" style="flex-shrink:0"></span>
        <div class="mini-av">{{ threadSenderName(t).charAt(0).toUpperCase() }}</div>
        <div class="thread-body">
          <div class="thread-top">
            <span class="thread-from">{{ threadSenderName(t) }}</span>
            <span class="thread-time">{{ timeAgo(t.last_message_at) }}</span>
          </div>
          <div class="thread-sub">{{ t.subject || t.latest_message?.body?.slice(0, 60) || t.latestMessage?.body?.slice(0, 60) || '…' }}</div>
          <div class="thread-meta">
            <span v-if="t.query_type" class="tag">{{ TYPES[t.query_type] ?? t.query_type }}</span>
            <span :class="['badge', t.status === 'closed' ? 'b-gray' : isUnread(t) ? 'b-blue' : 'b-green']" style="font-size:10px">
              {{ t.status === 'closed' ? 'Closed' : isUnread(t) ? 'Awaiting Reply' : 'Replied' }}
            </span>
          </div>
        </div>
        <div class="thread-acts" @click.stop>
          <button class="btn btn-ghost btn-xs" :disabled="processingId === t.id" @click="t.status === 'closed' ? reopenThread(t) : closeThread(t)">
            {{ t.status === 'closed' ? 'Reopen' : 'Close' }}
          </button>
          <button class="btn btn-danger btn-xs" :disabled="processingId === t.id" @click="deleteThread(t)" title="Delete">
            <LucideIcon name="trash-2" class="ic-sm" />
          </button>
        </div>
      </div>
    </div>
  </template>

  <!-- Staff → Admin -->
  <template v-if="activeTab === 'staff_to_admin'">
    <div v-if="loadingS" style="padding:40px;text-align:center;color:var(--faint)">Loading…</div>
    <div v-else-if="!staffThreads.length" class="empty-state" style="margin-top:20px">
      <LucideIcon name="message-square-dashed" style="width:38px;height:38px;opacity:.3;margin:0 auto 12px;display:block" />
      <div style="font-weight:600;margin-bottom:4px">No messages to admin</div>
      <div style="font-size:12.5px">Click "Message Admin" to send an internal message to the Chief Librarian.</div>
    </div>
    <div v-else class="thread-list" style="margin-top:14px">
      <div
        v-for="t in staffThreads" :key="t.id"
        :class="['thread-row', { unread: isUnread(t), closed: t.status === 'closed' }]"
        @click="openThread(t)"
      >
        <span class="dot" :style="dotStyle(t)" style="flex-shrink:0"></span>
        <div class="mini-av navy">{{ t.from_user?.name?.charAt(0)?.toUpperCase() ?? 'S' }}</div>
        <div class="thread-body">
          <div class="thread-top">
            <span class="thread-from">{{ t.subject }}</span>
            <span class="thread-time">{{ timeAgo(t.last_message_at) }}</span>
          </div>
          <div class="thread-sub">{{ t.latestMessage?.body?.slice(0, 70) ?? '…' }}</div>
          <div class="thread-meta">
            <span class="tag">Internal</span>
            <span :class="['badge', t.status === 'closed' ? 'b-gray' : isUnread(t) ? 'b-blue' : 'b-gray']" style="font-size:10px">
              {{ t.status === 'closed' ? 'Closed' : isUnread(t) ? 'Admin Replied' : 'Awaiting Admin' }}
            </span>
          </div>
        </div>
        <div class="thread-acts" @click.stop>
          <button class="btn btn-ghost btn-xs" :disabled="processingId === t.id" @click="t.status === 'closed' ? reopenThread(t) : closeThread(t)">
            {{ t.status === 'closed' ? 'Reopen' : 'Close' }}
          </button>
          <button class="btn btn-danger btn-xs" :disabled="processingId === t.id" @click="deleteThread(t)" title="Delete">
            <LucideIcon name="trash-2" class="ic-sm" />
          </button>
        </div>
      </div>
    </div>
  </template>

  <!-- ══ Thread Conversation Modal ══════════════════════════════════════════ -->
  <div :class="['mscrim', { open: showThread }]" @click.self="showThread = false">
    <div class="modal modal-lg" v-if="activeThread">
      <!-- Modal header -->
      <div class="thread-modal-hd">
        <div style="display:flex;align-items:center;gap:10px;min-width:0">
          <div :class="['mini-av', activeTab === 'staff_to_admin' ? 'navy' : '']">
            {{ activeTab === 'user_to_staff'
                ? threadSenderName(activeThread).charAt(0).toUpperCase()
                : (activeThread.from_user?.name?.charAt(0)?.toUpperCase() ?? 'S') }}
          </div>
          <div style="min-width:0">
            <div class="thread-modal-title">
              {{ activeTab === 'user_to_staff' ? threadSenderName(activeThread) : (activeThread.subject ?? 'Internal message') }}
            </div>
            <div style="font-size:11.5px;color:var(--muted);display:flex;align-items:center;gap:8px">
              <span v-if="activeTab === 'user_to_staff' && activeThread.query_type" class="tag" style="font-size:10px">{{ TYPES[activeThread.query_type] }}</span>
              <span v-if="activeThread.from_user?.member_number" class="mono">{{ activeThread.from_user.member_number }}</span>
              <span>{{ activeThread.status === 'closed' ? '· Closed' : '· Open' }}</span>
            </div>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
          <button class="btn btn-ghost btn-sm" @click="activeThread.status === 'closed' ? reopenThread(activeThread) : closeThread(activeThread)">
            {{ activeThread.status === 'closed' ? 'Reopen' : 'Close Thread' }}
          </button>
          <div class="dh-x" @click="showThread = false"><LucideIcon name="x" /></div>
        </div>
      </div>

      <!-- Messages -->
      <div class="thread-msgs" ref="msgListRef">
        <div v-if="loadingMsgs" style="padding:40px;text-align:center;color:var(--faint)">Loading…</div>
        <div v-else-if="!threadMsgs.length" style="padding:40px;text-align:center;color:var(--faint)">No messages.</div>
        <template v-else>
          <div v-for="msg in threadMsgs" :key="msg.id" :class="['msg-row', msgSide(msg)]">
            <div class="msg-av">{{ (msg.sender?.name ?? msg.sender_name ?? '?').charAt(0).toUpperCase() }}</div>
            <div class="msg-bubble">
              <div class="msg-meta">
                <span class="msg-name">{{ msg.sender?.name ?? msg.sender_name ?? 'Unknown' }}</span>
                <span v-if="msg.sender?.role" class="msg-role">{{ msg.sender.role }}</span>
                <span class="msg-time">{{ timeAgo(msg.created_at) }}</span>
              </div>
              <div class="msg-body">{{ msg.body }}</div>
            </div>
          </div>
        </template>
      </div>

      <!-- Reply -->
      <div class="thread-reply" v-if="activeThread.status === 'open'">
        <div v-if="sendError" style="color:var(--red);font-size:12.5px;margin-bottom:8px">{{ sendError }}</div>
        <div style="display:flex;gap:10px;align-items:flex-end">
          <div class="mini-av" style="flex-shrink:0">{{ (auth.user?.name ?? 'S').charAt(0).toUpperCase() }}</div>
          <textarea
            class="ft" style="flex:1;min-height:64px;resize:vertical"
            v-model="replyBody"
            placeholder="Write a reply… (Ctrl+Enter to send)"
            @keydown.ctrl.enter="sendReply"
          ></textarea>
          <button class="btn btn-primary" :disabled="sending || !replyBody.trim()" @click="sendReply">
            <LucideIcon name="send" class="ic-sm" />
            {{ sending ? '…' : 'Send' }}
          </button>
        </div>
      </div>
      <div v-else class="thread-reply" style="text-align:center;color:var(--muted);font-size:13px;padding:16px">
        Thread is closed — reopen to continue the conversation.
      </div>
    </div>
  </div>

  <!-- ══ Compose Staff → Admin drawer ═══════════════════════════════════════ -->
  <div :class="['scrim', { open: showCompose }]" @click.self="showCompose = false">
    <div :class="['drawer', { open: showCompose }]" style="max-width:480px">
      <div class="dh">
        <div><div class="dh-t">Message Admin</div><div class="dh-s">Send an internal message to the Chief Librarian</div></div>
        <div class="dh-x" @click="showCompose = false"><LucideIcon name="x" /></div>
      </div>
      <div class="db">
        <div v-if="composeError" class="err-box">{{ composeError }}</div>
        <div class="form-grid">
          <div class="fg col2">
            <label class="fl">Subject <span class="req">*</span></label>
            <input class="fi" v-model="composeSubject" placeholder="What is this about?" />
          </div>
          <div class="fg col2">
            <label class="fl">Message <span class="req">*</span></label>
            <textarea class="ft" style="min-height:120px" v-model="composeBody" placeholder="Write your message…"></textarea>
          </div>
        </div>
      </div>
      <div class="df">
        <button class="btn btn-primary" :disabled="composing" @click="submitCompose">
          <LucideIcon name="send" class="ic-sm" /> {{ composing ? 'Sending…' : 'Send Message' }}
        </button>
        <button class="btn btn-ghost" @click="showCompose = false">Cancel</button>
      </div>
    </div>
  </div>

  <!-- ══ Log Query drawer ════════════════════════════════════════════════════ -->
  <div :class="['scrim', { open: showLog }]" @click.self="showLog = false">
    <div :class="['drawer', { open: showLog }]" style="max-width:480px">
      <div class="dh">
        <div><div class="dh-t">Log a Query</div><div class="dh-s">Record a walk-in, phone, or email patron enquiry</div></div>
        <div class="dh-x" @click="showLog = false"><LucideIcon name="x" /></div>
      </div>
      <div class="db">
        <div v-if="logError" class="err-box">{{ logError }}</div>
        <div class="db-sec">
          <div class="db-sec-h"><LucideIcon name="user" /> Patron Details</div>
          <div class="form-grid">
            <div class="fg col2">
              <label class="fl">Name <span class="req">*</span></label>
              <input class="fi" v-model="logForm.from_name" placeholder="Patron name" />
            </div>
            <div class="fg col2">
              <label class="fl">Email (optional)</label>
              <input class="fi" type="email" v-model="logForm.from_email" placeholder="patron@example.com" />
            </div>
          </div>
        </div>
        <div class="db-sec">
          <div class="db-sec-h"><LucideIcon name="message-square-text" /> Query Details</div>
          <div class="form-grid">
            <div class="fg col2">
              <label class="fl">Type</label>
              <select class="fs" v-model="logForm.query_type">
                <option value="reference">Reference</option>
                <option value="acquisition">Acquisition Request</option>
                <option value="citation">Citation Help</option>
                <option value="support">Technical Support</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="fg col2">
              <label class="fl">Subject (optional)</label>
              <input class="fi" v-model="logForm.subject" placeholder="Brief subject line" />
            </div>
            <div class="fg col2">
              <label class="fl">Query / Message <span class="req">*</span></label>
              <textarea class="ft" v-model="logForm.body" placeholder="Describe the patron's query…"></textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="df">
        <button class="btn btn-primary" :disabled="logging" @click="submitLog">
          <LucideIcon name="inbox" class="ic-sm" /> {{ logging ? 'Saving…' : 'Log Query' }}
        </button>
        <button class="btn btn-ghost" @click="showLog = false">Cancel</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Thread list */
.thread-list { display:flex;flex-direction:column;gap:2px }
.thread-row  { display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:var(--r2);cursor:pointer;transition:background .15s;border:1px solid transparent }
.thread-row:hover { background:var(--hover) }
.thread-row.unread { background:#F0F5FF;border-color:rgba(37,99,235,.1) }
.thread-row.closed { opacity:.6 }

.thread-body  { flex:1;min-width:0 }
.thread-top   { display:flex;justify-content:space-between;align-items:baseline;gap:8px }
.thread-from  { font-size:13px;font-weight:600;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis }
.thread-time  { font-size:11px;color:var(--faint);white-space:nowrap;flex-shrink:0 }
.thread-sub   { font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:2px 0 4px }
.thread-meta  { display:flex;gap:5px;align-items:center }
.thread-acts  { display:flex;gap:6px;flex-shrink:0;opacity:0;transition:opacity .15s }
.thread-row:hover .thread-acts { opacity:1 }

/* Mini avatar */
.mini-av {
  width:32px;height:32px;border-radius:50%;background:var(--line);
  display:flex;align-items:center;justify-content:center;
  font-size:13px;font-weight:700;color:var(--navy);flex-shrink:0
}
.mini-av.navy { background:var(--navy);color:#fff }

/* Tab bar */
.tab-bar  { display:flex;gap:4px;border-bottom:2px solid var(--line);margin-bottom:0 }
.tab-btn  { padding:9px 16px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:500;color:var(--muted);border-bottom:2px solid transparent;margin-bottom:-2px;display:flex;align-items:center;gap:6px;transition:color .15s,border-color .15s;border-radius:var(--r2) var(--r2) 0 0 }
.tab-btn:hover  { color:var(--ink) }
.tab-btn.active { color:var(--blue);border-bottom-color:var(--blue);font-weight:600 }

/* Thread modal */
.modal-lg      { max-width:680px;width:100%;display:flex;flex-direction:column;max-height:85vh }
.thread-modal-hd { padding:16px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-shrink:0 }
.thread-modal-title { font-size:14px;font-weight:700;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis }

.thread-msgs { flex:1;overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:14px;min-height:0 }

.msg-row       { display:flex;gap:10px;align-items:flex-start }
.msg-row.mine  { flex-direction:row-reverse }

.msg-av {
  width:30px;height:30px;border-radius:50%;background:var(--line);
  display:flex;align-items:center;justify-content:center;
  font-size:12px;font-weight:700;color:var(--navy);flex-shrink:0
}
.msg-bubble    { max-width:75%;display:flex;flex-direction:column;gap:4px }
.msg-row.mine .msg-bubble { align-items:flex-end }

.msg-meta      { display:flex;align-items:center;gap:6px }
.msg-row.mine .msg-meta { flex-direction:row-reverse }
.msg-name      { font-size:11.5px;font-weight:700;color:var(--ink) }
.msg-role      { font-size:10px;color:var(--faint);text-transform:capitalize;background:var(--line);padding:1px 5px;border-radius:4px }
.msg-time      { font-size:10.5px;color:var(--faint) }
.msg-body      { padding:10px 14px;border-radius:12px;font-size:13px;line-height:1.55;background:var(--line-soft);color:var(--ink) }
.msg-row.mine  .msg-body { background:var(--blue);color:#fff;border-radius:12px 4px 12px 12px }
.msg-row.theirs .msg-body { border-radius:4px 12px 12px 12px }

.thread-reply { padding:14px 20px;border-top:1px solid var(--line);flex-shrink:0 }

.empty-state { padding:60px 20px;text-align:center;color:var(--faint) }
.err-box     { padding:10px 14px;background:var(--red-50);color:var(--red);border-radius:var(--r2);margin-bottom:12px;font-size:13px }
.btn-xs      { padding:4px 10px;font-size:11px;height:auto }
</style>
