<script setup>
import { usePlannerContext } from "../../composables/plannerContext";

const { csrfToken, recalculate, saving } = usePlannerContext();
</script>

<template>
    <header class="top-app-bar surface">
        <div>
            <p class="eyebrow">Planner personale</p>
            <h1>Calendario intelligente</h1>
        </div>
        <div class="top-actions">
            <button
                class="button tonal"
                :disabled="saving"
                @click="recalculate"
            >
                Ricalcola
            </button>
            <form method="post" action="/logout">
                <input type="hidden" name="_token" :value="csrfToken" />
                <button class="button text" type="submit">Esci</button>
            </form>
        </div>
    </header>
</template>
