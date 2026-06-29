<script setup lang="ts">
import { ref, computed } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'

const searchQuery  = ref('')
const activeSubject = ref('All')

const subjects = ['All', 'Political Science', 'Law', 'History', 'African Studies', 'Economics']

interface Journal {
  id: number; title: string; publisher: string; issn: string
  subject: string; issues: number; latestVol: string; access: 'open' | 'restricted'
}

const journals: Journal[] = [
  { id:1, title:'Journal of Democracy',                    publisher:'Johns Hopkins Univ. Press',  issn:'1045-5736', subject:'Political Science', issues:4,  latestVol:'Vol. 35 No. 1 · Jan 2024', access:'restricted' },
  { id:2, title:'African Affairs',                          publisher:'Oxford Univ. Press',         issn:'0001-9909', subject:'African Studies',   issues:4,  latestVol:'Vol. 123 No. 490 · 2024',  access:'restricted' },
  { id:3, title:'Journal of Modern African Studies',        publisher:'Cambridge Univ. Press',      issn:'0022-278X', subject:'African Studies',   issues:4,  latestVol:'Vol. 62 No. 1 · Mar 2024', access:'restricted' },
  { id:4, title:'Nigerian Journal of Public Law',           publisher:'Nigerian Bar Association',   issn:'0189-3351', subject:'Law',               issues:2,  latestVol:'Vol. 18 No. 2 · 2023',     access:'open'       },
  { id:5, title:'Electoral Studies',                        publisher:'Elsevier',                   issn:'0261-3794', subject:'Political Science', issues:4,  latestVol:'Vol. 88 · Apr 2024',        access:'restricted' },
  { id:6, title:'African Historical Studies',               publisher:'Boston Univ. Press',         issn:'0002-0206', subject:'History',           issues:3,  latestVol:'Vol. 57 No. 1 · 2024',     access:'restricted' },
  { id:7, title:'Journal of African Economies',             publisher:'Oxford Univ. Press',         issn:'0963-8024', subject:'Economics',         issues:5,  latestVol:'Vol. 33 No. 1 · Jan 2024', access:'restricted' },
  { id:8, title:'West African Review',                      publisher:'SUNY Press',                 issn:'1525-4488', subject:'African Studies',   issues:2,  latestVol:'Vol. 24 · 2024',            access:'open'       },
]

const filtered = computed(() => journals.filter(j => {
  const matchQ = !searchQuery.value || j.title.toLowerCase().includes(searchQuery.value.toLowerCase()) || j.publisher.toLowerCase().includes(searchQuery.value.toLowerCase())
  const matchS = activeSubject.value === 'All' || j.subject === activeSubject.value
  return matchQ && matchS
}))
</script>

<template>
  <!-- Page header -->
  <div class="relative overflow-hidden" style="background:linear-gradient(115deg,#0B2E63 0%,var(--blue) 48%,var(--sky) 100%);padding:40px 0 0">
    <div class="relative z-[2] max-w-[1260px] mx-auto px-4 sm:px-7">
      <div class="inline-flex items-center gap-2 text-[11px] font-semibold tracking-[.18em] uppercase text-[var(--gold)] mb-3">
        <span class="w-6 h-px bg-[var(--gold)]"></span> Academic Journals
      </div>
      <h1 class="text-[clamp(24px,3.5vw,40px)] font-bold text-white leading-tight tracking-tight mb-2" style="font-family:var(--display)">
        Journals <em class="not-italic text-[var(--gold)]">Collection</em>
      </h1>
      <p class="hidden sm:block text-[14.5px] text-white/70 font-light max-w-[520px] leading-[1.7] mb-5">
        Access 42 peer-reviewed academic journals on political science, African studies, law, history, and economics.
      </p>
      <!-- Search bar -->
      <div class="bg-white rounded-t-xl p-5" style="box-shadow:0 -10px 36px rgba(11,46,99,.18)">
        <div class="flex gap-2.5">
          <div class="flex-1 flex items-center gap-3 border-[1.5px] border-[var(--line)] rounded-[10px] px-4 transition-all focus-within:border-[var(--blue)]">
            <LucideIcon name="search" :size="18" class="text-[var(--faint)] flex-shrink-0" />
            <input v-model="searchQuery" type="text" placeholder="Search journals by title or publisher…"
              class="flex-1 border-none outline-none py-3 text-[15px] text-[var(--ink)] bg-transparent placeholder:text-[var(--faint)]" />
          </div>
          <button class="btn btn-primary btn-sm"><LucideIcon name="search" :size="15" /> Search</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Body -->
  <div class="max-w-[1260px] mx-auto px-4 sm:px-7 py-6 pb-16">
    <!-- Subject filters -->
    <div class="flex gap-2 flex-wrap items-center mb-6">
      <span class="text-[12px] font-semibold text-[var(--muted)] mr-1">Subject:</span>
      <button v-for="s in subjects" :key="s"
        :class="['chip', activeSubject===s ? 'active' : '']" @click="activeSubject=s">{{ s }}</button>
    </div>

    <div class="text-[13px] text-[var(--muted)] mb-4">
      <strong class="text-[var(--navy)]">{{ filtered.length }}</strong> journals
    </div>

    <!-- Journal list — scrollable on mobile -->
    <div class="bg-white border border-[var(--line)] rounded-xl overflow-hidden" style="box-shadow:var(--sh1)">
      <div class="overflow-x-auto">
        <div class="min-w-[660px]">
          <div class="grid px-5 py-3 bg-[var(--bg)] border-b border-[var(--line)]" style="grid-template-columns:1fr 160px 90px 140px">
            <span v-for="h in ['Journal / Publisher','Subject','Issues/yr','Actions']" :key="h"
              class="text-[10.5px] font-bold tracking-[.07em] uppercase text-[var(--muted)]">{{ h }}</span>
          </div>

          <div v-for="j in filtered" :key="j.id"
            class="grid px-5 py-4 border-b border-[var(--line-soft)] last:border-0 items-center hover:bg-[#FAFCFF] transition-colors"
            style="grid-template-columns:1fr 160px 90px 140px">
            <div>
              <div class="text-[14px] font-bold text-[var(--navy)] mb-0.5" style="font-family:var(--display)">{{ j.title }}</div>
              <div class="text-[12px] text-[var(--muted)]">{{ j.publisher }}</div>
              <div class="text-[11px] text-[var(--faint)] mt-0.5">{{ j.latestVol }}</div>
            </div>
            <div><span class="tag">{{ j.subject }}</span></div>
            <div class="text-[12.5px] text-[var(--muted)]">{{ j.issues }}/yr</div>
            <div class="flex gap-1.5 items-center flex-wrap">
              <span :class="['badge', j.access==='open' ? 'b-green' : 'b-gray']">
                {{ j.access==='open' ? 'Open' : 'Restricted' }}
              </span>
              <button :class="['btn btn-sm', j.access==='open' ? 'btn-primary' : 'btn-ghost']">
                <LucideIcon :name="j.access==='open' ? 'book-open' : 'unlock'" :size="13" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!filtered.length" class="flex flex-col items-center py-16 text-center">
      <LucideIcon name="newspaper" :size="44" class="text-[var(--faint)] mb-3" />
      <div class="text-[15px] font-semibold text-[var(--navy)] mb-1">No journals found</div>
      <p class="text-[13px] text-[var(--muted)]">Try a different subject or search term.</p>
    </div>
  </div>
</template>
