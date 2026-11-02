<script setup>
import { ChevronRight } from '@lucide/vue'

defineOptions({ name: 'ReportHeaderMenuTree' })

defineProps({ items: { type: Array, default: () => [] }, depth: { type: Number, default: 0 } })
const emit = defineEmits(['select'])
</script>

<template>
  <div class="pms-report-menu" :class="depth ? 'pms-report-submenu' : 'pms-report-root'">
    <div v-for="item in items" :key="item.type === 'group' ? `${depth}-${item.name}` : item.code" class="relative group/report-item">
      <button type="button" class="pms-report-menu-item" @click="item.type === 'report' && emit('select', item)">
        <span>{{ item.name }}</span>
        <ChevronRight v-if="item.type === 'group'" class="h-3.5 w-3.5 shrink-0 text-slate-400" />
      </button>
      <ReportHeaderMenuTree
        v-if="item.type === 'group' && item.children?.length"
        :items="item.children"
        :depth="depth + 1"
        class="invisible absolute top-0 left-full z-[110] opacity-0 transition-all duration-150 group-hover/report-item:visible group-hover/report-item:opacity-100"
        @select="emit('select', $event)"
      />
    </div>
  </div>
</template>

<style scoped>
.pms-report-menu { min-width: 220px; max-width: min(360px, calc(100vw - 28px)); border: 1px solid rgb(226 232 240 / .9); border-radius: 8px; background: #fff; padding: 4px; box-shadow: 0 12px 24px rgb(15 23 42 / .16); }
.pms-report-menu-item { display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 12px; border: 0; border-radius: 6px; background: transparent; padding: 6px 10px; color: #334155; cursor: pointer; font-size: 12px; line-height: 18px; text-align: left; }
.pms-report-menu-item:hover { background: #f0f9ff; color: #0369a1; }
.pms-report-submenu { margin-left: 4px; }
</style>
