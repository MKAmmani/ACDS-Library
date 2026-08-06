<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'
import LoomPlayer from '@/components/LoomPlayer.vue'
import { useAuthStore } from '@/stores/auth'
import { apiGet, apiDelete, apiUpload } from '@/api/http'

const auth     = useAuthStore()
const BASE_URL = import.meta.env.VITE_API_URL as string

// ── Types ─────────────────────────────────────────────────────────────────────
interface MediaItem {
  id: number
  title: string
  kind: string | null
  tag: string | null
  duration_label: string | null
  thumbnail_url: string | null
  thumbnail: string | null
  media_url: string | null
  media_path: string | null
  file_size: number | null
  mime_type: string | null
  views: number
  published_at: string | null
  thumbnail_display_url: string | null
  has_media_file: boolean
  is_audio: boolean
}

const MEDIA_KINDS = ['Public lecture', 'Documentary', 'Training', 'Oral history', 'Interview', 'Conference session', 'Podcast', 'Audio recording']

// Accepted file types for a single upload — video and audio alike.
const MEDIA_ACCEPT = [
  'video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska', 'video/x-msvideo', 'video/ogg',
  'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/flac', 'audio/x-flac', 'audio/ogg', 'audio/opus', 'audio/webm',
].join(',')

// ── List state ────────────────────────────────────────────────────────────────
const mediaItems  = ref<MediaItem[]>([])
const total       = ref(0)
const totalViews  = ref(0)
const loading     = ref(false)
const fetchError  = ref('')

// ── Filters ───────────────────────────────────────────────────────────────────
const searchQuery = ref('')
const activeKind  = ref('all')
const filterTabs  = computed(() => [{ key: 'all', label: 'All' }, ...MEDIA_KINDS.map(k => ({ key: k, label: k }))])

async function fetchMedia() {
  loading.value    = true
  fetchError.value = ''
  try {
    const params = new URLSearchParams({ limit: '200' })
    if (searchQuery.value.trim()) params.set('search', searchQuery.value.trim())
    if (activeKind.value !== 'all') params.set('kind', activeKind.value)

    const res = await apiGet<{ data: MediaItem[]; total: number; total_views: number }>(`/media?${params}`)
    mediaItems.value = res.data ?? []
    total.value      = res.total ?? mediaItems.value.length
    totalViews.value = res.total_views ?? 0
  } catch (e: any) {
    fetchError.value = e.message
  } finally {
    loading.value = false
  }
}

const filesHosted = computed(() => mediaItems.value.filter(v => v.has_media_file).length)

let debounce: ReturnType<typeof setTimeout>
watch([searchQuery, activeKind], () => {
  clearTimeout(debounce)
  debounce = setTimeout(fetchMedia, 320)
})

// ── Drawer (add / edit) ──────────────────────────────────────────────────────
const drawerOpen   = ref(false)
const editingMedia = ref<MediaItem | null>(null)
const saving       = ref(false)
const saveError    = ref('')

function emptyForm() {
  return { title: '', kind: 'Public lecture', tag: '', duration_label: '', published_at: '' }
}
const form = reactive(emptyForm())

// Thumbnail: URL vs uploaded image
const thumbMode    = ref<'url' | 'upload'>('url')
const thumbUrlText = ref('')
const thumbFile    = ref<File | null>(null)
const thumbPreview = ref('')
const thumbInput   = ref<HTMLInputElement>()

// Media source: pasted link vs uploaded file
const srcMode      = ref<'link' | 'upload'>('link')
const mediaUrlText = ref('')
const mediaFile    = ref<File | null>(null)
const mediaInput   = ref<HTMLInputElement>()

// Best-effort: also probe a pasted direct media link (skipped for YouTube —
// the plain <video> tag can't read its duration; that's fine, the field is
// simply left blank when it can't be determined).
let linkDurationDebounce: ReturnType<typeof setTimeout>
watch(mediaUrlText, (url) => {
  clearTimeout(linkDurationDebounce)
  if (srcMode.value !== 'link' || !url.trim() || youtubeId(url.trim())) return
  linkDurationDebounce = setTimeout(async () => {
    const seconds = await detectDuration(url.trim())
    if (seconds) form.duration_label = formatDuration(seconds)
  }, 500)
})

