<script setup lang="ts">
// A Loom-style full-screen video viewer — custom-built controls (progress
// scrub w/ preview, volume, speed, skip ±10s, PiP, fullscreen, keyboard
// shortcuts) themed with the ACDS brand palette. Works identically for a
// native <video> source and a YouTube source (driven via the YouTube
// IFrame API so both share the exact same control bar).
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'

interface PlayerVideo {
  title: string
  kind?: string | null
  tag?: string | null
  duration_label?: string | null
  views?: number
  published_at?: string | null
  file_size?: number | null
  media_url?: string | null
  has_media_file?: boolean
}

const props = defineProps<{
  open: boolean
  video: PlayerVideo | null
  mode: 'youtube' | 'video' | 'none'
  src: string
}>()
const emit = defineEmits<{ close: [] }>()

// ── Refs ─────────────────────────────────────────────────────────────────────
const stageEl    = ref<HTMLElement>()
const videoEl    = ref<HTMLVideoElement>()
const ytHost     = ref<HTMLElement>()
const progressEl = ref<HTMLElement>()

// ── Playback state ───────────────────────────────────────────────────────────
const playing        = ref(false)
const buffering       = ref(false)
const currentTime     = ref(0)
const duration        = ref(0)
const bufferedEnd     = ref(0)
const volume          = ref(1)
const muted           = ref(false)
const speed           = ref(1)
const speeds          = [0.5, 0.75, 1, 1.25, 1.5, 2]
const isFullscreen    = ref(false)
const isAudioOnly     = ref(false)
const controlsVisible = ref(true)
const volOpen         = ref(false)
const speedOpen       = ref(false)
const hoverTime       = ref<number | null>(null)
const hoverX          = ref(0)

let hideTimer: ReturnType<typeof setTimeout> | null = null
let scrubbing = false

const playedPct   = computed(() => (duration.value ? (currentTime.value / duration.value) * 100 : 0))
const bufferedPct = computed(() => (duration.value ? (bufferedEnd.value / duration.value) * 100 : 0))
const volIcon      = computed(() => (muted.value || volume.value === 0 ? 'volume-x' : volume.value < 0.5 ? 'volume-1' : 'volume-2'))

// ── Meta panel helpers ───────────────────────────────────────────────────────
const dateLabel = computed(() =>
  props.video?.published_at
    ? new Date(props.video.published_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
    : '—'
)
function fmtSize(bytes?: number | null) {
  if (!bytes) return ''
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}
const fileSizeLabel = computed(() => fmtSize(props.video?.file_size))
function youtubeIdOf(url: string): string | null {
  const m = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{11})/)
  return m?.[1] ?? null
}
const srcLabel = computed(() => {
  const v = props.video
  if (!v) return '—'
  if (v.has_media_file) return isAudioOnly.value ? 'Uploaded audio' : 'Uploaded file'
  if (v.media_url) return youtubeIdOf(v.media_url) ? 'YouTube' : (isAudioOnly.value ? 'Linked audio' : 'External link')
  return 'No source'
})

function fmtTime(t: number): string {
  if (!isFinite(t) || t < 0) t = 0
  const h  = Math.floor(t / 3600)
  const m  = Math.floor((t % 3600) / 60)
  const s  = Math.floor(t % 60)
  const mm = h > 0 ? String(m).padStart(2, '0') : String(m)
  const ss = String(s).padStart(2, '0')
  return h > 0 ? `${h}:${mm}:${ss}` : `${mm}:${ss}`
}

// ── YouTube IFrame API ───────────────────────────────────────────────────────
let ytPlayer: any = null
let ytTickHandle: ReturnType<typeof setInterval> | null = null

function loadYouTubeAPI(): Promise<any> {
  return new Promise((resolve) => {
    const w = window as any
    if (w.YT && w.YT.Player) { resolve(w.YT); return }
    if (!document.getElementById('yt-iframe-api')) {
      const tag = document.createElement('script')
      tag.id  = 'yt-iframe-api'
      tag.src = 'https://www.youtube.com/iframe_api'
      document.head.appendChild(tag)
    }
    const prev = w.onYouTubeIframeAPIReady
    w.onYouTubeIframeAPIReady = () => { prev?.(); resolve(w.YT) }
  })
}

