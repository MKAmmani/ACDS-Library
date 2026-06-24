<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import LucideIcon from '@/components/LucideIcon.vue'

const router = useRouter()
const showReadyModal = ref(false)
</script>

<template>
  <div class="shead">
    <div><h2>Reservations Queue</h2><p>5 pending · 1 ready for collection</p></div>
  </div>

  <!-- Pending -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-h">
      <h3><LucideIcon name="clock" class="ic" style="color:var(--gold-600)!important" /> Pending — Action Required</h3>
    </div>
    <div class="qitem">
      <div class="cover cv-charcoal"><div class="cover-top"><div class="cover-rule"></div><div class="cover-t">A.K.</div></div></div>
      <div class="q-info">
        <div class="q-t">The Life and Times of Mallam Aminu Kano</div>
        <div class="q-m">
          <span><LucideIcon name="user" :size="12" /> Aminu Garba · LIB-2024-0412</span>
          <span><LucideIcon name="clock" :size="12" /> 2h ago</span>
          <span><LucideIcon name="map-pin" :size="12" /> 1 copy free</span>
        </div>
      </div>
      <div class="q-acts">
        <button class="btn btn-green btn-sm" @click="showReadyModal = true"><LucideIcon name="check" class="ic-sm" /> Mark Ready</button>
        <button class="btn btn-danger btn-sm"><LucideIcon name="x" class="ic-sm" /></button>
      </div>
    </div>
    <div class="qitem">
      <div class="cover cv-slate"><div class="cover-top"><div class="cover-rule"></div><div class="cover-t">Fed.</div></div></div>
      <div class="q-info">
        <div class="q-t">Federalism and National Integration</div>
        <div class="q-m">
          <span><LucideIcon name="user" :size="12" /> Sani Bello · LIB-2024-0388</span>
          <span><LucideIcon name="clock" :size="12" /> 5h ago</span>
        </div>
      </div>
      <div class="q-acts">
        <button class="btn btn-green btn-sm" @click="showReadyModal = true"><LucideIcon name="check" class="ic-sm" /> Mark Ready</button>
        <button class="btn btn-danger btn-sm"><LucideIcon name="x" class="ic-sm" /></button>
      </div>
    </div>
    <div class="qitem">
      <div class="cover cv-burgundy"><div class="cover-top"><div class="cover-rule"></div><div class="cover-t">Elec.</div></div></div>
      <div class="q-info">
        <div class="q-t">Nigerian Electoral System</div>
        <div class="q-m">
          <span><LucideIcon name="user" :size="12" /> Zainab Musa</span>
          <span><LucideIcon name="clock" :size="12" /> Yesterday</span>
          <span style="color:var(--red)"><LucideIcon name="alert-triangle" :size="12" /> All copies on loan</span>
        </div>
      </div>
      <div class="q-acts">
        <button class="btn btn-ghost btn-sm">Notify when free</button>
      </div>
    </div>
  </div>

  <!-- Ready for collection -->
  <div class="card">
    <div class="card-h">
      <h3><LucideIcon name="package-check" class="ic" style="color:var(--green)!important" /> Ready for Collection</h3>
    </div>
    <div class="qitem">
      <div class="cover cv-navy"><div class="cover-top"><div class="cover-rule"></div><div class="cover-t">Dem.</div></div></div>
      <div class="q-info">
        <div class="q-t">Democracy and Development in Africa</div>
        <div class="q-m">
          <span><LucideIcon name="user" :size="12" /> Fatima Bello</span>
          <span><LucideIcon name="calendar" :size="12" /> Held until 16 Apr</span>
        </div>
      </div>
      <div class="q-acts">
        <span class="badge b-green"><span class="dot d-green"></span> Ready</span>
        <button class="btn btn-primary btn-sm" @click="router.push('/staff/circulation')">
          <LucideIcon name="hand-coins" class="ic-sm" /> Issue Now
        </button>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <div :class="['mscrim', { open: showReadyModal }]" @click.self="showReadyModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--green-50)"><LucideIcon name="package-check" :size="28" style="color:var(--green)" /></div>
        <div class="modal-t">Marked Ready</div>
        <div class="modal-s">The member has been notified that their reserved book is ready for collection at the front desk.</div>
        <div class="modal-f"><button class="btn btn-primary btn-block" @click="showReadyModal = false">Done</button></div>
      </div>
    </div>
  </div>
</template>