function handleThumbInput(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  thumbFile.value    = file
  thumbPreview.value = URL.createObjectURL(file)
}

// Reads a video/audio source's length client-side (no server-side ffmpeg
// available) via a hidden <video> element, which happily reports duration
// for audio-only sources too. Resolves null if it can't be determined
// (unsupported codec, CORS-blocked remote URL, etc.) within a short timeout.
function detectDuration(src: string): Promise<number | null> {
  return new Promise((resolve) => {
    const probe = document.createElement('video')
    probe.preload = 'metadata'
    let settled = false
    const finish = (val: number | null) => {
      if (settled) return
      settled = true
      probe.src = ''
      resolve(val)
    }
    probe.onloadedmetadata = () => finish(Number.isFinite(probe.duration) ? probe.duration : null)
    probe.onerror = () => finish(null)
    setTimeout(() => finish(null), 8000)
    probe.src = src
  })
}
function formatDuration(totalSeconds: number): string {
  const s  = Math.max(0, Math.round(totalSeconds))
  const h  = Math.floor(s / 3600)
  const m  = Math.floor((s % 3600) / 60)
  const ss = String(s % 60).padStart(2, '0')
  return h ? `${h}:${String(m).padStart(2, '0')}:${ss}` : `${m}:${ss}`
}

async function handleMediaInput(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  mediaFile.value      = file
  form.duration_label  = ''
  const objectUrl = URL.createObjectURL(file)
  const seconds   = await detectDuration(objectUrl)
  URL.revokeObjectURL(objectUrl)
  if (seconds) form.duration_label = formatDuration(seconds)
}

function openAdd() {
  editingMedia.value = null
  Object.assign(form, emptyForm())
  thumbMode.value = 'url'; thumbUrlText.value = ''; thumbFile.value = null; thumbPreview.value = ''
  srcMode.value   = 'link'; mediaUrlText.value = ''; mediaFile.value = null
  saveError.value = ''
  drawerOpen.value = true
}

function openEdit(v: MediaItem) {
  editingMedia.value = v
  Object.assign(form, {
    title: v.title, kind: v.kind ?? 'Public lecture', tag: v.tag ?? '',
    duration_label: v.duration_label ?? '',
    published_at: v.published_at ? v.published_at.slice(0, 10) : '',
  })
  if (v.thumbnail) {
    thumbMode.value = 'upload'; thumbFile.value = null; thumbPreview.value = v.thumbnail_display_url ?? ''
  } else {
    thumbMode.value = 'url'; thumbUrlText.value = v.thumbnail_url ?? ''; thumbPreview.value = v.thumbnail_url ?? ''
  }
  if (v.has_media_file) {
    srcMode.value = 'upload'; mediaFile.value = null
  } else {
    srcMode.value = 'link'; mediaUrlText.value = v.media_url ?? ''
  }
  saveError.value  = ''
  drawerOpen.value = true
}

watch(thumbUrlText, (v) => { if (thumbMode.value === 'url') thumbPreview.value = v })

async function save() {
  if (!form.title.trim()) { saveError.value = 'Title is required.'; return }
  if (srcMode.value === 'link' && !mediaUrlText.value.trim() && !editingMedia.value?.has_media_file) {
    saveError.value = 'Add a media link or switch to file upload.'
    return
  }
  saving.value    = true
  saveError.value = ''
  try {
    const fd = new FormData()
    fd.append('title', form.title)
    if (form.kind) fd.append('kind', form.kind)
    if (form.tag) fd.append('tag', form.tag)
    if (form.duration_label) fd.append('duration_label', form.duration_label)
    if (form.published_at) fd.append('published_at', form.published_at)

    if (thumbMode.value === 'upload' && thumbFile.value) fd.append('thumbnail', thumbFile.value)
    else if (thumbMode.value === 'url' && thumbUrlText.value.trim()) fd.append('thumbnail_url', thumbUrlText.value.trim())

    if (srcMode.value === 'upload' && mediaFile.value) fd.append('media', mediaFile.value)
    else if (srcMode.value === 'link' && mediaUrlText.value.trim()) fd.append('media_url', mediaUrlText.value.trim())

    const path = editingMedia.value ? `/admin/media/${editingMedia.value.id}` : '/admin/media'
    await apiUpload<MediaItem>(path, fd, auth.token!)
    drawerOpen.value     = false
    showSavedModal.value = true
    await fetchMedia()
  } catch (e: any) {
    saveError.value = e.message
  } finally {
    saving.value = false
  }
}

