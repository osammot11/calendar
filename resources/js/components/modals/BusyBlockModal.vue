<script setup>
import { usePlannerContext } from "../../composables/plannerContext";

const {
    busyForm,
    closeModal,
    deleteBusyBlockFromModal,
    saveBusyBlock,
    saving,
} = usePlannerContext();
</script>

<template>
    <form class="dialog surface" @submit.prevent="saveBusyBlock">
        <div class="dialog-heading">
            <h2>
                {{
                    busyForm.id
                        ? "Modifica blocco occupato"
                        : "Blocco occupato"
                }}
            </h2>
            <button class="icon-button" type="button" @click="closeModal">
                X
            </button>
        </div>
        <label class="field">
            <span>Titolo</span>
            <input v-model="busyForm.title" required />
        </label>
        <label class="field">
            <span>Inizio</span>
            <input v-model="busyForm.start_at" type="datetime-local" required />
        </label>
        <label class="field">
            <span>Fine</span>
            <input v-model="busyForm.end_at" type="datetime-local" required />
        </label>
        <div class="dialog-actions">
            <button
                v-if="busyForm.id"
                class="button text danger"
                type="button"
                @click="deleteBusyBlockFromModal"
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
