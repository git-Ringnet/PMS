<script setup>
defineProps({
  columns: {
    type: Number,
    default: 5
  },
  rows: {
    type: Number,
    default: 3
  }
})
</script>

<template>
  <tr v-for="r in rows" :key="r" class="border-b border-slate-100 last:border-none animate-pulse">
    <td v-for="c in columns" :key="c" class="p-3">
      <div class="h-4 bg-slate-200 rounded w-full max-w-[80%] opacity-50" :class="{'w-1/2': c % 2 === 0, 'w-3/4': c % 2 !== 0}"></div>
    </td>
  </tr>
</template>
