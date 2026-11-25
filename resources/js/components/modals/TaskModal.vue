<script setup>
import { computed } from "vue";
import { CalendarCheck2, ChevronDown, Clock3, Repeat2, X } from "@lucide/vue";
import { usePlannerContext } from "../../composables/plannerContext";

const {
    closeModal,
    data,
    deleteRecurringFromModal,
    deleteTaskFromModal,
    recurringForm,
    saveTaskOrSeries,
    saving,
    setTaskRepeats,
    taskForm,
    taskRepeats,
} = usePlannerContext();

// Il form attivo: task singola o serie ricorrente. Titolo, descrizione,
// progetto e durata sono in comune e vengono copiati quando si cambia modalità.
const form = computed(() => (taskRepeats.value ? recurringForm.value : taskForm.value));
const editing = computed(() => Boolean(form.value.id));
const heading = computed(() => {
    if (!editing.value) return "Nuova task";
    return taskRepeats.value ? "Modifica ricorrenza" : "Modifica task";
});

const hourOptions = Array.from({ length: 41 }, (_, hour) => hour);
const minuteOptions = Array.from({ length: 12 }, (_, index) => index * 5);

const durationHours = computed({
    get: () => Math.floor(Number(form.value.duration_minutes || 0) / 60),
    set: (hours) => setDuration(Number(hours), durationMinutes.value),
});

const durationMinutes = computed({
    get: () => Number(form.value.duration_minutes || 0) % 60,
    set: (minutes) => setDuration(durationHours.value, Number(minutes)),
});

function setDuration(hours, minutes) {
    form.value.duration_minutes = Math.min(2400, Math.max(5, hours * 60 + minutes));
}
</script>

