<script setup lang="ts">
import { ref } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'

const activeFilter = ref('all')
const filters = [
  { key: 'all',       label: 'All (840)' },
  { key: 'standing',  label: 'Good Standing' },
  { key: 'fines',     label: 'With Fines' },
  { key: 'suspended', label: 'Suspended' },
]
</script>

<template>
  <div class="shead">
    <div><h2>Members</h2><p>840 active members · 14 new this week</p></div>
    <button class="btn btn-primary"><LucideIcon name="user-plus" class="ic-sm" /> Register Member</button>
  </div>

  <div class="toolbar">
    <div class="search-in"><LucideIcon name="search" class="ic-sm" /><input placeholder="Search by name, member ID, or email…" /></div>
    <div class="fbtns">
      <div
        v-for="f in filters"
        :key="f.key"
        :class="['fbtn', { on: activeFilter === f.key }]"
        @click="activeFilter = f.key"
      >{{ f.label }}</div>
    </div>
  </div>

  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Member</th><th>Member ID</th><th>Type</th><th>On Loan</th><th>Fines</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
        <tr>
          <td><div style="display:flex;align-items:center;gap:10px"><div class="sb-av" style="width:32px;height:32px;background:linear-gradient(140deg,var(--blue),var(--sky))">AG</div><div><div class="bk-t">Aminu Garba Usman</div><div class="bk-a">aminu.g@gmail.com</div></div></div></td>
          <td class="mono">LIB-2024-0412</td><td><span class="tag">Standard</span></td><td>2 / 5</td>
          <td style="color:var(--green-600)">₦0</td><td><span class="badge b-green"><span class="dot d-green"></span> Active</span></td>
          <td><div class="rowacts" style="justify-content:flex-end"><div class="ra"><LucideIcon name="eye" /></div><div class="ra"><LucideIcon name="pencil" /></div></div></td>
        </tr>
        <tr>
          <td><div style="display:flex;align-items:center;gap:10px"><div class="sb-av" style="width:32px;height:32px;background:linear-gradient(140deg,#234034,var(--green))">FB</div><div><div class="bk-t">Fatima Bello</div><div class="bk-a">f.bello@buk.edu.ng</div></div></div></td>
          <td class="mono">LIB-2024-0391</td><td><span class="tag">Researcher</span></td><td>1 / 8</td>
          <td style="color:var(--green-600)">₦0</td><td><span class="badge b-green"><span class="dot d-green"></span> Active</span></td>
          <td><div class="rowacts" style="justify-content:flex-end"><div class="ra"><LucideIcon name="eye" /></div><div class="ra"><LucideIcon name="pencil" /></div></div></td>
        </tr>
        <tr>
          <td><div style="display:flex;align-items:center;gap:10px"><div class="sb-av" style="width:32px;height:32px;background:linear-gradient(140deg,#9b2230,var(--red))">IM</div><div><div class="bk-t">Ibrahim Musa Kano</div><div class="bk-a">ibrahim.m@example.com</div></div></div></td>
          <td class="mono">LIB-2023-0277</td><td><span class="tag">Standard</span></td><td>1 / 5</td>
          <td style="color:var(--red);font-weight:700">₦850</td><td><span class="badge b-red"><span class="dot d-red"></span> Suspended</span></td>
          <td><div class="rowacts" style="justify-content:flex-end"><div class="ra"><LucideIcon name="eye" /></div><div class="ra"><LucideIcon name="unlock" /></div></div></td>
        </tr>
        <tr>
          <td><div style="display:flex;align-items:center;gap:10px"><div class="sb-av" style="width:32px;height:32px;background:linear-gradient(140deg,#5E2A35,#8a4250)">ZM</div><div><div class="bk-t">Zainab Musa</div><div class="bk-a">zainab.m@nipss.gov.ng</div></div></div></td>
          <td class="mono">LIB-2024-0405</td><td><span class="tag">Institutional</span></td><td>0 / 10</td>
          <td style="color:var(--green-600)">₦0</td><td><span class="badge b-green"><span class="dot d-green"></span> Active</span></td>
          <td><div class="rowacts" style="justify-content:flex-end"><div class="ra"><LucideIcon name="eye" /></div><div class="ra"><LucideIcon name="pencil" /></div></div></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
