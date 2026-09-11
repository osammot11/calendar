<script setup>
import { computed } from "vue";
import { Plus, RefreshCw, LogOut } from "@lucide/vue";
import { usePlannerContext } from "../../composables/plannerContext";

const { activePanel, csrfToken, recalculate, saving, openContextualTask } = usePlannerContext();
const title = computed(() => ({
    overview: "Il tuo calendario", deadlines: "Scadenze", projects: "I tuoi progetti",
    projectDetail: "Dettaglio progetto", pastEvents: "Eventi passati", settings: "Impostazioni", day: "La tua giornata",
}[activePanel.value] || "Il tuo calendario"));
</script>

<template>
    <header class="top-app-bar">
        <div class="app-heading">
            <p class="eyebrow">Planner personale</p>
            <h1>{{ title }}</h1>
        </div>
        <div class="top-actions">
            <button class="icon-button recalculate-button" :disabled="saving" title="Ricalcola pianificazione" aria-label="Ricalcola pianificazione" @click="recalculate">
                <RefreshCw :size="19" :class="{ spinning: saving }" />
            </button>
            <button class="button filled header-create" @click="openContextualTask">
                <Plus :size="18" /> Nuova task
            </button>
            <form method="post" action="/logout">
                <input type="hidden" name="_token" :value="csrfToken" />
                <button class="icon-button" type="submit" title="Esci" aria-label="Esci"><LogOut :size="19" /></button>
            </form>
        </div>
    </header>
</template>