async function initYouTube() {
  if (!ytHost.value) return
  const id = youtubeIdOf(props.src) ?? (props.src.match(/embed\/([\w-]{11})/) || [])[1]
  if (!id) return
  const YT = await loadYouTubeAPI()
  if (!ytHost.value) return // closed while API was loading
  ytPlayer = new YT.Player(ytHost.value, {
    videoId: id,
    playerVars: { controls: 0, modestbranding: 1, rel: 0, playsinline: 1, disablekb: 1, fs: 0, iv_load_policy: 3 },
    events: {
      onReady: (e: any) => {
        duration.value = e.target.getDuration()
        volume.value   = e.target.getVolume() / 100
        e.target.playVideo()
      },
      onStateChange: (e: any) => {
        playing.value   = e.data === 1
        buffering.value = e.data === 3
        if (e.data === 1) startYtTick(); else stopYtTick()
      },
    },
  })
}
function startYtTick() {
  stopYtTick()
  ytTickHandle = setInterval(() => {
    if (!ytPlayer?.getCurrentTime) return
    currentTime.value = ytPlayer.getCurrentTime()
    duration.value    = ytPlayer.getDuration() || duration.value
    bufferedEnd.value = (ytPlayer.getVideoLoadedFraction?.() ?? 0) * duration.value
  }, 250)
}
function stopYtTick() { if (ytTickHandle) clearInterval(ytTickHandle); ytTickHandle = null }
function destroyYouTube() {
  stopYtTick()
  try { ytPlayer?.destroy?.() } catch { /* noop */ }
  ytPlayer = null
}

// ── Unified controls (native <video> or YouTube) ────────────────────────────
function togglePlay() {
  if (props.mode === 'video' && videoEl.value) {
    videoEl.value.paused ? videoEl.value.play().catch(() => {}) : videoEl.value.pause()
  } else if (props.mode === 'youtube' && ytPlayer) {
    playing.value ? ytPlayer.pauseVideo() : ytPlayer.playVideo()
  }
}
function seekTo(t: number) {
  const clamped = Math.min(Math.max(t, 0), duration.value || t)
  if (props.mode === 'video' && videoEl.value) videoEl.value.currentTime = clamped
  else if (props.mode === 'youtube' && ytPlayer) ytPlayer.seekTo(clamped, true)
  currentTime.value = clamped
}
function skip(delta: number) { seekTo(currentTime.value + delta) }
function setVolume() {
  if (props.mode === 'video' && videoEl.value) videoEl.value.volume = volume.value
  else if (props.mode === 'youtube' && ytPlayer) ytPlayer.setVolume(volume.value * 100)
  if (volume.value > 0) {
    muted.value = false
    if (props.mode === 'video' && videoEl.value) videoEl.value.muted = false
  }
}
function toggleMute() {
  muted.value = !muted.value
  if (props.mode === 'video' && videoEl.value) videoEl.value.muted = muted.value
  else if (props.mode === 'youtube' && ytPlayer) (muted.value ? ytPlayer.mute() : ytPlayer.unMute())
}
function setSpeed(s: number) {
  speed.value = s
  speedOpen.value = false
  if (props.mode === 'video' && videoEl.value) videoEl.value.playbackRate = s
  else if (props.mode === 'youtube' && ytPlayer) ytPlayer.setPlaybackRate(s)
}

// ── Native <video> events ────────────────────────────────────────────────────
function onTimeUpdate() {
  if (!videoEl.value) return
  currentTime.value = videoEl.value.currentTime
  const b = videoEl.value.buffered
  bufferedEnd.value = b.length ? b.end(b.length - 1) : 0
}
function onLoadedMeta() {
  if (!videoEl.value) return
  duration.value = videoEl.value.duration
  videoEl.value.volume = volume.value
  // An audio-only source (podcast, oral history recording, …) decodes fine
  // in a <video> element but reports a 0×0 frame — swap in the branded
  // "now playing" art instead of showing a blank rectangle.
  isAudioOnly.value = videoEl.value.videoWidth === 0
}
function onPlay()  { playing.value = true }
function onPause() { playing.value = false }
function onEnded() { playing.value = false }

