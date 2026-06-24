<script setup lang="ts">
import { ref } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'

const activeTab = ref<'issue' | 'return' | 'renew'>('issue')
const showIssueModal = ref(false)
</script>

<template>
  <div class="shead">
    <div><h2>Circulation Desk</h2><p>Issue, return, and renew books at the front desk</p></div>
    <div class="fbtns">
      <div :class="['fbtn', { on: activeTab === 'issue' }]"  @click="activeTab = 'issue'">Issue</div>
      <div :class="['fbtn', { on: activeTab === 'return' }]" @click="activeTab = 'return'">Return</div>
      <div :class="['fbtn', { on: activeTab === 'renew' }]"  @click="activeTab = 'renew'">Renew</div>
    </div>
  </div>

  <div class="circ-grid">
    <!-- Step 1: Member -->
    <div class="circ-panel">
      <div class="circ-ph">
        <div class="stp">1</div>
        <div><h3>Identify Member</h3><p>Scan member card or enter ID</p></div>
      </div>
      <div class="circ-b">
        <div class="scan">
          <div class="scan-in"><LucideIcon name="scan-line" /><input value="LIB-2024-0412" placeholder="Scan or type member ID…" /></div>
          <button class="btn btn-primary"><LucideIcon name="search" class="ic-sm" /></button>
        </div>
        <div class="mcard">
          <div class="mcard-top">
            <div class="mcard-av">AG</div>
            <div style="flex:1">
              <div class="mcard-n">Aminu Garba Usman</div>
              <div class="mcard-id">LIB-2024-0412</div>
            </div>
            <span class="badge b-green"><span class="dot d-green"></span> Good Standing</span>
          </div>
          <div class="mcard-body">
            <div class="mcard-row"><span class="k">Membership</span><span class="v">Standard Member</span></div>
            <div class="mcard-row"><span class="k">Outstanding Fines</span><span class="v" style="color:var(--green-600)">₦0</span></div>
            <div class="mcard-row"><span class="k">Borrowing Slots</span><span class="v">2 of 5 used</span></div>
            <div class="slot-bar"><div class="slot-fill" style="width:40%"></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Step 2: Book -->
    <div class="circ-panel">
      <div class="circ-ph">
        <div class="stp">2</div>
        <div><h3>Scan Book</h3><p>Scan barcode or enter call number</p></div>
      </div>
      <div class="circ-b">
        <div class="scan">
          <div class="scan-in"><LucideIcon name="barcode" /><input value="JZ1320-C2" placeholder="Scan barcode or call number…" /></div>
          <button class="btn btn-primary"><LucideIcon name="plus" class="ic-sm" /></button>
        </div>
        <div class="cbook">
          <div class="cover cv-navy"><div class="cover-top"><div class="cover-rule"></div><div class="cover-t">Democracy</div></div></div>
          <div style="flex:1">
            <div class="cbook-t">Democracy and Development in Africa</div>
            <div class="cbook-m">Diamond &amp; Plattner · Copy 2 · Shelf 14C</div>
          </div>
          <span class="badge b-green">Available</span>
        </div>
        <div class="issue-sum">
          <div class="issue-sum-row"><span class="k">Issue Date</span><span class="v">14 Apr 2025</span></div>
          <div class="issue-sum-row"><span class="k">Due Date (14 days)</span><span class="v">28 Apr 2025</span></div>
          <div class="issue-sum-row"><span class="k">Books in this transaction</span><span class="v">1 item</span></div>
        </div>
        <button class="btn btn-green btn-block" style="margin-top:16px" @click="showIssueModal = true">
          <LucideIcon name="check-check" class="ic-sm" /> Confirm Issue to Member
        </button>
      </div>
    </div>
  </div>

  <!-- Today's transactions table -->
  <div class="card">
    <div class="card-h">
      <h3><LucideIcon name="receipt-text" class="ic" /> Today's Transactions</h3>
      <span class="badge b-gray">42 today</span>
    </div>
    <table class="tbl">
      <thead><tr><th>Time</th><th>Member</th><th>Action</th><th>Book</th><th>Due / Returned</th><th>By</th></tr></thead>
      <tbody>
        <tr>
          <td class="mono">14:08</td><td>Aminu Garba</td>
          <td><span class="badge b-green">Issued</span></td>
          <td><div class="bookcell"><div class="cover cv-navy"><div class="cover-top"><div class="cover-t">Dem.</div></div></div><span class="bk-t" style="font-size:12px">Democracy &amp; Development</span></div></td>
          <td class="mono">Due 28 Apr</td><td style="color:var(--muted)">G. Sule</td>
        </tr>
        <tr>
          <td class="mono">13:52</td><td>Fatima Bello</td>
          <td><span class="badge b-blue">Returned</span></td>
          <td><div class="bookcell"><div class="cover cv-burgundy"><div class="cover-top"><div class="cover-t">Elec.</div></div></div><span class="bk-t" style="font-size:12px">Nigerian Electoral System</span></div></td>
          <td class="mono" style="color:var(--green-600)">On time</td><td style="color:var(--muted)">G. Sule</td>
        </tr>
        <tr>
          <td class="mono">13:20</td><td>Ibrahim Sani</td>
          <td><span class="badge b-amber">Renewed</span></td>
          <td><div class="bookcell"><div class="cover cv-slate"><div class="cover-top"><div class="cover-t">Fed.</div></div></div><span class="bk-t" style="font-size:12px">Federalism in Nigeria</span></div></td>
          <td class="mono">Due 30 Apr</td><td style="color:var(--muted)">B. Musa</td>
        </tr>
        <tr>
          <td class="mono">12:45</td><td>Musa Yaro</td>
          <td><span class="badge b-red">Late Return</span></td>
          <td><div class="bookcell"><div class="cover cv-forest"><div class="cover-top"><div class="cover-t">Civ.</div></div></div><span class="bk-t" style="font-size:12px">Civil Society &amp; Democracy</span></div></td>
          <td class="mono" style="color:var(--red)">₦150 fine</td><td style="color:var(--muted)">G. Sule</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Modal: Issue confirmed -->
  <div :class="['mscrim', { open: showIssueModal }]" @click.self="showIssueModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--green-50)"><LucideIcon name="check-check" :size="28" style="color:var(--green)" /></div>
        <div class="modal-t">Book Issued</div>
        <div class="modal-s">"Democracy and Development in Africa" issued to Aminu Garba. Due 28 Apr 2025.</div>
        <div class="modal-f"><button class="btn btn-primary btn-block" @click="showIssueModal = false">Done</button></div>
      </div>
    </div>
  </div>
</template>
