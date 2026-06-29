<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiPost, apiPatch, apiPut, apiDelete, apiUpload } from '@/api/http'

const auth = useAuthStore()

interface Book {
  id: number
  title: string
  authors: string
  publisher: string | null
  year: number | null
  isbn: string | null
  edition: string | null
  description: string | null
  subject_area: string
  call_number: string | null
  shelf_location: string | null
  language: string
  format: string | null
  material_type: string | null
  number_of_copies: number
  available_copies: number
  cover_treatment: string | null
}

interface BooksPage {
  data: Book[]
  current_page: number
  last_page: number
  total: number
}

// ── List state ──────────────────────────────────────────────────────────────
const books       = ref<Book[]>([])
const total       = ref(0)
const currentPage = ref(1)
const lastPage    = ref(1)
const loading     = ref(false)
const fetchError  = ref('')

// ── Filters ─────────────────────────────────────────────────────────────────
const searchQuery   = ref('')
const filterFormat  = ref('')
const filterSubject = ref('')

// ── Drawer ──────────────────────────────────────────────────────────────────
const drawerOpen  = ref(false)
const editingBook = ref<Book | null>(null)
const saving      = ref(false)
const saveError   = ref('')

// ── Modals ───────────────────────────────────────────────────────────────────
const showSavedModal  = ref(false)
const showDeleteModal = ref(false)
const deletingBook    = ref<Book | null>(null)
const deleting        = ref(false)

// ── Form ─────────────────────────────────────────────────────────────────────
const covers = ['cv-blue', 'cv-navy', 'cv-burgundy', 'cv-forest', 'cv-slate', 'cv-charcoal', 'cv-ochre']

function emptyForm() {
  return {
    title: '', authors: '', publisher: '', year: '',
    isbn: '', edition: '', description: '',
    subject_area: '', call_number: '', shelf_location: '',
    language: 'English', format: 'Book (Physical)',
    material_type: '', number_of_copies: 1, cover_treatment: 'cv-blue',
  }
}

const form = reactive(emptyForm())

function step(n: number) {
  form.number_of_copies = Math.max(1, form.number_of_copies + n)
}

function openAdd() {
  editingBook.value = null
  Object.assign(form, emptyForm())
  saveError.value = ''
  drawerOpen.value = true
}

function openEdit(book: Book) {
  editingBook.value = book
  Object.assign(form, {
    title:            book.title,
    authors:          book.authors,
    publisher:        book.publisher ?? '',
    year:             book.year?.toString() ?? '',
    isbn:             book.isbn ?? '',
    edition:          book.edition ?? '',
    description:      book.description ?? '',
    subject_area:     book.subject_area,
    call_number:      book.call_number ?? '',
    shelf_location:   book.shelf_location ?? '',
    language:         book.language,
    format:           book.format ?? 'Book (Physical)',
    material_type:    book.material_type ?? '',
    number_of_copies: book.number_of_copies,
    cover_treatment:  book.cover_treatment ?? 'cv-blue',
  })
  saveError.value = ''
  drawerOpen.value = true
}

// ── API calls ────────────────────────────────────────────────────────────────
async function fetchBooks() {
  loading.value = true
  fetchError.value = ''
  try {
    const params = new URLSearchParams({ page: currentPage.value.toString() })
    if (searchQuery.value)   params.set('search',       searchQuery.value)
    if (filterFormat.value)  params.set('format',       filterFormat.value)
    if (filterSubject.value) params.set('subject_area', filterSubject.value)

    const res = await apiGet<BooksPage>(`/books?${params}`)
    books.value       = res.data
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
    const payload = { ...form, year: form.year ? parseInt(form.year) : null }
    if (editingBook.value) {
      await apiPatch(`/admin/books/${editingBook.value.id}`, payload, auth.token!)
    } else {
      await apiPost(`/admin/books`, payload, auth.token!)
    }
    drawerOpen.value     = false
    showSavedModal.value = true
    await fetchBooks()
  } catch (e: any) {
    saveError.value = e.message
  } finally {
    saving.value = false
  }
}

function promptDelete(book: Book) {
  deletingBook.value    = book
  showDeleteModal.value = true
}

