<script setup>
import { usePlannerContext } from "../../composables/plannerContext";

const { csrfToken, saveSchedules, scheduleDays } = usePlannerContext();
</script>

<template>
    <section class="panel-section surface">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Routine</p>
                <h2>Orari di lavoro</h2>
            </div>
            <button class="button tonal" @click="saveSchedules">Salva</button>
        </div>
        <div class="schedule-list">
            <label
                v-for="day in scheduleDays"
                :key="day.weekday"
                class="schedule-row"
            >
                <input v-model="day.enabled" type="checkbox" />
                <span>{{ day.label }}</span>
                <input
                    v-model="day.start_time"
                    type="time"
                    :disabled="!day.enabled"
                />
                <input
                    v-model="day.end_time"
                    type="time"
                    :disabled="!day.enabled"
                />
            </label>
        </div>
        <form class="settings-logout" method="post" action="/logout">
            <input type="hidden" name="_token" :value="csrfToken" />
            <button class="button outlined" type="submit">Esci</button>
        </form>
    </section>
</template>
