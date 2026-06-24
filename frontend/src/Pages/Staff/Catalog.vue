<script setup lang="ts">
import { ref } from 'vue'
import LucideIcon from '@/components/LucideIcon.vue'

const drawerOpen    = ref(false)
const showDeleteModal = ref(false)
const showSavedModal  = ref(false)
const copies = ref(1)
const selectedCover = ref('cv-navy')

const covers = ['cv-navy', 'cv-burgundy', 'cv-forest', 'cv-slate', 'cv-charcoal', 'cv-ochre']

function step(n: number) {
  copies.value = Math.max(1, copies.value + n)
}
</script>

<template>
  <div class="shead">
    <div><h2>Catalog Manager</h2><p>12,480 titles · 32 added this month</p></div>
    <div class="shead-actions">
      <button class="btn btn-ghost"><LucideIcon name="upload" class="ic-sm" /> Import MARC</button>
      <button class="btn btn-primary" @click="drawerOpen = true"><LucideIcon name="book-plus" class="ic-sm" /> Add New Title</button>
    </div>
  </div>

  <div class="toolbar">
    <div class="search-in"><LucideIcon name="search" class="ic-sm" /><input placeholder="Search by title, author, ISBN, call number…" /></div>
    <select class="sel"><option>All Formats</option><option>Books</option><option>Journals</option><option>eBooks</option><option>Theses</option></select>
    <select class="sel"><option>All Subjects</option><option>Democracy</option><option>History</option><option>Law</option></select>
    <select class="sel"><option>All Status</option><option>Available</option><option>On Loan</option><option>Reference</option></select>
  </div>

  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Title / Author</th><th>Call No.</th><th>ISBN</th><th>Subject</th><th>Copies</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
        <tr>
          <td><div class="bookcell"><div class="cover cv-navy"><div class="cover-top"><div class="cover-rule"></div><div class="cover-t">Dem.</div></div></div><div><div class="bk-t">Democracy and Development in Africa</div><div class="bk-a">Diamond &amp; Plattner · 2010</div></div></div></td>
          <td class="mono">JZ1320.D46</td><td class="mono">978-0-8018</td><td><span class="tag">Governance</span></td><td>3 (2 free)</td><td><span class="badge b-green">Available</span></td>
          <td><div class="rowacts" style="justify-content:flex-end"><div class="ra" @click="drawerOpen = true"><LucideIcon name="pencil" /></div><div class="ra"><LucideIcon name="layers" /></div><div class="ra del" @click="showDeleteModal = true"><LucideIcon name="trash-2" /></div></div></td>
        </tr>
        <tr>
          <td><div class="bookcell"><div class="cover cv-burgundy"><div class="cover-top"><div class="cover-rule"></div><div class="cover-t">Elec.</div></div></div><div><div class="bk-t">Nigerian Electoral System and Democracy</div><div class="bk-a">Attahiru Jega · 2017</div></div></div></td>
          <td class="mono">JQ3089</td><td class="mono">978-978-053</td><td><span class="tag">Electoral</span></td><td>2 (0 free)</td><td><span class="badge b-red">On Loan</span></td>
          <td><div class="rowacts" style="justify-content:flex-end"><div class="ra" @click="drawerOpen = true"><LucideIcon name="pencil" /></div><div class="ra"><LucideIcon name="layers" /></div><div class="ra del" @click="showDeleteModal = true"><LucideIcon name="trash-2" /></div></div></td>
        </tr>
        <tr>
          <td><div class="bookcell"><div class="cover cv-forest"><div class="cover-top"><div class="cover-rule"></div><div class="cover-t">W.A.</div></div></div><div><div class="bk-t">Political Parties and Democracy in West Africa</div><div class="bk-a">Gyimah-Boadi · 2019</div></div></div></td>
          <td class="mono">JQ2900</td><td class="mono">eBook-4401</td><td><span class="tag">Pol. Science</span></td><td>Digital</td><td><span class="badge b-blue">eBook</span></td>
          <td><div class="rowacts" style="justify-content:flex-end"><div class="ra" @click="drawerOpen = true"><LucideIcon name="pencil" /></div><div class="ra"><LucideIcon name="layers" /></div><div class="ra del" @click="showDeleteModal = true"><LucideIcon name="trash-2" /></div></div></td>
        </tr>
        <tr>
          <td><div class="bookcell"><div class="cover cv-charcoal"><div class="cover-top"><div class="cover-rule"></div><div class="cover-t">A.K.</div></div></div><div><div class="bk-t">The Life and Times of Mallam Aminu Kano</div><div class="bk-a">Alan Feinstein · 1986</div></div></div></td>
          <td class="mono">DT515.9.K3</td><td class="mono">978-0-340</td><td><span class="tag">Biography</span></td><td>2 (1 free)</td><td><span class="badge b-gold">Reserved</span></td>
          <td><div class="rowacts" style="justify-content:flex-end"><div class="ra" @click="drawerOpen = true"><LucideIcon name="pencil" /></div><div class="ra"><LucideIcon name="layers" /></div><div class="ra del" @click="showDeleteModal = true"><LucideIcon name="trash-2" /></div></div></td>
        </tr>
        <tr>
          <td><div class="bookcell"><div class="cover cv-slate"><div class="cover-top"><div class="cover-rule"></div><div class="cover-t">Fed.</div></div></div><div><div class="bk-t">Federalism and National Integration in Nigeria</div><div class="bk-a">Rotimi Suberu · 2001</div></div></div></td>
          <td class="mono">JQ3089.A15</td><td class="mono">978-1-555</td><td><span class="tag">Federalism</span></td><td>4 (3 free)</td><td><span class="badge b-green">Available</span></td>
          <td><div class="rowacts" style="justify-content:flex-end"><div class="ra" @click="drawerOpen = true"><LucideIcon name="pencil" /></div><div class="ra"><LucideIcon name="layers" /></div><div class="ra del" @click="showDeleteModal = true"><LucideIcon name="trash-2" /></div></div></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Drawer scrim -->
  <div :class="['scrim', { open: drawerOpen }]" @click="drawerOpen = false"></div>

  <!-- Add Book Drawer -->
  <div :class="['drawer', { open: drawerOpen }]">
    <div class="dh">
      <div><div class="dh-t">Add New Title</div><div class="dh-s">Catalog a new book into the collection</div></div>
      <div class="dh-x" @click="drawerOpen = false"><LucideIcon name="x" /></div>
    </div>
    <div class="db">
      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="book" /> Bibliographic Information</div>
        <div class="form-grid">
          <div class="fg col2"><label class="fl">Title <span class="req">*</span></label><input class="fi" placeholder="Full book title" /></div>
          <div class="fg col2"><label class="fl">Author(s) <span class="req">*</span></label><input class="fi" placeholder="Surname, First; Surname, First…" /></div>
          <div class="fg"><label class="fl">Publisher</label><input class="fi" placeholder="e.g. Johns Hopkins UP" /></div>
          <div class="fg"><label class="fl">Year</label><input class="fi" placeholder="2024" /></div>
          <div class="fg"><label class="fl">ISBN <span class="req">*</span></label><input class="fi" placeholder="978-…" /></div>
          <div class="fg"><label class="fl">Edition</label><input class="fi" placeholder="1st / 2nd…" /></div>
          <div class="fg col2"><label class="fl">Abstract / Description</label><textarea class="ft" placeholder="Short description or abstract…"></textarea></div>
        </div>
      </div>

      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="map-pin" /> Classification &amp; Shelving</div>
        <div class="form-grid">
          <div class="fg"><label class="fl">Call Number <span class="req">*</span></label><input class="fi" placeholder="JZ1320.D46" /></div>
          <div class="fg"><label class="fl">Shelf Location</label><input class="fi" placeholder="Block A · Shelf 14C" /></div>
          <div class="fg"><label class="fl">Subject Area</label>
            <select class="fs"><option>Democracy &amp; Governance</option><option>Nigerian History &amp; Law</option><option>Political Science</option><option>African Studies</option><option>Electoral Systems</option><option>Biography</option></select>
          </div>
          <div class="fg"><label class="fl">Language</label>
            <select class="fs"><option>English</option><option>Hausa</option><option>Arabic</option><option>French</option></select>
          </div>
          <div class="fg"><label class="fl">Format</label>
            <select class="fs"><option>Book (Physical)</option><option>eBook</option><option>Journal</option><option>Thesis</option><option>Government Document</option></select>
          </div>
          <div class="fg"><label class="fl">Access Type</label>
            <select class="fs"><option>Loanable</option><option>Reference Only</option><option>Digital Access</option></select>
          </div>
        </div>
      </div>

      <div class="db-sec">
        <div class="db-sec-h"><LucideIcon name="layers" /> Copies &amp; Cover</div>
        <div class="form-grid">
          <div class="fg">
            <label class="fl">Number of Copies</label>
            <div class="copies-mgr">
              <div class="stepper">
                <button type="button" @click="step(-1)"><LucideIcon name="minus" class="ic-sm" /></button>
                <input :value="copies" readonly />
                <button type="button" @click="step(1)"><LucideIcon name="plus" class="ic-sm" /></button>
              </div>
              <span class="fhint">Each copy gets a unique barcode</span>
            </div>
          </div>
          <div class="fg">
            <label class="fl">Cover Treatment</label>
            <div class="cv-pick">
              <div
                v-for="c in covers"
                :key="c"
                :class="['cv-opt', c, { sel: selectedCover === c }]"
                @click="selectedCover = c"
              ></div>
            </div>
            <div class="fhint">Or upload a real cover image →</div>
          </div>
        </div>
      </div>
    </div>
    <div class="df">
      <button class="btn btn-primary" style="flex:1;justify-content:center" @click="drawerOpen = false; showSavedModal = true">
        <LucideIcon name="save" class="ic-sm" /> Save to Catalog
      </button>
      <button class="btn btn-ghost" @click="drawerOpen = false">Cancel</button>
    </div>
  </div>

  <!-- Modal: Saved -->
  <div :class="['mscrim', { open: showSavedModal }]" @click.self="showSavedModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--blue-50)"><LucideIcon name="book-check" :size="28" style="color:var(--blue)" /></div>
        <div class="modal-t">Title Catalogued</div>
        <div class="modal-s">The new title and its copies have been added to the catalog with unique barcodes generated.</div>
        <div class="modal-f"><button class="btn btn-primary btn-block" @click="showSavedModal = false">Done</button></div>
      </div>
    </div>
  </div>

  <!-- Modal: Delete -->
  <div :class="['mscrim', { open: showDeleteModal }]" @click.self="showDeleteModal = false">
    <div class="modal">
      <div class="modal-b">
        <div class="modal-ic" style="background:var(--red-50)"><LucideIcon name="trash-2" :size="28" style="color:var(--red)" /></div>
        <div class="modal-t">Remove Title?</div>
        <div class="modal-s">This removes the title and all its copies from the catalog. This cannot be undone.</div>
        <div class="modal-f">
          <button class="btn btn-ghost" style="flex:1;justify-content:center" @click="showDeleteModal = false">Cancel</button>
          <button class="btn btn-danger" style="flex:1;justify-content:center;background:var(--red);color:#fff;border:none" @click="showDeleteModal = false">Remove</button>
        </div>
      </div>
    </div>
  </div>
</template>
