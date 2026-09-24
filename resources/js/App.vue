<script setup>
import { ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute } from 'vue-router';
import { useAuth } from './auth';

const { user, ready, isAdmin, fetchUser, logout } = useAuth();
const route = useRoute();

const menuOpen = ref(false);

watch(route, () => {
    menuOpen.value = false;
});

const navItemClass = 'block rounded-md px-3 py-2 text-sm font-medium text-stone-600 hover:bg-stone-100 hover:text-stone-900';

fetchUser();
</script>

<template>
    <div class="min-h-screen bg-stone-50 text-stone-900">
        <header class="sticky top-0 z-10 border-b border-stone-200 bg-white/80 backdrop-blur">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
                <RouterLink to="/" class="flex items-center gap-2 text-lg font-semibold tracking-tight text-stone-900">
                    <img src="https://spaces-cdn.clipsafari.com/jmjrsnaahktp5zcacdc2bytf5nly" alt="MyFood" class="h-6 w-6 rounded object-contain" />
                    MyFood
                </RouterLink>

                <nav class="hidden items-center gap-1 sm:flex">
                    <RouterLink to="/" :class="navItemClass">View Recipes</RouterLink>
                    <RouterLink v-if="isAdmin" to="/tags" :class="navItemClass">Tags</RouterLink>
                    <RouterLink v-if="user" to="/recipes/new" :class="navItemClass">New Recipe</RouterLink>
                    <button v-if="user" type="button" :class="navItemClass" @click="logout">Log out</button>
                    <a v-else-if="ready" href="/auth/sso/redirect" :class="navItemClass">Log in</a>
                </nav>

                <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-md text-stone-600 hover:bg-stone-100 sm:hidden"
                    aria-label="Toggle menu"
                    @click="menuOpen = !menuOpen"
                >
                    <svg v-if="!menuOpen" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav v-if="menuOpen" class="flex flex-col gap-1 border-t border-stone-200 px-6 py-3 sm:hidden">
                <RouterLink to="/" :class="navItemClass">View Recipes</RouterLink>
                <RouterLink v-if="isAdmin" to="/tags" :class="navItemClass">Tags</RouterLink>
                <RouterLink v-if="user" to="/recipes/new" :class="navItemClass">New Recipe</RouterLink>
                <button v-if="user" type="button" :class="[navItemClass, 'text-left']" @click="logout">Log out</button>
                <a v-else-if="ready" href="/auth/sso/redirect" :class="navItemClass">Log in</a>
            </nav>
        </header>

        <main class="mx-auto max-w-4xl px-6 py-8">
            <RouterView />
        </main>
    </div>
</template>
