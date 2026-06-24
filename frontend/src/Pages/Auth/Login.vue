<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import LucideIcon from '@/components/LucideIcon.vue'
import { useAuthStore } from '@/stores/auth'

const router   = useRouter()
const auth     = useAuthStore()
const email    = ref('')
const password = ref('')
const showPw   = ref(false)
const loading  = ref(false)
const error    = ref('')

async function handleLogin() {
  error.value   = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value)

    if (!auth.isStaff) {
      auth.logout()
      error.value = 'Access denied. This panel is for staff and admin accounts only.'
      return
    }

    router.push('/staff/dashboard')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Login failed. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-shell">
    <div class="auth-card">

      <!-- Brand -->
      <div class="auth-brand">
        <div class="auth-logo">
          <LucideIcon name="library" :size="22" />
        </div>
        <div>
          <div class="auth-bname">Mambayya House</div>
          <div class="auth-bsub">Library Management System</div>
        </div>
      </div>

      <!-- Heading -->
      <div class="auth-head">
        <h1 class="auth-title">Welcome back</h1>
        <p class="auth-sub">Sign in to your staff account to continue</p>
      </div>

      <!-- Error banner -->
      <div v-if="error" class="auth-error">
        <LucideIcon name="alert-triangle" class="ic-sm" />
        {{ error }}
      </div>

      <!-- Form -->
      <form class="auth-form" @submit.prevent="handleLogin">
        <div class="fg">
          <label class="fl">Email address</label>
          <input
            v-model="email"
            class="fi"
            type="email"
            placeholder="you@acds-library.com"
            autocomplete="email"
            required
          />
        </div>

        <div class="fg">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <label class="fl" style="margin-bottom:0">Password</label>
            <a class="auth-link" @click.prevent="router.push('/auth/recover')">Forgot password?</a>
          </div>
          <div class="pw-wrap">
            <input
              v-model="password"
              class="fi"
              :type="showPw ? 'text' : 'password'"
              placeholder="Enter your password"
              autocomplete="current-password"
              required
            />
            <button type="button" class="pw-toggle" @click="showPw = !showPw">
              <LucideIcon :name="showPw ? 'eye' : 'eye'" :size="16" />
            </button>
          </div>
        </div>

        <button class="btn btn-primary btn-block auth-btn" type="submit" :disabled="loading">
          <LucideIcon v-if="loading" name="refresh-cw" class="ic-sm auth-spin" />
          <LucideIcon v-else name="arrow-right" class="ic-sm" />
          {{ loading ? 'Signing in…' : 'Sign In' }}
        </button>
      </form>

      <!-- Footer note -->
      <p class="auth-footer">
        Staff &amp; admin access only. Contact your system administrator if you need an account.
      </p>

    </div>

    <!-- Decorative side panel -->
    <div class="auth-panel">
      <div class="auth-panel-inner">
        <div class="auth-panel-icon"><LucideIcon name="book-open" :size="40" /></div>
        <h2 class="auth-panel-title">ACDS Library<br />Back-Office</h2>
        <p class="auth-panel-sub">Manage circulation, catalog, members, acquisitions, and reports — all in one place.</p>
        <div class="auth-panel-badges">
          <span class="auth-pbadge"><LucideIcon name="check" :size="13" /> Circulation &amp; Loans</span>
          <span class="auth-pbadge"><LucideIcon name="check" :size="13" /> Catalog Management</span>
          <span class="auth-pbadge"><LucideIcon name="check" :size="13" /> Member Registry</span>
          <span class="auth-pbadge"><LucideIcon name="check" :size="13" /> Reports &amp; Analytics</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.auth-shell {
  min-height: 100vh;
  display: flex;
  background: var(--bg);
}

/* Left card */
.auth-card {
  width: 460px;
  flex-shrink: 0;
  background: #fff;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 48px 48px;
  border-right: 1px solid var(--line);
}

.auth-brand {
  display: flex;
  align-items: center;
  gap: 11px;
  margin-bottom: 40px;
}
.auth-logo {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  background: linear-gradient(150deg, var(--blue), var(--sky));
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  position: relative;
  overflow: hidden;
  flex-shrink: 0;
}
.auth-logo::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--gold);
}
.auth-bname {
  font-family: var(--display);
  font-size: 15px;
  font-weight: 700;
  color: var(--navy);
  line-height: 1.2;
}
.auth-bsub {
  font-size: 11px;
  color: var(--faint);
  letter-spacing: .02em;
}

.auth-head { margin-bottom: 28px; }
.auth-title {
  font-family: var(--display);
  font-size: 26px;
  font-weight: 800;
  color: var(--navy);
  margin-bottom: 6px;
}
.auth-sub { font-size: 13.5px; color: var(--muted); }

.auth-error {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--red-50);
  border: 1px solid #f6c9cf;
  color: var(--red-600);
  font-size: 13px;
  padding: 10px 14px;
  border-radius: var(--r2);
  margin-bottom: 20px;
}

.auth-form { display: flex; flex-direction: column; gap: 18px; }

.pw-wrap { position: relative; }
.pw-wrap .fi { padding-right: 44px; }
.pw-toggle {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--faint);
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
}
.pw-toggle:hover { color: var(--blue); }

.auth-btn { margin-top: 8px; height: 44px; font-size: 14px; }
.auth-btn:disabled { opacity: .65; cursor: not-allowed; transform: none !important; }

.auth-link {
  font-size: 12px;
  color: var(--blue);
  cursor: pointer;
  font-weight: 500;
}
.auth-link:hover { text-decoration: underline; }

.auth-footer {
  margin-top: 28px;
  font-size: 11.5px;
  color: var(--faint);
  text-align: center;
  line-height: 1.6;
}

@keyframes spin { to { transform: rotate(360deg); } }
.auth-spin { animation: spin .8s linear infinite; }

/* Right decorative panel */
.auth-panel {
  flex: 1;
  background: linear-gradient(145deg, var(--navy) 0%, #1763C9 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48px;
  position: relative;
  overflow: hidden;
}
.auth-panel::before {
  content: '';
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(
    45deg,
    transparent,
    transparent 40px,
    rgba(255,255,255,.02) 40px,
    rgba(255,255,255,.02) 80px
  );
}
.auth-panel-inner {
  position: relative;
  z-index: 1;
  max-width: 380px;
  text-align: center;
}
.auth-panel-icon {
  width: 72px;
  height: 72px;
  border-radius: 18px;
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.2);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  margin: 0 auto 24px;
}
.auth-panel-title {
  font-family: var(--display);
  font-size: 30px;
  font-weight: 800;
  color: #fff;
  line-height: 1.2;
  margin-bottom: 14px;
}
.auth-panel-sub {
  font-size: 14px;
  color: rgba(255,255,255,.65);
  line-height: 1.7;
  margin-bottom: 32px;
}
.auth-panel-badges {
  display: flex;
  flex-direction: column;
  gap: 10px;
  align-items: flex-start;
  display: inline-flex;
}
.auth-pbadge {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: rgba(255,255,255,.85);
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.14);
  padding: 7px 14px;
  border-radius: 99px;
}

@media (max-width: 860px) {
  .auth-shell { flex-direction: column; }
  .auth-card  { width: 100%; padding: 32px 24px; border-right: none; border-bottom: 1px solid var(--line); }
  .auth-panel { min-height: 220px; padding: 32px 24px; }
}
</style>
