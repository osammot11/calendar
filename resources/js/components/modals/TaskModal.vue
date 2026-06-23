<script setup>
import { usePlannerContext } from "../../composables/plannerContext";

const {
    closeModal,
    data,
    deleteTaskFromModal,
    saveTask,
    saving,
    taskForm,
} = usePlannerContext();
</script>

<template>
    <form class="dialog surface" @submit.prevent="saveTask">
        <div class="dialog-heading">
            <h2>{{ taskForm.id ? "Modifica task" : "Nuova task" }}</h2>
            <button class="icon-button" type="button" @click="closeModal">
                X
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
        <div class="form-grid">
            <label class="field">
                <span>Durata minuti</span>
                <input
                    v-model="taskForm.duration_minutes"
                    type="number"
                    min="15"
                    step="15"
                    required
                />
            </label>
            <label class="field">
                <span>Priorita task</span>
                <input
                    v-model="taskForm.priority"
                    type="number"
                    min="1"
                    max="5"
                    required
                />
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
