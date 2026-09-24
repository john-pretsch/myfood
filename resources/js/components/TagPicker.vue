<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    allowCreate: { type: Boolean, default: false },
    creating: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'create']);

const search = ref('');

function isSelected(name) {
    return props.modelValue.includes(name);
}

function toggle(name) {
    const next = isSelected(name) ? props.modelValue.filter((t) => t !== name) : [...props.modelValue, name];
    emit('update:modelValue', next);
}

const filteredTags = computed(() => {
    const term = search.value.trim().toLowerCase();
    return props.tags.filter((tag) => !isSelected(tag.name) && (!term || tag.name.toLowerCase().includes(term)));
});

const exactMatch = computed(() => props.tags.some((tag) => tag.name.toLowerCase() === search.value.trim().toLowerCase()));

const canCreate = computed(() => props.allowCreate && search.value.trim() !== '' && !exactMatch.value);

function createTag() {
    if (!canCreate.value) return;
    emit('create', search.value.trim());
    search.value = '';
}
</script>

<template>
    <div class="space-y-3">
        <div v-if="modelValue.length" class="flex flex-wrap gap-2">
            <button
                v-for="name in modelValue"
                :key="name"
                type="button"
                class="inline-flex items-center gap-1 rounded-full border border-orange-300 bg-orange-100 px-3 py-1 text-sm text-orange-800"
                @click="toggle(name)"
            >
                {{ name }}
                <span aria-hidden="true">✕</span>
            </button>
        </div>
        <p v-else class="text-sm text-stone-500">No tags assigned yet.</p>

        <input
            v-model="search"
            type="text"
            placeholder="Search tags…"
            class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm"
            @keydown.enter.prevent="createTag"
        />

        <div v-if="filteredTags.length || canCreate" class="flex flex-wrap gap-2">
            <button
                v-for="tag in filteredTags"
                :key="tag.id"
                type="button"
                class="rounded-full border border-stone-300 bg-white px-3 py-1 text-sm text-stone-600 hover:bg-stone-100"
                @click="toggle(tag.name)"
            >
                {{ tag.name }}
            </button>

            <button
                v-if="canCreate"
                type="button"
                :disabled="creating"
                class="rounded-full border border-dashed border-amber-400 px-3 py-1 text-sm text-amber-700 hover:bg-amber-50 disabled:opacity-50"
                @click="createTag"
            >
                + Add "{{ search.trim() }}"
            </button>
        </div>
        <p v-else-if="!tags.length" class="text-sm text-stone-500">No tags yet.<span v-if="!allowCreate"> An admin can add them on the Tags page.</span></p>
    </div>
</template>
