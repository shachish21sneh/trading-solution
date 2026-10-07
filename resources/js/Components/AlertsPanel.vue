<template>
  <div class="bg-[#111827] border border-slate-800 rounded-xl p-4 flex flex-col h-full shadow-lg">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
      <div class="flex items-center space-x-2">
        <span class="relative flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
        </span>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-200">
          Live OI Intelligence Alerts
        </h3>
      </div>
      <div class="flex items-center space-x-2">
        <button
          @click="toggleAudio"
          :class="[
            'p-1.5 rounded text-xs transition border',
            audioEnabled ? 'bg-emerald-950/60 text-emerald-400 border-emerald-800' : 'bg-slate-800 text-slate-400 border-slate-700'
          ]"
          title="Toggle Audio Beep"
        >
          {{ audioEnabled ? '🔊 Sound On' : '🔇 Muted' }}
        </button>
        <button
          @click="$emit('clear')"
          class="text-xs text-slate-400 hover:text-slate-200 px-2 py-1 rounded hover:bg-slate-800 transition"
        >
          Clear
        </button>
      </div>
    </div>

    <!-- Alert List -->
    <div class="overflow-y-auto space-y-2.5 mt-3 flex-1 max-h-72 pr-1">
      <div
        v-for="alert in alerts"
        :key="alert.id || alert.title + alert.created_at"
        :class="[
          'p-2.5 rounded-lg border text-xs transition-all duration-300',
          severityClasses(alert.severity)
        ]"
      >
        <div class="flex items-start justify-between">
          <div class="flex items-center space-x-1.5">
            <span class="font-bold tracking-tight">{{ alert.title }}</span>
            <span v-if="alert.strike_price" class="px-1.5 py-0.2 rounded bg-black/40 text-[10px] font-num">
              {{ alert.strike_price }} {{ alert.option_type }}
            </span>
          </div>
          <span class="text-[10px] text-slate-400 font-num whitespace-nowrap ml-2">
            {{ formatTime(alert.created_at) }}
          </span>
        </div>
        <p class="text-[11px] text-slate-300 mt-1 leading-snug">
          {{ alert.message }}
        </p>
      </div>

      <div v-if="alerts.length === 0" class="text-center py-8 text-slate-500 text-xs">
        No active alerts triggered yet. Monitoring real-time OI spikes...
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  alerts: {
    type: Array,
    default: () => [],
  },
});

defineEmits(['clear']);

const audioEnabled = ref(false);

function toggleAudio() {
  audioEnabled.value = !audioEnabled.value;
}

function severityClasses(sev) {
  switch (sev) {
    case 'critical':
      return 'bg-rose-950/40 border-rose-800/60 text-rose-200';
    case 'warning':
      return 'bg-amber-950/40 border-amber-800/60 text-amber-200';
    case 'info':
    default:
      return 'bg-indigo-950/30 border-indigo-800/50 text-indigo-200';
  }
}

function formatTime(timestamp) {
  if (!timestamp) return 'Now';
  const date = new Date(timestamp);
  return date.toLocaleTimeString('en-IN', { hour12: false });
}
</script>
