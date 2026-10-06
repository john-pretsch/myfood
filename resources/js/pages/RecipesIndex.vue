<script setup>
import { computed, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import api from '../api';
import { useAuth } from '../auth';

const router = useRouter();
const { user } = useAuth();
const recipes = ref([]);
const search = ref('');
const loading = ref(true);
const page = ref(1);
const lastPage = ref(1);

const allTags = ref([]);
const tagSearch = ref('');
const selectedTags = ref([]);
const showAllTags = ref(false);
const TAG_LIMIT = 12;

// allTags arrives most-used first; selected tags stay pinned to the front.
const visibleTags = computed(() => {
    const q = tagSearch.value.trim().toLowerCase();
    const matches = allTags.value.filter((t) => !q || t.name.toLowerCase().includes(q));
    const selected = matches.filter((t) => selectedTags.value.includes(t.name));
    const rest = matches.filter((t) => !selectedTags.value.includes(t.name));
    const limited = q || showAllTags.value ? rest : rest.slice(0, Math.max(0, TAG_LIMIT - selected.length));
    return { list: [...selected, ...limited], hidden: rest.length - limited.length };
});

async function loadTags() {
    const { data } = await api.get('/tags', { params: { sort: 'popular' } });
    allTags.value = data.data;
}

function toggleTag(name) {
    selectedTags.value = selectedTags.value.includes(name)
        ? selectedTags.value.filter((n) => n !== name)
        : [...selectedTags.value, name];
    page.value = 1;
    load();
}

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
    const { data } = await api.get('/recipes', { params: { search: search.value || undefined, tag: selectedTags.value, page: page.value } });
    recipes.value = data.data;
    lastPage.value = data.meta?.last_page ?? 1;
    loading.value = false;
}

// The first recipe on an unfiltered first page is shown as a large featured tile.
function isFeatured(i) {
    return i === 0 && page.value === 1 && !search.value && !selectedTags.value.length && recipes.value.length > 1;
}

function goToPage(n) {
    if (n < 1 || n > lastPage.value) return;
    page.value = n;
    load();
}

let debounce;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        page.value = 1;
        load();
    }, 300);
});

load();
loadTags();
</script>

