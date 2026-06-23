<script setup>
import { usePlannerContext } from "../composables/plannerContext";

const {
    completePastEvent,
    data,
    durationLabel,
    formatDateTime,
    reschedulePastEvent,
} = usePlannerContext();
</script>

<template>
    <section class="project-detail-page surface">
        <div class="project-detail-header">
            <div>
                <p class="eyebrow">Revisione</p>
                <h2>Eventi passati</h2>
                <small>
                    {{ data.pastEvents.length }} eventi da classificare
                </small>
            </div>
        </div>

        <div class="project-task-list">
            <article
                v-for="event in data.pastEvents"
                :key="event.id"
                class="project-task-row"
            >
                <div class="project-task-main">
                    <div class="project-task-title">
                        <span
                            class="project-dot"
                            :style="{ background: event.project.color }"
                        ></span>
                        <strong>{{ event.title }}</strong>
                        <span
                            v-if="event.task.is_max_priority"
                            class="chip alert-chip"
                        >
                            Massima
                        </span>
                    </div>
                    <small>
                        {{ event.project.name }} ·
                        {{ durationLabel(event.task.duration_minutes) }} ·
                        priorita {{ event.task.priority }}/5
                    </small>
                </div>
                <div class="project-task-schedule">
                    <span>Scaduto</span>
                    <strong>{{ formatDateTime(event.end) }}</strong>
                    <small>
                        {{ formatDateTime(event.start) }} -
                        {{ formatDateTime(event.end) }}
                    </small>
                </div>
                <div class="row-actions past-event-actions">
                    <button
                        class="button tonal"
                        @click="completePastEvent(event)"
                    >
                        Completato
                    </button>
                    <button
                        class="button text"
                        @click="reschedulePastEvent(event)"
                    >
                        Non completato
                    </button>
                </div>
            </article>

            <div v-if="data.pastEvents.length === 0" class="empty-state">
                <strong>Nessun evento passato da revisionare</strong>
                <span>
                    Le task non completate compariranno qui solo dopo la fine
                    del loro slot.
                </span>
            </div>
        </div>
    </section>
</template>