async function confirmDelete() {
  if (!deletingBook.value) return
  deleting.value = true
  try {
    await apiDelete(`/admin/books/${deletingBook.value.id}`, auth.token!)
    showDeleteModal.value = false
    deletingBook.value    = null
    if (books.value.length === 1 && currentPage.value > 1) currentPage.value--
    await fetchBooks()
  } catch {
    showDeleteModal.value = false
  } finally {
    deleting.value = false
  }
}

// ── MARC Import ──────────────────────────────────────────────────────────────
interface ImportResult { imported: number; skipped: number; total: number; errors: string[] }

const showImportModal = ref(false)
const marcInput       = ref<HTMLInputElement>()
const importDragging  = ref(false)
const importState     = ref<'idle' | 'uploading' | 'done' | 'error'>('idle')
const importResult    = ref<ImportResult | null>(null)
const importError     = ref('')
const importFile      = ref<File | null>(null)

function openImportModal() {
  importState.value  = 'idle'
  importResult.value = null
  importError.value  = ''
  importFile.value   = null
  showImportModal.value = true
}

function handleImportDrop(e: DragEvent) {
  e.preventDefault()
  importDragging.value = false
  const file = e.dataTransfer?.files[0]
  if (file) setImportFile(file)
}

function handleImportInput(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files?.[0]) setImportFile(input.files[0])
}

const ACCEPTED_IMPORT = ['mrc', 'marc', 'xlsx', 'xls', 'xlsm', 'ods', 'csv', 'accdb', 'mdb']

function setImportFile(file: File) {
  const ext = file.name.split('.').pop()?.toLowerCase()
  if (!ext || !ACCEPTED_IMPORT.includes(ext)) {
    importError.value = 'Unsupported file. Choose a MARC (.mrc, .marc), Excel (.xlsx, .xls, .csv), or Access (.accdb, .mdb) file.'
    return
  }
  importError.value = ''
  importFile.value  = file
}

