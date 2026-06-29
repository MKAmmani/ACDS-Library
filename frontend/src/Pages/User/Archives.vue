<script setup lang="ts">
import { ref, computed } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'

const searchQuery   = ref('')
const activeType    = ref('All')
const activeDecade  = ref('All')

const types   = ['All', 'Government Documents', 'Speeches', 'Photographs', 'Manuscripts', 'Maps & Plans']
const decades = ['All', '2000s–2020s', '1990s', '1980s', '1970s', '1960s & Before']

interface ArchiveItem {
  id: number; title: string; creator: string; date: string
  type: string; decade: string; items: number; digitised: boolean; description: string
}

const items: ArchiveItem[] = [
  { id:1, title:'Aminu Kano Personal Correspondence Collection',    creator:'Aminu Kano Estate',          date:'1950–1983', type:'Manuscripts',          decade:'1960s & Before', items:1240, digitised:true,  description:'Letters, speeches, and personal notes of Mallam Aminu Kano, founder of NEPU.' },
  { id:2, title:'NEPU Party Records 1950–1966',                    creator:'Northern Elements Progressive Union', date:'1950–1966', type:'Government Documents', decade:'1960s & Before', items:620,  digitised:true,  description:'Official party records, manifestos, and meeting minutes.' },
  { id:3, title:'Kano State Government Gazettes 1968–2000',        creator:'Kano State Govt.',            date:'1968–2000', type:'Government Documents', decade:'1970s',           items:840,  digitised:false, description:'Complete set of Kano State official gazettes.' },
  { id:4, title:'Northern Nigeria Governor-General Dispatches',    creator:'British Colonial Office',     date:'1900–1960', type:'Government Documents', decade:'1960s & Before', items:380,  digitised:false, description:'Colonial-era dispatches between Lagos and London.' },
  { id:5, title:'Mambayya House Photographic Archive',             creator:'Centre for Democratic Research', date:'1980–2010', type:'Photographs',         decade:'1980s',           items:2100, digitised:true,  description:'Photographs documenting democracy conferences and events.' },
  { id:6, title:'Nigerian Constitutional Conference Reports 1995', creator:'Federal Govt. of Nigeria',    date:'1995',      type:'Government Documents', decade:'1990s',           items:14,   digitised:true,  description:'Full proceedings of the 1995 Constitutional Conference.' },
  { id:7, title:'Aminu Kano Speeches Transcripts',                 creator:'Aminu Kano',                  date:'1954–1982', type:'Speeches',             decade:'1960s & Before', items:186,  digitised:true,  description:'Transcripts and audio recordings of key political speeches.' },
  { id:8, title:'Kano City Historical Survey Maps 1903–1960',      creator:'Survey Dept., N. Nigeria',    date:'1903–1960', type:'Maps & Plans',         decade:'1960s & Before', items:64,   digitised:false, description:'Historical cartographic records of Kano city and environs.' },
]

const filtered = computed(() => items.filter(item => {
  const matchQ = !searchQuery.value || item.title.toLowerCase().includes(searchQuery.value.toLowerCase()) || item.creator.toLowerCase().includes(searchQuery.value.toLowerCase())
  const matchT = activeType.value   === 'All' || item.type   === activeType.value
  const matchD = activeDecade.value === 'All' || item.decade === activeDecade.value
  return matchQ && matchT && matchD
}))

const typeIcon = (t: string) => ({
  'Government Documents':'receipt-text', 'Speeches':'message-square-text',
  'Photographs':'eye', 'Manuscripts':'pencil', 'Maps & Plans':'map-pin',
}[t] ?? 'archive')
</script>

