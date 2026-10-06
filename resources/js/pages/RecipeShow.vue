<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import api from '../api';
import { useAuth } from '../auth';
import { formatQuantity } from '../format';
import { resizeImage } from '../image';

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

const wakeLockSupported = 'wakeLock' in navigator;
const screenAwake = ref(false);
let wakeLock = null;

async function enableWakeLock() {
    try {
        wakeLock = await navigator.wakeLock.request('screen');
        screenAwake.value = true;
        wakeLock.addEventListener('release', () => {
            screenAwake.value = false;
        });
    } catch {
        screenAwake.value = false;
    }
}

async function disableWakeLock() {
    if (wakeLock) {
        await wakeLock.release();
        wakeLock = null;
    }
    screenAwake.value = false;
}

function toggleWakeLock() {
    screenAwake.value ? disableWakeLock() : enableWakeLock();
}

// The wake lock is released automatically when the tab is hidden, so reacquire it on return.
async function handleVisibilityChange() {
    if (screenAwake.value && document.visibilityState === 'visible' && !wakeLock) {
        await enableWakeLock();
    }
}

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    disableWakeLock();
});

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
        <div class="mb-4 flex gap-2">
            <button
                type="button"
                class="flex flex-1 items-center justify-center gap-2 rounded-lg border-2 border-brand-600 bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-800 shadow-sm active:scale-[0.99]"
                @click="togglePlainMode"
            >
                <span v-if="plainMode">✨ Switch to fancy view</span>
                <span v-else>📵 Switch to plain view (cooking mode)</span>
            </button>

            <button
                v-if="wakeLockSupported"
                type="button"
                class="flex flex-1 items-center justify-center gap-2 rounded-lg border-2 px-4 py-3 text-sm font-semibold shadow-sm active:scale-[0.99] sm:hidden"
                :class="screenAwake ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-stone-300 bg-white text-stone-600'"
                @click="toggleWakeLock"
            >
                <span v-if="screenAwake">☀️ Screen on</span>
                <span v-else>💤 Keep screen on</span>
            </button>
        </div>

        <div :class="plainMode ? 'grayscale contrast-125' : ''">
            <div
                v-if="!plainMode"
                class="relative mb-6 aspect-[4/5] w-full overflow-hidden rounded-3xl bg-gradient-to-br from-brand-500 via-brand-600 to-brand-800 shadow-xl shadow-brand-800/20 sm:aspect-[16/9]"
            >
                <img
                    v-if="recipe.image_url"
                    :src="recipe.image_url"
                    :alt="recipe.title"
                    class="absolute inset-0 h-full w-full scale-105 object-cover motion-safe:animate-fade-up"
                />
                <div v-else class="absolute inset-0 flex items-center justify-center text-7xl">🍳</div>
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>

                <button
                    v-if="user && !imageEditorOpen"
                    type="button"
                    class="absolute top-3 right-3 rounded-full bg-white/20 px-3 py-1.5 text-xs font-medium text-white backdrop-blur hover:bg-white/30"
                    @click="openImageEditor"
                >
                    📷 Change image
                </button>

                <div class="absolute inset-x-0 bottom-0 p-5 text-white sm:p-8">
                    <h1 class="text-3xl leading-[1.05] font-extrabold tracking-tight text-balance drop-shadow sm:text-5xl">{{ recipe.title }}</h1>
                    <p v-if="recipe.description" class="mt-2 line-clamp-3 max-w-2xl text-sm text-white/85 sm:text-base">{{ recipe.description }}</p>
                </div>
            </div>

            <div v-if="!plainMode && imageEditorOpen" class="-mt-4 mb-6 rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <label
                        class="cursor-pointer rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"
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

            <h1 v-if="plainMode" class="text-lg font-semibold text-stone-900">{{ recipe.title }}</h1>

            <div v-if="!plainMode" class="flex flex-wrap items-center justify-between gap-3">
                <div class="grid flex-1 grid-cols-2 gap-2 sm:grid-cols-4">
                    <div v-if="recipe.servings" class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-stone-200">
                        <p class="text-xl font-bold text-ink">{{ recipe.servings }}</p>
                        <p class="text-xs font-medium tracking-wider text-stone-500 uppercase">Serves</p>
                    </div>
                    <div v-if="recipe.prep_minutes" class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-stone-200">
                        <p class="text-xl font-bold text-ink">{{ recipe.prep_minutes }}<span class="text-sm font-medium"> min</span></p>
                        <p class="text-xs font-medium tracking-wider text-stone-500 uppercase">Prep</p>
                    </div>
                    <div v-if="recipe.cook_minutes" class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-stone-200">
                        <p class="text-xl font-bold text-ink">{{ recipe.cook_minutes }}<span class="text-sm font-medium"> min</span></p>
                        <p class="text-xs font-medium tracking-wider text-stone-500 uppercase">Cook</p>
                    </div>
                    <div v-if="recipe.difficulty" class="rounded-2xl bg-brand-600 p-3 text-white shadow-sm">
                        <p class="text-xl font-bold">{{ recipe.difficulty }}</p>
                        <p class="text-xs font-medium tracking-wider text-white/80 uppercase">{{ recipe.cuisine || 'Difficulty' }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 gap-2">
                    <RouterLink
                        :to="{ name: 'recipes.edit', params: { id: recipe.id } }"
                        class="rounded-full border border-stone-300 bg-white px-4 py-1.5 text-sm hover:bg-stone-100"
                    >
                        Edit
                    </RouterLink>
                    <button
                        type="button"
                        class="rounded-full border border-red-300 px-4 py-1.5 text-sm text-red-600 hover:bg-red-50"
                        @click="destroy"
                    >
                        Delete
                    </button>
                </div>
            </div>

            <div v-else class="mt-2 flex flex-wrap gap-2 text-xs text-stone-600">
                <span v-if="recipe.servings" class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5">🍽 Serves {{ recipe.servings }}</span>
                <span v-if="recipe.prep_minutes" class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5">🔪 Prep {{ recipe.prep_minutes }} min</span>
                <span v-if="recipe.cook_minutes" class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5">🔥 Cook {{ recipe.cook_minutes }} min</span>
                <span v-if="recipe.difficulty" class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5">{{ recipe.difficulty }}</span>
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
                <section :class="plainMode ? '' : 'rounded-3xl border border-stone-200 bg-white p-5 shadow-sm sm:col-span-1'">
                    <h2 :class="plainMode ? 'mb-1 flex items-center gap-2 text-sm font-semibold text-stone-900' : 'mb-3 flex items-center gap-2 font-medium text-stone-900'">
                        🧺 Ingredients
                    </h2>
                    <ul :class="plainMode ? 'space-y-0.5 text-lg leading-snug sm:text-sm' : 'space-y-2 text-sm'">
                        <li
                            v-for="ing in recipe.ingredients"
                            :key="ing.id"
                            :class="plainMode ? 'flex gap-2' : 'flex gap-2 border-b border-stone-100 pb-2 last:border-0 last:pb-0'"
                        >
                            <span v-if="!plainMode" class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500"></span>
                            <span>
                                <span v-if="ing.quantity">{{ formatQuantity(ing.quantity) }}</span>
                                <span v-if="ing.unit">{{ ing.unit }}</span>
                                {{ ing.name }}
                                <span v-if="ing.notes" class="text-stone-400">({{ ing.notes }})</span>
                            </span>
                        </li>
                    </ul>
                </section>

                <section :class="plainMode ? '' : 'rounded-3xl border border-stone-200 bg-white p-5 shadow-sm sm:col-span-2'">
                    <h2 :class="plainMode ? 'mb-1 flex items-center gap-2 text-sm font-semibold text-stone-900' : 'mb-3 flex items-center gap-2 font-medium text-stone-900'">
                        📋 Steps
                    </h2>
                    <ol :class="plainMode ? 'space-y-1 text-lg leading-snug sm:text-sm' : 'space-y-4 text-sm'">
                        <li v-for="(step, index) in recipe.steps" :key="step.id" class="flex gap-2">
                            <span
                                v-if="!plainMode"
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white shadow-sm shadow-brand-800/30"
                            >
                                {{ index + 1 }}
                            </span>
                            <span v-else class="shrink-0 font-semibold text-stone-500">{{ index + 1 }}.</span>
                            <span :class="plainMode ? '' : 'pt-1'">{{ step.instruction }}</span>
                        </li>
                    </ol>
                </section>
            </div>

            <section v-if="recipe.nutrition && !plainMode" class="mt-6 rounded-3xl border border-stone-200 bg-white p-5 shadow-sm">
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
