<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPost } from '@/api/http'

const auth = useAuthStore()

// ── FAQ ─────────────────────────────────────────────────────────────────────
const openFaq = ref<number | null>(null)
const faqs = [
  { q:'How do I register as a library member?',      a:'Visit the Library in Block A with a valid ID and two passport photographs, or complete the online membership form. Staff and affiliated researchers register free.' },
  { q:'How many books can I borrow?',                a:'Up to 5 books for 14 days, with up to 2 online renewals per item provided no one else has reserved it.' },
  { q:'Can I access the eLibrary off-site?',          a:"Yes — members access digital resources 24/7 from any device using their member login, subject to each publisher's access terms." },
  { q:'What is the overdue fine policy?',             a:'₦50 per day per book. Fines must be cleared before new borrowings. Persistent overdue items may suspend borrowing privileges.' },
  { q:'How do I export a citation?',                  a:'Open any catalog record, choose "Cite," and select APA, MLA, Chicago, or Harvard. Copy it or export to Zotero / Mendeley.' },
  { q:'Can I suggest a book for acquisition?',        a:'Yes — use "Ask the Librarian" with the title, author, publisher, and ISBN, and select "Acquisition Request" as the category.' },
  { q:'How does the reservation system work?',        a:"Reserve any available book from the catalog. You will be notified when it's ready. Books are held at the front desk for 48 hours." },
  { q:'Who is eligible for a reading room session?',  a:'Any registered member may book a reading room session. Advance booking is recommended for private study rooms.' },
]
function toggleFaq(i: number) { openFaq.value = openFaq.value === i ? null : i }

// ── Ask the librarian ────────────────────────────────────────────────────────
const helpTab     = ref<'compose' | 'threads'>('compose')
const category    = ref('General')
const subject     = ref('')
const message     = ref('')
const attachment  = ref<File | null>(null)
const sending     = ref(false)
const sent        = ref(false)
const sendError   = ref('')
const categories  = ['General', 'Research Help', 'Acquisition Request', 'Digital Access', 'Account / Fines', 'Renewal Request', 'Feedback']

const queryTypeMap: Record<string, string> = {
  'General': 'reference',
  'Research Help': 'reference',
  'Acquisition Request': 'acquisition',
  'Digital Access': 'support',
  'Account / Fines': 'support',
  'Renewal Request': 'support',
  'Feedback': 'other',
}

// ── Threads ──────────────────────────────────────────────────────────────────
const threads       = ref<any[]>([])
const threadsLoading = ref(false)
const activeThread  = ref<any>(null)
const messages      = ref<any[]>([])
const messagesLoading = ref(false)
const replyBody     = ref('')
const replySending  = ref(false)

const unreadCount = computed(() =>
  threads.value.filter(t => t.status === 'open' && (t.last_sender_role === 'staff' || t.last_sender_role === 'admin')).length
)

let listPollTimer: ReturnType<typeof setInterval> | null = null
let msgPollTimer:  ReturnType<typeof setInterval> | null = null

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' })
}
function formatTime(d: string) {
  return new Date(d).toLocaleTimeString('en-GB', { hour:'2-digit', minute:'2-digit' })
}

async function loadThreads(bustCache = false) {
  if (!bustCache) threadsLoading.value = true
  try {
    const q = bustCache ? `?_t=${Date.now()}` : ''
    const data = await apiGet<any>(`/inbox/threads${q}`, auth.token ?? undefined)
    threads.value = data.data ?? []
  } catch {}
  threadsLoading.value = false
}

