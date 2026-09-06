<script setup>
import { RouterLink, RouterView } from 'vue-router';
</script>

<template>
    <div class="min-h-screen bg-neutral-50 text-neutral-900">
        <header class="border-b border-neutral-200 bg-white">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
                <RouterLink to="/" class="text-lg font-semibold tracking-tight">MyFood</RouterLink>
                <RouterLink
                    to="/recipes/new"
                    class="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-700"
                >
                    New Recipe
                </RouterLink>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-6 py-8">
            <RouterView />
        </main>
    </div>
</template>
