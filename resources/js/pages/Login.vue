<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '../auth';

const router = useRouter();
const { login } = useAuth();

const email = ref('');
const password = ref('');
const error = ref('');
const submitting = ref(false);

async function submit() {
    submitting.value = true;
    error.value = '';
    try {
        await login(email.value, password.value);
        router.push({ name: 'recipes.index' });
    } catch (e) {
        error.value = e.response?.data?.errors?.email?.[0] ?? e.response?.data?.message ?? 'Login failed.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-sm">
        <h1 class="mb-4 text-lg font-semibold text-stone-900">Log in</h1>
        <form class="space-y-3 rounded-xl border border-stone-200 bg-white p-4 shadow-sm" @submit.prevent="submit">
            <input
                v-model="email"
                type="email"
                placeholder="Email"
                autocomplete="email"
                required
                class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none"
            />
            <input
                v-model="password"
                type="password"
                placeholder="Password"
                autocomplete="current-password"
                required
                class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none"
            />
            <button
                type="submit"
                :disabled="submitting"
                class="w-full rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700 disabled:opacity-50"
            >
                {{ submitting ? 'Logging in…' : 'Log in' }}
            </button>
            <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
        </form>
    </div>
</template>