<template>
    <form class="dialog surface task-dialog" @submit.prevent="saveTaskOrSeries">
        <div class="dialog-heading">
            <div>
                <p v-if="editing && taskRepeats" class="eyebrow">Serie ricorrente</p>
                <h2 id="dialog-title">{{ heading }}</h2>
            </div>
            <button class="icon-button" type="button" title="Chiudi" aria-label="Chiudi" @click="closeModal">
                <X :size="20" />
            </button>
        </div>

        <div v-if="!editing" class="filter-pill-group task-kind" role="group" aria-label="Frequenza">
            <button
                type="button"
                class="filter-pill"
                :class="{ active: !taskRepeats }"
                :aria-pressed="!taskRepeats"
                @click="setTaskRepeats(false)"
            >Una volta</button>
            <button
                type="button"
                class="filter-pill"
                :class="{ active: taskRepeats }"
                :aria-pressed="taskRepeats"
                @click="setTaskRepeats(true)"
            ><Repeat2 :size="15" aria-hidden="true" /> Si ripete</button>
        </div>

        <label class="field">
            <span>Titolo</span>
            <input v-model="form.title" required maxlength="255" />
        </label>
        <label class="field">
            <span>Descrizione</span>
            <textarea v-model="form.description" rows="3"></textarea>
        </label>
        <label class="field">
            <span>Progetto</span>
            <select v-model="form.project_id" required>
                <option v-for="project in data.projects" :key="project.id" :value="project.id">
                    {{ project.name }}
                </option>
            </select>
        </label>

        <div class="duration-priority-grid">
            <div class="field duration-field">
                <span>{{ taskRepeats ? "Durata di ogni occorrenza" : "Durata" }}</span>
                <div class="duration-selectors">
                    <label>
                        <span>Ore</span>
                        <select v-model="durationHours" aria-label="Ore di durata">
                            <option v-for="hour in hourOptions" :key="hour" :value="hour">{{ hour }}</option>
                        </select>
                    </label>
                    <label>
                        <span>Minuti</span>
                        <select v-model="durationMinutes" aria-label="Minuti di durata">
                            <option
                                v-for="minute in minuteOptions"
                                :key="minute"
                                :value="minute"
                                :disabled="durationHours === 40 && minute > 0"
                            >{{ String(minute).padStart(2, "0") }}</option>
                        </select>
                    </label>
                </div>
            </div>
            <label v-if="!taskRepeats" class="field priority-field">
                <span>Priorità task</span>
                <input v-model="taskForm.priority" type="number" min="1" max="5" required />
            </label>
        </div>

        <template v-if="!taskRepeats">
            <label class="field">
                <span>Deadline opzionale</span>
                <input v-model="taskForm.deadline" type="date" />
            </label>
            <label class="check-row">
                <input v-model="taskForm.is_max_priority" type="checkbox" />
                <span>Priorità massima</span>
            </label>
            <label class="check-row">
                <input v-model="taskForm.is_pinned" type="checkbox" />
                <span>Fissa in calendario</span>
            </label>
            <label v-if="taskForm.is_pinned" class="field">
                <span>Inizio fissato</span>
                <input v-model="taskForm.pinned_start_at" type="datetime-local" required />
            </label>
            <label class="field">
                <span>Stato</span>
                <select v-model="taskForm.status">
                    <option value="open">Aperta</option>
                    <option value="done">Completata</option>
                </select>
            </label>
            <details v-if="!taskForm.is_pinned" class="task-advanced">
                <summary>
                    <span>Avanzate</span>
                    <ChevronDown :size="18" aria-hidden="true" />
                </summary>
                <div class="task-advanced-fields">
                    <label class="field advanced-start-date">
                        <span>Pianificabile dal</span>
                        <input v-model="taskForm.earliest_start_date" type="date" />
                    </label>
                    <label class="field">
                        <span>Non può iniziare prima delle</span>
                        <input v-model="taskForm.earliest_start_time" type="time" step="300" />
                    </label>
                    <label class="field">
                        <span>Non può finire dopo le</span>
                        <input v-model="taskForm.latest_end_time" type="time" step="300" />
                    </label>
                </div>
            </details>
        </template>

        <template v-else>
            <div class="recurring-section">
                <span class="recurring-section-label"><Clock3 :size="16" /> Inizio</span>
                <div class="filter-pill-group recurring-mode" role="group" aria-label="Modalità di inizio">
                    <button
                        type="button"
                        class="filter-pill"
                        :class="{ active: recurringForm.start_mode === 'fixed' }"
                        :aria-pressed="recurringForm.start_mode === 'fixed'"
                        @click="recurringForm.start_mode = 'fixed'"
                    >Ora precisa</button>
                    <button
                        type="button"
                        class="filter-pill"
                        :class="{ active: recurringForm.start_mode === 'window' }"
                        :aria-pressed="recurringForm.start_mode === 'window'"
                        @click="recurringForm.start_mode = 'window'"
                    >Fascia di inizio</button>
                </div>
                <div class="recurring-time-fields">
                    <label class="field">
                        <span>{{ recurringForm.start_mode === "fixed" ? "Prima occorrenza" : "Prima data e inizio fascia" }}</span>
                        <input v-model="recurringForm.starts_at" type="datetime-local" step="300" required />
                    </label>
                    <label v-if="recurringForm.start_mode === 'window'" class="field">
                        <span>Deve iniziare entro le</span>
                        <input v-model="recurringForm.latest_start_time" type="time" step="300" required />
                    </label>
                </div>
            </div>

            <div class="recurring-section">
                <span class="recurring-section-label"><Repeat2 :size="16" /> Ricorrenza</span>
                <div class="recurring-interval">
                    <label class="field">
                        <span>Ogni</span>
                        <input v-model.number="recurringForm.interval_count" type="number" min="1" max="365" required />
                    </label>
                    <label class="field">
                        <span>Unità</span>
                        <select v-model="recurringForm.interval_unit" required>
                            <option value="hours">Ore</option>
                            <option value="days">Giorni</option>
                            <option value="weeks">Settimane</option>
                            <option value="months">Mesi</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="recurring-section">
                <span class="recurring-section-label"><CalendarCheck2 :size="16" /> Fine</span>
                <div class="filter-pill-group recurring-mode" role="group" aria-label="Fine della serie">
                    <button
                        type="button"
                        class="filter-pill"
                        :class="{ active: recurringForm.end_mode === 'count' }"
                        :aria-pressed="recurringForm.end_mode === 'count'"
                        @click="recurringForm.end_mode = 'count'"
                    >Numero di ricorrenze</button>
                    <button
                        type="button"
                        class="filter-pill"
                        :class="{ active: recurringForm.end_mode === 'date' }"
                        :aria-pressed="recurringForm.end_mode === 'date'"
                        @click="recurringForm.end_mode = 'date'"
                    >Data di fine</button>
                </div>
                <div class="recurring-time-fields">
                    <label v-if="recurringForm.end_mode === 'count'" class="field">
                        <span>Quante ricorrenze</span>
                        <input v-model.number="recurringForm.occurrence_count" type="number" min="1" max="1000" required />
                    </label>
                    <label v-else class="field">
                        <span>Ultimo giorno</span>
                        <input
                            v-model="recurringForm.ends_on"
                            type="date"
                            :min="recurringForm.starts_at?.slice(0, 10)"
                            required
                        />
                    </label>
                </div>
            </div>
        </template>

        <div class="dialog-actions">
            <button
                v-if="!taskRepeats && taskForm.id"
                class="button text danger"
                type="button"
                @click="deleteTaskFromModal"
            >Elimina</button>
            <button
                v-if="taskRepeats && recurringForm.id && recurringForm.active"
                class="button text danger"
                type="button"
                :disabled="saving"
                @click="deleteRecurringFromModal"
            >Interrompi serie</button>
            <button class="button text" type="button" @click="closeModal">Annulla</button>
            <button class="button filled" type="submit" :disabled="saving">
                {{ taskRepeats && recurringForm.id && !recurringForm.active ? "Riattiva" : "Salva" }}
            </button>
        </div>
    </form>
</template>
