<script setup>
import { computed, ref } from "vue";
import { Pencil, X } from "@lucide/vue";
import { usePlannerContext } from "../../composables/plannerContext";

const {
    closeModal,
    completeSelectedEvent,
    deleteSelectedEvent,
    durationLabel,
    editSelectedEvent,
    formatDateTime,
    projectFor,
    saveCompletedDuration,
    saving,
    selectedCalendarEvent,
} = usePlannerContext();

const editingDuration = ref(false);
const editedMinutes = ref(60);
const hourOptions = Array.from({ length: 41 }, (_, hour) => hour);
const minuteOptions = Array.from({ length: 12 }, (_, index) => index * 5);
const durationHours = computed({
    get: () => Math.floor(editedMinutes.value / 60),
    set: (hours) => setDuration(Number(hours), durationMinutes.value),
});
const durationMinutes = computed({
    get: () => editedMinutes.value % 60,
    set: (minutes) => setDuration(durationHours.value, Number(minutes)),
});

function setDuration(hours, minutes) {
    editedMinutes.value = Math.min(2400, Math.max(5, hours * 60 + minutes));
}

function startDurationEdit() {
    editedMinutes.value = Number(
        selectedCalendarEvent.value?.props?.block_minutes ||
        selectedCalendarEvent.value?.task?.duration_minutes || 60,
    );
    editingDuration.value = true;
}

async function submitDuration() {
    await saveCompletedDuration(editedMinutes.value);
}

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
                    <span>{{ selectedCalendarEvent.task.status === "done" ? "Durata effettiva" : "Durata totale" }}</span>
                    <div class="detail-duration-value">
                        <strong>
                        {{
                            durationLabel(
                                selectedCalendarEvent.props.block_minutes || selectedCalendarEvent.task.duration_minutes,
                            )
                        }}
                        </strong>
                        <button
                            v-if="selectedCalendarEvent.task.status === 'done'"
                            class="icon-button"
                            type="button"
                            title="Modifica durata effettiva"
                            aria-label="Modifica durata effettiva"
                            @click="startDurationEdit"
                        ><Pencil :size="17" /></button>
                    </div>
                </div>
                <div v-if="editingDuration" class="detail-wide completed-duration-editor">
                    <form @submit.prevent="submitDuration">
                        <div class="duration-selectors">
                            <label>
                                <span>Ore</span>
                                <select v-model="durationHours" aria-label="Ore lavorate">
                                    <option v-for="hour in hourOptions" :key="hour" :value="hour">{{ hour }}</option>
                                </select>
                            </label>
                            <label>
                                <span>Minuti</span>
                                <select v-model="durationMinutes" aria-label="Minuti lavorati">
                                    <option v-for="minute in minuteOptions" :key="minute" :value="minute" :disabled="durationHours === 40 && minute > 0">
                                        {{ String(minute).padStart(2, "0") }}
                                    </option>
                                </select>
                            </label>
                        </div>
                        <div class="completed-duration-actions">
                            <button class="button text" type="button" @click="editingDuration = false">Annulla</button>
                            <button class="button filled" type="submit" :disabled="saving">Salva durata</button>
                        </div>
                    </form>
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
