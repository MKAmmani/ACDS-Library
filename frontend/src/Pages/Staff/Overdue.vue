<script setup lang="ts">
import LucideIcon from '@/components/LucideIcon.vue'
</script>

<template>
  <div class="shead">
    <div><h2>Overdue &amp; Fines</h2><p>3 overdue items · ₦850 in outstanding fines</p></div>
    <button class="btn btn-primary"><LucideIcon name="send" class="ic-sm" /> Send All Reminders</button>
  </div>

  <!-- Summary KPIs -->
  <div class="kpis" style="grid-template-columns:repeat(3,1fr)">
    <div class="kpi">
      <div class="kpi-top"><div class="kpi-n" style="color:var(--red)">3</div><div class="kpi-ic t-red"><LucideIcon name="alarm-clock" /></div></div>
      <div class="kpi-l">Overdue Items</div>
    </div>
    <div class="kpi">
      <div class="kpi-top"><div class="kpi-n" style="color:var(--amber)">₦850</div><div class="kpi-ic t-amber"><LucideIcon name="banknote" /></div></div>
      <div class="kpi-l">Outstanding Fines</div>
    </div>
    <div class="kpi">
      <div class="kpi-top"><div class="kpi-n">₦12,400</div><div class="kpi-ic t-green"><LucideIcon name="hand-coins" /></div></div>
      <div class="kpi-l">Collected This Month</div>
    </div>
  </div>

  <!-- Overdue table -->
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr><th>Member</th><th>Book</th><th>Due Date</th><th>Days Late</th><th>Fine</th><th style="text-align:right">Actions</th></tr>
      </thead>
      <tbody>
        <tr>
          <td><div style="display:flex;align-items:center;gap:8px"><span class="dot d-red"></span> Ibrahim Musa Kano</div></td>
          <td><div class="bookcell"><div class="cover cv-slate"><div class="cover-top"><div class="cover-t">Fed.</div></div></div><span class="bk-t" style="font-size:12px">Federalism in Nigeria</span></div></td>
          <td class="mono">15 Apr 2025</td>
          <td><span class="badge b-red">17 days</span></td>
          <td style="font-weight:700;color:var(--red)">₦850</td>
          <td><div class="rowacts" style="justify-content:flex-end">
            <button class="btn btn-ghost btn-sm"><LucideIcon name="send" class="ic-sm" /> Remind</button>
            <button class="btn btn-green btn-sm"><LucideIcon name="hand-coins" class="ic-sm" /> Collect</button>
            <button class="btn btn-danger btn-sm">Waive</button>
          </div></td>
        </tr>
        <tr>
          <td><div style="display:flex;align-items:center;gap:8px"><span class="dot d-amber"></span> Halima Abdullahi</div></td>
          <td><div class="bookcell"><div class="cover cv-forest"><div class="cover-top"><div class="cover-t">Civ.</div></div></div><span class="bk-t" style="font-size:12px">Civil Society &amp; Democracy</span></div></td>
          <td class="mono">22 Apr 2025</td>
          <td><span class="badge b-amber">4 days</span></td>
          <td style="font-weight:700;color:var(--amber)">₦200</td>
          <td><div class="rowacts" style="justify-content:flex-end">
            <button class="btn btn-ghost btn-sm"><LucideIcon name="send" class="ic-sm" /> Remind</button>
            <button class="btn btn-green btn-sm"><LucideIcon name="hand-coins" class="ic-sm" /> Collect</button>
          </div></td>
        </tr>
        <tr>
          <td><div style="display:flex;align-items:center;gap:8px"><span class="dot d-amber"></span> Yusuf Adamu</div></td>
          <td><div class="bookcell"><div class="cover cv-burgundy"><div class="cover-top"><div class="cover-t">Elec.</div></div></div><span class="bk-t" style="font-size:12px">Electoral Integrity</span></div></td>
          <td class="mono">24 Apr 2025</td>
          <td><span class="badge b-amber">2 days</span></td>
          <td style="font-weight:700;color:var(--amber)">₦100</td>
          <td><div class="rowacts" style="justify-content:flex-end">
            <button class="btn btn-ghost btn-sm"><LucideIcon name="send" class="ic-sm" /> Remind</button>
            <button class="btn btn-green btn-sm"><LucideIcon name="hand-coins" class="ic-sm" /> Collect</button>
          </div></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
