<script setup>
import { ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import api from '../api';

const router = useRouter();
const recipes = ref([]);
const search = ref('');
const loading = ref(true);

const importUrl = ref('');
const importing = ref(false);
const importError = ref('');
const pasteMode = ref(false);
const pasteHtml = ref('');

async function importFromUrl() {
    const url = importUrl.value.trim();
    const html = pasteHtml.value.trim();
    if (importing.value || (pasteMode.value ? !html : !url)) return;

    importing.value = true;
    importError.value = '';
    try {
        const payload = pasteMode.value ? { html, url: url || undefined } : { url };
        const { data } = await api.post('/recipes/import', payload);
        router.push({ name: 'recipes.show', params: { id: data.data.id } });
    } catch (e) {
        importError.value =
            e.response?.data?.message ??
            e.response?.data?.errors?.url?.[0] ??
            e.response?.data?.errors?.html?.[0] ??
            'Import failed.';
        importing.value = false;
    }
}

async function load() {
    loading.value = true;
    const { data } = await api.get('/recipes', { params: { search: search.value || undefined } });
    recipes.value = data.data;
    loading.value = false;
}

let debounce;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(load, 300);
});

load();
</script>

<template>
    <div>
        <form class="mb-1 space-y-2" @submit.prevent="importFromUrl">
            <div class="flex gap-2">
                <input
                    v-model="importUrl"
                    type="url"
                    :placeholder="pasteMode ? 'Original URL (optional, for reference)…' : 'Import from a recipe URL…'"
                    class="flex-1 rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none"
                />
                <button
                    v-if="!pasteMode"
                    type="submit"
                    :disabled="importing"
                    class="shrink-0 rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700 disabled:opacity-50"
                >
                    {{ importing ? 'Importing…' : 'Import' }}
                </button>
            </div>
            <div v-if="pasteMode" class="flex gap-2">
                <textarea
                    v-model="pasteHtml"
                    rows="3"
                    placeholder="Paste the page's HTML here (open the recipe in your browser, View Source or Save Page As, then copy it)…"
                    class="flex-1 rounded-md border border-neutral-300 px-3 py-2 font-mono text-xs focus:border-neutral-500 focus:outline-none"
                ></textarea>
                <button
                    type="submit"
                    :disabled="importing"
                    class="h-fit shrink-0 rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700 disabled:opacity-50"
                >
                    {{ importing ? 'Importing…' : 'Import' }}
                </button>
            </div>
        </form>
        <p class="mb-4 text-xs text-neutral-500">
            <button type="button" class="hover:underline" @click="pasteMode = !pasteMode">
                {{ pasteMode ? '← Import from a URL instead' : 'Site blocking the import? Paste its page source instead' }}
            </button>
        </p>
        <p v-if="importError" class="mb-4 text-xs text-red-600">{{ importError }}</p>

        <input
            v-model="search"
            type="search"
            placeholder="Search recipes…"
            class="mb-6 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none"
        />

        <p v-if="loading" class="text-sm text-neutral-500">Loading…</p>
        <p v-else-if="recipes.length === 0" class="text-sm text-neutral-500">No recipes yet.</p>

        <ul v-else class="grid gap-4 sm:grid-cols-2">
            <li v-for="recipe in recipes" :key="recipe.id">
                <RouterLink
                    :to="{ name: 'recipes.show', params: { id: recipe.id } }"
                    class="block rounded-lg border border-neutral-200 bg-white p-4 hover:border-neutral-400"
                >
                    <h2 class="font-medium">{{ recipe.title }}</h2>
                    <p v-if="recipe.description" class="mt-1 line-clamp-2 text-sm text-neutral-500">
                        {{ recipe.description }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs text-neutral-500">
                        <span v-if="recipe.total_minutes">{{ recipe.total_minutes }} min</span>
                        <span v-if="recipe.difficulty">{{ recipe.difficulty }}</span>
                        <span v-if="recipe.cuisine">{{ recipe.cuisine }}</span>
                    </div>
                </RouterLink>
            </li>
        </ul>
    </div>
</template>
