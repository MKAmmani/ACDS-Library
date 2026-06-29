<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiDelete, apiUpload } from '@/api/http'

const auth     = useAuthStore()
const BASE_URL = import.meta.env.VITE_API_URL as string

// ── Types ─────────────────────────────────────────────────────────────────────
interface RepoItem {
  id: number
  title: string
  authors: string
  publisher: string | null
  year: number | null
  isbn: string | null
  edition: string | null
  description: string | null
  call_number: string | null
  shelf_location: string | null
  subject_area: string
  language: string | null
  format: string | null
  file_path: string | null
  file_size: number | null
  file_type: string | null
  cover_image: string | null
  cover_image_url: string | null
  has_file: boolean
}

interface RepoPage {
  data: RepoItem[]
  current_page: number
  last_page: number
  total: number
}

// ── Stats ─────────────────────────────────────────────────────────────────────
interface RepoStats { total: number; storage_bytes: number; downloads_30d: number }

const storageLabel  = ref('—')
const downloads30d  = ref('—')

function fmtStorage(bytes: number): string {
  if (bytes < 1024)       return bytes + ' B'
  if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB'
  if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB'
  return (bytes / 1073741824).toFixed(2) + ' GB'
}

async function fetchStats() {
  try {
    const s = await apiGet<RepoStats>('/repository/stats')
    storageLabel.value = fmtStorage(s.storage_bytes)
    downloads30d.value = s.downloads_30d.toLocaleString()
  } catch { /* non-blocking */ }
}

// ── List state ────────────────────────────────────────────────────────────────
const docs        = ref<RepoItem[]>([])
const total       = ref(0)
const currentPage = ref(1)
const lastPage    = ref(1)
const loading     = ref(false)
const fetchError  = ref('')

// ── Filters ───────────────────────────────────────────────────────────────────
const searchQuery  = ref('')
const activeFilter = ref('all')

const filterTabs = [
  { key: 'all',     label: 'All'     },
  { key: 'pdf',     label: 'PDF'     },
  { key: 'epub',    label: 'eBooks'  },
  { key: 'journal', label: 'Journals'},
]

// ── Drawer ────────────────────────────────────────────────────────────────────
const drawerOpen = ref(false)
const editingDoc = ref<RepoItem | null>(null)
const saving     = ref(false)
const saveError  = ref('')

// ── File state ────────────────────────────────────────────────────────────────
const coverFile       = ref<File | null>(null)
const coverPreviewUrl = ref('')
const docFile         = ref<File | null>(null)
const coverInput      = ref<HTMLInputElement>()
const fileInput       = ref<HTMLInputElement>()

// ── Modals ────────────────────────────────────────────────────────────────────
const showSavedModal  = ref(false)
const showDeleteModal = ref(false)
const deletingDoc     = ref<RepoItem | null>(null)
const deleting        = ref(false)

// ── Form ──────────────────────────────────────────────────────────────────────
function emptyForm() {
  return {
    title: '', authors: '', publisher: '', year: '',
    isbn: '', edition: '', description: '',
    subject_area: '', call_number: '', shelf_location: '',
    language: 'English', format: 'PDF Document',
  }
}

const form = reactive(emptyForm())

function openAdd() {
  editingDoc.value      = null
  coverFile.value       = null
  docFile.value         = null
  coverPreviewUrl.value = ''
  saveError.value       = ''
  Object.assign(form, emptyForm())
  drawerOpen.value = true
}

function openEdit(doc: RepoItem) {
  editingDoc.value = doc
  Object.assign(form, {
    title:          doc.title,
    authors:        doc.authors,
    publisher:      doc.publisher ?? '',
    year:           doc.year?.toString() ?? '',
    isbn:           doc.isbn ?? '',
    edition:        doc.edition ?? '',
    description:    doc.description ?? '',
    subject_area:   doc.subject_area,
    call_number:    doc.call_number ?? '',
    shelf_location: doc.shelf_location ?? '',
    language:       doc.language ?? 'English',
    format:         doc.format ?? 'PDF Document',
  })
  coverFile.value       = null
  docFile.value         = null
  coverPreviewUrl.value = doc.cover_image_url ?? ''
  saveError.value       = ''
  drawerOpen.value      = true
}

