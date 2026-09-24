<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import { useAuth } from '../auth';
import TagPicker from '../components/TagPicker.vue';

const props = defineProps({ id: [String, Number] });
const router = useRouter();
const { isAdmin } = useAuth();
const isEdit = computed(() => !!props.id);
const saving = ref(false);
const errors = ref({});

const form = ref({
    title: '',
    description: '',
    servings: null,
    prep_minutes: null,
    cook_minutes: null,
    total_minutes: null,
    image_url: '',
    source_url: '',
    notes: '',
    ingredients: [{ name: '', quantity: null, unit: '', notes: '' }],
    steps: [{ instruction: '' }],
    tags: [],
    nutrition: { calories: null, protein_g: null, carbs_g: null, fat_g: null },
});

const availableTags = ref([]);
const creatingTag = ref(false);
const tagError = ref('');

async function loadTags() {
    const { data } = await api.get('/tags');
    availableTags.value = data.data;
}

async function createTag(name) {
    creatingTag.value = true;
    tagError.value = '';
    try {
        const { data } = await api.post('/tags', { name });
        availableTags.value = [...availableTags.value, data.data].sort((a, b) => a.name.localeCompare(b.name));
        form.value.tags.push(data.data.name);
    } catch (e) {
        tagError.value = e.response?.data?.errors?.name?.[0] ?? e.response?.data?.message ?? 'Could not add tag.';
    } finally {
        creatingTag.value = false;
    }
}

async function load() {
    if (!isEdit.value) return;
    const { data } = await api.get(`/recipes/${props.id}`);
    const recipe = data.data;
    form.value = {
        title: recipe.title,
        description: recipe.description ?? '',
        servings: recipe.servings,
        prep_minutes: recipe.prep_minutes,
        cook_minutes: recipe.cook_minutes,
        total_minutes: recipe.total_minutes,
        image_url: recipe.image_url ?? '',
        source_url: recipe.source_url ?? '',
        notes: recipe.notes ?? '',
        ingredients: recipe.ingredients.length
            ? recipe.ingredients.map((i) => ({ name: i.name, quantity: i.quantity, unit: i.unit ?? '', notes: i.notes ?? '' }))
            : [{ name: '', quantity: null, unit: '', notes: '' }],
        steps: recipe.steps.length ? recipe.steps.map((s) => ({ instruction: s.instruction })) : [{ instruction: '' }],
        tags: [...recipe.tags],
        nutrition: recipe.nutrition ?? { calories: null, protein_g: null, carbs_g: null, fat_g: null },
    };
}

function addIngredient() {
    form.value.ingredients.push({ name: '', quantity: null, unit: '', notes: '' });
}

function removeIngredient(index) {
    form.value.ingredients.splice(index, 1);
}

function addStep() {
    form.value.steps.push({ instruction: '' });
}

function removeStep(index) {
    form.value.steps.splice(index, 1);
}

async function submit() {
    saving.value = true;
    errors.value = {};

    const payload = {
        ...form.value,
        ingredients: form.value.ingredients.filter((i) => i.name.trim() !== ''),
        steps: form.value.steps.filter((s) => s.instruction.trim() !== ''),
    };

    try {
        const response = isEdit.value
            ? await api.put(`/recipes/${props.id}`, payload)
            : await api.post('/recipes', payload);

        router.push({ name: 'recipes.show', params: { id: response.data.data.id } });
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else {
            throw e;
        }
    } finally {
        saving.value = false;
    }
}

load();
loadTags();
</script>

