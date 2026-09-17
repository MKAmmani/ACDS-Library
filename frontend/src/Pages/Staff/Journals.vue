<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiDelete, apiUpload } from '@/api/http'
import { renderAsync } from 'docx-preview'

const auth     = useAuthStore()
const BASE_URL = import.meta.env.VITE_API_URL as string

// ── Types ─────────────────────────────────────────────────────────────────────
interface JournalArticleRow {
  id?: number
  title: string
  authors: string
  page_range: string
}

interface JournalItem {
  id: number
  title: string
  publisher_authors: string | null
  issn: string | null
  year: number | null
  call_number: string | null
  subject: string | null
  shelf_location: string | null
  file_path: string | null
  file_size: number | null
  file_type: string | null
  cover_image: string | null
  cover_image_url: string | null
  has_file: boolean
  articles: JournalArticleRow[]
}

interface JournalPage {
  data: JournalItem[]
  current_page: number
  last_page: number
  total: number
}

// ── Stats ─────────────────────────────────────────────────────────────────────
interface JournalStats { total: number; storage_bytes: number; downloads_30d: number }

const storageLabel = ref('—')
const downloads30d = ref('—')

function fmtStorage(bytes: number): string {
  if (bytes < 1024)       return bytes + ' B'
  if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB'
  if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB'
  return (bytes / 1073741824).toFixed(2) + ' GB'
}

async function fetchStats() {
  try {
    const s = await apiGet<JournalStats>('/journals/stats')
    storageLabel.value = fmtStorage(s.storage_bytes)
    downloads30d.value = s.downloads_30d.toLocaleString()
  } catch { /* non-blocking */ }
}

// ── List state ────────────────────────────────────────────────────────────────
const docs        = ref<JournalItem[]>([])
const total       = ref(0)
const currentPage = ref(1)
const lastPage    = ref(1)
const loading     = ref(false)
const fetchError  = ref('')

// ── Filters ───────────────────────────────────────────────────────────────────
const searchQuery = ref('')

// ── Drawer ────────────────────────────────────────────────────────────────────
const drawerOpen = ref(false)
const editingDoc = ref<JournalItem | null>(null)
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
const deletingDoc     = ref<JournalItem | null>(null)
const deleting        = ref(false)
const showReader      = ref(false)
const readerUrl       = ref('')
const readerTitle     = ref('')
const readerMode      = ref<'pdf' | 'docx' | 'unsupported'>('pdf')
const readerLoading   = ref(false)
const readerError     = ref('')
const docxHost        = ref<HTMLElement | null>(null)
const opening         = ref<number | null>(null)
const readerDoc       = ref<JournalItem | null>(null)
const readerBlobUrl   = ref('')

// ── Form ──────────────────────────────────────────────────────────────────────
function emptyForm() {
  return {
    title: '', publisher_authors: '', issn: '', year: '',
    call_number: '', subject: '', shelf_location: '',
  }
}

const form     = reactive(emptyForm())
const articles = ref<JournalArticleRow[]>([])

function addArticle() {
  articles.value.push({ title: '', authors: '', page_range: '' })
}

function removeArticle(idx: number) {
  articles.value.splice(idx, 1)
}

function openAdd() {
  editingDoc.value      = null
  coverFile.value       = null
  docFile.value         = null
  coverPreviewUrl.value = ''
  saveError.value       = ''
  Object.assign(form, emptyForm())
  articles.value   = []
  drawerOpen.value = true
}

function openEdit(doc: JournalItem) {
  editingDoc.value = doc
  Object.assign(form, {
    title:             doc.title,
    publisher_authors: doc.publisher_authors ?? '',
    issn:              doc.issn ?? '',
    year:              doc.year?.toString() ?? '',
    call_number:       doc.call_number ?? '',
    subject:           doc.subject ?? '',
    shelf_location:    doc.shelf_location ?? '',
  })
  articles.value = (doc.articles ?? []).map(a => ({
    id: a.id, title: a.title, authors: a.authors ?? '', page_range: a.page_range ?? '',
  }))
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
    if (searchQuery.value) params.set('search', searchQuery.value)

    const res     = await apiGet<JournalPage>(`/journals?${params}`)
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
  if (!form.title.trim()) {
    saveError.value = 'Title is required.'
    return
  }
  if (!editingDoc.value && !docFile.value) {
    saveError.value = 'A document file is required.'
    return
  }
  saving.value    = true
  saveError.value = ''
  try {
    const fd = new FormData()
    Object.entries(form).forEach(([k, v]) => {
      if (v !== '' && v !== null && v !== undefined) fd.append(k, String(v))
    })
    if (coverFile.value) fd.append('cover_image', coverFile.value)
    if (docFile.value)   fd.append('file',        docFile.value)

    const cleanArticles = articles.value
      .filter(a => a.title.trim() !== '')
      .map(a => ({ title: a.title.trim(), authors: a.authors.trim(), page_range: a.page_range.trim() }))
    fd.append('articles', JSON.stringify(cleanArticles))

    const path = editingDoc.value
      ? `/admin/journals/${editingDoc.value.id}`
      : '/admin/journals'

    await apiUpload<JournalItem>(path, fd, auth.token!)
    drawerOpen.value     = false
    showSavedModal.value = true
    await fetchDocs()
    await fetchStats()
  } catch (e: any) {
    saveError.value = e.message
  } finally {
    saving.value = false
  }
}