<template>
  <!-- Page header -->
  <div class="relative overflow-hidden" style="background:linear-gradient(115deg,#2B2D31 0%,#33526E 60%,#22304F 100%);padding:40px 0 0">
    <div class="relative z-[2] max-w-[1260px] mx-auto px-4 sm:px-7">
      <div class="inline-flex items-center gap-2 text-[11px] font-semibold tracking-[.18em] uppercase text-[var(--gold)] mb-3">
        <span class="w-6 h-px bg-[var(--gold)]"></span> Special Collections
      </div>
      <h1 class="text-[clamp(24px,3.5vw,40px)] font-bold text-white leading-tight tracking-tight mb-2" style="font-family:var(--display)">
        Historical <em class="not-italic text-[var(--gold)]">Archives</em>
      </h1>
      <p class="hidden sm:block text-[14.5px] text-white/70 font-light max-w-[540px] leading-[1.7] mb-5">
        Explore rare manuscripts, government documents, photographs, and primary sources spanning over a century of Nigerian political history.
      </p>
      <!-- Search bar -->
      <div class="bg-white rounded-t-xl p-5" style="box-shadow:0 -10px 36px rgba(11,46,99,.2)">
        <div class="flex gap-2.5">
          <div class="flex-1 flex items-center gap-3 border-[1.5px] border-[var(--line)] rounded-[10px] px-4 transition-all focus-within:border-[var(--blue)]">
            <LucideIcon name="search" :size="18" class="text-[var(--faint)] flex-shrink-0" />
            <input v-model="searchQuery" type="text" placeholder="Search archives by title, creator, or keyword…"
              class="flex-1 border-none outline-none py-3 text-[15px] text-[var(--ink)] bg-transparent placeholder:text-[var(--faint)]" />
          </div>
          <button class="btn btn-primary btn-sm"><LucideIcon name="search" :size="15" /> Search</button>
        </div>
        <div class="hidden sm:flex gap-5 mt-4 pt-4 border-t border-[var(--line-soft)]">
          <div v-for="[icon,val,lbl] in [['archive','440','Archive items'],['circle-check-big','6','Digitised collections'],['book-marked','1903','Oldest item (year)']]"
               :key="lbl" class="flex items-center gap-1.5 text-[12.5px] text-[var(--muted)]">
            <LucideIcon :name="icon" :size="15" class="text-[var(--blue)]" />
            <strong class="text-[var(--navy)]">{{ val }}</strong> {{ lbl }}
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Body -->
  <div class="max-w-[1260px] mx-auto px-4 sm:px-7 py-6 pb-16">
    <!-- Filters -->
    <div class="flex gap-2 flex-wrap items-center mb-3">
      <span class="text-[12px] font-semibold text-[var(--muted)] mr-1">Type:</span>
      <button v-for="t in types" :key="t"
        :class="['chip', activeType===t ? 'active' : '']" @click="activeType=t">{{ t }}</button>
    </div>
    <div class="flex gap-2 flex-wrap items-center mb-6">
      <span class="text-[12px] font-semibold text-[var(--muted)] mr-1">Period:</span>
      <button v-for="d in decades" :key="d"
        :class="['chip', activeDecade===d ? 'active' : '']" @click="activeDecade=d">{{ d }}</button>
    </div>

    <div class="text-[13px] text-[var(--muted)] mb-4">
      <strong class="text-[var(--navy)]">{{ filtered.length }}</strong> collections
    </div>

    <!-- Archive cards -->
    <div class="flex flex-col gap-3">
      <div v-for="item in filtered" :key="item.id"
        class="bg-white border border-[var(--line)] rounded-xl p-5 cursor-pointer transition-all duration-200 hover:border-[var(--blue-100)] hover:shadow-[var(--sh2)]"
        style="box-shadow:var(--sh1)">
        <div class="flex gap-3 sm:gap-4 items-start">
          <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-[9px] bg-[var(--bg)] flex items-center justify-center flex-shrink-0 mt-0.5">
            <LucideIcon :name="typeIcon(item.type)" :size="17" class="text-[var(--blue)]" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2 mb-1 flex-wrap">
              <div class="text-[14px] sm:text-[15px] font-bold text-[var(--navy)] leading-snug" style="font-family:var(--display)">{{ item.title }}</div>
              <div class="flex items-center gap-1.5 flex-shrink-0">
                <span v-if="item.digitised" class="badge b-blue"><LucideIcon name="monitor" :size="11" /> Digitised</span>
                <span v-else class="badge b-gray">Physical Only</span>
              </div>
            </div>
            <div class="flex items-center gap-2 sm:gap-3 text-[11.5px] sm:text-[12px] text-[var(--muted)] mb-2 flex-wrap">
              <span class="flex items-center gap-1"><LucideIcon name="user" :size="12" /> {{ item.creator }}</span>
              <span class="flex items-center gap-1"><LucideIcon name="calendar" :size="12" /> {{ item.date }}</span>
              <span class="flex items-center gap-1"><LucideIcon name="archive" :size="12" /> {{ item.items }} items</span>
            </div>
            <p class="hidden sm:block text-[13px] text-[var(--muted)] font-light leading-snug mb-3">{{ item.description }}</p>
            <div class="flex items-center gap-2 flex-wrap mb-3 sm:mb-0">
              <span class="tag">{{ item.type }}</span>
              <span class="tag">{{ item.decade }}</span>
            </div>
          </div>
          <div class="flex flex-col gap-2 flex-shrink-0">
            <button :class="['btn btn-sm', item.digitised ? 'btn-primary' : 'btn-ghost']">
              <LucideIcon :name="item.digitised ? 'book-open' : 'map-pin'" :size="13" />
              <span class="hidden sm:inline">{{ item.digitised ? 'View Online' : 'Visit Library' }}</span>
            </button>
            <button class="btn btn-ghost btn-sm">
              <LucideIcon name="bookmark" :size="13" /> <span class="hidden sm:inline">Save</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!filtered.length" class="flex flex-col items-center py-16 text-center">
      <LucideIcon name="archive" :size="44" class="text-[var(--faint)] mb-3" />
      <div class="text-[15px] font-semibold text-[var(--navy)] mb-1">No collections found</div>
      <p class="text-[13px] text-[var(--muted)]">Try adjusting your type or period filters.</p>
    </div>
  </div>
</template>
