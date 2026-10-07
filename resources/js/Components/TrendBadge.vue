<template>
  <span
    :class="[
      'inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium tracking-tight whitespace-nowrap transition-all duration-200',
      badgeStyle
    ]"
    :title="trend"
  >
    <span v-if="icon" class="mr-1">{{ icon }}</span>
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  trend: {
    type: String,
    default: 'Neutral',
  },
});

const config = computed(() => {
  const t = props.trend || 'Neutral';

  switch (t) {
    case 'OI Unwinding':
      return {
        label: 'OI Unwinding',
        icon: '🔴',
        class: 'bg-rose-950/80 text-rose-300 border border-rose-800/60 shadow-sm shadow-rose-900/40',
      };
    case 'Fresh OI Build-up':
      return {
        label: 'Fresh OI Build-up',
        icon: '🟢',
        class: 'bg-emerald-950/80 text-emerald-300 border border-emerald-800/60 shadow-sm shadow-emerald-900/40',
      };
    case 'Long Build-up':
      return {
        label: 'Long Build-up',
        icon: '▲',
        class: 'bg-emerald-900/60 text-emerald-300 border border-emerald-700/50',
      };
    case 'Short Build-up':
      return {
        label: 'Short Build-up',
        icon: '▼',
        class: 'bg-rose-900/60 text-rose-300 border border-rose-700/50',
      };
    case 'Short Covering':
      return {
        label: 'Short Covering',
        icon: '⚡',
        class: 'bg-amber-900/60 text-amber-300 border border-amber-700/50',
      };
    case 'Long Unwinding':
      return {
        label: 'Long Unwinding',
        icon: '⏳',
        class: 'bg-orange-900/60 text-orange-300 border border-orange-700/50',
      };
    case 'Call Writing':
      return {
        label: 'Call Writing',
        icon: '🛡️',
        class: 'bg-indigo-950/70 text-indigo-300 border border-indigo-700/50',
      };
    case 'Put Writing':
      return {
        label: 'Put Writing',
        icon: '🧱',
        class: 'bg-teal-950/70 text-teal-300 border border-teal-700/50',
      };
    case 'OI Increasing':
      return {
        label: 'OI Increasing',
        icon: '↑',
        class: 'bg-blue-950/60 text-blue-300 border border-blue-800/40',
      };
    case 'OI Decreasing':
      return {
        label: 'OI Decreasing',
        icon: '↓',
        class: 'bg-slate-800 text-slate-400 border border-slate-700',
      };
    default:
      return {
        label: t,
        icon: '',
        class: 'bg-slate-800/80 text-slate-400 border border-slate-700/50',
      };
  }
});

const label = computed(() => config.value.label);
const icon = computed(() => config.value.icon);
const badgeStyle = computed(() => config.value.class);
</script>
