<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm transition-opacity">
    <div class="bg-[#111827] border border-slate-700/80 rounded-2xl w-full max-w-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
      
      <!-- Modal Header -->
      <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/60">
        <div class="flex items-center space-x-3">
          <div class="w-9 h-9 rounded-xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-purple-400 font-bold text-lg">
            ⚡
          </div>
          <div>
            <h3 class="text-base font-semibold text-white flex items-center gap-2">
              <span>Broker Live Market Feed Integration</span>
              <span class="text-[11px] px-2 py-0.5 rounded bg-emerald-950 border border-emerald-800 text-emerald-400 font-mono">
                Active: {{ statusData?.current_provider || 'simulation' }}
              </span>
            </h3>
            <p class="text-xs text-slate-400">
              Connect official Zerodha Kite, Upstox v2, or Angel One SmartAPI for real-time tick streaming
            </p>
          </div>
        </div>

        <button
          @click="$emit('close')"
          class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition"
        >
          ✕
        </button>
      </div>

      <!-- Security / Regulatory Notice -->
      <div class="bg-amber-950/30 border-b border-amber-800/30 px-6 py-2.5 flex items-start gap-3">
        <span class="text-amber-400 text-sm mt-0.5">ℹ️</span>
        <div class="text-[11px] text-amber-200/90 leading-relaxed">
          <strong class="font-semibold text-amber-300">SEBI Regulatory Notice:</strong> Broker API keys cannot be automatically generated with just an email (<span class="underline">shachisheh@gmail.com</span>) or phone (<span class="underline">+91 9711438500</span>). You must log into the official broker portal once with your 2FA OTP to generate the keys. Follow the quick steps below:
        </div>
      </div>

      <!-- Tabs Navigation -->
      <div class="flex border-b border-slate-800 bg-slate-900/40 px-6 pt-2 overflow-x-auto">
        <button
          @click="activeTab = 'angelone'"
          :class="[
            'px-4 py-2.5 text-xs font-semibold border-b-2 transition flex items-center gap-2 whitespace-nowrap cursor-pointer',
            activeTab === 'angelone'
              ? 'border-blue-500 text-blue-400 bg-blue-500/5'
              : 'border-transparent text-slate-400 hover:text-slate-200'
          ]"
        >
          <span>👼 Angel One SmartAPI</span>
          <span class="px-1.5 py-0.2 rounded bg-blue-900/60 text-[10px] text-blue-300 border border-blue-700/50">Free API</span>
        </button>

        <button
          @click="activeTab = 'upstox'"
          :class="[
            'px-4 py-2.5 text-xs font-semibold border-b-2 transition flex items-center gap-2 whitespace-nowrap cursor-pointer',
            activeTab === 'upstox'
              ? 'border-purple-500 text-purple-400 bg-purple-500/5'
              : 'border-transparent text-slate-400 hover:text-slate-200'
          ]"
        >
          <span>💜 Upstox API v2</span>
          <span class="px-1.5 py-0.2 rounded bg-purple-900/60 text-[10px] text-purple-300 border border-purple-700/50">100% Free</span>
        </button>

        <button
          @click="activeTab = 'zerodha'"
          :class="[
            'px-4 py-2.5 text-xs font-semibold border-b-2 transition flex items-center gap-2 whitespace-nowrap cursor-pointer',
            activeTab === 'zerodha'
              ? 'border-orange-500 text-orange-400 bg-orange-500/5'
              : 'border-transparent text-slate-400 hover:text-slate-200'
          ]"
        >
          <span>🧡 Zerodha Kite Connect</span>
        </button>

        <button
          @click="activeTab = 'simulation'"
          :class="[
            'px-4 py-2.5 text-xs font-semibold border-b-2 transition flex items-center gap-2 whitespace-nowrap cursor-pointer',
            activeTab === 'simulation'
              ? 'border-emerald-500 text-emerald-400 bg-emerald-500/5'
              : 'border-transparent text-slate-400 hover:text-slate-200'
          ]"
        >
          <span>🟢 Simulation Stream (Offline/Weekend)</span>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6 overflow-y-auto space-y-5 text-xs">
        
        <!-- ANGEL ONE TAB -->
        <div v-if="activeTab === 'angelone'" class="space-y-4">
          <div class="p-3.5 rounded-xl bg-blue-950/20 border border-blue-800/40 text-blue-200 space-y-2">
            <h4 class="font-bold text-blue-300 text-sm flex items-center justify-between">
              <span>Angel One SmartAPI Setup (Free Developer Account)</span>
              <a
                href="https://smartapi.angelone.in"
                target="_blank"
                class="px-2.5 py-1 rounded bg-blue-600 hover:bg-blue-500 text-white text-[11px] font-medium transition inline-flex items-center gap-1 cursor-pointer"
              >
                Open SmartAPI Portal ↗
              </a>
            </h4>
            <ol class="list-decimal list-inside space-y-2 text-slate-300 text-xs">
              <li>Log in to <a href="https://smartapi.angelone.in" target="_blank" class="text-blue-400 underline font-mono">smartapi.angelone.in</a> with your Angel One Client ID.</li>
              <li>Click <strong class="text-white">"Create an App"</strong> / <strong class="text-white">"Add App"</strong>:
                <ul class="list-disc list-inside ml-4 mt-1 space-y-1 text-slate-300">
                  <li><strong>App Name:</strong> <span class="font-mono text-blue-300">Option Chain Pro</span></li>
                  <li><strong>Redirect URL:</strong> <code class="px-1.5 py-0.5 rounded bg-slate-900 border border-slate-700 text-emerald-300 font-mono text-[11px] select-all">https://smartapi.angelone.in</code> <span class="text-slate-400 text-[11px]">(Angel One requires HTTPS and rejects localhost)</span></li>
                  <li><strong>Primary Static IP:</strong> <code class="px-1.5 py-0.5 rounded bg-slate-900 border border-slate-700 text-amber-300 font-mono text-[11px] select-all">122.168.79.123</code> <span class="text-slate-400 text-[11px]">(Your current public IP)</span></li>
                </ul>
              </li>
              <li>Click <strong class="text-white">"Add"</strong>, then copy your generated <strong class="text-white">API Key</strong> below.</li>
              <li>Enter your <strong class="text-white">Client Code</strong>, <strong class="text-white">MPIN</strong>, and current <strong class="text-white">6-digit TOTP</strong> (or TOTP Secret):</li>
            </ol>
          </div>

          <!-- Credentials Input Form -->
          <div class="space-y-3 p-4 rounded-xl bg-slate-900/60 border border-slate-800">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-300 font-semibold mb-1">Angel One API Key (SmartAPI Key)</label>
                <input
                  v-model="angelKey"
                  type="text"
                  placeholder="e.g. fE2o3m8X..."
                  class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:border-blue-500 focus:outline-none"
                />
              </div>

              <div>
                <label class="block text-slate-300 font-semibold mb-1">Client ID / Code</label>
                <input
                  v-model="angelClientCode"
                  type="text"
                  placeholder="e.g. S123456"
                  class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono uppercase focus:border-blue-500 focus:outline-none"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-300 font-semibold mb-1">MPIN / Password</label>
                <input
                  v-model="angelPassword"
                  type="password"
                  placeholder="Enter 4-digit MPIN or SmartAPI password"
                  class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:border-blue-500 focus:outline-none"
                />
              </div>

              <div>
                <label class="block text-slate-300 font-semibold mb-1 flex items-center justify-between">
                  <span>6-Digit TOTP or TOTP Secret</span>
                  <span class="text-[10px] text-slate-400 font-normal">Authenticator App</span>
                </label>
                <input
                  v-model="angelTotp"
                  type="text"
                  placeholder="Current 6-digit TOTP code or TOTP Secret key"
                  class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:border-blue-500 focus:outline-none"
                />
              </div>
            </div>

            <p class="text-[11px] text-slate-400 italic">
              💡 Tip: If you enter your TOTP Secret Key (from Angel One TOTP QR setup), the terminal will automatically generate tokens and keep your session renewed every morning!
            </p>

            <div class="flex items-center justify-between pt-2">
              <button
                @click="saveCredentials('angelone')"
                :disabled="saving || authenticating"
                class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium rounded-lg border border-slate-700 transition cursor-pointer"
              >
                {{ saving ? 'Saving...' : '💾 Save to .env' }}
              </button>

              <button
                @click="authorizeAngelOne"
                :disabled="authenticating || saving"
                class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg shadow-lg shadow-blue-600/30 transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
              >
                <span>{{ authenticating ? '⏳ Authenticating...' : '🔑 1-Click Connect & Authorize' }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- UPSTOX TAB -->
        <div v-if="activeTab === 'upstox'" class="space-y-4">
          <div class="p-3.5 rounded-xl bg-purple-950/20 border border-purple-800/40 text-purple-200 space-y-2">
            <h4 class="font-bold text-purple-300 text-sm flex items-center justify-between">
              <span>Step-by-Step Upstox Setup (Takes 2 minutes)</span>
              <a
                href="https://account.upstox.com/developer/apps"
                target="_blank"
                class="px-2.5 py-1 rounded bg-purple-600 hover:bg-purple-500 text-white text-[11px] font-medium transition inline-flex items-center gap-1"
              >
                Open Upstox Developer Portal ↗
              </a>
            </h4>
            <ol class="list-decimal list-inside space-y-1.5 text-slate-300 text-xs">
              <li>Open <a href="https://account.upstox.com/developer/apps" target="_blank" class="text-purple-400 underline font-mono">account.upstox.com/developer/apps</a> and sign in with your phone <span class="font-mono text-purple-300">+91 9711438500</span> and OTP.</li>
              <li>Click <strong class="text-white">"New App"</strong>. Give it an App Name (e.g. <span class="font-mono text-purple-300">Option Chain Pro</span>).</li>
              <li>Set <strong class="text-white">Redirect URL</strong> to:
                <code class="px-2 py-0.5 rounded bg-slate-900 border border-slate-700 font-mono text-amber-300 select-all block mt-1">
                  http://localhost:8000/broker/upstox/callback
                </code>
              </li>
              <li>Copy the generated <strong class="text-white">API Key</strong> and <strong class="text-white">API Secret</strong> below:</li>
            </ol>
          </div>

          <!-- Credentials Input Form -->
          <div class="space-y-3 p-4 rounded-xl bg-slate-900/60 border border-slate-800">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Upstox API Key (Client ID)</label>
              <input
                v-model="upstoxKey"
                type="text"
                placeholder="e.g. 5d891b9f-...."
                class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:border-purple-500 focus:outline-none"
              />
            </div>

            <div>
              <label class="block text-slate-300 font-semibold mb-1">Upstox API Secret</label>
              <input
                v-model="upstoxSecret"
                type="password"
                placeholder="Enter your Upstox API secret"
                class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:border-purple-500 focus:outline-none"
              />
            </div>

            <div class="flex items-center justify-between pt-2">
              <button
                @click="saveCredentials('upstox')"
                :disabled="saving"
                class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium rounded-lg border border-slate-700 transition"
              >
                {{ saving ? 'Saving...' : '💾 Save to .env' }}
              </button>

              <a
                href="/broker/upstox/login"
                class="px-5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg shadow-lg shadow-purple-600/30 transition flex items-center gap-2"
              >
                <span>🔑 1-Click Connect & Authorize</span>
              </a>
            </div>
          </div>
        </div>

        <!-- ZERODHA TAB -->
        <div v-if="activeTab === 'zerodha'" class="space-y-4">
          <div class="p-3.5 rounded-xl bg-orange-950/20 border border-orange-800/40 text-orange-200 space-y-2">
            <h4 class="font-bold text-orange-300 text-sm flex items-center justify-between">
              <span>Zerodha Kite Connect Setup</span>
              <a
                href="https://developers.kite.trade"
                target="_blank"
                class="px-2.5 py-1 rounded bg-orange-600 hover:bg-orange-500 text-white text-[11px] font-medium transition inline-flex items-center gap-1"
              >
                Open Kite Developer Portal ↗
              </a>
            </h4>
            <ol class="list-decimal list-inside space-y-1.5 text-slate-300 text-xs">
              <li>Open <a href="https://developers.kite.trade" target="_blank" class="text-orange-400 underline font-mono">developers.kite.trade</a> and log in with your Kite Client ID & TOTP.</li>
              <li>Click <strong class="text-white">"Create New App"</strong>.</li>
              <li>Set <strong class="text-white">Redirect URL</strong> to:
                <code class="px-2 py-0.5 rounded bg-slate-900 border border-slate-700 font-mono text-amber-300 select-all block mt-1">
                  http://localhost:8000/broker/zerodha/callback
                </code>
              </li>
              <li>Copy the <strong class="text-white">API Key</strong> and <strong class="text-white">API Secret</strong> into the fields below:</li>
            </ol>
          </div>

          <!-- Credentials Input Form -->
          <div class="space-y-3 p-4 rounded-xl bg-slate-900/60 border border-slate-800">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Zerodha API Key</label>
              <input
                v-model="zerodhaKey"
                type="text"
                placeholder="e.g. xy78za90b..."
                class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:border-orange-500 focus:outline-none"
              />
            </div>

            <div>
              <label class="block text-slate-300 font-semibold mb-1">Zerodha API Secret</label>
              <input
                v-model="zerodhaSecret"
                type="password"
                placeholder="Enter your Kite API secret"
                class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:border-orange-500 focus:outline-none"
              />
            </div>

            <div class="flex items-center justify-between pt-2">
              <button
                @click="saveCredentials('zerodha')"
                :disabled="saving"
                class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium rounded-lg border border-slate-700 transition"
              >
                {{ saving ? 'Saving...' : '💾 Save to .env' }}
              </button>

              <a
                href="/broker/zerodha/login"
                class="px-5 py-2 bg-orange-600 hover:bg-orange-500 text-white font-bold rounded-lg shadow-lg shadow-orange-600/30 transition flex items-center gap-2"
              >
                <span>🔑 1-Click Connect & Authorize</span>
              </a>
            </div>
          </div>
        </div>

        <!-- SIMULATION TAB -->
        <div v-if="activeTab === 'simulation'" class="space-y-4">
          <div class="p-4 rounded-xl bg-emerald-950/20 border border-emerald-800/40 text-emerald-200 space-y-2">
            <h4 class="font-bold text-emerald-300 text-sm">High-Fidelity Simulated Market Stream</h4>
            <p class="text-slate-300 leading-relaxed">
              Provides real-time realistic drift, random OI movements, institutional unwinding, and dynamic ATM shifts 24/7. Perfect for testing indicators, strategies, and alerts when Indian markets are closed or outside trading hours (3:30 PM - 9:15 AM).
            </p>
            <div class="pt-3">
              <button
                @click="saveCredentials('simulation')"
                :disabled="saving"
                class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-black font-bold rounded-lg transition"
              >
                {{ saving ? 'Switching...' : '🟢 Activate Simulated Stream' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Action Message Alert -->
        <div v-if="noticeMessage" :class="['p-3 rounded-lg text-xs font-medium border', isNoticeSuccess ? 'bg-emerald-950/40 text-emerald-300 border-emerald-800' : 'bg-rose-950/40 text-rose-300 border-rose-800']">
          {{ noticeMessage }}
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="px-6 py-3 border-t border-slate-800 bg-slate-900/60 flex items-center justify-between text-xs text-slate-400">
        <div>
          <span>Registered User: </span>
          <span class="text-slate-200 font-medium">shachisheh@gmail.com</span>
          <span class="text-slate-500 mx-2">|</span>
          <span class="text-slate-200 font-medium">+91 9711438500</span>
        </div>
        <button
          @click="$emit('close')"
          class="px-4 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg border border-slate-700 transition"
        >
          Close
        </button>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'provider-changed']);

const activeTab = ref('angelone');
const angelKey = ref('');
const angelClientCode = ref('');
const angelPassword = ref('');
const angelTotp = ref('');
const upstoxKey = ref('');
const upstoxSecret = ref('');
const zerodhaKey = ref('');
const zerodhaSecret = ref('');
const saving = ref(false);
const authenticating = ref(false);
const statusData = ref(null);
const noticeMessage = ref('');
const isNoticeSuccess = ref(true);

const loadStatus = async () => {
  try {
    const res = await fetch('/broker/status');
    if (res.ok) {
      statusData.value = await res.json();
      if (statusData.value?.angelone?.api_key && !angelKey.value) {
        angelKey.value = statusData.value.angelone.api_key;
      }
      if (statusData.value?.angelone?.client_code && !angelClientCode.value) {
        angelClientCode.value = statusData.value.angelone.client_code;
      }
      if (!angelPassword.value) {
        angelPassword.value = '1234';
      }
      if (statusData.value?.current_provider) {
        const cur = statusData.value.current_provider.toLowerCase();
        if (cur.includes('angel')) activeTab.value = 'angelone';
        else if (cur.includes('upstox')) activeTab.value = 'upstox';
        else if (cur.includes('zerodha') || cur.includes('kite')) activeTab.value = 'zerodha';
      }
    }
  } catch (e) {
    console.error('Failed to load broker status', e);
  }
};

const saveCredentials = async (provider) => {
  saving.value = true;
  noticeMessage.value = '';

  try {
    const payload = {
      provider,
      api_key: provider === 'angelone' ? angelKey.value : (provider === 'upstox' ? upstoxKey.value : (provider === 'zerodha' ? zerodhaKey.value : null)),
      api_secret: provider === 'upstox' ? upstoxSecret.value : (provider === 'zerodha' ? zerodhaSecret.value : null),
      client_code: provider === 'angelone' ? angelClientCode.value : null,
      password: provider === 'angelone' ? angelPassword.value : null,
      totp_secret: provider === 'angelone' ? angelTotp.value : null,
    };

    const res = await fetch('/broker/save-credentials', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (res.ok && data.success) {
      noticeMessage.value = data.message || 'Saved successfully.';
      isNoticeSuccess.value = true;
      await loadStatus();
      emit('provider-changed', provider);
    } else {
      noticeMessage.value = data.error || 'Failed to save credentials.';
      isNoticeSuccess.value = false;
    }
  } catch (e) {
    noticeMessage.value = 'Failed to save credentials: ' + e.message;
    isNoticeSuccess.value = false;
  } finally {
    saving.value = false;
  }
};

const authorizeAngelOne = async () => {
  authenticating.value = true;
  noticeMessage.value = '';

  try {
    const cleanTotp = angelTotp.value?.trim() || '';
    const isDirectDigits = /^\d{6}$/.test(cleanTotp);

    const payload = {
      api_key: angelKey.value?.trim(),
      client_code: angelClientCode.value?.trim(),
      password: angelPassword.value?.trim(),
      totp: isDirectDigits ? cleanTotp : null,
      totp_secret: !isDirectDigits ? cleanTotp : null,
    };

    const res = await fetch('/broker/angelone/authorize', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (res.ok && data.success) {
      noticeMessage.value = data.message || 'Angel One SmartAPI connected successfully!';
      isNoticeSuccess.value = true;
      await loadStatus();
      emit('provider-changed', 'AngelOneSmartAPI');
    } else {
      noticeMessage.value = data.error || 'Failed to authenticate with Angel One SmartAPI.';
      isNoticeSuccess.value = false;
    }
  } catch (e) {
    noticeMessage.value = 'Failed to authenticate with Angel One: ' + e.message;
    isNoticeSuccess.value = false;
  } finally {
    authenticating.value = false;
  }
};

onMounted(() => {
  loadStatus();
});
</script>
