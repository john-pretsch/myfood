<script setup>
import { ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import api from '../api';
import { useAuth } from '../auth';

const router = useRouter();
const { user } = useAuth();
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
        <form
            v-if="user"
            class="mb-1 space-y-2 rounded-xl border border-stone-200 bg-white p-4 shadow-sm"
            @submit.prevent="importFromUrl"
        >
            <div class="flex gap-2">
                <input
                    v-model="importUrl"
                    type="url"
                    :placeholder="pasteMode ? 'Original URL (optional, for reference)…' : 'Import from a recipe URL…'"
                    class="flex-1 rounded-md border border-stone-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none"
                />
                <button
                    v-if="!pasteMode"
                    type="submit"
                    :disabled="importing"
                    class="shrink-0 rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700 disabled:opacity-50"
                >
                    {{ importing ? 'Importing…' : 'Import' }}
                </button>
            </div>
            <div v-if="pasteMode" class="flex gap-2">
                <textarea
                    v-model="pasteHtml"
                    rows="3"
                    placeholder="Paste the page's HTML here (open the recipe in your browser, View Source or Save Page As, then copy it)…"
                    class="flex-1 rounded-md border border-stone-300 px-3 py-2 font-mono text-xs focus:border-amber-500 focus:outline-none"
                ></textarea>
                <button
                    type="submit"
                    :disabled="importing"
                    class="h-fit shrink-0 rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700 disabled:opacity-50"
                >
                    {{ importing ? 'Importing…' : 'Import' }}
                </button>
            </div>
            <p class="text-xs text-stone-500">
                <button type="button" class="hover:text-amber-700 hover:underline" @click="pasteMode = !pasteMode">
                    {{ pasteMode ? '← Import from a URL instead' : 'Site blocking the import? Paste its page source instead' }}
                </button>
            </p>
            <p v-if="importError" class="text-xs text-red-600">{{ importError }}</p>
        </form>

        <div class="relative mt-6 mb-6">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-stone-400">🔎</span>
            <input
                v-model="search"
                type="search"
                placeholder="Search recipes…"
                class="w-full rounded-md border border-stone-300 bg-white py-2 pr-3 pl-9 text-sm focus:border-amber-500 focus:outline-none"
            />
        </div>

        <p v-if="loading" class="text-sm text-stone-500">Loading…</p>
        <div v-else-if="recipes.length === 0" class="rounded-xl border border-dashed border-stone-300 bg-white py-12 text-center">
            <p class="text-2xl">🍽️</p>
            <p class="mt-2 text-sm text-stone-500">No recipes yet — import one above to get started.</p>
        </div>

        <ul v-else class="grid gap-4 sm:grid-cols-2">
            <li v-for="recipe in recipes" :key="recipe.id">
                <RouterLink
                    :to="{ name: 'recipes.show', params: { id: recipe.id } }"
                    class="group block overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="aspect-video w-full overflow-hidden bg-gradient-to-br from-amber-100 to-orange-100">
                        <img
                            v-if="recipe.image_url"
                            :src="recipe.image_url"
                            :alt="recipe.title"
                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                            loading="lazy"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center text-3xl">🍳</div>
                    </div>
                    <div class="p-4">
                        <h2 class="font-medium text-stone-900">{{ recipe.title }}</h2>
                        <p v-if="recipe.description" class="mt-1 line-clamp-2 text-sm text-stone-500">
                            {{ recipe.description }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs text-stone-600">
                            <span v-if="recipe.total_minutes" class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5">
                                ⏱ {{ recipe.total_minutes }} min
                            </span>
                            <span v-if="recipe.difficulty" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-amber-800">
                                {{ recipe.difficulty }}
                            </span>
                            <span v-if="recipe.cuisine" class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5">
                                {{ recipe.cuisine }}
                            </span>
                        </div>
                    </div>
                </RouterLink>
            </li>
        </ul>
    </div>
</template>
