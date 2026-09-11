<script setup>
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import api from '../api';
import { formatQuantity } from '../format';

const props = defineProps({ id: [String, Number] });
const router = useRouter();
const recipe = ref(null);

function readStoredPlainMode() {
    try {
        return localStorage.getItem('myfood-plain-view') === '1';
    } catch {
        return false;
    }
}

const plainMode = ref(readStoredPlainMode());

function togglePlainMode() {
    plainMode.value = !plainMode.value;
    try {
        localStorage.setItem('myfood-plain-view', plainMode.value ? '1' : '0');
    } catch {
        // ignore (e.g. private browsing)
    }
}

async function load() {
    const { data } = await api.get(`/recipes/${props.id}`);
    recipe.value = data.data;
}

async function destroy() {
    if (!confirm('Delete this recipe?')) return;
    await api.delete(`/recipes/${props.id}`);
    router.push({ name: 'recipes.index' });
}

load();
</script>

<template>
    <div v-if="recipe">
        <button
            type="button"
            class="mb-4 flex w-full items-center justify-center gap-2 rounded-lg border-2 border-amber-600 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 shadow-sm active:scale-[0.99]"
            @click="togglePlainMode"
        >
            <span v-if="plainMode">✨ Switch to fancy view</span>
            <span v-else>📵 Switch to plain view (cooking mode)</span>
        </button>

        <div :class="plainMode ? 'grayscale contrast-125' : ''">
            <div
                v-if="!plainMode"
                class="mb-6 aspect-[3/1] w-full overflow-hidden rounded-xl bg-gradient-to-br from-amber-100 to-orange-100 sm:aspect-[3.5/1]"
            >
                <img
                    v-if="recipe.image_url"
                    :src="recipe.image_url"
                    :alt="recipe.title"
                    class="h-full w-full object-cover"
                />
                <div v-else class="flex h-full w-full items-center justify-center text-5xl">🍳</div>
            </div>

            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 :class="plainMode ? 'text-lg font-semibold text-stone-900' : 'text-2xl font-semibold text-stone-900'">{{ recipe.title }}</h1>
                    <p v-if="recipe.description && !plainMode" class="mt-1 text-stone-600">{{ recipe.description }}</p>
                </div>
                <div v-if="!plainMode" class="flex shrink-0 gap-2">
                    <RouterLink
                        :to="{ name: 'recipes.edit', params: { id: recipe.id } }"
                        class="rounded-md border border-stone-300 bg-white px-3 py-1.5 text-sm hover:bg-stone-100"
                    >
                        Edit
                    </RouterLink>
                    <button
                        type="button"
                        class="rounded-md border border-red-300 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
                        @click="destroy"
                    >
                        Delete
                    </button>
                </div>
            </div>

            <div :class="plainMode ? 'mt-2 flex flex-wrap gap-2 text-xs text-stone-600' : 'mt-4 flex flex-wrap gap-2 text-xs text-stone-600'">
                <span v-if="recipe.servings" class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5">🍽 Serves {{ recipe.servings }}</span>
                <span v-if="recipe.prep_minutes" class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5">🔪 Prep {{ recipe.prep_minutes }} min</span>
                <span v-if="recipe.cook_minutes" class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5">🔥 Cook {{ recipe.cook_minutes }} min</span>
                <span v-if="recipe.difficulty" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-amber-800">{{ recipe.difficulty }}</span>
                <span v-if="recipe.cuisine" class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5">{{ recipe.cuisine }}</span>
            </div>

            <div v-if="recipe.tags.length && !plainMode" class="mt-3 flex flex-wrap gap-2">
                <span
                    v-for="tag in recipe.tags"
                    :key="tag"
                    class="rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-800"
                >
                    {{ tag }}
                </span>
            </div>

            <div :class="plainMode ? 'mt-3 flex flex-col gap-3' : 'mt-8 grid gap-6 sm:grid-cols-3'">
                <section :class="plainMode ? '' : 'rounded-xl border border-stone-200 bg-white p-5 shadow-sm sm:col-span-1'">
                    <h2 :class="plainMode ? 'mb-1 flex items-center gap-2 text-sm font-semibold text-stone-900' : 'mb-3 flex items-center gap-2 font-medium text-stone-900'">
                        🧺 Ingredients
                    </h2>
                    <ul :class="plainMode ? 'space-y-0.5 text-sm leading-snug' : 'space-y-2 text-sm'">
                        <li
                            v-for="ing in recipe.ingredients"
                            :key="ing.id"
                            :class="plainMode ? 'flex gap-2' : 'flex gap-2 border-b border-stone-100 pb-2 last:border-0 last:pb-0'"
                        >
                            <span v-if="!plainMode" class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                            <span>
                                <span v-if="ing.quantity">{{ formatQuantity(ing.quantity) }}</span>
                                <span v-if="ing.unit">{{ ing.unit }}</span>
                                {{ ing.name }}
                                <span v-if="ing.notes" class="text-stone-400">({{ ing.notes }})</span>
                            </span>
                        </li>
                    </ul>
                </section>

                <section :class="plainMode ? '' : 'rounded-xl border border-stone-200 bg-white p-5 shadow-sm sm:col-span-2'">
                    <h2 :class="plainMode ? 'mb-1 flex items-center gap-2 text-sm font-semibold text-stone-900' : 'mb-3 flex items-center gap-2 font-medium text-stone-900'">
                        📋 Steps
                    </h2>
                    <ol :class="plainMode ? 'space-y-1 text-sm leading-snug' : 'space-y-4 text-sm'">
                        <li v-for="(step, index) in recipe.steps" :key="step.id" class="flex gap-2">
                            <span
                                v-if="!plainMode"
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-600 text-xs font-semibold text-white"
                            >
                                {{ index + 1 }}
                            </span>
                            <span v-else class="shrink-0 font-semibold text-stone-500">{{ index + 1 }}.</span>
                            <span :class="plainMode ? '' : 'pt-0.5'">{{ step.instruction }}</span>
                        </li>
                    </ol>
                </section>
            </div>

            <section v-if="recipe.nutrition && !plainMode" class="mt-6 rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 font-medium text-stone-900">Nutrition</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div v-if="recipe.nutrition.calories" class="rounded-lg bg-stone-50 p-3 text-center">
                        <p class="text-lg font-semibold text-stone-900">{{ recipe.nutrition.calories }}</p>
                        <p class="text-xs text-stone-500">kcal</p>
                    </div>
                    <div v-if="recipe.nutrition.protein_g" class="rounded-lg bg-stone-50 p-3 text-center">
                        <p class="text-lg font-semibold text-stone-900">{{ recipe.nutrition.protein_g }}g</p>
                        <p class="text-xs text-stone-500">protein</p>
                    </div>
                    <div v-if="recipe.nutrition.carbs_g" class="rounded-lg bg-stone-50 p-3 text-center">
                        <p class="text-lg font-semibold text-stone-900">{{ recipe.nutrition.carbs_g }}g</p>
                        <p class="text-xs text-stone-500">carbs</p>
                    </div>
                    <div v-if="recipe.nutrition.fat_g" class="rounded-lg bg-stone-50 p-3 text-center">
                        <p class="text-lg font-semibold text-stone-900">{{ recipe.nutrition.fat_g }}g</p>
                        <p class="text-xs text-stone-500">fat</p>
                    </div>
                </div>
            </section>

            <p v-if="recipe.notes && !plainMode" class="mt-6 text-sm text-stone-500">{{ recipe.notes }}</p>
        </div>
    </div>
</template>