// ── Delete ───────────────────────────────────────────────────────────────────
const showSavedModal  = ref(false)
const showDeleteModal = ref(false)
const deletingMedia   = ref<MediaItem | null>(null)
const deleting        = ref(false)

function promptDelete(v: MediaItem) { deletingMedia.value = v; showDeleteModal.value = true }
async function confirmDelete() {
  if (!deletingMedia.value) return
  deleting.value = true
  try {
    await apiDelete(`/admin/media/${deletingMedia.value.id}`, auth.token!)
    showDeleteModal.value = false
    deletingMedia.value   = null
    await fetchMedia()
  } catch {
    showDeleteModal.value = false
  } finally {
    deleting.value = false
  }
}

// ── Player ────────────────────────────────────────────────────────────────────
const playerOpen  = ref(false)
const playerMedia = ref<MediaItem | null>(null)
const playerMode  = ref<'youtube' | 'video' | 'none'>('none')
const playerSrc   = ref('')

function youtubeId(url: string): string | null {
  const m = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{11})/)
  return m?.[1] ?? null
}

function openPlayer(v: MediaItem) {
  playerMedia.value = v
  if (v.media_url) {
    const yid = youtubeId(v.media_url)
    if (yid) { playerMode.value = 'youtube'; playerSrc.value = `https://www.youtube.com/embed/${yid}` }
    else     { playerMode.value = 'video';   playerSrc.value = v.media_url }
  } else if (v.has_media_file) {
    playerMode.value = 'video'
    playerSrc.value  = `${BASE_URL}/media/${v.id}/stream`
  } else {
    playerMode.value = 'none'
    playerSrc.value  = ''
  }
  playerOpen.value = true
}
function closePlayer() { playerOpen.value = false; playerMedia.value = null }

// ── Bulk upload ───────────────────────────────────────────────────────────────
interface BulkFile { file: File; title: string; status: 'pending' | 'uploading' | 'done' | 'error'; error?: string; duration?: string }

const bulkDrawerOpen  = ref(false)
const bulkUploading   = ref(false)
const bulkFiles       = ref<BulkFile[]>([])
const bulkFileInput   = ref<HTMLInputElement>()
const bulkFolderInput = ref<HTMLInputElement>()

function emptyBulkForm() { return { kind: 'Public lecture', tag: '' } }
const bulkForm = reactive(emptyBulkForm())

const bulkSummary = computed(() => {
  const done  = bulkFiles.value.filter(f => f.status === 'done').length
  const error = bulkFiles.value.filter(f => f.status === 'error').length
  return { done, error, total: bulkFiles.value.length }
})

function titleFromFilename(name: string) { return name.replace(/\.[^/.]+$/, '') }

function openBulk() {
  Object.assign(bulkForm, emptyBulkForm())
  bulkFiles.value     = []
  bulkUploading.value = false
  bulkDrawerOpen.value = true
}

function handleBulkFilesInput(e: Event) {
  const files = Array.from((e.target as HTMLInputElement).files ?? [])
  for (const file of files) {
    if (!file.type.startsWith('video/') && !file.type.startsWith('audio/')) continue
    const entry: BulkFile = { file, title: titleFromFilename(file.name), status: 'pending' }
    bulkFiles.value.push(entry)
    // Detect duration in the background — doesn't block adding the file to the list.
    const objectUrl = URL.createObjectURL(file)
    detectDuration(objectUrl).then((seconds) => {
      URL.revokeObjectURL(objectUrl)
      if (seconds) entry.duration = formatDuration(seconds)
    })
  }
  ;(e.target as HTMLInputElement).value = ''
}
function removeBulkFile(idx: number) { bulkFiles.value.splice(idx, 1) }

