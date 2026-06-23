<script setup>
import { usePlannerContext } from "../../composables/plannerContext";

const { closeModal, projectForm, saveProject, saving } = usePlannerContext();
</script>

<template>
    <form class="dialog surface" @submit.prevent="saveProject">
        <div class="dialog-heading">
            <h2>
                {{ projectForm.id ? "Modifica progetto" : "Nuovo progetto" }}
            </h2>
            <button class="icon-button" type="button" @click="closeModal">
                X
            </button>
        </div>
        <label class="field">
            <span>Nome</span>
            <input v-model="projectForm.name" required />
        </label>
        <div class="form-grid">
            <label class="field">
                <span>Colore</span>
                <input v-model="projectForm.color" type="color" />
            </label>
            <label class="field">
                <span>Priorita progetto</span>
                <input
                    v-model="projectForm.priority"
                    type="number"
                    min="1"
                    max="5"
                    required
                />
            </label>
        </div>
        <label class="field">
            <span>Deadline opzionale</span>
            <input v-model="projectForm.deadline" type="date" />
        </label>
        <div class="dialog-actions">
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
