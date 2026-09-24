<script setup>
import { RouterLink, RouterView } from 'vue-router';
import { useAuth } from './auth';

const { user, ready, isAdmin, fetchUser, logout } = useAuth();

fetchUser();
</script>

<template>
    <div class="min-h-screen bg-stone-50 text-stone-900">
        <header class="sticky top-0 z-10 border-b border-stone-200 bg-white/80 backdrop-blur">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
                <RouterLink to="/" class="flex items-center gap-2 text-lg font-semibold tracking-tight text-stone-900">
                    <span class="text-xl">🍲</span>
                    MyFood
                </RouterLink>
                <div class="flex items-center gap-3">
                    <RouterLink v-if="isAdmin" to="/tags" class="text-sm text-stone-500 hover:text-stone-700">Tags</RouterLink>
                    <RouterLink
                        v-if="user"
                        to="/recipes/new"
                        class="rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-amber-700"
                    >
                        New Recipe
                    </RouterLink>
                    <button
                        v-if="user"
                        type="button"
                        class="text-sm text-stone-500 hover:text-stone-700"
                        @click="logout"
                    >
                        Log out
                    </button>
                    <a v-else-if="ready" href="/auth/sso/redirect" class="text-sm text-stone-500 hover:text-stone-700">
                        Log in
                    </a>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-6 py-8">
            <RouterView />
        </main>
    </div>
</template>
