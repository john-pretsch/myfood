<script setup>
import { computed, ref } from 'vue';
import { useAuth } from '../auth';
import api from '../api';

const { isAdmin, ready } = useAuth();

const tags = ref([]);
const name = ref('');
const search = ref('');
const error = ref('');
const saving = ref(false);

const filteredTags = computed(() => {
    const term = search.value.trim().toLowerCase();
    return term ? tags.value.filter((tag) => tag.name.toLowerCase().includes(term)) : tags.value;
});

async function load() {
    const { data } = await api.get('/tags');
    tags.value = data.data;
}

async function addTag() {
    if (!name.value.trim()) return;
    saving.value = true;
    error.value = '';
    try {
        const { data } = await api.post('/tags', { name: name.value.trim() });
        tags.value = [...tags.value, data.data].sort((a, b) => a.name.localeCompare(b.name));
        name.value = '';
    } catch (e) {
        error.value = e.response?.data?.errors?.name?.[0] ?? e.response?.data?.message ?? 'Could not add tag.';
    } finally {
        saving.value = false;
    }
}

async function removeTag(tag) {
    if (!confirm(`Delete the "${tag.name}" tag? It will be removed from every recipe that uses it.`)) return;
    error.value = '';
    try {
        await api.delete(`/tags/${tag.id}`);
        tags.value = tags.value.filter((t) => t.id !== tag.id);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Could not delete tag.';
    }
}

load();
</script>

<template>
    <div class="mx-auto max-w-lg">
        <h1 class="mb-4 text-2xl font-semibold text-stone-900">Tags</h1>

        <p v-if="ready && !isAdmin" class="text-sm text-stone-500">Only admins can manage tags.</p>

        <template v-else>
            <form class="mb-4 flex gap-2" @submit.prevent="addTag">
                <input
                    v-model="name"
                    type="text"
                    maxlength="100"
                    placeholder="New tag, e.g. vegetarian"
                    class="min-w-0 flex-1 rounded-md border border-stone-300 px-3 py-2 text-sm"
                />
                <button
                    type="submit"
                    :disabled="saving || !name.trim()"
                    class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700 disabled:opacity-50"
                >
                    Add
                </button>
            </form>

            <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>

            <input
                v-if="tags.length"
                v-model="search"
                type="text"
                placeholder="Search tags…"
                class="mb-4 w-full rounded-md border border-stone-300 px-3 py-2 text-sm"
            />

            <div v-if="filteredTags.length" class="flex flex-wrap gap-2">
                <button
                    v-for="tag in filteredTags"
                    :key="tag.id"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full border border-orange-300 bg-orange-100 px-3 py-1 text-sm text-orange-800 hover:bg-orange-200"
                    @click="removeTag(tag)"
                >
                    {{ tag.name }}
                    <span aria-hidden="true">✕</span>
                </button>
            </div>
            <p v-else-if="tags.length" class="text-sm text-stone-500">No tags match "{{ search }}".</p>
            <p v-else class="text-sm text-stone-500">No tags yet.</p>
        </template>
    </div>
</template>