// ── Progress bar scrubbing ───────────────────────────────────────────────────
function pctFromEvent(e: PointerEvent): number {
  const el = progressEl.value
  if (!el) return 0
  const rect = el.getBoundingClientRect()
  const x = Math.min(Math.max(e.clientX - rect.left, 0), rect.width)
  return rect.width ? x / rect.width : 0
}
function startScrub(e: PointerEvent) {
  scrubbing = true
  updateScrub(e)
  window.addEventListener('pointermove', updateScrub)
  window.addEventListener('pointerup', endScrub)
}
function updateScrub(e: PointerEvent) {
  const pct = pctFromEvent(e)
  const t = pct * (duration.value || 0)
  hoverTime.value = t
  hoverX.value = pct * (progressEl.value?.clientWidth || 0)
  if (scrubbing) seekTo(t)
}
function endScrub() {
  scrubbing = false
  window.removeEventListener('pointermove', updateScrub)
  window.removeEventListener('pointerup', endScrub)
}
function hoverScrub(e: PointerEvent) {
  if (scrubbing) return
  const pct = pctFromEvent(e)
  hoverTime.value = pct * (duration.value || 0)
  hoverX.value = pct * (progressEl.value?.clientWidth || 0)
}
function hidePreview() { if (!scrubbing) hoverTime.value = null }

// ── Auto-hide control bar ────────────────────────────────────────────────────
function showControlsTemporarily() {
  controlsVisible.value = true
  if (hideTimer) clearTimeout(hideTimer)
  hideTimer = setTimeout(() => {
    if (playing.value && !volOpen.value && !speedOpen.value) controlsVisible.value = false
  }, 2600)
}
function scheduleHideControls() {
  if (hideTimer) clearTimeout(hideTimer)
  if (playing.value) hideTimer = setTimeout(() => (controlsVisible.value = false), 400)
}

// ── Fullscreen / PiP ─────────────────────────────────────────────────────────
function toggleFullscreenStage() {
  if (!document.fullscreenElement) stageEl.value?.requestFullscreen?.().catch(() => {})
  else document.exitFullscreen?.()
}
function onFsChange() { isFullscreen.value = !!document.fullscreenElement }
async function togglePiP() {
  if (!videoEl.value) return
  try {
    if (document.pictureInPictureElement) await document.exitPictureInPicture()
    else await videoEl.value.requestPictureInPicture()
  } catch { /* PiP unsupported / denied — ignore */ }
}

// ── Keyboard shortcuts ────────────────────────────────────────────────────────
function onKeydown(e: KeyboardEvent) {
  if (!props.open) return
  switch (e.key) {
    case ' ': case 'k': e.preventDefault(); togglePlay(); break
    case 'ArrowRight': skip(5); break
    case 'ArrowLeft':  skip(-5); break
    case 'ArrowUp':    e.preventDefault(); volume.value = Math.min(1, volume.value + 0.05); setVolume(); break
    case 'ArrowDown':  e.preventDefault(); volume.value = Math.max(0, volume.value - 0.05); setVolume(); break
    case 'm': toggleMute(); break
    case 'f': toggleFullscreenStage(); break
    case 'Escape': if (!document.fullscreenElement) emit('close'); break
  }
}

// ── Open / close lifecycle ───────────────────────────────────────────────────
function resetState() {
  playing.value = false; buffering.value = false
  currentTime.value = 0; duration.value = 0; bufferedEnd.value = 0
  speed.value = 1; controlsVisible.value = true; isAudioOnly.value = false
  hoverTime.value = null; volOpen.value = false; speedOpen.value = false
}