async function openThread(thread: any) {
  activeThread.value   = thread
  messagesLoading.value = true
  try {
    const data = await apiGet<any>(`/inbox/threads/${thread.id}/messages`, auth.token ?? undefined)
    messages.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch {}
  messagesLoading.value = false

  if (msgPollTimer) clearInterval(msgPollTimer)
  msgPollTimer = setInterval(async () => {
    if (document.hidden || !activeThread.value) return
    try {
      const data = await apiGet<any>(`/inbox/threads/${thread.id}/messages?_t=${Date.now()}`, auth.token ?? undefined)
      const fresh = Array.isArray(data) ? data : (data.data ?? [])
      if (fresh.length !== messages.value.length) {
        messages.value = fresh
      }
    } catch {}
  }, 5_000)
}

async function sendReply() {
  if (!replyBody.value.trim() || !activeThread.value) return
  replySending.value = true
  try {
    const msg = await apiPost<any>(
      `/inbox/threads/${activeThread.value.id}/reply`,
      { body: replyBody.value },
      auth.token ?? undefined
    )
    messages.value.push(msg)
    replyBody.value = ''
    // Refresh thread status
    activeThread.value.last_sender_role = 'user'
  } catch {}
  replySending.value = false
}

async function submitAsk() {
  if (!message.value.trim()) return
  sending.value   = true
  sendError.value = ''
  try {
    await apiPost('/inbox/submit', {
      subject:    subject.value.trim() || null,
      body:       message.value,
      query_type: queryTypeMap[category.value] ?? 'reference',
    }, auth.token ?? undefined)
    sent.value    = true
    message.value = ''
    subject.value = ''
    attachment.value = null
  } catch (e: any) {
    sendError.value = e.message
  }
  sending.value = false
}

function handleFile(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (f) attachment.value = f
}

function switchToThreads() {
  helpTab.value = 'threads'
  loadThreads()
}

onMounted(() => {
  loadThreads()
  listPollTimer = setInterval(() => {
    if (!document.hidden) loadThreads(true)
  }, 30_000)
})

onUnmounted(() => {
  if (listPollTimer) clearInterval(listPollTimer)
  if (msgPollTimer)  clearInterval(msgPollTimer)
})

// Stop message polling when user closes a thread (goes back to list)
watch(activeThread, (val) => {
  if (!val && msgPollTimer) {
    clearInterval(msgPollTimer)
    msgPollTimer = null
  }
})
</script>

<template>
  <!-- Page header -->
  <div class="bg-white border-b border-[var(--line)] px-4 sm:px-7 py-6 sm:py-8">
    <div class="max-w-[860px]">
      <div class="text-[11px] font-semibold tracking-[.16em] uppercase text-[var(--gold-600)] mb-2">Help Center</div>
      <h1 class="text-[30px] font-bold text-[var(--navy)] tracking-tight" style="font-family:var(--display)">How can we help you?</h1>
      <div class="w-12 h-[3px] rounded-full bg-[var(--gold)] mt-3 mb-3"></div>
      <p class="text-[14.5px] text-[var(--muted)] font-light">Browse frequently asked questions or send a message directly to the library team.</p>
    </div>
  </div>

  <div class="max-w-[1260px] mx-auto px-4 sm:px-7 py-6 sm:py-8 pb-16">
    <div class="grid gap-8 lg:gap-10 items-start help-grid">

      <!-- FAQ section -->
      <div>
        <h2 class="text-[18px] font-bold text-[var(--navy)] mb-5 flex items-center gap-2" style="font-family:var(--display)">
          <LucideIcon name="circle-help" :size="20" class="text-[var(--blue)]" /> Frequently Asked Questions
        </h2>
        <div class="flex flex-col gap-3">
          <div v-for="(faq, i) in faqs" :key="i"
            :class="['bg-white border rounded-[10px] overflow-hidden transition-colors duration-200', openFaq===i ? 'border-[var(--blue-100)]' : 'border-[var(--line)]']">
            <button class="w-full flex items-center justify-between gap-3 px-5 py-[17px] text-left" @click="toggleFaq(i)">
              <span class="text-[14px] font-semibold text-[var(--navy)]">{{ faq.q }}</span>
              <div :class="['w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 transition-all duration-200',
                openFaq===i ? 'bg-[var(--blue)] text-white rotate-45' : 'bg-[var(--blue-50)] text-[var(--blue)]']">
                <LucideIcon name="plus" :size="14" />
              </div>
            </button>
            <div v-if="openFaq===i" class="border-t border-[var(--line-soft)] px-5 py-4">
              <p class="text-[13.5px] text-[var(--muted)] leading-[1.7] font-light">{{ faq.a }}</p>
            </div>
          </div>
        </div>

        <!-- Info cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-8">
          <div v-for="[icon, title, desc, color] in [
            ['clock',   'Opening Hours', 'Mon–Fri 8am–5pm · Sat 9am–1pm',       'var(--blue)'],
            ['map-pin', 'Location',      'Block A, Mambayya House, Kano',        'var(--green)'],
            ['bell',    'Alerts',        'Enable SMS & email alerts for due dates','var(--gold-600)'],
          ]" :key="title" class="bg-white border border-[var(--line)] rounded-xl p-4" style="box-shadow:var(--sh1)">
            <div class="w-9 h-9 rounded-[9px] flex items-center justify-center mb-3" :style="{ background: color + '18' }">
              <LucideIcon :name="icon" :size="18" :style="{ color }" />
            </div>
            <div class="text-[13px] font-bold text-[var(--navy)] mb-1">{{ title }}</div>
            <p class="text-[12px] text-[var(--muted)] leading-snug">{{ desc }}</p>
          </div>
        </div>
      </div>

      <!-- Ask the librarian panel -->
      <div class="lg:sticky lg:top-[6rem]">
        <div class="bg-white border border-[var(--line)] rounded-xl overflow-hidden" style="box-shadow:var(--sh1)">

          <!-- Card header -->
          <div class="px-6 py-5 border-b border-[var(--line)]" style="background:linear-gradient(115deg,#0B2E63,var(--blue))">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white flex-shrink-0">
                <LucideIcon name="user-round" :size="20" />
              </div>
              <div>
                <div class="text-[15px] font-bold text-white" style="font-family:var(--display)">Ask the Librarian</div>
                <div class="text-[11.5px] text-white/70 flex items-center gap-1.5 mt-px">
                  <span class="w-1.5 h-1.5 rounded-full bg-[var(--green)]"></span>
                  Online — Typically replies within 1 day
                </div>
              </div>
            </div>
            <!-- Tabs -->
            <div class="flex gap-1">
              <button @click="helpTab='compose'"
                :class="['text-[12.5px] font-semibold px-3 py-1.5 rounded-md transition-all',
                  helpTab==='compose' ? 'bg-white text-[var(--navy)]' : 'text-white/70 hover:text-white hover:bg-white/15']">
                New Message
              </button>
              <button @click="switchToThreads()"
                :class="['text-[12.5px] font-semibold px-3 py-1.5 rounded-md transition-all flex items-center gap-1.5',
                  helpTab==='threads' ? 'bg-white text-[var(--navy)]' : 'text-white/70 hover:text-white hover:bg-white/15']">
                My Conversations
                <span v-if="unreadCount" class="w-[18px] h-[18px] rounded-full bg-[var(--red)] text-white text-[10px] flex items-center justify-center font-bold">{{ unreadCount }}</span>
              </button>
            </div>
          </div>

          <!-- ── Compose tab ── -->
          <div v-if="helpTab==='compose'" class="p-6">
            <!-- Sent state -->
            <div v-if="sent" class="flex flex-col items-center py-6 text-center">
              <div class="w-14 h-14 rounded-full bg-[var(--green-50)] flex items-center justify-center mb-3">
                <LucideIcon name="check" :size="28" class="text-[var(--green)]" />
              </div>
              <div class="text-[17px] font-bold text-[var(--navy)] mb-1" style="font-family:var(--display)">Message Sent!</div>
              <p class="text-[13px] text-[var(--muted)] font-light">The library team will respond within 1 business day.</p>
              <div class="flex gap-2 mt-4">
                <button class="btn btn-outline btn-sm" @click="sent=false">Send Another</button>
                <button class="btn btn-primary btn-sm" @click="switchToThreads(); sent=false">View Conversations</button>
              </div>
            </div>

            <template v-else>
              <div v-if="sendError" class="flex items-center gap-2 text-[12.5px] text-[var(--red)] bg-[var(--red-50)] rounded-[10px] px-3 py-2.5 mb-4">
                <LucideIcon name="alert-triangle" :size="14" /> {{ sendError }}
              </div>

              <div class="mb-4">
                <label class="block text-[11px] font-bold tracking-[.05em] uppercase text-[var(--muted)] mb-1.5">Category</label>
                <select v-model="category" class="w-full text-[14px] px-3.5 py-2.5 border-[1.5px] border-[var(--line)] rounded-[10px] outline-none bg-white text-[var(--ink)] focus:border-[var(--blue)]">
                  <option v-for="c in categories" :key="c">{{ c }}</option>
                </select>
              </div>
              <div class="mb-4">
                <label class="block text-[11px] font-bold tracking-[.05em] uppercase text-[var(--muted)] mb-1.5">Subject</label>
                <input v-model="subject" type="text" placeholder="Brief subject line…"
                  class="w-full text-[14px] px-3.5 py-2.5 border-[1.5px] border-[var(--line)] rounded-[10px] outline-none text-[var(--ink)] placeholder:text-[var(--faint)] focus:border-[var(--blue)]" />
              </div>
              <div class="mb-4">
                <label class="block text-[11px] font-bold tracking-[.05em] uppercase text-[var(--muted)] mb-1.5">Your Message</label>
                <textarea v-model="message" rows="5"
                  class="w-full border-[1.5px] border-[var(--line)] rounded-[10px] px-3.5 py-3 text-[13.5px] text-[var(--ink)] resize-y leading-relaxed outline-none transition-colors focus:border-[var(--blue)] placeholder:text-[var(--faint)]"
                  placeholder="Describe your question in detail…"></textarea>
              </div>

              <!-- Attachment -->
              <div class="mb-5">
                <label class="block text-[11px] font-bold tracking-[.05em] uppercase text-[var(--muted)] mb-1.5">Attachment <span class="font-normal text-[var(--faint)] normal-case tracking-normal">(optional)</span></label>
                <label class="flex items-center gap-2 border-[1.5px] border-dashed border-[var(--line)] rounded-[10px] px-4 py-3 cursor-pointer hover:border-[var(--blue)] hover:bg-[var(--blue-50)] transition-all">
                  <LucideIcon name="paperclip" :size="16" class="text-[var(--muted)]" />
                  <span class="text-[13px] text-[var(--muted)] truncate">{{ attachment ? attachment.name : 'Click to attach a file' }}</span>
                  <input type="file" class="hidden" @change="handleFile" />
                </label>
              </div>

              <button class="btn btn-primary btn-block" :disabled="sending || !message.trim()" @click="submitAsk">
                <LucideIcon :name="sending ? 'refresh-cw' : 'send'" :size="15" />
                {{ sending ? 'Sending…' : 'Send Message' }}
              </button>
              <p class="text-center text-[11.5px] text-[var(--faint)] mt-3">We respond within 1 business day.</p>
            </template>
          </div>

          <!-- ── Threads tab: list ── -->
          <div v-else-if="helpTab==='threads' && !activeThread">
            <div v-if="threadsLoading" class="p-6 flex flex-col gap-3">
              <div v-for="i in 3" :key="i" class="h-[64px] bg-[var(--bg)] rounded-[10px] animate-pulse"></div>
            </div>
            <div v-else-if="!threads.length" class="p-8 text-center">
              <LucideIcon name="message-circle" :size="36" class="text-[var(--faint)] mx-auto mb-3" />
              <div class="text-[14px] font-semibold text-[var(--navy)] mb-1">No conversations yet</div>
              <p class="text-[12.5px] text-[var(--muted)]">Send a message to start a conversation.</p>
              <button class="btn btn-primary btn-sm mt-4" @click="helpTab='compose'">
                <LucideIcon name="plus" :size="14" /> New Message
              </button>
            </div>
            <div v-else>
              <div class="flex flex-col divide-y divide-[var(--line-soft)]">
                <div v-for="t in threads" :key="t.id"
                  class="px-5 py-4 cursor-pointer hover:bg-[#FAFCFF] transition-colors"
                  @click="openThread(t)">
                  <div class="flex items-start justify-between gap-2 mb-1">
                    <div class="text-[13.5px] font-semibold text-[var(--navy)] truncate">
                      {{ t.subject || 'Library Enquiry' }}
                    </div>
                    <span :class="['badge flex-shrink-0', t.status==='open' ? 'b-green' : 'b-gray']">{{ t.status }}</span>
                  </div>
                  <div class="text-[12px] text-[var(--muted)] truncate mb-1.5">
                    {{ t.latest_message?.body ?? 'No messages yet' }}
                  </div>
                  <div class="flex items-center justify-between text-[11px]">
                    <span class="text-[var(--faint)]">{{ t.query_type ?? 'reference' }}</span>
                    <div class="flex items-center gap-2">
                      <span v-if="t.status==='open' && (t.last_sender_role==='staff' || t.last_sender_role==='admin')"
                        class="text-[var(--blue)] font-semibold flex items-center gap-1">
                        <LucideIcon name="reply" :size="11" /> Staff replied
                      </span>
                      <span class="text-[var(--faint)]">{{ formatDate(t.last_message_at) }}</span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="p-4 border-t border-[var(--line)]">
                <button class="btn btn-ghost btn-sm btn-block" @click="helpTab='compose'">
                  <LucideIcon name="plus" :size="14" /> New Message
                </button>
              </div>
            </div>
          </div>

          <!-- ── Threads tab: active thread ── -->
          <div v-else-if="helpTab==='threads' && activeThread" class="flex flex-col">
            <!-- Thread header -->
            <div class="px-5 py-3.5 border-b border-[var(--line)] flex items-center gap-2.5">
              <button class="w-8 h-8 flex items-center justify-center rounded-md border border-[var(--line)] text-[var(--muted)] hover:bg-[var(--bg)] flex-shrink-0"
                @click="activeThread=null">
                <LucideIcon name="arrow-left" :size="15" />
              </button>
              <div class="flex-1 min-w-0">
                <div class="text-[13px] font-semibold text-[var(--navy)] truncate">{{ activeThread.subject || 'Library Enquiry' }}</div>
                <div class="text-[11px] text-[var(--faint)]">{{ activeThread.query_type }}</div>
              </div>
              <span :class="['badge flex-shrink-0', activeThread.status==='open' ? 'b-green' : 'b-gray']">{{ activeThread.status }}</span>
            </div>

            <!-- Messages -->
            <div class="p-4 max-h-[320px] overflow-y-auto flex flex-col gap-3 bg-[var(--bg)]">
              <div v-if="messagesLoading" class="flex flex-col gap-3">
                <div v-for="i in 3" :key="i" class="h-[56px] bg-white rounded-[10px] animate-pulse"></div>
              </div>
              <template v-else>
                <div v-for="msg in messages" :key="msg.id"
                  :class="['flex', msg.sender?.role === 'user' || msg.sender_id === auth.user?.id ? 'justify-end' : 'justify-start']">
                  <div :class="['max-w-[85%] rounded-[12px] px-3.5 py-2.5 text-[13px] leading-[1.55]',
                    msg.sender?.role === 'user' || msg.sender_id === auth.user?.id
                      ? 'bg-[var(--blue)] text-white'
                      : 'bg-white border border-[var(--line)] text-[var(--ink)]']">
                    <div :class="['text-[10.5px] font-semibold mb-0.5', msg.sender?.role === 'user' || msg.sender_id === auth.user?.id ? 'text-white/70' : 'text-[var(--blue)]']">
                      {{ msg.sender_name || msg.sender?.name || 'Unknown' }}
                    </div>
                    <div class="whitespace-pre-wrap">{{ msg.body }}</div>
                    <div :class="['text-[10px] mt-1 text-right', msg.sender?.role === 'user' || msg.sender_id === auth.user?.id ? 'text-white/50' : 'text-[var(--faint)]']">
                      {{ formatDate(msg.created_at) }} {{ formatTime(msg.created_at) }}
                    </div>
                  </div>
                </div>
              </template>
            </div>

            <!-- Reply box -->
            <div v-if="activeThread.status === 'open'" class="p-4 border-t border-[var(--line)]">
              <div class="flex gap-2">
                <textarea v-model="replyBody" rows="2"
                  class="flex-1 border-[1.5px] border-[var(--line)] rounded-[10px] px-3 py-2 text-[13px] resize-none outline-none focus:border-[var(--blue)] leading-relaxed"
                  placeholder="Type your reply…"></textarea>
                <button @click="sendReply" :disabled="replySending || !replyBody.trim()"
                  class="self-end w-10 h-10 flex items-center justify-center rounded-[10px] bg-[var(--blue)] text-white disabled:opacity-40 hover:bg-[var(--blue-700)] transition-colors flex-shrink-0">
                  <LucideIcon :name="replySending ? 'refresh-cw' : 'send'" :size="16" />
                </button>
              </div>
            </div>
            <div v-else class="p-4 text-center text-[12px] text-[var(--faint)] border-t border-[var(--line)]">
              This conversation is closed.
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.help-grid {
  grid-template-columns: 1fr;
}
@media (min-width: 1024px) {
  .help-grid { grid-template-columns: 1fr 380px; }
}
</style>
