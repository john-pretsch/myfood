<script setup>
import { ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../api';

const recipes = ref([]);
const search = ref('');
const loading = ref(true);

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