// ── File pickers ──────────────────────────────────────────────────────────────
function handleCoverInput(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  coverFile.value       = file
  coverPreviewUrl.value = URL.createObjectURL(file)
}

function handleDocInput(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) docFile.value = file
}

// ── API ───────────────────────────────────────────────────────────────────────
async function fetchDocs() {
  loading.value    = true
  fetchError.value = ''
  try {
    const params = new URLSearchParams({ page: currentPage.value.toString() })
    if (searchQuery.value)             params.set('search',    searchQuery.value)
    if (activeFilter.value === 'pdf')  params.set('file_type', 'pdf')
    if (activeFilter.value === 'epub') params.set('file_type', 'epub')
    if (activeFilter.value === 'journal') params.set('format', 'Journal')

    const res     = await apiGet<RepoPage>(`/repository?${params}`)
    docs.value        = res.data
    total.value       = res.total
    currentPage.value = res.current_page
    lastPage.value    = res.last_page
  } catch (e: any) {
    fetchError.value = e.message
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value    = true
  saveError.value = ''
  try {
    const fd = new FormData()
    Object.entries(form).forEach(([k, v]) => {
      if (v !== '' && v !== null && v !== undefined) fd.append(k, String(v))
    })
    if (coverFile.value) fd.append('cover_image', coverFile.value)
    if (docFile.value)   fd.append('file',        docFile.value)

    const path = editingDoc.value
      ? `/admin/repository/${editingDoc.value.id}`
      : '/admin/repository'

    await apiUpload<RepoItem>(path, fd, auth.token!)
    drawerOpen.value     = false
    showSavedModal.value = true
    await fetchDocs()
  } catch (e: any) {
    saveError.value = e.message
  } finally {
    saving.value = false
  }
}

function promptDelete(doc: RepoItem) {
  deletingDoc.value     = doc
  showDeleteModal.value = true
}

async function confirmDelete() {
  if (!deletingDoc.value) return
  deleting.value = true
  try {
    await apiDelete(`/admin/repository/${deletingDoc.value.id}`, auth.token!)
    showDeleteModal.value = false
    deletingDoc.value     = null
    if (docs.value.length === 1 && currentPage.value > 1) currentPage.value--
    await fetchDocs()
  } catch {
    showDeleteModal.value = false
  } finally {
    deleting.value = false
  }
}

function openDoc(doc: RepoItem) {
  window.open(`${BASE_URL}/repository/${doc.id}/read`, '_blank')
}

async function downloadDoc(doc: RepoItem) {
  try {
    const res  = await fetch(`${BASE_URL}/repository/${doc.id}/download`, {
      headers: { Authorization: `Bearer ${auth.token!}`, Accept: 'application/json' },
    })
    const blob = await res.blob()
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    a.href     = url
    a.download = doc.title + '.' + (doc.file_type ?? 'bin')
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    console.error('Download failed', e)
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function docKind(doc: RepoItem): string {
  const t = (doc.file_type ?? '').toLowerCase()
  if (t === 'pdf') return 'pdf'
  if (t === 'epub') return 'epub'
  if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(t)) return 'img'
  if (['doc', 'docx', 'txt', 'rtf'].includes(t)) return 'doc'
  return 'file'
}

function docIcon(doc: RepoItem): string {
  const map: Record<string, string> = {
    pdf: 'file-text', epub: 'book-open', img: 'image', doc: 'file-text', file: 'file',
  }
  return map[docKind(doc)] || 'file'
}

function fmtSize(bytes: number | null): string {
  if (!bytes) return ''
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}

// ── Watchers ──────────────────────────────────────────────────────────────────
let debounce: ReturnType<typeof setTimeout>
watch([searchQuery, activeFilter], () => {
  currentPage.value = 1
  clearTimeout(debounce)
  debounce = setTimeout(fetchDocs, 320)
})

watch(currentPage, fetchDocs)

onMounted(() => { fetchDocs(); fetchStats() })
</script>

<template>
  <!-- Header -->
  <div class="shead">
    <div>
      <h2>Digital Repository</h2>
      <p>Upload and store eBooks, PDFs, journals &amp; documents — members download these online</p>
    </div>
    <div class="shead-actions">
      <button class="btn btn-ghost" @click="openAdd">
        <LucideIcon name="upload" class="ic-sm" /> Upload Files
      </button>
    </div>
  </div>

  <!-- KPIs -->
  <div class="kpis" style="grid-template-columns:repeat(3,1fr)">
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n">{{ total.toLocaleString() }}</div>
        <div class="kpi-ic t-blue"><LucideIcon name="layers" /></div>
      </div>
      <div class="kpi-l">Documents Stored</div>
    </div>
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n">{{ storageLabel }}</div>
        <div class="kpi-ic t-slate"><LucideIcon name="package" /></div>
      </div>
      <div class="kpi-l">Storage Used</div>
    </div>
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n">{{ downloads30d }}</div>
        <div class="kpi-ic t-green"><LucideIcon name="download" /></div>
      </div>
      <div class="kpi-l">Member Downloads (30d)</div>
    </div>
  </div>

  <!-- Fetch error -->
  <div v-if="fetchError" style="color:var(--red);font-size:13px;margin-bottom:12px;display:flex;align-items:center;gap:6px">
    <LucideIcon name="alert-triangle" class="ic-sm" /> {{ fetchError }}
  </div>

  <!-- Toolbar -->
  <div class="toolbar" style="margin-bottom:16px">
    <div class="search-in">
      <LucideIcon name="search" class="ic-sm" />
      <input v-model="searchQuery" placeholder="Search documents…" />
    </div>
    <div class="fbtns">
      <div
        v-for="tab in filterTabs" :key="tab.key"
        class="fbtn"
        :class="{ on: activeFilter === tab.key }"
        @click="activeFilter = tab.key"
      >{{ tab.label }}</div>
    </div>
  </div>

  <!-- Document grid -->
  <div class="repo-grid">
    <!-- Skeleton -->
    <template v-if="loading">
      <div v-for="n in 8" :key="'sk'+n" class="doc" style="opacity:.5">
        <div class="doc-head" style="animation:pulse 1.4s infinite"></div>
        <div class="doc-body">
          <div style="height:12px;background:var(--line-soft);border-radius:4px;margin-bottom:6px;animation:pulse 1.4s infinite"></div>
          <div style="height:10px;background:var(--line-soft);border-radius:4px;width:60%;animation:pulse 1.4s infinite"></div>
        </div>
      </div>
    </template>

    <!-- Empty -->
    <div v-else-if="!docs.length" class="repo-empty">
      <LucideIcon name="layers" style="width:32px;height:32px;margin-bottom:8px;display:block;margin-inline:auto" />
      No documents yet — click <b>Upload Files</b> to add the first one.
    </div>

    <!-- Cards -->
    <div v-else v-for="doc in docs" :key="doc.id" class="doc">
      <div
        class="doc-head"
        :class="doc.cover_image_url ? 'img' : docKind(doc)"
        :style="doc.cover_image_url ? `background-image:url(${doc.cover_image_url});background-size:cover;background-position:center` : ''"
      >
        <span class="doc-type">{{ (doc.file_type ?? 'file').toUpperCase() }}</span>
        <LucideIcon v-if="!doc.cover_image_url" :name="docIcon(doc)" />
        <div
          title="Edit"
          style="position:absolute;top:8px;right:8px;width:26px;height:26px;border-radius:6px;background:rgba(255,255,255,.88);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--muted);transition:all .14s"
          @click.stop="openEdit(doc)"
        >
          <LucideIcon name="pencil" style="width:13px;height:13px" />
        </div>
      </div>
      <div class="doc-body">
        <div class="doc-name">{{ doc.title }}</div>
        <div class="doc-meta">
          {{ doc.authors }}
          <template v-if="fmtSize(doc.file_size)"> · {{ fmtSize(doc.file_size) }}</template>
          <template v-if="doc.year"> · {{ doc.year }}</template>
        </div>
      </div>
      <div class="doc-foot">
        <div class="doc-act" @click="openDoc(doc)"><LucideIcon name="eye" /> Open</div>
        <div class="doc-act" @click="downloadDoc(doc)"><LucideIcon name="download" /> Download</div>
        <div class="doc-act del" @click="promptDelete(doc)"><LucideIcon name="trash-2" /></div>
      </div>
    </div>
  </div>

  <!-- Pagination -->
  <div v-if="lastPage > 1" style="display:flex;align-items:center;justify-content:space-between;padding:16px 0">
    <span style="font-size:12px;color:var(--muted)">Page {{ currentPage }} of {{ lastPage }}</span>
    <div style="display:flex;gap:6px">
      <button class="btn btn-ghost btn-sm" :disabled="currentPage === 1" @click="currentPage--">
        <LucideIcon name="chevron-left" class="ic-sm" /> Prev
      </button>
      <button class="btn btn-ghost btn-sm" :disabled="currentPage === lastPage" @click="currentPage++">
        Next <LucideIcon name="chevron-right" class="ic-sm" />
      </button>
    </div>
  </div>

  <!-- ── Drawer scrim ── -->
  <div :class="['scrim', { open: drawerOpen }]" @click="drawerOpen = false"></div>

  <!-- ── Add / Edit Drawer ── -->
  <div :class="['drawer', { open: drawerOpen }]">
    <div class="dh">
      <div>
        <div class="dh-t">{{ editingDoc ? 'Edit Document' : 'Upload Document' }}</div>
        <div class="dh-s">{{ editingDoc ? 'Update repository record' : 'Add a new document to the repository' }}</div>
      </div>
      <div class="dh-x" @click="drawerOpen = false"><LucideIcon name="x" /></div>
    </div>

    <div class="db">

      <!-- Document Information -->
      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="file-text" /> Document Information</div>
        <div class="form-grid">
          <div class="fg col2">
            <label class="fl">Title <span class="req">*</span></label>
            <input class="fi" v-model="form.title" placeholder="Full document title" />
          </div>
          <div class="fg col2">
            <label class="fl">Author(s) <span class="req">*</span></label>
            <input class="fi" v-model="form.authors" placeholder="Surname, First; Surname, First…" />
          </div>
          <div class="fg">
            <label class="fl">Publisher</label>
            <input class="fi" v-model="form.publisher" placeholder="e.g. University Press" />
          </div>
          <div class="fg">
            <label class="fl">Year</label>
            <input class="fi" v-model="form.year" type="number" placeholder="2024" min="1000" :max="new Date().getFullYear()" />
          </div>
          <div class="fg">
            <label class="fl">ISBN / ISSN</label>
            <input class="fi" v-model="form.isbn" placeholder="978-…" />
          </div>
          <div class="fg">
            <label class="fl">Edition</label>
            <input class="fi" v-model="form.edition" placeholder="1st / 2nd…" />
          </div>
          <div class="fg col2">
            <label class="fl">Abstract / Description</label>
            <textarea class="ft" v-model="form.description" placeholder="Short description or abstract…"></textarea>
          </div>
        </div>
      </div>

      <!-- Classification -->
      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="map-pin" /> Classification</div>
        <div class="form-grid">
          <div class="fg">
            <label class="fl">Subject Area <span class="req">*</span></label>
            <input class="fi" v-model="form.subject_area" placeholder="e.g. Democracy &amp; Governance" />
          </div>
          <div class="fg">
            <label class="fl">Call Number</label>
            <input class="fi" v-model="form.call_number" placeholder="JZ1320.D46" />
          </div>
          <div class="fg">
            <label class="fl">Shelf / Location</label>
            <input class="fi" v-model="form.shelf_location" placeholder="e.g. Digital Archive A" />
          </div>
          <div class="fg">
            <label class="fl">Language</label>
            <select class="fs" v-model="form.language">
              <option>English</option><option>Hausa</option><option>Arabic</option>
              <option>French</option><option>Yoruba</option><option>Igbo</option>
            </select>
          </div>
          <div class="fg col2">
            <label class="fl">Document Format</label>
            <select class="fs" v-model="form.format">
              <option>PDF Document</option><option>eBook</option><option>Journal</option>
              <option>Thesis / Dissertation</option><option>Research Report</option>
              <option>Working Paper</option><option>Conference Paper</option>
              <option>Government Document</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Files -->
      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="upload" /> Files</div>

        <!-- Cover Image -->
        <label class="fl" style="margin-bottom:8px">
          Cover Image
          <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--faint)"> — optional · JPEG, PNG, WebP · max 2 MB</span>
        </label>
        <div class="up-row">
          <div
            v-if="coverPreviewUrl"
            class="up-preview show"
            :style="`background-image:url(${coverPreviewUrl})`"
          ></div>
          <label class="up-btn">
            <LucideIcon name="image" />
            {{ coverPreviewUrl ? 'Change cover' : 'Upload cover image' }}
            <input
              ref="coverInput" type="file"
              accept="image/jpeg,image/jpg,image/png,image/webp"
              style="display:none"
              @change="handleCoverInput"
            />
          </label>
          <span v-if="coverFile" style="font-size:11.5px;color:var(--green-600);font-weight:600">
            {{ coverFile.name }}
          </span>
        </div>

        <!-- Document File -->
        <label class="fl" style="margin-top:18px;margin-bottom:8px">
          Document File <span class="req" v-if="!editingDoc">*</span>
          <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--faint)"> — PDF, EPUB, DOCX, TXT · max 1 GB</span>
        </label>
        <div class="up-row">
          <label class="up-btn">
            <LucideIcon name="upload" />
            {{ docFile ? 'Change file' : editingDoc ? 'Replace file' : 'Choose document file' }}
            <input
              ref="fileInput" type="file"
              accept=".pdf,.epub,.doc,.docx,.txt,image/*"
              style="display:none"
              @change="handleDocInput"
            />
          </label>
          <span v-if="docFile" class="up-file show">
            <LucideIcon name="circle-check" /> {{ docFile.name }} ({{ fmtSize(docFile.size) }})
          </span>
          <span v-else-if="editingDoc?.has_file" style="font-size:11.5px;color:var(--muted)">
            Current: {{ editingDoc.title }}.{{ editingDoc.file_type }}
            <template v-if="fmtSize(editingDoc.file_size)"> ({{ fmtSize(editingDoc.file_size) }})</template>
          </span>
        </div>
      </div>

      <!-- Save error -->
      <div v-if="saveError" style="color:var(--red);font-size:13px;margin-top:4px;display:flex;align-items:center;gap:6px">
        <LucideIcon name="alert-triangle" class="ic-sm" /> {{ saveError }}
      </div>
    </div>

    <div class="df">
      <button
        class="btn btn-primary" style="flex:1;justify-content:center"
        :disabled="saving" @click="save"
      >
        <LucideIcon :name="saving ? 'refresh-cw' : 'save'" class="ic-sm" />
        {{ saving ? 'Saving…' : editingDoc ? 'Save Changes' : 'Upload to Repository' }}
      </button>
      <button class="btn btn-ghost" @click="drawerOpen = false">Cancel</button>
    </div>
  </div>

  <!-- ── Modal: Saved ── -->
  <div :class="['mscrim', { open: showSavedModal }]" @click.self="showSavedModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--blue-50)">
          <LucideIcon name="book-check" style="width:28px;height:28px;color:var(--blue)" />
        </div>
        <div class="modal-t">{{ editingDoc ? 'Document Updated' : 'Document Uploaded' }}</div>
        <div class="modal-s">
          {{ editingDoc
              ? 'The repository record has been updated successfully.'
              : 'The document has been added to the digital repository.' }}
        </div>
        <div class="modal-f">
          <button class="btn btn-primary btn-block" @click="showSavedModal = false; editingDoc = null">Done</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Modal: Delete ── -->
  <div :class="['mscrim', { open: showDeleteModal }]" @click.self="showDeleteModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--red-50)">
          <LucideIcon name="trash-2" style="width:28px;height:28px;color:var(--red)" />
        </div>
        <div class="modal-t">Delete Document?</div>
        <div class="modal-s">
          <b>{{ deletingDoc?.title }}</b> and its stored file will be permanently deleted. This cannot be undone.
        </div>
        <div class="modal-f">
          <button class="btn btn-ghost" style="flex:1;justify-content:center" @click="showDeleteModal = false">Cancel</button>
          <button
            class="btn btn-danger" style="flex:1;justify-content:center;background:var(--red);color:#fff;border:none"
            :disabled="deleting" @click="confirmDelete"
          >
            {{ deleting ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes pulse {
  0%, 100% { opacity: 1 }
  50%       { opacity: .4 }
}
</style>
