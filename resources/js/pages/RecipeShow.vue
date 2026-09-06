<script setup>
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import api from '../api';

const props = defineProps({ id: [String, Number] });
const router = useRouter();
const recipe = ref(null);

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
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ recipe.title }}</h1>
                <p v-if="recipe.description" class="mt-1 text-neutral-600">{{ recipe.description }}</p>
            </div>
            <div class="flex shrink-0 gap-2">
                <RouterLink
                    :to="{ name: 'recipes.edit', params: { id: recipe.id } }"
                    class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm hover:bg-neutral-100"
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

        <div class="mt-4 flex flex-wrap gap-2 text-xs text-neutral-500">
            <span v-if="recipe.servings">Serves {{ recipe.servings }}</span>
            <span v-if="recipe.prep_minutes">Prep {{ recipe.prep_minutes }} min</span>
            <span v-if="recipe.cook_minutes">Cook {{ recipe.cook_minutes }} min</span>
            <span v-if="recipe.difficulty">{{ recipe.difficulty }}</span>
            <span v-if="recipe.cuisine">{{ recipe.cuisine }}</span>
        </div>

        <div v-if="recipe.tags.length" class="mt-3 flex flex-wrap gap-2">
            <span
                v-for="tag in recipe.tags"
                :key="tag"
                class="rounded-full bg-neutral-200 px-2 py-0.5 text-xs text-neutral-700"
            >
                {{ tag }}
            </span>
        </div>

        <div class="mt-8 grid gap-8 sm:grid-cols-3">
            <section class="sm:col-span-1">
                <h2 class="mb-2 font-medium">Ingredients</h2>
                <ul class="space-y-1 text-sm">
                    <li v-for="ing in recipe.ingredients" :key="ing.id">
                        <span v-if="ing.quantity">{{ ing.quantity }}</span>
                        <span v-if="ing.unit">{{ ing.unit }}</span>
                        {{ ing.name }}
                        <span v-if="ing.notes" class="text-neutral-400">({{ ing.notes }})</span>
                    </li>
                </ul>
            </section>

            <section class="sm:col-span-2">
                <h2 class="mb-2 font-medium">Steps</h2>
                <ol class="list-decimal space-y-3 pl-5 text-sm">
                    <li v-for="step in recipe.steps" :key="step.id">{{ step.instruction }}</li>
                </ol>
            </section>
        </div>

        <section v-if="recipe.nutrition" class="mt-8">
            <h2 class="mb-2 font-medium">Nutrition</h2>
            <div class="flex flex-wrap gap-4 text-sm text-neutral-600">
                <span v-if="recipe.nutrition.calories">{{ recipe.nutrition.calories }} kcal</span>
                <span v-if="recipe.nutrition.protein_g">{{ recipe.nutrition.protein_g }}g protein</span>
                <span v-if="recipe.nutrition.carbs_g">{{ recipe.nutrition.carbs_g }}g carbs</span>
                <span v-if="recipe.nutrition.fat_g">{{ recipe.nutrition.fat_g }}g fat</span>
            </div>
        </section>

        <p v-if="recipe.notes" class="mt-8 text-sm text-neutral-500">{{ recipe.notes }}</p>
    </div>
</template>