async function uploadBulkFiles() {
  if (!bulkFiles.value.length) return
  bulkUploading.value = true
  for (const entry of bulkFiles.value) {
    if (entry.status === 'done') continue
    entry.status = 'uploading'
    entry.error  = undefined
    try {
      const fd = new FormData()
      fd.append('title', entry.title || titleFromFilename(entry.file.name))
      if (bulkForm.kind) fd.append('kind', bulkForm.kind)
      if (bulkForm.tag) fd.append('tag', bulkForm.tag)
      if (entry.duration) fd.append('duration_label', entry.duration)
      fd.append('media', entry.file)
      await apiUpload<MediaItem>('/admin/media', fd, auth.token!)
      entry.status = 'done'
    } catch (e: any) {
      entry.status = 'error'
      entry.error  = e.message ?? 'Upload failed.'
    }
  }
  bulkUploading.value = false
  await fetchMedia()
  bulkDrawerOpen.value = false
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmtSize(bytes: number | null): string {
  if (!bytes) return ''
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}
function fmtDate(d: string | null) {
  return d ? new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
}
function sourceLabel(v: MediaItem) {
  if (v.has_media_file) return v.is_audio ? 'Audio' : 'Uploaded'
  if (v.media_url) return youtubeId(v.media_url) ? 'YouTube' : 'Link'
  return 'No source'
}
function cardIcon(v: MediaItem) {
  return v.is_audio ? 'music' : 'video'
}

onMounted(fetchMedia)
</script>

<template>
  <!-- Header -->
  <div class="shead">
    <div>
      <h2>Media Library</h2>
      <p>Upload recorded lectures, documentaries &amp; audio recordings, or link out to YouTube — shown on the public landing page</p>
    </div>
    <div class="shead-actions">
      <button class="btn btn-ghost" @click="openBulk">
        <LucideIcon name="layers" class="ic-sm" /> Bulk Upload
      </button>
      <button class="btn btn-ghost" @click="openAdd">
        <LucideIcon name="upload" class="ic-sm" /> Add Media
      </button>
    </div>
  </div>

  <!-- KPIs -->
  <div class="kpis" style="grid-template-columns:repeat(3,1fr)">
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n">{{ total.toLocaleString() }}</div>
        <div class="kpi-ic t-blue"><LucideIcon name="video" /></div>
      </div>
      <div class="kpi-l">Media Items Published</div>
    </div>
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n">{{ totalViews.toLocaleString() }}</div>
        <div class="kpi-ic t-green"><LucideIcon name="eye" /></div>
      </div>
      <div class="kpi-l">Total Views</div>
    </div>
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n">{{ filesHosted }}</div>
        <div class="kpi-ic t-slate"><LucideIcon name="hard-drive-upload" /></div>
      </div>
      <div class="kpi-l">Files Hosted (vs. linked)</div>
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
      <input v-model="searchQuery" placeholder="Search media…" />
    </div>
    <div class="fbtns" style="flex-wrap:wrap">
      <div v-for="tab in filterTabs" :key="tab.key" class="fbtn" :class="{ on: activeKind === tab.key }" @click="activeKind = tab.key">
        {{ tab.label }}
      </div>
    </div>
  </div>

  <!-- Grid -->
  <div class="repo-grid">
    <template v-if="loading">
      <div v-for="n in 8" :key="'sk'+n" class="doc" style="opacity:.5">
        <div class="doc-head" style="animation:pulse 1.4s infinite"></div>
        <div class="doc-body">
          <div style="height:12px;background:var(--line-soft);border-radius:4px;margin-bottom:6px;animation:pulse 1.4s infinite"></div>
          <div style="height:10px;background:var(--line-soft);border-radius:4px;width:60%;animation:pulse 1.4s infinite"></div>
        </div>
      </div>
    </template>

    <div v-else-if="!mediaItems.length" class="repo-empty">
      <LucideIcon name="video" style="width:32px;height:32px;margin-bottom:8px;display:block;margin-inline:auto" />
      No media yet — click <b>Add Media</b> to publish the first one.
    </div>

    <div v-else v-for="v in mediaItems" :key="v.id" class="doc">
      <div
        class="doc-head"
        :class="v.thumbnail_display_url ? 'img' : ''"
        :style="v.thumbnail_display_url ? `background-image:url(${v.thumbnail_display_url});background-size:cover;background-position:center` : ''"
        style="cursor:pointer"
        @click="openPlayer(v)"
      >
        <span class="doc-type">{{ sourceLabel(v) }}</span>
        <LucideIcon v-if="!v.thumbnail_display_url" :name="cardIcon(v)" />
        <span v-if="v.duration_label" style="position:absolute;right:8px;bottom:8px;font-size:10px;font-weight:700;color:#fff;background:rgba(12,33,71,.78);padding:2px 6px;border-radius:5px">{{ v.duration_label }}</span>
        <div
          title="Edit"
          style="position:absolute;top:8px;right:8px;width:26px;height:26px;border-radius:6px;background:rgba(255,255,255,.88);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--muted);transition:all .14s"
          @click.stop="openEdit(v)"
        >
          <LucideIcon name="pencil" style="width:13px;height:13px" />
        </div>
      </div>
      <div class="doc-body">
        <div class="doc-name">{{ v.title }}</div>
        <div class="doc-meta">
          {{ v.kind || 'Media' }} · {{ v.views.toLocaleString() }} views
          <template v-if="fmtSize(v.file_size)"> · {{ fmtSize(v.file_size) }}</template>
        </div>
      </div>
      <div class="doc-foot">
        <div class="doc-act" @click="openPlayer(v)"><LucideIcon name="play" /> Preview</div>
        <div class="doc-act" @click="openEdit(v)"><LucideIcon name="pencil" /> Edit</div>
        <div class="doc-act del" @click="promptDelete(v)"><LucideIcon name="trash-2" /></div>
      </div>
    </div>
  </div>

  <!-- ── Add / Edit Drawer ── -->
  <div :class="['scrim', { open: drawerOpen }]" @click="drawerOpen = false"></div>
  <div :class="['drawer', { open: drawerOpen }]">
    <div class="dh">
      <div>
        <div class="dh-t">{{ editingMedia ? 'Edit Media' : 'Add Media' }}</div>
        <div class="dh-s">{{ editingMedia ? 'Update media record' : 'Publish media to the landing page' }}</div>
      </div>
      <div class="dh-x" @click="drawerOpen = false"><LucideIcon name="x" /></div>
    </div>

    <div class="db">
      <!-- Details -->
      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="file-text" /> Media Details</div>
        <div class="form-grid">
          <div class="fg col2">
            <label class="fl">Title <span class="req">*</span></label>
            <input class="fi" v-model="form.title" placeholder="Media title" />
          </div>
          <div class="fg">
            <label class="fl">Kind</label>
            <select class="fs" v-model="form.kind">
              <option v-for="k in MEDIA_KINDS" :key="k">{{ k }}</option>
            </select>
          </div>
          <div class="fg">
            <label class="fl">Tag / Subject</label>
            <input class="fi" v-model="form.tag" placeholder="e.g. Governance" />
          </div>
          <div class="fg">
            <label class="fl">Published</label>
            <input class="fi" type="date" v-model="form.published_at" />
            <span style="font-size:11px;color:var(--faint)">{{ editingMedia ? 'Leave blank to keep the current date' : 'Leave blank to publish now' }}</span>
          </div>
        </div>
      </div>

      <!-- Thumbnail -->
      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="image" /> Thumbnail / Cover Art</div>
        <div class="fbtns" style="margin-bottom:10px">
          <div class="fbtn" :class="{ on: thumbMode === 'url' }" @click="thumbMode = 'url'">Paste URL</div>
          <div class="fbtn" :class="{ on: thumbMode === 'upload' }" @click="thumbMode = 'upload'">Upload Image</div>
        </div>
        <div class="up-row">
          <div v-if="thumbPreview" class="up-preview show" :style="`background-image:url(${thumbPreview})`"></div>
          <template v-if="thumbMode === 'url'">
            <input class="fi" style="flex:1;min-width:200px" v-model="thumbUrlText" placeholder="https://…/thumbnail.jpg" />
          </template>
          <template v-else>
            <label class="up-btn">
              <LucideIcon name="image" />
              {{ thumbFile ? 'Change image' : 'Upload thumbnail' }}
              <input ref="thumbInput" type="file" accept="image/jpeg,image/jpg,image/png,image/webp" style="display:none" @change="handleThumbInput" />
            </label>
            <span v-if="thumbFile" style="font-size:11.5px;color:var(--green-600);font-weight:600">{{ thumbFile.name }}</span>
          </template>
        </div>
        <div style="font-size:11px;color:var(--faint);margin-top:6px">Optional — a placeholder icon is shown if left blank. For audio, this doubles as cover art.</div>
      </div>

      <!-- Media source -->
      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="clapperboard" /> Media Source</div>
        <div class="fbtns" style="margin-bottom:10px">
          <div class="fbtn" :class="{ on: srcMode === 'link' }" @click="srcMode = 'link'">Media Link</div>
          <div class="fbtn" :class="{ on: srcMode === 'upload' }" @click="srcMode = 'upload'">Upload File</div>
        </div>
        <template v-if="srcMode === 'link'">
          <input class="fi" v-model="mediaUrlText" placeholder="YouTube, Vimeo, or a direct video/audio URL" />
          <div style="font-size:11px;color:var(--faint);margin-top:6px">YouTube links are embedded automatically on the landing page.</div>
        </template>
        <template v-else>
          <div class="up-row">
            <label class="up-btn">
              <LucideIcon name="upload" />
              {{ mediaFile ? 'Change file' : editingMedia?.has_media_file ? 'Replace file' : 'Choose media file' }}
              <input ref="mediaInput" type="file" :accept="MEDIA_ACCEPT" style="display:none" @change="handleMediaInput" />
            </label>
            <span v-if="mediaFile" class="up-file show">
              <LucideIcon name="circle-check" /> {{ mediaFile.name }} ({{ fmtSize(mediaFile.size) }}<template v-if="form.duration_label"> · {{ form.duration_label }}</template>)
            </span>
            <span v-else-if="editingMedia?.has_media_file" style="font-size:11.5px;color:var(--muted)">
              Current file{{ fmtSize(editingMedia.file_size) ? ` (${fmtSize(editingMedia.file_size)})` : '' }} — choose a new file to replace it
            </span>
          </div>
          <div style="font-size:11px;color:var(--faint);margin-top:6px">Video: MP4, WebM, MOV, MKV, AVI, OGG · Audio: MP3, WAV, M4A, AAC, FLAC, OGG, OPUS · max 1 GB · duration is detected automatically</div>
        </template>
      </div>

      <div v-if="saveError" style="color:var(--red);font-size:13px;margin-top:4px;display:flex;align-items:center;gap:6px">
        <LucideIcon name="alert-triangle" class="ic-sm" /> {{ saveError }}
      </div>
    </div>

    <div class="df">
      <button class="btn btn-primary" style="flex:1;justify-content:center" :disabled="saving" @click="save">
        <LucideIcon :name="saving ? 'refresh-cw' : 'save'" class="ic-sm" />
        {{ saving ? 'Saving…' : editingMedia ? 'Save Changes' : 'Publish Media' }}
      </button>
      <button class="btn btn-ghost" @click="drawerOpen = false">Cancel</button>
    </div>
  </div>

  <!-- ── Bulk Upload Drawer ── -->
  <div :class="['scrim', { open: bulkDrawerOpen }]" @click="!bulkUploading && (bulkDrawerOpen = false)"></div>
  <div :class="['drawer', { open: bulkDrawerOpen }]">
    <div class="dh">
      <div>
        <div class="dh-t">Bulk Upload Media</div>
        <div class="dh-s">Upload multiple video or audio files at once — shared kind &amp; tag apply to every file</div>
      </div>
      <div class="dh-x" @click="!bulkUploading && (bulkDrawerOpen = false)"><LucideIcon name="x" /></div>
    </div>

    <div class="db">
      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="file-text" /> Shared Details</div>
        <div class="form-grid">
          <div class="fg">
            <label class="fl">Kind</label>
            <select class="fs" v-model="bulkForm.kind">
              <option v-for="k in MEDIA_KINDS" :key="k">{{ k }}</option>
            </select>
          </div>
          <div class="fg">
            <label class="fl">Tag / Subject</label>
            <input class="fi" v-model="bulkForm.tag" placeholder="e.g. Governance" />
          </div>
        </div>
      </div>

      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="upload" /> Files <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--faint)"> — video or audio files only, or a whole folder · max 1 GB each</span></div>

        <div style="display:flex;gap:10px;margin-bottom:12px">
          <label class="up-btn">
            <LucideIcon name="upload" /> Choose files…
            <input ref="bulkFileInput" type="file" accept="video/*,audio/*" multiple style="display:none" @change="handleBulkFilesInput" />
          </label>
          <label class="up-btn">
            <LucideIcon name="layers" /> Choose folder…
            <input ref="bulkFolderInput" type="file" webkitdirectory directory multiple style="display:none" @change="handleBulkFilesInput" />
          </label>
        </div>

        <div v-if="!bulkFiles.length" style="padding:20px;text-align:center;color:var(--faint);font-size:13px;background:var(--bg);border-radius:var(--r2)">
          No media files selected yet.
        </div>

        <div v-else style="display:flex;flex-direction:column;gap:8px">
          <div v-for="(entry, idx) in bulkFiles" :key="idx" style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:var(--bg);border-radius:var(--r2)">
            <LucideIcon :name="entry.file.type.startsWith('audio/') ? 'music' : 'video'" class="ic-sm" style="flex-shrink:0;color:var(--muted)" />
            <input class="fi" v-model="entry.title" :disabled="entry.status !== 'pending'" style="flex:1;min-width:0" placeholder="Media title" />
            <span style="font-size:11px;color:var(--faint);flex-shrink:0">{{ fmtSize(entry.file.size) }}<template v-if="entry.duration"> · {{ entry.duration }}</template></span>
            <span v-if="entry.status === 'pending'" class="ra" style="flex-shrink:0" title="Remove" @click="removeBulkFile(idx)">
              <LucideIcon name="x" style="width:13px;height:13px" />
            </span>
            <LucideIcon v-else-if="entry.status === 'uploading'" name="refresh-cw" class="ic-sm" style="flex-shrink:0;animation:spin .8s linear infinite;color:var(--blue)" />
            <LucideIcon v-else-if="entry.status === 'done'" name="circle-check" class="ic-sm" style="flex-shrink:0;color:var(--green)" />
            <span v-else-if="entry.status === 'error'" class="ic-sm" style="flex-shrink:0;color:var(--red)" :title="entry.error">
              <LucideIcon name="alert-triangle" style="width:15px;height:15px" />
            </span>
          </div>
        </div>

        <div v-if="bulkSummary.total && (bulkSummary.done || bulkSummary.error)" style="margin-top:10px;font-size:12.5px;color:var(--muted)">
          {{ bulkSummary.done }} of {{ bulkSummary.total }} uploaded
          <template v-if="bulkSummary.error"> · {{ bulkSummary.error }} failed</template>
        </div>
      </div>
    </div>

    <div class="df">
      <button class="btn btn-primary" style="flex:1;justify-content:center" :disabled="bulkUploading || !bulkFiles.length" @click="uploadBulkFiles">
        <LucideIcon :name="bulkUploading ? 'refresh-cw' : 'upload'" class="ic-sm" />
        {{ bulkUploading ? 'Uploading…' : `Upload All (${bulkFiles.length})` }}
      </button>
      <button class="btn btn-ghost" :disabled="bulkUploading" @click="bulkDrawerOpen = false">Close</button>
    </div>
  </div>

  <!-- ── Player ── -->
  <LoomPlayer :open="playerOpen" :video="playerMedia" :mode="playerMode" :src="playerSrc" @close="closePlayer" />

  <!-- ── Modal: Saved ── -->
  <div :class="['mscrim', { open: showSavedModal }]" @click.self="showSavedModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--blue-50)">
          <LucideIcon name="video" style="width:28px;height:28px;color:var(--blue)" />
        </div>
        <div class="modal-t">{{ editingMedia ? 'Media Updated' : 'Media Published' }}</div>
        <div class="modal-s">
          {{ editingMedia ? 'The media record has been updated successfully.' : 'The media item has been added to the landing page.' }}
        </div>
        <div class="modal-f">
          <button class="btn btn-primary btn-block" @click="showSavedModal = false; editingMedia = null">Done</button>
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
        <div class="modal-t">Delete Media?</div>
        <div class="modal-s">
          <b>{{ deletingMedia?.title }}</b> and its stored file (if any) will be permanently deleted. This cannot be undone.
        </div>
        <div class="modal-f">
          <button class="btn btn-ghost" style="flex:1;justify-content:center" @click="showDeleteModal = false">Cancel</button>
          <button class="btn btn-danger" style="flex:1;justify-content:center;background:var(--red);color:#fff;border:none" :disabled="deleting" @click="confirmDelete">
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
