<script setup lang="ts">
import LucideIcon from '@/components/LucideIcon.vue'
</script>

<template>
  <div class="shead">
    <div><h2>Library Settings</h2><p>Circulation rules &amp; policies</p></div>
  </div>

  <div class="grid2">
    <!-- Borrowing rules -->
    <div class="card">
      <div class="card-h"><h3><LucideIcon name="book-open" class="ic" /> Borrowing Rules</h3></div>
      <div class="card-b">
        <div class="form-grid">
          <div class="fg"><label class="fl">Loan Period (days)</label><input class="fi" value="14" /></div>
          <div class="fg"><label class="fl">Max Books / Member</label><input class="fi" value="5" /></div>
          <div class="fg"><label class="fl">Renewals Allowed</label><input class="fi" value="2" /></div>
          <div class="fg"><label class="fl">Reservation Hold (hrs)</label><input class="fi" value="48" /></div>
        </div>
        <button class="btn btn-primary" style="margin-top:14px"><LucideIcon name="save" class="ic-sm" /> Save Rules</button>
      </div>
    </div>

    <!-- Fine policy -->
    <div class="card">
      <div class="card-h"><h3><LucideIcon name="banknote" class="ic" /> Fine Policy</h3></div>
      <div class="card-b">
        <div class="form-grid">
          <div class="fg"><label class="fl">Overdue Fine / Day (₦)</label><input class="fi" value="50" /></div>
          <div class="fg"><label class="fl">Max Fine Before Suspension (₦)</label><input class="fi" value="1000" /></div>
          <div class="fg col2"><label class="fl">Grace Period (days)</label><input class="fi" value="0" /></div>
        </div>
        <button class="btn btn-primary" style="margin-top:14px"><LucideIcon name="save" class="ic-sm" /> Save Policy</button>
      </div>
    </div>
  </div>
</template>
