<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import LucideIcon from '@/components/LucideIcon.vue'

const router  = useRouter()
const email   = ref('')
const loading = ref(false)
const sent    = ref(false)

async function handleSubmit() {
  loading.value = true
  // TODO: wire to POST /api/auth/forgot-password
  await new Promise(r => setTimeout(r, 900))
  loading.value = false
  sent.value    = true
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

      <!-- Sent confirmation -->
      <template v-if="sent">
        <div class="auth-sent">
          <div class="auth-sent-ic"><LucideIcon name="mail-check" :size="32" /></div>
          <h2 class="auth-title" style="margin-bottom:8px">Check your email</h2>
          <p class="auth-sub" style="margin-bottom:24px">
            If <strong>{{ email }}</strong> is linked to a staff account, a reset link has been sent. Check your inbox (and spam folder).
          </p>
          <button class="btn btn-ghost btn-block" style="justify-content:center" @click="router.push('/auth/login')">
            <LucideIcon name="arrow-left" class="ic-sm" /> Back to Sign In
          </button>
        </div>
      </template>

      <!-- Recovery form -->
      <template v-else>
        <div class="auth-back" @click="router.push('/auth/login')">
          <LucideIcon name="arrow-left" class="ic-sm" /> Back to Sign In
        </div>

        <div class="auth-head">
          <h1 class="auth-title">Forgot password?</h1>
          <p class="auth-sub">Enter your email and we'll send a reset link to your inbox.</p>
        </div>

        <form class="auth-form" @submit.prevent="handleSubmit">
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

          <button class="btn btn-primary btn-block auth-btn" type="submit" :disabled="loading">
            <LucideIcon v-if="loading" name="refresh-cw" class="ic-sm auth-spin" />
            <LucideIcon v-else name="send" class="ic-sm" />
            {{ loading ? 'Sending…' : 'Send Reset Link' }}
          </button>
        </form>

        <p class="auth-footer">
          Only registered staff and admin accounts can request a password reset.
        </p>
      </template>

    </div>

    <!-- Decorative side panel -->
    <div class="auth-panel">
      <div class="auth-panel-inner">
        <div class="auth-panel-icon"><LucideIcon name="lock-keyhole" :size="40" /></div>
        <h2 class="auth-panel-title">Secure Access<br />Recovery</h2>
        <p class="auth-panel-sub">Reset links expire after 60 minutes and can only be used once. Contact your system administrator if you need further assistance.</p>
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

.auth-card {
  width: 460px;
  flex-shrink: 0;
  background: #fff;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 48px;
  border-right: 1px solid var(--line);
}

.auth-brand {
  display: flex;
  align-items: center;
  gap: 11px;
  margin-bottom: 36px;
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
  bottom: 0; left: 0; right: 0;
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
.auth-bsub { font-size: 11px; color: var(--faint); letter-spacing: .02em; }

.auth-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--muted);
  cursor: pointer;
  margin-bottom: 24px;
  font-weight: 500;
}
.auth-back:hover { color: var(--blue); }

.auth-head { margin-bottom: 28px; }
.auth-title {
  font-family: var(--display);
  font-size: 26px;
  font-weight: 800;
  color: var(--navy);
  margin-bottom: 6px;
}
.auth-sub { font-size: 13.5px; color: var(--muted); }

.auth-form { display: flex; flex-direction: column; gap: 18px; }

.auth-btn { height: 44px; font-size: 14px; }
.auth-btn:disabled { opacity: .65; cursor: not-allowed; }

.auth-footer {
  margin-top: 28px;
  font-size: 11.5px;
  color: var(--faint);
  text-align: center;
  line-height: 1.6;
}

/* Sent state */
.auth-sent { text-align: center; }
.auth-sent-ic {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  background: #EBF5FF;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--blue);
  margin: 0 auto 24px;
}

@keyframes spin { to { transform: rotate(360deg); } }
.auth-spin { animation: spin .8s linear infinite; }

/* Right panel */
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
    transparent, transparent 40px,
    rgba(255,255,255,.02) 40px,
    rgba(255,255,255,.02) 80px
  );
}
.auth-panel-inner {
  position: relative;
  z-index: 1;
  max-width: 360px;
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
}

@media (max-width: 860px) {
  .auth-shell { flex-direction: column; }
  .auth-card  { width: 100%; padding: 32px 24px; border-right: none; border-bottom: 1px solid var(--line); }
  .auth-panel { min-height: 200px; padding: 32px 24px; }
}
</style>
