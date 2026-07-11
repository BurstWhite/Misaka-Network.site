<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import Icon from './Icon.vue'

defineProps<{ label: string; icon: string; options: { value: string; label: string; icon?: string }[]; modelValue: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const open = ref(false)
const root = ref<HTMLElement>()
const outside = (event: MouseEvent) => { if (!root.value?.contains(event.target as Node)) open.value = false }
onMounted(() => document.addEventListener('click', outside))
onBeforeUnmount(() => document.removeEventListener('click', outside))
</script>

<template>
  <div ref="root" class="dropdown">
    <button class="dropdown-trigger" type="button" :aria-label="label" :aria-expanded="open" @click="open = !open"><Icon :name="icon" :size="18" /><span class="dropdown-trigger-label">{{ options.find(o => o.value === modelValue)?.label }}</span><Icon name="chevron" :size="14" /></button>
    <div v-if="open" class="dropdown-menu" role="menu">
      <button v-for="option in options" :key="option.value" type="button" :class="['dropdown-option', { active: option.value === modelValue }]" @click="emit('update:modelValue', option.value); open = false"><Icon v-if="option.icon" :name="option.icon" :size="17" /><span>{{ option.label }}</span><span class="check">{{ option.value === modelValue ? '✓' : '' }}</span></button>
    </div>
  </div>
</template>
