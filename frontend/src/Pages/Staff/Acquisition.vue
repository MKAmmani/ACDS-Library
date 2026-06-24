<script setup lang="ts">
import { ref } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'

const showDrawer = ref(false)
</script>

<template>
  <div class="shead">
    <div><h2>Acquisitions</h2><p>8 requests · 3 awaiting approval · annual budget 62% used</p></div>
    <button class="btn btn-primary" @click="showDrawer = true"><LucideIcon name="plus" class="ic-sm" /> New Acquisition</button>
  </div>

  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Title / Author</th><th>Requested By</th><th>Est. Cost</th><th>Copies</th><th>Status</th><th style="text-align:right">Action</th></tr></thead>
      <tbody>
        <tr>
          <td><div><div class="bk-t">The Politics of Bureaucracy</div><div class="bk-a">B. Guy Peters</div></div></td>
          <td style="color:var(--muted)">Halima Sani (member)</td><td>₦24,000</td><td>2</td>
          <td><span class="badge b-gold">Awaiting Approval</span></td>
          <td style="text-align:right"><div class="rowacts" style="justify-content:flex-end"><button class="btn btn-green btn-sm">Approve</button><button class="btn btn-danger btn-sm">Decline</button></div></td>
        </tr>
        <tr>
          <td><div><div class="bk-t">Comparative Federalism</div><div class="bk-a">Michael Burgess</div></div></td>
          <td style="color:var(--muted)">Research Dept</td><td>₦38,000</td><td>3</td>
          <td><span class="badge b-blue">Ordered</span></td>
          <td style="text-align:right"><button class="btn btn-ghost btn-sm">Track</button></td>
        </tr>
        <tr>
          <td><div><div class="bk-t">Voting Behaviour in Africa</div><div class="bk-a">Bratton et al.</div></div></td>
          <td style="color:var(--muted)">Chief Librarian</td><td>₦19,500</td><td>2</td>
          <td><span class="badge b-green">Received</span></td>
          <td style="text-align:right"><button class="btn btn-primary btn-sm" @click="showDrawer = true">Catalog It</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