<template>
    <form class="space-y-8" @submit.prevent="submit">
        <h1 class="text-2xl font-semibold text-stone-900">{{ isEdit ? 'Edit Recipe' : 'New Recipe' }}</h1>

        <section class="space-y-4 rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
            <div>
                <label class="mb-1 block text-sm font-medium">Title</label>
                <input v-model="form.title" type="text" required class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm" />
                <p v-if="errors.title" class="mt-1 text-xs text-red-600">{{ errors.title[0] }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Description</label>
                <textarea v-model="form.description" rows="2" class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">Servings</label>
                    <input v-model.number="form.servings" type="number" min="1" class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Prep (min)</label>
                    <input v-model.number="form.prep_minutes" type="number" min="0" class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Cook (min)</label>
                    <input v-model.number="form.cook_minutes" type="number" min="0" class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Total (min)</label>
                    <input v-model.number="form.total_minutes" type="number" min="0" class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm" />
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Tags</label>
                <TagPicker
                    v-model="form.tags"
                    :tags="availableTags"
                    :allow-create="isAdmin"
                    :creating="creatingTag"
                    @create="createTag"
                />
                <p v-if="tagError" class="mt-1 text-xs text-red-600">{{ tagError }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Image URL</label>
                <input v-model="form.image_url" type="text" class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm" />
            </div>

            <div v-if="isEdit" class="flex justify-end">
                <button
                    type="button"
                    :disabled="saving"
                    class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700 disabled:opacity-50"
                    @click="submit"
                >
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-medium text-stone-900">🧺 Ingredients</h2>
                <button type="button" class="text-sm text-amber-700 hover:underline" @click="addIngredient">+ Add ingredient</button>
            </div>
            <div v-for="(ingredient, index) in form.ingredients" :key="index" class="mb-2 grid grid-cols-12 gap-2">
                <input v-model="ingredient.quantity" type="text" placeholder="qty" class="col-span-2 rounded-md border border-stone-300 px-2 py-1.5 text-sm" />
                <input v-model="ingredient.unit" type="text" placeholder="unit" class="col-span-2 rounded-md border border-stone-300 px-2 py-1.5 text-sm" />
                <input v-model="ingredient.name" type="text" placeholder="ingredient" class="col-span-5 rounded-md border border-stone-300 px-2 py-1.5 text-sm" />
                <input v-model="ingredient.notes" type="text" placeholder="notes" class="col-span-2 rounded-md border border-stone-300 px-2 py-1.5 text-sm" />
                <button type="button" class="col-span-1 text-sm text-red-500" @click="removeIngredient(index)">✕</button>
            </div>

            <div v-if="isEdit" class="mt-2 flex justify-end">
                <button
                    type="button"
                    :disabled="saving"
                    class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700 disabled:opacity-50"
                    @click="submit"
                >
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-medium text-stone-900">📋 Steps</h2>
                <button type="button" class="text-sm text-amber-700 hover:underline" @click="addStep">+ Add step</button>
            </div>
            <div v-for="(step, index) in form.steps" :key="index" class="mb-2 flex gap-2">
                <span class="mt-2 w-5 shrink-0 text-sm text-stone-400">{{ index + 1 }}.</span>
                <textarea v-model="step.instruction" rows="2" class="w-full rounded-md border border-stone-300 px-3 py-1.5 text-sm"></textarea>
                <button type="button" class="text-sm text-red-500" @click="removeStep(index)">✕</button>
            </div>

            <div v-if="isEdit" class="mt-2 flex justify-end">
                <button
                    type="button"
                    :disabled="saving"
                    class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700 disabled:opacity-50"
                    @click="submit"
                >
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-medium text-stone-900">Nutrition (optional)</h2>
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label class="mb-1 block text-xs text-stone-500">Calories</label>
                    <input v-model.number="form.nutrition.calories" type="number" min="0" class="w-full rounded-md border border-stone-300 px-2 py-1.5 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-stone-500">Protein (g)</label>
                    <input v-model.number="form.nutrition.protein_g" type="number" min="0" class="w-full rounded-md border border-stone-300 px-2 py-1.5 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-stone-500">Carbs (g)</label>
                    <input v-model.number="form.nutrition.carbs_g" type="number" min="0" class="w-full rounded-md border border-stone-300 px-2 py-1.5 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-stone-500">Fat (g)</label>
                    <input v-model.number="form.nutrition.fat_g" type="number" min="0" class="w-full rounded-md border border-stone-300 px-2 py-1.5 text-sm" />
                </div>
            </div>

            <div v-if="isEdit" class="mt-4 flex justify-end">
                <button
                    type="button"
                    :disabled="saving"
                    class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700 disabled:opacity-50"
                    @click="submit"
                >
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </section>

        <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
            <label class="mb-1 block text-sm font-medium">Notes</label>
            <textarea v-model="form.notes" rows="2" class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm"></textarea>

            <div v-if="isEdit" class="mt-4 flex justify-end">
                <button
                    type="button"
                    :disabled="saving"
                    class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700 disabled:opacity-50"
                    @click="submit"
                >
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </div>

        <button
            type="submit"
            :disabled="saving"
            class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700 disabled:opacity-50"
        >
            {{ saving ? 'Saving…' : 'Save Recipe' }}
        </button>
    </form>
</template>
