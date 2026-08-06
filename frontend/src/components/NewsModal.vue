<script setup lang="ts">
// Full-detail popup for a single news post — used by the landing page's News
// & Events teaser and by the standalone /user/news listing so a click always
// shows the complete write-up instead of the trimmed card copy.
import LucideIcon from '@/components/LucideIcon.vue'

interface NewsDetail {
  title: string
  body: string
  date: string
  icon: string
  tone: string
  tag: string
}

defineProps<{
  open: boolean
  news: NewsDetail | null
}>()
const emit = defineEmits<{ close: [] }>()

const TONES: Record<string, { bg: string; text: string }> = {
  blue:   { bg: 'bg-[var(--blue-50)]',   text: 'text-[var(--blue)]' },
  sky:    { bg: 'bg-[var(--blue-50)]',   text: 'text-[var(--sky)]' },
  gold:   { bg: 'bg-[var(--gold-50)]',   text: 'text-[var(--amber)]' },
  purple: { bg: 'bg-[var(--purple-50)]', text: 'text-[var(--purple)]' },
  amber:  { bg: 'bg-[var(--amber-50)]',  text: 'text-[var(--amber)]' },
  green:  { bg: 'bg-[var(--green-50)]',  text: 'text-[var(--green)]' },
  red:    { bg: 'bg-[var(--red-50)]',    text: 'text-[var(--red)]' },
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open && news" class="fixed inset-0 z-[999] flex items-center justify-center p-4"
         style="background:rgba(6,20,48,.55)" @click.self="emit('close')">
      <div class="bg-white rounded-2xl max-w-[560px] w-full max-h-[85vh] overflow-y-auto shadow-2xl">
        <div class="flex items-start gap-3.5 p-5 border-b border-[var(--line)] sticky top-0 bg-white">
          <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0" :class="TONES[news.tone]?.bg ?? 'bg-[var(--blue-50)]'">
            <LucideIcon :name="news.icon" :size="21" :class="TONES[news.tone]?.text ?? 'text-[var(--blue)]'" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-[10px] font-bold tracking-[.07em] uppercase text-[var(--amber)]">{{ news.tag }}</div>
            <div class="font-[var(--display)] text-[17px] font-bold text-[var(--navy)] leading-snug mt-1">{{ news.title }}</div>
            <div class="text-[11px] text-[var(--faint)] mt-1.5 flex items-center gap-1">
              <LucideIcon name="calendar" :size="12" /> {{ news.date }}
            </div>
          </div>
          <button class="w-8 h-8 flex items-center justify-center rounded-md border border-[var(--line)] text-[var(--muted)] hover:bg-[var(--bg)] flex-shrink-0" @click="emit('close')">
            <LucideIcon name="x" :size="16" />
          </button>
        </div>
        <div class="p-5 text-[13.5px] text-[var(--ink)] leading-[1.75] whitespace-pre-line">
          {{ news.body || 'No further details were provided for this post.' }}
        </div>
      </div>
    </div>
  </Teleport>
</template>
