<script setup>
import { usePlannerContext } from "../../composables/plannerContext";

const { saveSchedules, scheduleDays } = usePlannerContext();
</script>

<template>
    <section class="panel-section surface">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Routine</p>
                <h2>Fasce default</h2>
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
    </section>
</template>