<template>
    <div>
        <form
            v-if="user"
            class="mb-1 space-y-2 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm"
            @submit.prevent="importFromUrl"
        >
            <div class="flex gap-2">
                <input
                    v-model="importUrl"
                    type="url"
                    :placeholder="pasteMode ? 'Original URL (optional, for reference)…' : 'Import from a recipe URL…'"
                    class="flex-1 rounded-md border border-stone-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                />
                <button
                    v-if="!pasteMode"
                    type="submit"
                    :disabled="importing"
                    class="shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700 disabled:opacity-50"
                >
                    {{ importing ? 'Importing…' : 'Import' }}
                </button>
            </div>
            <div v-if="pasteMode" class="flex gap-2">
                <textarea
                    v-model="pasteHtml"
                    rows="3"
                    placeholder="Paste the page's HTML here (open the recipe in your browser, View Source or Save Page As, then copy it)…"
                    class="flex-1 rounded-md border border-stone-300 px-3 py-2 font-mono text-xs focus:border-brand-500 focus:outline-none"
                ></textarea>
                <button
                    type="submit"
                    :disabled="importing"
                    class="h-fit shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700 disabled:opacity-50"
                >
                    {{ importing ? 'Importing…' : 'Import' }}
                </button>
            </div>
            <p class="text-xs text-stone-500">
                <button type="button" class="hover:text-brand-700 hover:underline" @click="pasteMode = !pasteMode">
                    {{ pasteMode ? '← Import from a URL instead' : 'Site blocking the import? Paste its page source instead' }}
                </button>
            </p>
            <p v-if="importError" class="text-xs text-red-600">{{ importError }}</p>
        </form>

        <div class="mt-6 mb-6 space-y-3">
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-stone-400">🔎</span>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search recipes…"
                    class="w-full rounded-full border border-stone-300 bg-white py-2.5 pr-4 pl-10 text-sm focus:border-brand-500 focus:outline-none"
                />
            </div>

            <div v-if="allTags.length" class="space-y-2">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-stone-400">🏷️</span>
                    <input
                        v-model="tagSearch"
                        type="search"
                        placeholder="Search tags…"
                        class="w-full rounded-full border border-stone-300 bg-white py-2.5 pr-4 pl-10 text-sm focus:border-brand-500 focus:outline-none"
                    />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="tag in visibleTags.list"
                        :key="tag.id"
                        type="button"
                        class="rounded-full border px-3 py-1 text-sm font-medium transition active:scale-95"
                        :class="
                            selectedTags.includes(tag.name)
                                ? 'border-brand-600 bg-brand-600 text-white'
                                : 'border-stone-300 bg-white text-stone-700 hover:border-brand-400 hover:text-brand-700'
                        "
                        :aria-pressed="selectedTags.includes(tag.name)"
                        @click="toggleTag(tag.name)"
                    >
                        {{ tag.name }}
                        <span class="ml-0.5 text-xs opacity-70">{{ tag.recipes_count }}</span>
                    </button>
                    <button
                        v-if="!tagSearch && !showAllTags && visibleTags.hidden > 0"
                        type="button"
                        class="px-2 py-1 text-sm text-stone-500 hover:text-brand-700"
                        @click="showAllTags = true"
                    >
                        +{{ visibleTags.hidden }} more
                    </button>
                    <button
                        v-else-if="!tagSearch && showAllTags && allTags.length > TAG_LIMIT"
                        type="button"
                        class="px-2 py-1 text-sm text-stone-500 hover:text-brand-700"
                        @click="showAllTags = false"
                    >
                        Show fewer
                    </button>
                    <button
                        v-if="selectedTags.length"
                        type="button"
                        class="px-2 py-1 text-sm text-stone-500 underline hover:text-brand-700"
                        @click="selectedTags = []; page = 1; load()"
                    >
                        Clear
                    </button>
                    <p v-if="tagSearch && !visibleTags.list.length" class="text-sm text-stone-500">No tags match “{{ tagSearch }}”.</p>
                </div>
            </div>
        </div>

        <ul v-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="n in 6" :key="n" class="aspect-[4/5] animate-shimmer rounded-3xl bg-stone-200 sm:aspect-[4/3]"></li>
        </ul>
        <div v-else-if="recipes.length === 0" class="rounded-3xl border border-dashed border-stone-300 bg-white py-12 text-center">
            <p class="text-2xl">🍽️</p>
            <p class="mt-2 text-sm text-stone-500">No recipes yet — import one above to get started.</p>
        </div>

        <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li
                v-for="(recipe, i) in recipes"
                :key="recipe.id"
                class="animate-fade-up"
                :class="isFeatured(i) ? 'sm:col-span-2 lg:col-span-3' : ''"
                :style="{ animationDelay: `${Math.min(i, 9) * 60}ms` }"
            >
                <RouterLink
                    :to="{ name: 'recipes.show', params: { id: recipe.id } }"
                    class="group relative block overflow-hidden rounded-3xl bg-brand-600 shadow-lg shadow-brand-800/10 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-brand-800/25 active:scale-[0.99]"
                    :class="isFeatured(i) ? 'aspect-[4/5] sm:aspect-[21/9]' : 'aspect-[4/5] sm:aspect-[4/3]'"
                >
                    <img
                        v-if="recipe.image_url"
                        :src="recipe.image_url"
                        :alt="recipe.title"
                        class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105"
                        :loading="i < 2 ? 'eager' : 'lazy'"
                    />
                    <div
                        v-else
                        class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-brand-500 via-brand-600 to-brand-800 text-6xl"
                    >
                        🍳
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>

                    <div class="absolute inset-x-0 bottom-0 p-5 text-white">
                        <div class="mb-2 flex flex-wrap gap-1.5 text-xs font-medium">
                            <span v-if="recipe.total_minutes" class="rounded-full bg-white/20 px-2.5 py-1 backdrop-blur">
                                ⏱ {{ recipe.total_minutes }} min
                            </span>
                            <span v-if="recipe.difficulty" class="rounded-full bg-brand-500 px-2.5 py-1">
                                {{ recipe.difficulty }}
                            </span>
                            <span v-if="recipe.cuisine" class="rounded-full bg-white/20 px-2.5 py-1 backdrop-blur">
                                {{ recipe.cuisine }}
                            </span>
                        </div>
                        <h2
                            class="font-bold tracking-tight text-balance drop-shadow"
                            :class="isFeatured(i) ? 'text-2xl sm:text-4xl' : 'text-xl'"
                        >
                            {{ recipe.title }}
                        </h2>
                        <p v-if="isFeatured(i) && recipe.description" class="mt-1 line-clamp-2 hidden max-w-2xl text-sm text-white/80 sm:block">
                            {{ recipe.description }}
                        </p>
                    </div>
                </RouterLink>
            </li>
        </ul>

        <div v-if="!loading && recipes.length && lastPage > 1" class="mt-6 flex items-center justify-center gap-3">
            <button
                type="button"
                :disabled="page <= 1"
                class="rounded-md border border-stone-300 bg-white px-3 py-1.5 text-sm hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-50"
                @click="goToPage(page - 1)"
            >
                ← Prev
            </button>
            <span class="text-sm text-stone-500">Page {{ page }} of {{ lastPage }}</span>
            <button
                type="button"
                :disabled="page >= lastPage"
                class="rounded-md border border-stone-300 bg-white px-3 py-1.5 text-sm hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-50"
                @click="goToPage(page + 1)"
            >
                Next →
            </button>
        </div>
    </div>
</template>
