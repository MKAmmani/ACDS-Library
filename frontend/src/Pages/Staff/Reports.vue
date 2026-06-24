<script setup lang="ts">
import LucideIcon from '@/components/LucideIcon.vue'
</script>

<template>
  <div class="shead">
    <div><h2>Reports &amp; Analytics</h2><p>Library performance · last 30 days</p></div>
    <button class="btn btn-ghost"><LucideIcon name="download" class="ic-sm" /> Export PDF</button>
  </div>

  <!-- KPIs -->
  <div class="kpis">
    <div class="kpi">
      <div class="kpi-top"><div class="kpi-n">1,840</div><div class="kpi-ic t-blue"><LucideIcon name="book-open" /></div></div>
      <div class="kpi-l">Total Loans (30d)</div>
      <div class="kpi-tr tr-up"><LucideIcon name="trending-up" class="ic-sm" /> +12%</div>
    </div>
    <div class="kpi">
      <div class="kpi-top"><div class="kpi-n">94%</div><div class="kpi-ic t-green"><LucideIcon name="circle-check-big" /></div></div>
      <div class="kpi-l">On-time Return Rate</div>
      <div class="kpi-tr tr-up"><LucideIcon name="trending-up" class="ic-sm" /> +2%</div>
    </div>
    <div class="kpi">
      <div class="kpi-top"><div class="kpi-n">68%</div><div class="kpi-ic t-gold"><LucideIcon name="gauge" /></div></div>
      <div class="kpi-l">Collection Utilisation</div>
      <div class="kpi-tr tr-fl"><LucideIcon name="minus" class="ic-sm" /> stable</div>
    </div>
    <div class="kpi">
      <div class="kpi-top"><div class="kpi-n">4.2</div><div class="kpi-ic t-wine"><LucideIcon name="star" /></div></div>
      <div class="kpi-l">Avg Loans / Member</div>
      <div class="kpi-tr tr-up"><LucideIcon name="trending-up" class="ic-sm" /> +0.3</div>
    </div>
  </div>

  <div class="grid2">
    <!-- Loans by subject -->
    <div class="card">
      <div class="card-h"><h3><LucideIcon name="chart-no-axes-column" class="ic" /> Loans by Subject Area</h3></div>
      <div class="card-b">
        <div class="bars">
          <div class="bar" style="height:90%"></div>
          <div class="bar" style="height:65%"></div>
          <div class="bar" style="height:78%"></div>
          <div class="bar" style="height:50%"></div>
          <div class="bar" style="height:42%"></div>
          <div class="bar" style="height:30%"></div>
        </div>
        <div class="barlabels">
          <span>Gov.</span><span>Hist.</span><span>Pol.</span><span>Law</span><span>Afr.</span><span>Bio.</span>
        </div>
      </div>
    </div>

    <!-- Member activity -->
    <div class="card">
      <div class="card-h"><h3><LucideIcon name="users-round" class="ic" /> Member Activity</h3></div>
      <div class="card-b" style="padding:8px 18px">
        <div class="poprow"><div class="pop-t">Active borrowers (30d)</div><div class="pop-n" style="font-weight:700;color:var(--navy)">412</div></div>
        <div class="poprow"><div class="pop-t">New registrations</div><div class="pop-n" style="font-weight:700;color:var(--green-600)">+58</div></div>
        <div class="poprow"><div class="pop-t">eLibrary logins</div><div class="pop-n" style="font-weight:700;color:var(--navy)">2,140</div></div>
        <div class="poprow"><div class="pop-t">Reservations placed</div><div class="pop-n" style="font-weight:700;color:var(--navy)">186</div></div>
        <div class="poprow"><div class="pop-t">Ask-librarian queries</div><div class="pop-n" style="font-weight:700;color:var(--navy)">74</div></div>
      </div>
    </div>
  </div>
</template>
