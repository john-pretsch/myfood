<script setup>
function signIn() {
    window.location.href = '/auth/sso/redirect';
}
</script>

<template>
    <div class="mx-auto max-w-sm">
        <h1 class="mb-4 text-lg font-semibold text-stone-900">Log in</h1>
        <div class="space-y-3 rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-stone-600">Sign in with your Jepflow account.</p>
            <button
                type="button"
                class="w-full rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700"
                @click="signIn"
            >
                Sign in with Jepflow
            </button>
        </div>
    </div>
</template>
