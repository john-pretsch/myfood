import { createRouter, createWebHistory } from 'vue-router';
import RecipesIndex from './pages/RecipesIndex.vue';
import RecipeShow from './pages/RecipeShow.vue';
import RecipeForm from './pages/RecipeForm.vue';
import Login from './pages/Login.vue';

const routes = [
    { path: '/', name: 'recipes.index', component: RecipesIndex },
    { path: '/login', name: 'login', component: Login },
    { path: '/recipes/new', name: 'recipes.create', component: RecipeForm },
    { path: '/recipes/:id', name: 'recipes.show', component: RecipeShow, props: true },
    { path: '/recipes/:id/edit', name: 'recipes.edit', component: RecipeForm, props: true },
];

export default createRouter({
    history: createWebHistory(),
    routes,
});
