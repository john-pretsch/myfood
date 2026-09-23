import { ref } from 'vue';
import api from './api';

const user = ref(null);
const ready = ref(false);

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
    await api.post('/logout');
    user.value = null;
}

export function useAuth() {
    return { user, ready, fetchUser, logout };
}
