<script setup>
import { computed } from "vue";
import { ChevronDown, X } from "@lucide/vue";
import { usePlannerContext } from "../../composables/plannerContext";

const {
    closeModal,
    data,
    deleteTaskFromModal,
    saveTask,
    saving,
    taskForm,
} = usePlannerContext();

const hourOptions = Array.from({ length: 41 }, (_, hour) => hour);
const minuteOptions = Array.from({ length: 12 }, (_, index) => index * 5);

const durationHours = computed({
    get: () => Math.floor(Number(taskForm.value.duration_minutes || 0) / 60),
    set: (hours) => setDuration(Number(hours), durationMinutes.value),
});

const durationMinutes = computed({
    get: () => Number(taskForm.value.duration_minutes || 0) % 60,
    set: (minutes) => setDuration(durationHours.value, Number(minutes)),
});

function setDuration(hours, minutes) {
    const total = Math.min(2400, Math.max(5, hours * 60 + minutes));
    taskForm.value.duration_minutes = total;
}
</script>

<template>
    <form class="dialog surface" @submit.prevent="saveTask">
        <div class="dialog-heading">
            <h2 id="dialog-title">{{ taskForm.id ? "Modifica task" : "Nuova task" }}</h2>
            <button class="icon-button" type="button" title="Chiudi" aria-label="Chiudi" @click="closeModal">
                <X :size="20" />
            </button>
        </div>
        <label class="field">
            <span>Titolo</span>
            <input v-model="taskForm.title" required />
        </label>
        <label class="field">
            <span>Descrizione</span>
            <textarea v-model="taskForm.description" rows="3"></textarea>
        </label>
        <label class="field">
            <span>Progetto</span>
            <select v-model="taskForm.project_id" required>
                <option
                    v-for="project in data.projects"
                    :key="project.id"
                    :value="project.id"
                >
                    {{ project.name }}
                </option>
            </select>
        </label>
        <div class="duration-priority-grid">
            <div class="field duration-field">
                <span>Durata</span>
                <div class="duration-selectors">
                    <label>
                        <span>Ore</span>
                        <select v-model="durationHours" aria-label="Ore di durata">
                            <option v-for="hour in hourOptions" :key="hour" :value="hour">
                                {{ hour }}
                            </option>
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
                            >
                                {{ String(minute).padStart(2, "0") }}
                            </option>
                        </select>
                    </label>
                </div>
            </div>
            <label class="field priority-field">
                <span>Priorita task</span>
                <input v-model="taskForm.priority" type="number" min="1" max="5" required />
            </label>
        </div>
        <label class="field">
            <span>Deadline opzionale</span>
            <input v-model="taskForm.deadline" type="date" />
        </label>
        <label class="check-row">
            <input v-model="taskForm.is_max_priority" type="checkbox" />
            <span>Priorita massima</span>
        </label>
        <label class="check-row">
            <input v-model="taskForm.is_pinned" type="checkbox" />
            <span>Fissa in calendario</span>
        </label>
        <label v-if="taskForm.is_pinned" class="field">
            <span>Inizio fissato</span>
            <input
                v-model="taskForm.pinned_start_at"
                type="datetime-local"
                required
            />
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
                <label class="field">
                    <span>Non puo iniziare prima delle</span>
                    <input v-model="taskForm.earliest_start_time" type="time" step="300" />
                </label>
                <label class="field">
                    <span>Non puo finire dopo le</span>
                    <input v-model="taskForm.latest_end_time" type="time" step="300" />
                </label>
            </div>
        </details>
        <div class="dialog-actions">
            <button
                v-if="taskForm.id"
                class="button text danger"
                type="button"
                @click="deleteTaskFromModal"
            >
                Elimina
            </button>
            <button class="button text" type="button" @click="closeModal">
                Annulla
            </button>
            <button
                class="button filled"
                type="submit"
                :disabled="saving"
            >
                Salva
            </button>
        </div>
    </form>
</template>
