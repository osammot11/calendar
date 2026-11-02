<script setup>
import { computed } from "vue";
import { X } from "@lucide/vue";
import { usePlannerContext } from "../../composables/plannerContext";

const {
    closeModal,
    completeSelectedEvent,
    deleteSelectedEvent,
    durationLabel,
    editSelectedEvent,
    formatDateTime,
    projectFor,
    selectedCalendarEvent,
} = usePlannerContext();

const recurrenceLabel = computed(() => {
    const series = selectedCalendarEvent.value?.recurring;
    if (!series) return "";
    const units = {
        hours: ["ora", "ore"],
        days: ["giorno", "giorni"],
        weeks: ["settimana", "settimane"],
        months: ["mese", "mesi"],
    };
    const unit = units[series.interval_unit] || [series.interval_unit, series.interval_unit];
    return `Ogni ${series.interval_count} ${unit[series.interval_count === 1 ? 0 : 1]}`;
});
const recurrenceStartTime = computed(() => {
    const value = selectedCalendarEvent.value?.task?.recurrence_at;
    if (!value) return "";
    return new Intl.DateTimeFormat("it-IT", {
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(value));
});
</script>

<template>
    <section class="dialog surface">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">
                    {{
                        selectedCalendarEvent.type === "task"
                            ? selectedCalendarEvent.recurring ? "Occorrenza ricorrente" : "Task schedulata"
                            : "Blocco occupato"
                    }}
                </p>
                <h2 id="dialog-title">{{ selectedCalendarEvent.title }}</h2>
            </div>
            <button class="icon-button" type="button" title="Chiudi" aria-label="Chiudi" @click="closeModal">
                <X :size="20" />
            </button>
        </div>

        <div class="detail-list">
            <div>
                <span>Inizio</span>
                <strong>{{ formatDateTime(selectedCalendarEvent.start) }}</strong>
            </div>
            <div>
                <span>Fine</span>
                <strong>{{ formatDateTime(selectedCalendarEvent.end) }}</strong>
            </div>
            <template
                v-if="
                    selectedCalendarEvent.type === 'task' &&
                    selectedCalendarEvent.task
                "
            >
                <div>
                    <span>Progetto</span>
                    <strong>
                        {{ projectFor(selectedCalendarEvent.task).name }}
                    </strong>
                </div>
                <div v-if="selectedCalendarEvent.recurring">
                    <span>Ricorrenza</span>
                    <strong>{{ recurrenceLabel }}</strong>
                </div>
                <div v-if="selectedCalendarEvent.recurring?.start_mode === 'window'">
                    <span>Inizio consentito</span>
                    <strong>
                        {{ recurrenceStartTime }}
                        -
                        {{ selectedCalendarEvent.recurring.latest_start_time.slice(0, 5) }}
                    </strong>
                </div>
                <div>
                    <span>Durata totale</span>
                    <strong>
                        {{
                            durationLabel(
                                selectedCalendarEvent.task.duration_minutes,
                            )
                        }}
                    </strong>
                </div>
                <div v-if="!selectedCalendarEvent.recurring">
                    <span>Priorita task</span>
                    <strong>
                        {{ selectedCalendarEvent.task.priority }}/5
                    </strong>
                </div>
                <div v-if="!selectedCalendarEvent.recurring">
                    <span>Deadline</span>
                    <strong>
                        {{ selectedCalendarEvent.task.deadline || "Nessuna" }}
                    </strong>
                </div>
                <div>
                    <span>Stato</span>
                    <strong>
                        {{
                            selectedCalendarEvent.task.status === "done"
                                ? "Completata"
                                : "Aperta"
                        }}
                    </strong>
                </div>
                <div v-if="!selectedCalendarEvent.recurring">
                    <span>Priorita massima</span>
                    <strong>
                        {{
                            selectedCalendarEvent.task.is_max_priority
                                ? "Si"
                                : "No"
                        }}
                    </strong>
                </div>
                <div v-if="!selectedCalendarEvent.recurring">
                    <span>Fissata</span>
                    <strong>
                        {{
                            selectedCalendarEvent.task.is_pinned
                                ? formatDateTime(
                                      selectedCalendarEvent.task
                                          .pinned_start_at,
                                  )
                                : "No"
                        }}
                    </strong>
                </div>
                <div v-if="selectedCalendarEvent.task.earliest_start_time">
                    <span>Inizio non prima delle</span>
                    <strong>{{ selectedCalendarEvent.task.earliest_start_time.slice(0, 5) }}</strong>
                </div>
                <div v-if="selectedCalendarEvent.task.latest_end_time">
                    <span>Fine non dopo le</span>
                    <strong>{{ selectedCalendarEvent.task.latest_end_time.slice(0, 5) }}</strong>
                </div>
                <div
                    v-if="selectedCalendarEvent.task.description"
                    class="detail-wide"
                >
                    <span>Descrizione</span>
                    <strong>
                        {{ selectedCalendarEvent.task.description }}
                    </strong>
                </div>
            </template>
        </div>

        <div class="dialog-actions">
            <button
                v-if="selectedCalendarEvent.task?.status === 'open'"
                class="button text"
                type="button"
                @click="completeSelectedEvent"
            >Completa</button>
            <button
                v-if="!selectedCalendarEvent.recurring || selectedCalendarEvent.task?.status !== 'done'"
                class="button text danger"
                type="button"
                @click="deleteSelectedEvent"
            >
                {{ selectedCalendarEvent.recurring ? "Salta questa" : "Elimina" }}
            </button>
            <button class="button text" type="button" @click="closeModal">
                Annulla
            </button>
            <button
                class="button filled"
                type="button"
                @click="editSelectedEvent"
            >
                {{ selectedCalendarEvent.recurring ? "Modifica serie" : "Modifica" }}
            </button>
        </div>
    </section>
</template>