function downloadTemplate() {
  const headers = ['Title', 'Authors', 'ISBN', 'Publisher', 'Year', 'Edition', 'Subject', 'Call Number', 'Shelf', 'Language', 'Copies', 'Format']
  const examples = [
    ['Things Fall Apart', 'Chinua Achebe', '9780385474542', 'Anchor Books', '1994', '1st', 'African Literature', 'PR9387.9 A3', 'A-12', 'English', '3', 'Book (Physical)'],
    ['Half of a Yellow Sun', 'Chimamanda Ngozi Adichie', '9780007200283', 'Knopf', '2006', '', 'Nigerian History', 'PR9387.9 A34', 'B-04', 'English', '2', 'Book (Physical)'],
  ]
  const esc = (v: string) => /[",\n]/.test(v) ? `"${v.replace(/"/g, '""')}"` : v
  const csv = [headers, ...examples].map(row => row.map(esc).join(',')).join('\r\n') + '\r\n'
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = 'catalog-import-template.csv'
  a.click()
  URL.revokeObjectURL(url)
}

async function runImport() {
  if (!importFile.value) return
  importState.value = 'uploading'
  importError.value = ''
  try {
    const fd = new FormData()
    fd.append('file', importFile.value)
    const res = await apiUpload<ImportResult>('/admin/books/import', fd, auth.token!)
    importResult.value = res
    importState.value  = 'done'
    await fetchBooks()
  } catch (e: any) {
    importError.value = e.message
    importState.value = 'error'
  }
}

// ── Copies / Accession numbers ───────────────────────────────────────────────
interface CopyRow { copy_number: number; accession_number: string }

const showCopies     = ref(false)
const copiesBook     = ref<Book | null>(null)
const copyRows       = ref<CopyRow[]>([])
const copiesLoading  = ref(false)
const copiesSaving   = ref(false)
const copiesError    = ref('')

async function openCopies(book: Book) {
  copiesBook.value    = book
  copyRows.value      = []
  copiesError.value   = ''
  copiesLoading.value = true
  showCopies.value    = true
  try {
    const res = await apiGet<{ copies: { copy_number: number; accession_number: string | null }[] }>(
      `/admin/books/${book.id}/copies`, auth.token!,
    )
    copyRows.value = res.copies.map(c => ({
      copy_number: c.copy_number,
      accession_number: c.accession_number ?? '',
    }))
  } catch (e: any) {
    copiesError.value = e.message
  } finally {
    copiesLoading.value = false
  }
}

async function saveCopies() {
  if (!copiesBook.value) return
  copiesSaving.value = true
  copiesError.value  = ''
  try {
    await apiPut(`/admin/books/${copiesBook.value.id}/copies`, {
      copies: copyRows.value.map(r => ({
        copy_number: r.copy_number,
        accession_number: r.accession_number.trim() || null,
      })),
    }, auth.token!)
    showCopies.value = false
  } catch (e: any) {
    copiesError.value = e.message
  } finally {
    copiesSaving.value = false
  }
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function coverLabel(title: string): string {
  const words = title.split(' ').filter(w => w.length > 2)
  return words.length ? words[0].substring(0, 4) + '.' : title.substring(0, 4)
}

function bookStatus(book: Book): { label: string; cls: string } {
  const fmt = (book.format ?? '').toLowerCase()
  if (fmt === 'ebook' || fmt === 'digital access') return { label: 'eBook', cls: 'b-blue' }
  if (book.available_copies > 0) return { label: 'Available', cls: 'b-green' }
  return { label: 'On Loan', cls: 'b-red' }
}

function copiesLabel(book: Book): string {
  const fmt = (book.format ?? '').toLowerCase()
  if (fmt === 'ebook' || fmt === 'digital access') return 'Digital'
  return `${book.number_of_copies} (${book.available_copies} free)`
}

// ── Watchers ─────────────────────────────────────────────────────────────────
let debounce: ReturnType<typeof setTimeout>
watch([searchQuery, filterFormat, filterSubject], () => {
  currentPage.value = 1
  clearTimeout(debounce)
  debounce = setTimeout(fetchBooks, 320)
})

watch(currentPage, fetchBooks)

onMounted(fetchBooks)
</script>

<template>
  <div class="shead">
    <div>
      <h2>Catalog Manager</h2>
      <p>{{ total.toLocaleString() }} titles in the collection</p>
    </div>
    <div class="shead-actions">
      <button class="btn btn-ghost" @click="openImportModal"><LucideIcon name="upload" class="ic-sm" /> Import Records</button>
      <button class="btn btn-primary" @click="openAdd"><LucideIcon name="book-plus" class="ic-sm" /> Add New Title</button>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <div class="search-in">
      <LucideIcon name="search" class="ic-sm" />
      <input v-model="searchQuery" placeholder="Search by title, author, ISBN, call number…" />
    </div>
    <select class="sel" v-model="filterFormat">
      <option value="">All Formats</option>
      <option>Book (Physical)</option>
      <option>Journal</option>
      <option>eBook</option>
      <option>Thesis</option>
      <option>Government Document</option>
    </select>
    <select class="sel" v-model="filterSubject">
      <option value="">All Subjects</option>
      <option>Democracy &amp; Governance</option>
      <option>Nigerian History &amp; Law</option>
      <option>Political Science</option>
      <option>African Studies</option>
      <option>Electoral Systems</option>
      <option>Biography</option>
    </select>
  </div>

  <!-- Error -->
  <div v-if="fetchError" style="color:var(--red);font-size:13px;margin-bottom:12px">
    <LucideIcon name="alert-triangle" class="ic-sm" /> {{ fetchError }}
  </div>

  <!-- Table -->
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Title / Author</th>
          <th>Call No.</th>
          <th>ISBN</th>
          <th>Subject</th>
          <th>Copies</th>
          <th>Status</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <!-- Loading skeleton -->
        <tr v-if="loading" v-for="n in 5" :key="'sk'+n">
          <td colspan="7">
            <div style="height:14px;background:var(--line-soft);border-radius:4px;animation:pulse 1.4s infinite"></div>
          </td>
        </tr>

        <!-- Empty -->
        <tr v-else-if="!books.length">
          <td colspan="7" style="text-align:center;padding:32px;color:var(--faint)">
            <LucideIcon name="book" style="width:32px;height:32px;margin-bottom:8px;display:block;margin-inline:auto" />
            No books found
          </td>
        </tr>

        <!-- Rows -->
        <tr v-else v-for="book in books" :key="book.id">
          <td>
            <div class="bookcell">
              <div :class="['cover', book.cover_treatment ?? 'cv-blue']">
                <div class="cover-top">
                  <div class="cover-rule"></div>
                  <div class="cover-t">{{ coverLabel(book.title) }}</div>
                </div>
              </div>
              <div>
                <div class="bk-t">{{ book.title }}</div>
                <div class="bk-a">{{ book.authors }}{{ book.year ? ' · ' + book.year : '' }}</div>
              </div>
            </div>
          </td>
          <td class="mono">{{ book.call_number ?? '—' }}</td>
          <td class="mono">{{ book.isbn ?? '—' }}</td>
          <td><span class="tag">{{ book.subject_area }}</span></td>
          <td>{{ copiesLabel(book) }}</td>
          <td><span :class="['badge', bookStatus(book).cls]">{{ bookStatus(book).label }}</span></td>
          <td>
            <div class="rowacts" style="justify-content:flex-end">
              <div class="ra" title="Edit" @click="openEdit(book)"><LucideIcon name="pencil" /></div>
              <div class="ra" title="Copies & Accession Numbers" @click="openCopies(book)"><LucideIcon name="layers" /></div>
              <div class="ra del" title="Delete" @click="promptDelete(book)"><LucideIcon name="trash-2" /></div>
            </div>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Pagination -->
    <div v-if="lastPage > 1" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid var(--line);background:var(--bg)">
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
  </div>

  <!-- ── Drawer scrim ── -->
  <div :class="['scrim', { open: drawerOpen }]" @click="drawerOpen = false"></div>

  <!-- ── Add / Edit Drawer ── -->
  <div :class="['drawer', { open: drawerOpen }]">
    <div class="dh">
      <div>
        <div class="dh-t">{{ editingBook ? 'Edit Title' : 'Add New Title' }}</div>
        <div class="dh-s">{{ editingBook ? 'Update catalog record' : 'Catalog a new book into the collection' }}</div>
      </div>
      <div class="dh-x" @click="drawerOpen = false"><LucideIcon name="x" /></div>
    </div>

    <div class="db">
      <!-- Bibliographic -->
      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="book" /> Bibliographic Information</div>
        <div class="form-grid">
          <div class="fg col2">
            <label class="fl">Title <span class="req">*</span></label>
            <input class="fi" v-model="form.title" placeholder="Full book title" />
          </div>
          <div class="fg col2">
            <label class="fl">Author(s) <span class="req">*</span></label>
            <input class="fi" v-model="form.authors" placeholder="Surname, First; Surname, First…" />
          </div>
          <div class="fg">
            <label class="fl">Publisher</label>
            <input class="fi" v-model="form.publisher" placeholder="e.g. Johns Hopkins UP" />
          </div>
          <div class="fg">
            <label class="fl">Year</label>
            <input class="fi" v-model="form.year" placeholder="2024" type="number" min="1000" :max="new Date().getFullYear()" />
          </div>
          <div class="fg">
            <label class="fl">ISBN <span class="req">*</span></label>
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
        <div class="db-sec-h"><LucideIcon name="map-pin" /> Classification &amp; Shelving</div>
        <div class="form-grid">
          <div class="fg">
            <label class="fl">Call Number <span class="req">*</span></label>
            <input class="fi" v-model="form.call_number" placeholder="JZ1320.D46" />
          </div>
          <div class="fg">
            <label class="fl">Shelf Location</label>
            <input class="fi" v-model="form.shelf_location" placeholder="Block A · Shelf 14C" />
          </div>
          <div class="fg">
            <label class="fl">Subject Area <span class="req">*</span></label>
            <input class="fi" v-model="form.subject_area" placeholder="e.g. Democracy &amp; Governance" />
          </div>
          <div class="fg">
            <label class="fl">Language</label>
            <select class="fs" v-model="form.language">
              <option>English</option><option>Hausa</option><option>Arabic</option><option>French</option>
            </select>
          </div>
          <div class="fg">
            <label class="fl">Format</label>
            <select class="fs" v-model="form.format">
              <option>Book (Physical)</option><option>eBook</option><option>Journal</option>
              <option>Thesis</option><option>Government Document</option>
            </select>
          </div>
          <div class="fg">
            <label class="fl">Access Type</label>
            <select class="fs" v-model="form.material_type">
              <option>Loanable</option><option>Reference Only</option><option>Digital Access</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Copies & Cover -->
      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="layers" /> Copies &amp; Cover</div>
        <div class="form-grid">
          <div class="fg">
            <label class="fl">Number of Copies</label>
            <div class="copies-mgr">
              <div class="stepper">
                <button type="button" @click="step(-1)"><LucideIcon name="minus" class="ic-sm" /></button>
                <input :value="form.number_of_copies" readonly />
                <button type="button" @click="step(1)"><LucideIcon name="plus" class="ic-sm" /></button>
              </div>
              <span class="fhint">Each copy gets a unique barcode</span>
            </div>
          </div>
          <div class="fg">
            <label class="fl">Cover Colour</label>
            <div class="cv-pick">
              <div
                v-for="c in covers" :key="c"
                :class="['cv-opt', c, { sel: form.cover_treatment === c }]"
                @click="form.cover_treatment = c"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Save error -->
      <div v-if="saveError" style="color:var(--red);font-size:13px;margin-top:4px">
        <LucideIcon name="alert-triangle" class="ic-sm" /> {{ saveError }}
      </div>
    </div>

    <div class="df">
      <button class="btn btn-primary" style="flex:1;justify-content:center" :disabled="saving" @click="save">
        <LucideIcon :name="saving ? 'refresh-cw' : 'save'" class="ic-sm" />
        {{ saving ? 'Saving…' : 'Save to Catalog' }}
      </button>
      <button class="btn btn-ghost" @click="drawerOpen = false">Cancel</button>
    </div>
  </div>

  <!-- ── Copies / Accession Drawer ── -->
  <div :class="['scrim', { open: showCopies }]" @click="showCopies = false"></div>
  <div :class="['drawer', { open: showCopies }]">
    <div class="dh">
      <div>
        <div class="dh-t">Copies &amp; Accession Numbers</div>
        <div class="dh-s" v-if="copiesBook">{{ copiesBook.title }}</div>
      </div>
      <div class="dh-x" @click="showCopies = false"><LucideIcon name="x" /></div>
    </div>

    <div class="db">
      <div class="db-sec">
        <div class="db-sec-h">
          <LucideIcon name="layers" /> {{ copyRows.length }} {{ copyRows.length === 1 ? 'Copy' : 'Copies' }}
        </div>

        <!-- Loading -->
        <div v-if="copiesLoading" style="display:flex;align-items:center;gap:8px;color:var(--muted);font-size:13px;padding:18px 2px">
          <LucideIcon name="refresh-cw" class="ic-sm" style="animation:spin .8s linear infinite" /> Loading copies…
        </div>

        <!-- Copy rows -->
        <div v-else style="display:flex;flex-direction:column;gap:10px">
          <div v-for="row in copyRows" :key="row.copy_number"
            style="display:flex;align-items:center;gap:12px">
            <div style="flex-shrink:0;width:78px;display:flex;align-items:center;gap:7px">
              <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:6px;background:var(--blue-50);color:var(--blue);font-size:12px;font-weight:700">{{ row.copy_number }}</span>
              <span style="font-size:12.5px;color:var(--muted);font-weight:600">Copy</span>
            </div>
            <input class="fi" style="flex:1" v-model="row.accession_number"
              :placeholder="`Accession number for copy ${row.copy_number}`" />
          </div>
        </div>

        <p style="font-size:12px;color:var(--faint);margin-top:12px">
          The number of copies is set on the title's record. Edit the title to add or remove copies.
        </p>
      </div>

      <div v-if="copiesError" style="color:var(--red);font-size:13px;margin-top:4px">
        <LucideIcon name="alert-triangle" class="ic-sm" /> {{ copiesError }}
      </div>
    </div>

    <div class="df">
      <button class="btn btn-primary" style="flex:1;justify-content:center"
        :disabled="copiesSaving || copiesLoading" @click="saveCopies">
        <LucideIcon :name="copiesSaving ? 'refresh-cw' : 'save'" class="ic-sm" />
        {{ copiesSaving ? 'Saving…' : 'Save Accession Numbers' }}
      </button>
      <button class="btn btn-ghost" @click="showCopies = false">Cancel</button>
    </div>
  </div>

  <!-- ── Modal: Saved ── -->
  <div :class="['mscrim', { open: showSavedModal }]" @click.self="showSavedModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--blue-50)">
          <LucideIcon name="book-check" :size="28" style="color:var(--blue)" />
        </div>
        <div class="modal-t">{{ editingBook ? 'Title Updated' : 'Title Catalogued' }}</div>
        <div class="modal-s">
          {{ editingBook
            ? 'The catalog record has been updated successfully.'
            : 'The new title and its copies have been added to the catalog.' }}
        </div>
        <div class="modal-f">
          <button class="btn btn-primary btn-block" @click="showSavedModal = false; editingBook = null">Done</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Modal: Import MARC ── -->
  <div :class="['mscrim', { open: showImportModal }]" @click.self="showImportModal = false">
    <div class="modal" style="max-width:520px">
      <div class="dh" style="padding:16px 22px">
        <div>
          <div class="dh-t">Import Catalog Records</div>
          <div class="dh-s">MARC 21 · Excel · Microsoft Access</div>
        </div>
        <div class="dh-x" @click="showImportModal = false"><LucideIcon name="x" /></div>
      </div>

      <div style="padding:22px">

        <!-- Idle / file select state -->
        <template v-if="importState === 'idle' || importState === 'error'">
          <div
            class="dropzone"
            :class="{ drag: importDragging }"
            style="padding:28px 20px"
            @click="marcInput?.click()"
            @dragover.prevent="importDragging = true"
            @dragleave.prevent="importDragging = false"
            @drop="handleImportDrop"
          >
            <div class="dz-ic"><LucideIcon name="upload" /></div>
            <div class="dz-t" style="font-size:14px">Drop your catalog file here</div>
            <div class="dz-s">or <b>browse</b> — MARC, Excel, or Access</div>
            <div class="dz-formats" style="margin-top:10px">
              <span class="tag">.mrc / .marc</span>
              <span class="tag">.xlsx / .xls / .csv</span>
              <span class="tag">.accdb / .mdb</span>
              <span class="tag">Up to 20 MB</span>
            </div>
            <input ref="marcInput" type="file" accept=".mrc,.marc,.xlsx,.xls,.xlsm,.ods,.csv,.accdb,.mdb" style="display:none" @change="handleImportInput" />
          </div>

          <!-- Template helper (Excel/CSV/Access column guide) -->
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px;padding:9px 13px;background:var(--bg);border:1px solid var(--line);border-radius:var(--r2)">
            <span style="font-size:12px;color:var(--muted)">Not sure about the columns for Excel / Access?</span>
            <button type="button" class="btn btn-ghost btn-sm" style="flex-shrink:0" @click.stop="downloadTemplate">
              <LucideIcon name="download" class="ic-sm" /> Download template
            </button>
          </div>

          <!-- Selected file chip -->
          <div v-if="importFile" style="display:flex;align-items:center;gap:10px;margin-top:14px;padding:10px 13px;background:var(--blue-50);border:1px solid var(--blue-100);border-radius:var(--r2)">
            <LucideIcon name="file" style="color:var(--blue);width:16px;height:16px;flex-shrink:0" />
            <span style="font-size:13px;font-weight:600;color:var(--navy);flex:1">{{ importFile.name }}</span>
            <span style="font-size:11px;color:var(--muted)">{{ (importFile.size / 1024).toFixed(0) }} KB</span>
            <div style="cursor:pointer;color:var(--muted)" @click="importFile = null; importError = ''">
              <LucideIcon name="x" style="width:14px;height:14px" />
            </div>
          </div>

          <div v-if="importError" style="color:var(--red);font-size:12.5px;margin-top:10px;display:flex;align-items:center;gap:6px">
            <LucideIcon name="alert-triangle" style="width:14px;height:14px;flex-shrink:0" /> {{ importError }}
          </div>
        </template>

        <!-- Uploading -->
        <template v-else-if="importState === 'uploading'">
          <div style="text-align:center;padding:32px 0">
            <div style="width:48px;height:48px;border:3px solid var(--blue-100);border-top-color:var(--blue);border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 16px"></div>
            <div style="font-size:14px;font-weight:600;color:var(--navy)">Importing records…</div>
            <div style="font-size:12px;color:var(--muted);margin-top:4px">Parsing and writing to catalog</div>
          </div>
        </template>

        <!-- Done -->
        <template v-else-if="importState === 'done' && importResult">
          <div style="text-align:center;margin-bottom:20px">
            <div class="modal-ic" style="background:var(--green-50);margin-bottom:12px">
              <LucideIcon name="circle-check-big" style="width:28px;height:28px;color:var(--green)" />
            </div>
            <div style="font-family:var(--display);font-size:18px;font-weight:700;color:var(--navy)">Import Complete</div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:18px">
            <div style="text-align:center;padding:14px;background:var(--green-50);border-radius:var(--r2)">
              <div style="font-family:var(--display);font-size:26px;font-weight:700;color:var(--green)">{{ importResult.imported }}</div>
              <div style="font-size:11px;color:var(--muted);margin-top:2px">Imported</div>
            </div>
            <div style="text-align:center;padding:14px;background:var(--amber-50);border-radius:var(--r2)">
              <div style="font-family:var(--display);font-size:26px;font-weight:700;color:var(--amber)">{{ importResult.skipped }}</div>
              <div style="font-size:11px;color:var(--muted);margin-top:2px">Skipped</div>
            </div>
            <div style="text-align:center;padding:14px;background:var(--blue-50);border-radius:var(--r2)">
              <div style="font-family:var(--display);font-size:26px;font-weight:700;color:var(--blue)">{{ importResult.total }}</div>
              <div style="font-size:11px;color:var(--muted);margin-top:2px">Total Records</div>
            </div>
          </div>
          <div v-if="importResult.errors.length" style="background:var(--red-50);border:1px solid #f6c9cf;border-radius:var(--r2);padding:12px 14px">
            <div style="font-size:11.5px;font-weight:700;color:var(--red);margin-bottom:6px">Warnings ({{ importResult.errors.length }})</div>
            <div v-for="(err, i) in importResult.errors" :key="i" style="font-size:11.5px;color:var(--muted);padding:2px 0">{{ err }}</div>
          </div>
        </template>

      </div>

      <!-- Footer -->
      <div class="df" style="padding:14px 22px">
        <template v-if="importState === 'idle' || importState === 'error'">
          <button class="btn btn-primary" style="flex:1;justify-content:center" :disabled="!importFile" @click="runImport">
            <LucideIcon name="upload" class="ic-sm" /> Import Records
          </button>
          <button class="btn btn-ghost" @click="showImportModal = false">Cancel</button>
        </template>
        <template v-else-if="importState === 'done'">
          <button class="btn btn-primary btn-block" @click="showImportModal = false">Done</button>
        </template>
      </div>
    </div>
  </div>

  <!-- ── Modal: Delete ── -->
  <div :class="['mscrim', { open: showDeleteModal }]" @click.self="showDeleteModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--red-50)">
          <LucideIcon name="trash-2" :size="28" style="color:var(--red)" />
        </div>
        <div class="modal-t">Remove Title?</div>
        <div class="modal-s">
          <b>{{ deletingBook?.title }}</b> and all its copies will be removed from the catalog. This cannot be undone.
        </div>
        <div class="modal-f">
          <button class="btn btn-ghost" style="flex:1;justify-content:center" @click="showDeleteModal = false">Cancel</button>
          <button
            class="btn btn-danger" style="flex:1;justify-content:center;background:var(--red);color:#fff;border:none"
            :disabled="deleting" @click="confirmDelete"
          >
            {{ deleting ? 'Removing…' : 'Remove' }}
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
@keyframes spin {
  to { transform: rotate(360deg) }
}
</style>
