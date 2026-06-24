<script setup lang="ts">
import LucideIcon from '@/components/LucideIcon.vue'
</script>

<template>
  <div class="shead">
    <div><h2>Ask-Librarian Inbox</h2><p>4 unread queries from members</p></div>
  </div>

  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>From</th><th>Query</th><th>Type</th><th>Received</th><th style="text-align:right">Action</th></tr></thead>
      <tbody>
        <tr>
          <td><div style="display:flex;align-items:center;gap:8px"><span class="dot" style="background:var(--blue)"></span> Aminu Garba</div></td>
          <td>Do you have any titles on electoral reform in Ghana?</td>
          <td><span class="tag">Reference</span></td>
          <td class="mono">10m ago</td>
          <td style="text-align:right"><button class="btn btn-primary btn-sm"><LucideIcon name="reply" class="ic-sm" /> Reply</button></td>
        </tr>
        <tr>
          <td><div style="display:flex;align-items:center;gap:8px"><span class="dot" style="background:var(--blue)"></span> Halima Sani</div></td>
          <td>Can you acquire "The Politics of Bureaucracy" by Peters?</td>
          <td><span class="badge b-gold">Acquisition</span></td>
          <td class="mono">1h ago</td>
          <td style="text-align:right"><button class="btn btn-primary btn-sm"><LucideIcon name="reply" class="ic-sm" /> Reply</button></td>
        </tr>
        <tr>
          <td><div style="display:flex;align-items:center;gap:8px"><span class="dot" style="background:var(--blue)"></span> Yusuf Bala</div></td>
          <td>How do I cite a government gazette in APA?</td>
          <td><span class="tag">Citation</span></td>
          <td class="mono">3h ago</td>
          <td style="text-align:right"><button class="btn btn-primary btn-sm"><LucideIcon name="reply" class="ic-sm" /> Reply</button></td>
        </tr>
        <tr>
          <td><div style="display:flex;align-items:center;gap:8px"><span class="dot d-gray"></span> Sadiq Umar</div></td>
          <td>Is the eLibrary down? I can't log in this morning.</td>
          <td><span class="badge b-red">Support</span></td>
          <td class="mono">Yesterday</td>
          <td style="text-align:right"><button class="btn btn-ghost btn-sm">Resolved</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
