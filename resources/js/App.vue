<script setup>
import { providePlanner } from "./composables/plannerContext";
import { usePlanner } from "./composables/usePlanner";
import AppHeader from "./components/layout/AppHeader.vue";
import AppNavigation from "./components/layout/AppNavigation.vue";
import ModalHost from "./components/modals/ModalHost.vue";
import DashboardView from "./views/DashboardView.vue";
import PastEventsView from "./views/PastEventsView.vue";
import ProjectDetailView from "./views/ProjectDetailView.vue";

const planner = usePlanner();
providePlanner(planner);

const {
    activePanel,
    error,
    loading,
    openContextualTask,
    selectedProject,
} = planner;
</script>

<template>
    <div v-if="loading" class="boot-screen">
        <div class="progress"></div>
        <p>Carico il tuo calendario...</p>
    </div>

    <div v-else class="app-shell">
        <AppNavigation />

        <main class="main-area">
            <AppHeader />

            <div v-if="error" class="snackbar">{{ error }}</div>

            <PastEventsView v-if="activePanel === 'pastEvents'" />
            <ProjectDetailView
                v-else-if="activePanel === 'projectDetail' && selectedProject"
            />
            <DashboardView v-else />

            <button
                class="fab"
                title="Nuova task"
                @click="openContextualTask"
            >
                +
            </button>
        </main>

        <ModalHost />
    </div>
</template>
