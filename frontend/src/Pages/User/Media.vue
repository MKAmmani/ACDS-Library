<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import LucideIcon from '@/components/LucideIcon.vue'
import LoomPlayer from '@/components/LoomPlayer.vue'
import { apiGet } from '@/api/http'

const route     = useRoute()
const BASE_URL  = import.meta.env.VITE_API_URL as string

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

let debounce: ReturnType<typeof setTimeout>
watch([searchQuery, activeKind], () => {
  clearTimeout(debounce)
  debounce = setTimeout(fetchMedia, 320)
})

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

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmtSize(bytes: number | null): string {
  if (!bytes) return ''
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}
function sourceLabel(v: MediaItem) {
  if (v.has_media_file) return v.is_audio ? 'Audio' : 'Video'
  if (v.media_url) return youtubeId(v.media_url) ? 'YouTube' : 'Link'
  return 'No source'
}
function cardIcon(v: MediaItem) {
  return v.is_audio ? 'music' : 'video'
}

// Landing-page video/audio cards link here with ?id=<media id> so the exact
// item that was clicked opens straight in the player. The hero search bar's
// "Video" scope instead lands here with ?search=<term>.
onMounted(async () => {
  const q = route.query.search
  if (typeof q === 'string' && q.trim()) searchQuery.value = q

  await fetchMedia()
  const requestedId = Number(route.query.id)
  if (!requestedId) return
  const match = mediaItems.value.find(m => m.id === requestedId)
  if (match) openPlayer(match)
})
</script>

<template>
  <!-- Header -->
  <div class="shead">
    <div>
      <h2>Media Library</h2>
      <p>Watch recorded lectures, documentaries &amp; oral histories, or listen to audio recordings from the library's collection</p>
    </div>
  </div>

  <!-- KPIs -->
  <div class="kpis" style="grid-template-columns:repeat(2,1fr)">
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n">{{ total.toLocaleString() }}</div>
        <div class="kpi-ic t-blue"><LucideIcon name="video" /></div>
      </div>
      <div class="kpi-l">Media Items</div>
    </div>
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-n">{{ totalViews.toLocaleString() }}</div>
        <div class="kpi-ic t-green"><LucideIcon name="eye" /></div>
      </div>
      <div class="kpi-l">Total Views</div>
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
      No media has been published yet — check back soon.
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
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .14s" class="doc-play-ov">
          <div style="width:44px;height:44px;border-radius:50%;background:rgba(12,33,71,.72);display:flex;align-items:center;justify-content:center">
            <LucideIcon name="play" style="width:18px;height:18px;color:#fff" />
          </div>
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
        <div class="doc-act" @click="openPlayer(v)">
          <LucideIcon :name="v.is_audio ? 'headphones' : 'play'" /> {{ v.is_audio ? 'Listen' : 'Watch' }}
        </div>
      </div>
    </div>
  </div>

  <!-- ── Player ── -->
  <LoomPlayer :open="playerOpen" :video="playerMedia" :mode="playerMode" :src="playerSrc" @close="closePlayer" />
</template>

<style scoped>
@keyframes pulse {
  0%, 100% { opacity: 1 }
  50%       { opacity: .4 }
}
.doc-head:hover .doc-play-ov { opacity: 1 }
</style>