function promptDelete(doc: JournalItem) {
  deletingDoc.value     = doc
  showDeleteModal.value = true
}

async function confirmDelete() {
  if (!deletingDoc.value) return
  deleting.value = true
  try {
    await apiDelete(`/admin/journals/${deletingDoc.value.id}`, auth.token!)
    showDeleteModal.value = false
    deletingDoc.value     = null
    if (docs.value.length === 1 && currentPage.value > 1) currentPage.value--
    await fetchDocs()
    await fetchStats()
  } catch {
    showDeleteModal.value = false
  } finally {
    deleting.value = false
  }
}

async function openDoc(doc: JournalItem) {
  const ext  = (doc.file_type ?? '').toLowerCase()
  const kind = docKind(doc)
  readerDoc.value   = doc
  readerTitle.value = doc.title
  readerError.value = ''
  revokeReaderBlob()
  readerUrl.value = ''

  const isDocx = ext === 'docx'
  const previewable = kind === 'pdf' || kind === 'img' || isDocx
  if (!previewable) {
    // Legacy .doc, .epub, etc. — no in-browser viewer.
    readerMode.value = 'unsupported'
    showReader.value = true
    return
  }

  readerMode.value    = isDocx ? 'docx' : 'pdf'
  readerLoading.value = true
  opening.value       = doc.id
  showReader.value    = true

  try {
    // Fetch the file as a base64 JSON envelope (application/json) so download
    // managers like IDM don't intercept it, then decode to a blob locally.
    const res = await fetch(`${BASE_URL}/journals/${doc.id}/inline`, {
      headers: { Authorization: `Bearer ${auth.token!}` },
    })
    if (!res.ok) throw new Error('Could not load the document.')
    const json = await res.json()
    const blob = await (await fetch(`data:${json.mime};base64,${json.data}`)).blob()

    if (isDocx) {
      const buffer = await blob.arrayBuffer()
      await nextTick()
      if (docxHost.value) {
        docxHost.value.innerHTML = ''
        await renderAsync(buffer, docxHost.value, undefined, {
          className: 'docx',
          inWrapper: true,
          ignoreWidth: false,
        })
      }
    } else {
      readerBlobUrl.value = URL.createObjectURL(blob)
      readerUrl.value     = readerBlobUrl.value
    }
  } catch (e: any) {
    readerError.value = e.message ?? 'Could not display the document.'
  } finally {
    readerLoading.value = false
    opening.value = null
  }
}

function revokeReaderBlob() {
  if (readerBlobUrl.value) {
    URL.revokeObjectURL(readerBlobUrl.value)
    readerBlobUrl.value = ''
  }
}

function closeReader() {
  showReader.value  = false
  readerUrl.value   = ''
  readerError.value = ''
  revokeReaderBlob()
  if (docxHost.value) docxHost.value.innerHTML = ''
}

