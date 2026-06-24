<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import LucideIcon from '@/components/LucideIcon.vue'

const router = useRouter()
const showReadyModal = ref(false)
</script>

<template>
  <!-- KPI row -->
  <div class="kpis">
    <div class="kpi">
      <div class="kpi-top"><div class="kpi-n">12,480</div><div class="kpi-ic t-blue"><LucideIcon name="book-copy" /></div></div>
      <div class="kpi-l">Total Volumes</div>
      <div class="kpi-tr tr-up"><LucideIcon name="trending-up" class="ic-sm" /> +32 this month</div>
    </div>
    <div class="kpi">
      <div class="kpi-top"><div class="kpi-n">214</div><div class="kpi-ic t-slate"><LucideIcon name="book-open" /></div></div>
      <div class="kpi-l">Currently on Loan</div>
      <div class="kpi-tr tr-fl"><LucideIcon name="minus" class="ic-sm" /> 28 due this week</div>
    </div>
    <div class="kpi">
      <div class="kpi-top"><div class="kpi-n" style="color:var(--red)">3</div><div class="kpi-ic t-red"><LucideIcon name="alarm-clock" /></div></div>
      <div class="kpi-l">Overdue Returns</div>
      <div class="kpi-tr tr-dn"><LucideIcon name="banknote" class="ic-sm" /> ₦850 fines due</div>
    </div>
    <div class="kpi">
      <div class="kpi-top"><div class="kpi-n">840</div><div class="kpi-ic t-green"><LucideIcon name="users-round" /></div></div>
      <div class="kpi-l">Active Members</div>
      <div class="kpi-tr tr-up"><LucideIcon name="trending-up" class="ic-sm" /> +14 this week</div>
    </div>
  </div>

  <!-- Chart + Most Borrowed -->
  <div class="grid3" style="margin-bottom:18px">
    <div class="card" style="grid-column:span 2">
      <div class="card-h">
        <h3><LucideIcon name="activity" class="ic" /> Circulation — Last 12 Days</h3>
        <div style="display:flex;gap:12px">
          <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted)"><span class="dot" style="background:var(--blue)"></span> Issued</span>
          <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted)"><span class="dot" style="background:var(--green)"></span> Returned</span>
        </div>
      </div>
      <div class="card-b">
        <div class="bars">
          <div class="bar" style="height:55%"></div><div class="bar g" style="height:40%"></div>
          <div class="bar" style="height:70%"></div><div class="bar g" style="height:60%"></div>
          <div class="bar" style="height:85%"></div><div class="bar g" style="height:50%"></div>
          <div class="bar" style="height:75%"></div><div class="bar g" style="height:65%"></div>
          <div class="bar" style="height:95%"></div><div class="bar g" style="height:72%"></div>
          <div class="bar" style="height:88%"></div><div class="bar g" style="height:80%"></div>
        </div>
        <div class="barlabels"><span v-for="n in 12" :key="n">{{ n }}</span></div>
      </div>
    </div>

    <div class="card">
      <div class="card-h"><h3><LucideIcon name="flame" class="ic" /> Most Borrowed</h3></div>
      <div class="card-b" style="padding:8px 18px">
        <div class="poprow"><div class="poprank">1</div><div class="cover cv-navy" style="width:24px;height:30px"><div class="cover-top"><div class="cover-t">Dem.</div></div></div><div style="flex:1"><div class="pop-t">Democracy &amp; Development</div><div class="pop-n">142 loans</div></div></div>
        <div class="poprow"><div class="poprank">2</div><div class="cover cv-burgundy" style="width:24px;height:30px"><div class="cover-top"><div class="cover-t">Elec.</div></div></div><div style="flex:1"><div class="pop-t">Nigerian Electoral System</div><div class="pop-n">118 loans</div></div></div>
        <div class="poprow"><div class="poprank">3</div><div class="cover cv-charcoal" style="width:24px;height:30px"><div class="cover-top"><div class="cover-t">A.K.</div></div></div><div style="flex:1"><div class="pop-t">Aminu Kano Biography</div><div class="pop-n">96 loans</div></div></div>
        <div class="poprow"><div class="poprank">4</div><div class="cover cv-slate" style="width:24px;height:30px"><div class="cover-top"><div class="cover-t">Fed.</div></div></div><div style="flex:1"><div class="pop-t">Federalism in Nigeria</div><div class="pop-n">84 loans</div></div></div>
      </div>
    </div>
  </div>

  <!-- Transactions + Reservations -->
  <div class="grid2">
    <div class="card">
      <div class="card-h">
        <h3><LucideIcon name="history" class="ic" /> Recent Transactions</h3>
        <button class="btn btn-ghost btn-sm" @click="router.push('/staff/circulation')">Open Desk</button>
      </div>
      <table class="tbl">
        <thead><tr><th>Member</th><th>Action</th><th>Book</th><th>Time</th></tr></thead>
        <tbody>
          <tr><td>Aminu Garba</td><td><span class="badge b-green"><LucideIcon name="arrow-up-right" :size="11" /> Issued</span></td><td class="mono">Democracy &amp; Dev.</td><td class="mono">2m ago</td></tr>
          <tr><td>Fatima Bello</td><td><span class="badge b-blue"><LucideIcon name="arrow-down-left" :size="11" /> Returned</span></td><td class="mono">Electoral System</td><td class="mono">14m ago</td></tr>
          <tr><td>Ibrahim Sani</td><td><span class="badge b-amber"><LucideIcon name="refresh-cw" :size="11" /> Renewed</span></td><td class="mono">Federalism</td><td class="mono">38m ago</td></tr>
          <tr><td>Zainab Musa</td><td><span class="badge b-green"><LucideIcon name="arrow-up-right" :size="11" /> Issued</span></td><td class="mono">Civil Society</td><td class="mono">1h ago</td></tr>
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="card-h">
        <h3><LucideIcon name="bookmark-check" class="ic" /> Reservations to Fulfil</h3>
        <span class="badge b-gold">5 pending</span>
      </div>
      <div class="qitem">
        <div class="cover cv-charcoal"><div class="cover-top"><div class="cover-rule"></div><div class="cover-t">Aminu Kano</div></div></div>
        <div class="q-info">
          <div class="q-t">The Life and Times of Mallam Aminu Kano</div>
          <div class="q-m"><span><LucideIcon name="user" :size="12" /> Aminu Garba</span><span><LucideIcon name="clock" :size="12" /> Reserved 2h ago</span></div>
        </div>
        <div class="q-acts"><button class="btn btn-green btn-sm" @click="showReadyModal = true"><LucideIcon name="check" class="ic-sm" /> Mark Ready</button></div>
      </div>
      <div class="qitem">
        <div class="cover cv-slate"><div class="cover-top"><div class="cover-rule"></div><div class="cover-t">Federalism</div></div></div>
        <div class="q-info">
          <div class="q-t">Federalism and National Integration</div>
          <div class="q-m"><span><LucideIcon name="user" :size="12" /> Sani Bello</span><span><LucideIcon name="clock" :size="12" /> Reserved 5h ago</span></div>
        </div>
        <div class="q-acts"><button class="btn btn-green btn-sm" @click="showReadyModal = true"><LucideIcon name="check" class="ic-sm" /> Mark Ready</button></div>
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