watch(() => props.open, async (isOpen) => {
  if (isOpen) {
    resetState()
    window.addEventListener('keydown', onKeydown)
    document.addEventListener('fullscreenchange', onFsChange)
    document.body.style.overflow = 'hidden'
    await nextTick()
    if (props.mode === 'youtube') initYouTube()
    else if (props.mode === 'video' && videoEl.value) {
      videoEl.value.volume = volume.value
      videoEl.value.play().catch(() => {})
    }
  } else {
    window.removeEventListener('keydown', onKeydown)
    document.removeEventListener('fullscreenchange', onFsChange)
    document.body.style.overflow = ''
    videoEl.value?.pause()
    destroyYouTube()
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  document.removeEventListener('fullscreenchange', onFsChange)
  window.removeEventListener('pointermove', updateScrub)
  window.removeEventListener('pointerup', endScrub)
  destroyYouTube()
})
</script>

<template>
  <Teleport to="body">
    <Transition name="lp-fade" appear>
      <div v-if="open" class="lp-root">
        <!-- Header -->
        <div class="lp-head">
          <div class="lp-head-l">
            <button class="lp-icbtn" title="Close (Esc)" @click="emit('close')"><LucideIcon name="x" /></button>
            <div class="lp-head-info">
              <div class="lp-head-title">{{ video?.title }}</div>
              <div class="lp-head-sub">
                <span v-if="video?.kind">{{ video.kind }}</span>
                <span v-if="video?.kind">·</span>
                <span>{{ (video?.views ?? 0).toLocaleString() }} views</span>
                <span>·</span>
                <span>{{ dateLabel }}</span>
              </div>
            </div>
          </div>
          <div class="lp-head-r">
            <span v-if="video?.tag" class="lp-tag">{{ video.tag }}</span>
            <button class="lp-icbtn" :title="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'" @click="toggleFullscreenStage">
              <LucideIcon :name="isFullscreen ? 'minimize' : 'maximize'" />
            </button>
          </div>
        </div>

        <!-- Body -->
        <div class="lp-body">
          <div
            ref="stageEl"
            class="lp-stage"
            @mousemove="showControlsTemporarily"
            @mouseleave="scheduleHideControls"
            @dblclick="toggleFullscreenStage"
          >
            <video
              v-if="mode === 'video'"
              ref="videoEl"
              :src="src"
              class="lp-video"
              playsinline
              @click="togglePlay"
              @timeupdate="onTimeUpdate"
              @loadedmetadata="onLoadedMeta"
              @play="onPlay"
              @pause="onPause"
              @ended="onEnded"
              @waiting="buffering = true"
              @playing="buffering = false"
            ></video>
            <div v-else-if="mode === 'youtube'" class="lp-yt" @click="togglePlay">
              <div ref="ytHost"></div>
            </div>
            <div v-else class="lp-empty">
              <LucideIcon name="video-off" class="lp-empty-ic" />
              No playable source for this video yet.
            </div>

            <div v-if="mode === 'video' && isAudioOnly" class="lp-audio-art">
              <div class="lp-audio-ic"><LucideIcon name="music" :size="38" /></div>
              <div class="lp-audio-title">{{ video?.title }}</div>
              <div v-if="video?.kind" class="lp-audio-sub">{{ video.kind }}</div>
            </div>

            <button v-if="mode !== 'none' && !playing" class="lp-bigplay" @click="togglePlay">
              <LucideIcon name="play" />
            </button>

            <div v-if="buffering" class="lp-spinner"></div>

            <div v-if="mode !== 'none'" class="lp-controls" :class="{ show: controlsVisible || !playing }" @click.stop>
              <div
                ref="progressEl"
                class="lp-progress"
                @pointerdown="startScrub"
                @pointermove="hoverScrub"
                @pointerleave="hidePreview"
              >
                <div class="lp-progress-track">
                  <div class="lp-progress-buffered" :style="{ width: bufferedPct + '%' }"></div>
                  <div class="lp-progress-fill" :style="{ width: playedPct + '%' }"></div>
                  <div class="lp-progress-knob" :style="{ left: playedPct + '%' }"></div>
                </div>
                <div v-if="hoverTime !== null" class="lp-progress-tip" :style="{ left: hoverX + 'px' }">{{ fmtTime(hoverTime) }}</div>
              </div>

              <div class="lp-ctlrow">
                <button class="lp-ctl" :title="playing ? 'Pause (space)' : 'Play (space)'" @click="togglePlay">
                  <LucideIcon :name="playing ? 'pause' : 'play'" />
                </button>
                <button class="lp-ctl" title="Back 10s" @click="skip(-10)"><LucideIcon name="rotate-ccw" /></button>
                <button class="lp-ctl" title="Forward 10s" @click="skip(10)"><LucideIcon name="rotate-cw" /></button>

                <div class="lp-vol" @mouseenter="volOpen = true" @mouseleave="volOpen = false">
                  <button class="lp-ctl" title="Mute (m)" @click="toggleMute"><LucideIcon :name="volIcon" /></button>
                  <div class="lp-vol-pop" :class="{ open: volOpen }">
                    <input v-model.number="volume" class="lp-vol-slider" type="range" min="0" max="1" step="0.01" @input="setVolume" />
                  </div>
                </div>

                <div class="lp-time">{{ fmtTime(currentTime) }}<span class="lp-time-sep">/</span>{{ fmtTime(duration) }}</div>

                <div class="lp-spacer"></div>

                <div class="lp-speed">
                  <button class="lp-ctl lp-ctl-txt" @click="speedOpen = !speedOpen">{{ speed }}×</button>
                  <div v-if="speedOpen" class="lp-speed-menu" @mouseleave="speedOpen = false">
                    <div v-for="s in speeds" :key="s" class="lp-speed-opt" :class="{ on: s === speed }" @click="setSpeed(s)">{{ s }}×</div>
                  </div>
                </div>

                <button v-if="mode === 'video'" class="lp-ctl" title="Picture in picture" @click="togglePiP">
                  <LucideIcon name="picture-in-picture-2" />
                </button>
                <button class="lp-ctl" :title="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'" @click="toggleFullscreenStage">
                  <LucideIcon :name="isFullscreen ? 'minimize' : 'maximize'" />
                </button>
              </div>
            </div>
          </div>

          <!-- Sidebar -->
          <div class="lp-side">
            <div class="lp-side-h">Video Info</div>
            <div class="lp-info-row"><span>Kind</span><b>{{ video?.kind || '—' }}</b></div>
            <div class="lp-info-row"><span>Tag</span><b>{{ video?.tag || '—' }}</b></div>
            <div class="lp-info-row"><span>Duration</span><b>{{ video?.duration_label || fmtTime(duration) }}</b></div>
            <div class="lp-info-row"><span>Views</span><b>{{ (video?.views ?? 0).toLocaleString() }}</b></div>
            <div class="lp-info-row"><span>Published</span><b>{{ dateLabel }}</b></div>
            <div v-if="fileSizeLabel" class="lp-info-row"><span>File size</span><b>{{ fileSizeLabel }}</b></div>
            <div class="lp-info-row"><span>Source</span><b>{{ srcLabel }}</b></div>

            <a v-if="video?.media_url" class="lp-openlink" :href="video.media_url" target="_blank" rel="noopener">
              <LucideIcon name="arrow-up-right" :size="14" /> Open original source
            </a>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.lp-fade-enter-active, .lp-fade-leave-active { transition: opacity .2s ease; }
.lp-fade-enter-from, .lp-fade-leave-to { opacity: 0; }

.lp-root {
  position: fixed; inset: 0; z-index: 999;
  display: flex; flex-direction: column;
  background: #060D1E;
  color: #fff;
  font-family: var(--ui);
}

/* ── Header ── */
.lp-head {
  flex-shrink: 0; height: 60px;
  display: flex; align-items: center; justify-content: space-between; gap: 14px;
  padding: 0 18px;
  background: rgba(8, 16, 32, .92);
  border-bottom: 1px solid rgba(255, 255, 255, .08);
  backdrop-filter: blur(8px);
}
.lp-head-l { display: flex; align-items: center; gap: 12px; min-width: 0; }
.lp-head-info { min-width: 0; }
.lp-head-title { font-family: var(--display); font-size: 14.5px; font-weight: 700; color: #fff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 50vw; }
.lp-head-sub { font-size: 11px; color: rgba(255, 255, 255, .5); margin-top: 2px; display: flex; gap: 6px; flex-wrap: wrap; }
.lp-head-r { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.lp-tag {
  font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
  padding: 4px 9px; border-radius: 99px;
  background: rgba(23, 99, 201, .18); color: #7fb3f5; border: 1px solid rgba(23, 99, 201, .38);
}
.lp-icbtn {
  width: 34px; height: 34px; border-radius: 8px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  color: rgba(255, 255, 255, .75); background: rgba(255, 255, 255, .06);
  border: 1px solid rgba(255, 255, 255, .09); transition: all .15s;
}
.lp-icbtn:hover { background: rgba(255, 255, 255, .15); color: #fff; border-color: rgba(255, 255, 255, .18); }
.lp-icbtn svg { width: 16px; height: 16px; }

/* ── Body / stage ── */
.lp-body { flex: 1; display: flex; min-height: 0; }
.lp-stage { flex: 1; position: relative; background: #000; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.lp-video { width: 100%; height: 100%; object-fit: contain; background: #000; cursor: pointer; }
.lp-yt { position: relative; width: 100%; height: 100%; cursor: pointer; }
.lp-yt :deep(iframe) { position: absolute; inset: 0; width: 100% !important; height: 100% !important; border: 0; pointer-events: none; }
.lp-empty { display: flex; flex-direction: column; align-items: center; gap: 10px; color: rgba(255, 255, 255, .5); font-size: 13px; padding: 30px; text-align: center; }
.lp-empty-ic { width: 34px; height: 34px; opacity: .6; }

/* ── Audio-only "now playing" art (shown over the invisible <video>) ── */
.lp-audio-art {
  position: absolute; inset: 0; z-index: 1; pointer-events: none;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px;
  padding: 40px; text-align: center;
  background: radial-gradient(circle at 50% 42%, #16234A 0%, #0A1428 62%, #060D1E 100%);
}
.lp-audio-ic {
  width: 108px; height: 108px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(150deg, var(--blue), var(--sky));
  box-shadow: 0 16px 40px rgba(23, 99, 201, .35), 0 0 0 1px rgba(255, 255, 255, .08);
  color: #fff;
}
.lp-audio-title { font-family: var(--display); font-size: 19px; font-weight: 700; color: #fff; max-width: 480px; line-height: 1.3; }
.lp-audio-sub { font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: rgba(255, 255, 255, .45); }

.lp-bigplay {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
  z-index: 2;
  width: 74px; height: 74px; border-radius: 50%;
  background: rgba(23, 99, 201, .85); border: 1px solid rgba(255, 255, 255, .3);
  display: flex; align-items: center; justify-content: center; color: #fff;
  backdrop-filter: blur(4px); transition: transform .15s, background .15s;
  box-shadow: 0 10px 30px rgba(0, 0, 0, .45);
}
.lp-bigplay:hover { transform: translate(-50%, -50%) scale(1.08); background: var(--blue); }
.lp-bigplay svg { width: 30px; height: 30px; margin-left: 3px; }

.lp-spinner {
  position: absolute; top: 50%; left: 50%; width: 40px; height: 40px; margin: -20px 0 0 -20px;
  z-index: 2;
  border-radius: 50%; border: 3px solid rgba(255, 255, 255, .25); border-top-color: #fff;
  animation: lp-spin .8s linear infinite;
}
@keyframes lp-spin { to { transform: rotate(360deg); } }

/* ── Control bar ── */
.lp-controls {
  position: absolute; left: 0; right: 0; bottom: 0;
  z-index: 3;
  padding: 26px 18px 14px;
  background: linear-gradient(to top, rgba(4, 9, 20, .94), rgba(4, 9, 20, .6) 55%, transparent);
  opacity: 0; transform: translateY(6px); transition: opacity .2s, transform .2s; pointer-events: none;
}
.lp-controls.show { opacity: 1; transform: translateY(0); pointer-events: auto; }

.lp-progress { position: relative; padding: 10px 0 6px; cursor: pointer; }
.lp-progress-track { position: relative; height: 4px; border-radius: 3px; background: rgba(255, 255, 255, .22); transition: height .12s; }
.lp-progress:hover .lp-progress-track { height: 6px; }
.lp-progress-buffered { position: absolute; inset: 0; width: 0; background: rgba(255, 255, 255, .32); border-radius: 3px; }
.lp-progress-fill { position: absolute; inset: 0; width: 0; background: linear-gradient(90deg, var(--blue), var(--sky)); border-radius: 3px; }
.lp-progress-knob {
  position: absolute; top: 50%; width: 13px; height: 13px; border-radius: 50%;
  background: #fff; border: 2px solid var(--blue); transform: translate(-50%, -50%);
  opacity: 0; transition: opacity .12s; box-shadow: 0 1px 4px rgba(0, 0, 0, .4);
}
.lp-progress:hover .lp-progress-knob { opacity: 1; }
.lp-progress-tip {
  position: absolute; bottom: 100%; transform: translateX(-50%); margin-bottom: 8px;
  background: rgba(8, 16, 32, .96); border: 1px solid rgba(255, 255, 255, .12); color: #fff;
  font-size: 11px; font-weight: 600; padding: 3px 7px; border-radius: 5px; pointer-events: none; white-space: nowrap;
}

.lp-ctlrow { display: flex; align-items: center; gap: 3px; }
.lp-ctl { width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 7px; color: #fff; transition: background .15s; flex-shrink: 0; }
.lp-ctl:hover { background: rgba(255, 255, 255, .14); }
.lp-ctl svg { width: 18px; height: 18px; }
.lp-ctl-txt { width: auto; padding: 0 10px; font-size: 12.5px; font-weight: 700; }
.lp-time { font-size: 12px; font-weight: 600; color: rgba(255, 255, 255, .85); margin-left: 6px; white-space: nowrap; font-variant-numeric: tabular-nums; }
.lp-time-sep { color: rgba(255, 255, 255, .4); margin: 0 5px; }
.lp-spacer { flex: 1; }

.lp-vol { position: relative; display: flex; align-items: center; }
.lp-vol-pop { width: 0; overflow: hidden; transition: width .18s ease; display: flex; align-items: center; }
.lp-vol-pop.open { width: 76px; margin-left: 2px; }
.lp-vol-slider { -webkit-appearance: none; appearance: none; width: 100%; height: 4px; border-radius: 2px; background: rgba(255, 255, 255, .25); outline: none; cursor: pointer; }
.lp-vol-slider::-webkit-slider-thumb { -webkit-appearance: none; width: 12px; height: 12px; border-radius: 50%; background: #fff; box-shadow: 0 0 0 3px rgba(23, 99, 201, .4); cursor: pointer; }
.lp-vol-slider::-moz-range-thumb { width: 12px; height: 12px; border: none; border-radius: 50%; background: #fff; box-shadow: 0 0 0 3px rgba(23, 99, 201, .4); cursor: pointer; }

.lp-speed { position: relative; }
.lp-speed-menu {
  position: absolute; bottom: calc(100% + 8px); right: 0;
  background: rgba(10, 20, 40, .98); border: 1px solid rgba(255, 255, 255, .1); border-radius: 8px;
  padding: 4px; min-width: 64px; box-shadow: 0 8px 24px rgba(0, 0, 0, .4); z-index: 5;
}
.lp-speed-opt { padding: 6px 10px; font-size: 12.5px; font-weight: 600; border-radius: 5px; color: rgba(255, 255, 255, .75); cursor: pointer; text-align: center; }
.lp-speed-opt:hover { background: rgba(255, 255, 255, .1); color: #fff; }
.lp-speed-opt.on { background: var(--blue); color: #fff; }

/* ── Sidebar ── */
.lp-side { width: 300px; flex-shrink: 0; background: #0D1B38; border-left: 1px solid rgba(255, 255, 255, .08); padding: 20px 18px; overflow-y: auto; }
.lp-side-h { font-size: 10.5px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: rgba(255, 255, 255, .4); margin-bottom: 14px; }
.lp-info-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 0; border-bottom: 1px solid rgba(255, 255, 255, .06); font-size: 12.5px; }
.lp-info-row span { color: rgba(255, 255, 255, .5); flex-shrink: 0; }
.lp-info-row b { color: #fff; font-weight: 600; text-align: right; }
.lp-openlink { margin-top: 16px; display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--sky); transition: color .15s; }
.lp-openlink:hover { color: #fff; }
.lp-openlink svg { flex-shrink: 0; }

/* ── Responsive ── */
@media (max-width: 900px) {
  .lp-side { display: none; }
}
@media (max-width: 560px) {
  .lp-head { height: 52px; padding: 0 12px; }
  .lp-head-title { max-width: 40vw; font-size: 13px; }
  .lp-head-sub span:nth-child(1), .lp-head-sub span:nth-child(2) { display: none; }
  .lp-controls { padding: 16px 10px 10px; }
  .lp-ctl { width: 30px; height: 30px; }
  .lp-bigplay { width: 60px; height: 60px; }
  .lp-bigplay svg { width: 24px; height: 24px; }
}
</style>