async function downloadDoc(doc: JournalItem) {
  try {
    const res  = await fetch(`${BASE_URL}/journals/${doc.id}/download`, {
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
function docKind(doc: JournalItem): string {
  const t = (doc.file_type ?? '').toLowerCase()
  if (t === 'pdf') return 'pdf'
  if (t === 'epub') return 'epub'
  if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(t)) return 'img'
  if (['doc', 'docx', 'txt', 'rtf'].includes(t)) return 'doc'
  return 'file'
}

function docIcon(doc: JournalItem): string {
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
watch(searchQuery, () => {
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
      <h2>Journals & Thesis</h2>
      <p>Upload academic journal issues and index every article they contain</p>
    </div>
    <div class="shead-actions">
      <button class="btn btn-ghost" @click="openAdd">
        <LucideIcon name="upload" class="ic-sm" /> Upload Journal
      </button>
    </div>
  </div>

  <!-- KPIs -->
  <div class="kpis" style="grid-template-columns:repeat(3,1fr)">
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n">{{ total.toLocaleString() }}</div>
        <div class="kpi-ic t-blue"><LucideIcon name="newspaper" /></div>
      </div>
      <div class="kpi-l">Journals Stored</div>
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
      <input v-model="searchQuery" placeholder="Search journals by title, publisher, ISSN, subject…" />
    </div>
  </div>

  <!-- Journal grid -->
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
      <LucideIcon name="newspaper" style="width:32px;height:32px;margin-bottom:8px;display:block;margin-inline:auto" />
      No journals yet — click <b>Upload Journal</b> to add the first issue.
    </div>

    <!-- Cards -->
    <div v-else v-for="doc in docs" :key="doc.id" class="doc">
      <div
        class="doc-head"
        :class="doc.cover_image_url ? 'img' : docKind(doc)"
        :style="doc.cover_image_url ? `background-image:url(${doc.cover_image_url});background-size:cover;background-position:center` : ''"
      >
        <span class="doc-type" :class="{ 'doc-type-missing': !doc.has_file }">{{ doc.has_file ? (doc.file_type ?? 'file').toUpperCase() : 'NO FILE' }}</span>
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
          {{ doc.publisher_authors || 'Publisher pending' }}
          <template v-if="doc.year"> · {{ doc.year }}</template>
          <template v-if="fmtSize(doc.file_size)"> · {{ fmtSize(doc.file_size) }}</template>
        </div>
        <div v-if="doc.articles?.length" class="doc-meta" style="margin-top:3px">
          <LucideIcon name="list-ordered" style="width:11px;height:11px;vertical-align:-1px" />
          {{ doc.articles.length }} article{{ doc.articles.length === 1 ? '' : 's' }} indexed
        </div>
      </div>
      <div class="doc-foot">
        <div class="doc-act" :class="{ disabled: !doc.has_file }" :title="doc.has_file ? '' : 'No file uploaded yet — edit this record to upload one'" @click="doc.has_file && openDoc(doc)"><LucideIcon name="eye" /> Open</div>
        <div class="doc-act" :class="{ disabled: !doc.has_file }" :title="doc.has_file ? '' : 'No file uploaded yet — edit this record to upload one'" @click="doc.has_file && downloadDoc(doc)"><LucideIcon name="download" /> Download</div>
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
        <div class="dh-t">{{ editingDoc ? 'Edit Journal' : 'Upload Journal' }}</div>
        <div class="dh-s">{{ editingDoc ? 'Update journal record' : 'Add a new journal issue' }}</div>
      </div>
      <div class="dh-x" @click="drawerOpen = false"><LucideIcon name="x" /></div>
    </div>

    <div class="db">

      <!-- Bibliographic Information -->
      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="file-text" /> Bibliographic Information</div>
        <div class="form-grid">
          <div class="fg col2">
            <label class="fl">Title <span class="req">*</span></label>
            <input class="fi" v-model="form.title" placeholder="Full journal / issue title" />
          </div>
          <div class="fg col2">
            <label class="fl">Publisher / Author(s)</label>
            <input class="fi" v-model="form.publisher_authors" placeholder="e.g. University Press · Editorial Board" />
          </div>
          <div class="fg">
            <label class="fl">ISSN</label>
            <input class="fi" v-model="form.issn" placeholder="2049-3630" />
          </div>
          <div class="fg">
            <label class="fl">Year</label>
            <input class="fi" v-model="form.year" type="number" placeholder="2024" min="1000" :max="new Date().getFullYear()" />
          </div>
        </div>
      </div>

      <!-- Classification & Shelving -->
      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="map-pin" /> Classification &amp; Shelving</div>
        <div class="form-grid">
          <div class="fg">
            <label class="fl">Call Number</label>
            <input class="fi" v-model="form.call_number" placeholder="JZ1320.D46" />
          </div>
          <div class="fg">
            <label class="fl">Subject</label>
            <input class="fi" v-model="form.subject" placeholder="e.g. Democracy &amp; Governance" />
          </div>
          <div class="fg col2">
            <label class="fl">Shelf Location</label>
            <input class="fi" v-model="form.shelf_location" placeholder="e.g. Periodicals Rack B" />
          </div>
        </div>
      </div>

      <!-- Articles Index -->
      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="list-ordered" /> Articles Index</div>
        <div style="font-size:12px;color:var(--faint);margin:-6px 0 12px">
          List every article in this issue — title, contributing author(s), and the page range they occupy.
        </div>
        <div class="tbl-wrap" style="margin-bottom:10px">
          <table class="tbl">
            <thead>
              <tr>
                <th style="width:42%">Article Title</th>
                <th style="width:32%">Author(s)</th>
                <th style="width:16%">Page Range</th>
                <th style="width:10%"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!articles.length">
                <td colspan="4" style="text-align:center;color:var(--faint);padding:18px">No articles added yet.</td>
              </tr>
              <tr v-for="(a, idx) in articles" :key="idx">
                <td><input class="fi" v-model="a.title" placeholder="Article title" style="padding:7px 9px" /></td>
                <td><input class="fi" v-model="a.authors" placeholder="Author(s)" style="padding:7px 9px" /></td>
                <td><input class="fi" v-model="a.page_range" placeholder="e.g. 12–24" style="padding:7px 9px" /></td>
                <td>
                  <div class="ra del" title="Remove article" @click="removeArticle(idx)">
                    <LucideIcon name="x" style="width:13px;height:13px" />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" @click="addArticle">
          <LucideIcon name="plus" class="ic-sm" /> Add Article
        </button>
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
        {{ saving ? 'Saving…' : editingDoc ? 'Save Changes' : 'Upload Journal' }}
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
        <div class="modal-t">{{ editingDoc ? 'Journal Updated' : 'Journal Uploaded' }}</div>
        <div class="modal-s">
          {{ editingDoc
              ? 'The journal record has been updated successfully.'
              : 'The journal has been added and indexed.' }}
        </div>
        <div class="modal-f">
          <button class="btn btn-primary btn-block" @click="showSavedModal = false; editingDoc = null">Done</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Document Reader ── -->
  <Teleport to="body">
    <div v-if="showReader" style="position:fixed;inset:0;z-index:999;display:flex;flex-direction:column;background:rgba(0,0,0,.72)">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 18px;background:#fff;border-bottom:1px solid var(--line);flex-shrink:0">
        <div style="font-size:13.5px;font-weight:600;color:var(--navy);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ readerTitle }}</div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
          <a v-if="readerMode === 'pdf'" :href="readerUrl" target="_blank" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px">
            <LucideIcon name="external-link" class="ic-sm" /> Open in tab
          </a>
          <button v-if="readerDoc" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px" @click="downloadDoc(readerDoc)">
            <LucideIcon name="download" class="ic-sm" /> Download
          </button>
          <button class="btn btn-ghost btn-sm" @click="closeReader">
            <LucideIcon name="x" class="ic-sm" />
          </button>
        </div>
      </div>

      <!-- PDF / image: same-origin blob URL in an iframe -->
      <div v-if="readerMode === 'pdf'" style="flex:1;position:relative;background:#525659">
        <div v-if="readerLoading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;gap:10px;color:#fff;font-size:14px">
          <LucideIcon name="refresh-cw" style="width:18px;height:18px;animation:spin .8s linear infinite" /> Loading document…
        </div>
        <div v-else-if="readerError" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:#fff;font-size:14px;padding:24px;text-align:center">
          <LucideIcon name="alert-triangle" style="width:22px;height:22px" /> {{ readerError }}
        </div>
        <iframe v-if="readerUrl" :src="readerUrl" style="width:100%;height:100%;border:none" allow="fullscreen"></iframe>
      </div>

      <!-- DOCX: rendered to HTML -->
      <div v-else-if="readerMode === 'docx'" style="flex:1;overflow:auto;background:#f3f3f3;position:relative">
        <div v-if="readerLoading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;gap:10px;color:var(--muted);font-size:14px">
          <LucideIcon name="refresh-cw" style="width:18px;height:18px;animation:spin .8s linear infinite" /> Rendering document…
        </div>
        <div v-if="readerError" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:var(--red);font-size:14px;padding:24px;text-align:center">
          <LucideIcon name="alert-triangle" style="width:22px;height:22px" /> {{ readerError }}
        </div>
        <div ref="docxHost" style="padding:24px 0"></div>
      </div>

      <!-- Unsupported: download fallback -->
      <div v-else style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;background:#fff;padding:32px;text-align:center">
        <LucideIcon name="file-text" style="width:46px;height:46px;color:var(--faint)" />
        <div style="font-size:15px;font-weight:600;color:var(--navy)">This file type can't be previewed in the browser</div>
        <div style="font-size:13px;color:var(--muted);max-width:380px">Legacy Word (.doc) and EPUB files have no built-in browser viewer. Download the file to open it on your device.</div>
        <button v-if="readerDoc" class="btn btn-primary btn-sm" style="margin-top:4px" @click="downloadDoc(readerDoc)">
          <LucideIcon name="download" class="ic-sm" /> Download file
        </button>
      </div>
    </div>
  </Teleport>

  <!-- ── Modal: Delete ── -->
  <div :class="['mscrim', { open: showDeleteModal }]" @click.self="showDeleteModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--red-50)">
          <LucideIcon name="trash-2" style="width:28px;height:28px;color:var(--red)" />
        </div>
        <div class="modal-t">Delete Journal?</div>
        <div class="modal-s">
          <b>{{ deletingDoc?.title }}</b>, its stored file and its articles index will be permanently deleted. This cannot be undone.
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
.doc-type-missing { color: var(--red) }
.doc-act.disabled { color: var(--faint); cursor: not-allowed; opacity: .55 }
.doc-act.disabled:hover { background: none; color: var(--faint) }
</style>
