import { computed, ref } from 'vue';
import api from './api';

const user = ref(null);
const ready = ref(false);
const isAdmin = computed(() => user.value?.role === 'admin');

async function fetchUser() {
    try {
        const { data } = await api.get('/user');
        user.value = data.user;
    } catch {
        user.value = null;
    } finally {
        ready.value = true;
    }
}

async function logout() {
    const { data } = await api.post('/logout');
    user.value = null;
    window.location.href = data.redirect;
}

export function useAuth() {
    return { user, ready, isAdmin, fetchUser, logout };
}
