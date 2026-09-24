<script setup>
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import api from '../api';
import { useAuth } from '../auth';
import { formatQuantity } from '../format';

const props = defineProps({ id: [String, Number] });
const router = useRouter();
const recipe = ref(null);
const { user } = useAuth();

const imageEditorOpen = ref(false);
const imageUrlInput = ref('');
const imageSaving = ref(false);
const imageError = ref('');

function openImageEditor() {
    imageUrlInput.value = '';
    imageError.value = '';
    imageEditorOpen.value = true;
}

// Phone photos are often larger than the server's upload limit, so shrink them first.
function resizeImage(file, maxSize = 1600) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(img.width * scale);
            canvas.height = Math.round(img.height * scale);
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(img.src);
            canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Could not read image'))), 'image/jpeg', 0.85);
        };
        img.onerror = () => reject(new Error('Could not read image'));
        img.src = URL.createObjectURL(file);
    });
}

async function saveImage(payload) {
    imageSaving.value = true;
    imageError.value = '';
    try {
        const { data } = await api.post(`/recipes/${props.id}/image`, payload);
        recipe.value = data.data;
        imageEditorOpen.value = false;
    } catch (e) {
        imageError.value = e.response?.data?.message ?? e.message ?? 'Could not update image';
    } finally {
        imageSaving.value = false;
    }
}

async function uploadImage(event) {
    const file = event.target.files[0];
    event.target.value = '';
    if (!file) return;
    try {
        const body = new FormData();
        body.append('image', await resizeImage(file), 'image.jpg');
        await saveImage(body);
    } catch (e) {
        imageError.value = e.message;
    }
}

function saveImageUrl() {
    if (!imageUrlInput.value.trim()) return;
    saveImage({ image_url: imageUrlInput.value.trim() });
}

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
                class="relative mb-6 aspect-[3/1] w-full overflow-hidden rounded-xl bg-gradient-to-br from-amber-100 to-orange-100 sm:aspect-[3.5/1]"
            >
                <img
                    v-if="recipe.image_url"
                    :src="recipe.image_url"
                    :alt="recipe.title"
                    class="h-full w-full object-cover"
                />
                <div v-else class="flex h-full w-full items-center justify-center text-5xl">🍳</div>
                <button
                    v-if="user && !imageEditorOpen"
                    type="button"
                    class="absolute right-2 bottom-2 rounded-md bg-white/90 px-3 py-1.5 text-xs font-medium text-stone-800 shadow hover:bg-white"
                    @click="openImageEditor"
                >
                    📷 Change image
                </button>
            </div>

            <div v-if="!plainMode && imageEditorOpen" class="-mt-4 mb-6 rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <label
                        class="cursor-pointer rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700"
                        :class="imageSaving ? 'pointer-events-none opacity-50' : ''"
                    >
                        Upload photo
                        <input type="file" accept="image/*" class="hidden" @change="uploadImage" />
                    </label>
                    <span class="text-sm text-stone-500">or</span>
                    <form class="flex min-w-0 flex-1 gap-2" @submit.prevent="saveImageUrl">
                        <input
                            v-model="imageUrlInput"
                            type="url"
                            placeholder="Paste image URL"
                            class="min-w-0 flex-1 rounded-md border border-stone-300 px-3 py-1.5 text-sm"
                        />
                        <button
                            type="submit"
                            :disabled="imageSaving || !imageUrlInput.trim()"
                            class="rounded-md border border-stone-300 bg-white px-3 py-1.5 text-sm hover:bg-stone-100 disabled:opacity-50"
                        >
                            Save
                        </button>
                    </form>
                    <button type="button" class="px-2 py-1.5 text-sm text-stone-500 hover:text-stone-800" @click="imageEditorOpen = false">
                        Cancel
                    </button>
                </div>
                <p v-if="imageSaving" class="mt-2 text-sm text-stone-500">Saving…</p>
                <p v-if="imageError" class="mt-2 text-sm text-red-600">{{ imageError }}</p>
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
                    <ul :class="plainMode ? 'space-y-0.5 text-lg leading-snug sm:text-sm' : 'space-y-2 text-sm'">
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
                    <ol :class="plainMode ? 'space-y-1 text-lg leading-snug sm:text-sm' : 'space-y-4 text-sm'">
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
