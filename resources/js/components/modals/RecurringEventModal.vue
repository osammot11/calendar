<script setup>
import { computed } from "vue";
import { Clock3, Repeat2, X } from "@lucide/vue";
import { usePlannerContext } from "../../composables/plannerContext";

const {
    closeModal,
    data,
    deleteRecurringFromModal,
    recurringForm,
    saveRecurring,
    saving,
} = usePlannerContext();

const hourOptions = Array.from({ length: 41 }, (_, hour) => hour);
const minuteOptions = Array.from({ length: 12 }, (_, index) => index * 5);

const durationHours = computed({
    get: () => Math.floor(Number(recurringForm.value.duration_minutes || 0) / 60),
    set: (hours) => setDuration(Number(hours), durationMinutes.value),
});

const durationMinutes = computed({
    get: () => Number(recurringForm.value.duration_minutes || 0) % 60,
    set: (minutes) => setDuration(durationHours.value, Number(minutes)),
});

function setDuration(hours, minutes) {
    recurringForm.value.duration_minutes = Math.min(
        2400,
        Math.max(5, hours * 60 + minutes),
    );
}
</script>

<template>
    <form class="dialog surface recurring-dialog" @submit.prevent="saveRecurring">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">Serie ricorrente</p>
                <h2 id="dialog-title">{{ recurringForm.id ? "Modifica ricorrenza" : "Nuovo ricorrente" }}</h2>
            </div>
            <button class="icon-button" type="button" title="Chiudi" aria-label="Chiudi" @click="closeModal">
                <X :size="20" />
            </button>
        </div>

        <label class="field">
            <span>Titolo</span>
            <input v-model="recurringForm.title" required maxlength="255" />
        </label>
        <label class="field">
            <span>Descrizione</span>
            <textarea v-model="recurringForm.description" rows="2"></textarea>
        </label>
        <label class="field">
            <span>Progetto</span>
            <select v-model="recurringForm.project_id" required>
                <option v-for="project in data.projects" :key="project.id" :value="project.id">
                    {{ project.name }}
                </option>
            </select>
        </label>

        <div class="field duration-field">
            <span>Durata di ogni occorrenza</span>
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

        <div class="recurring-section">
            <span class="recurring-section-label"><Clock3 :size="16" /> Inizio</span>
            <div class="filter-pill-group recurring-mode" aria-label="Modalita di inizio">
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
                    <span>Unita</span>
                    <select v-model="recurringForm.interval_unit" required>
                        <option value="hours">Ore</option>
                        <option value="days">Giorni</option>
                        <option value="weeks">Settimane</option>
                        <option value="months">Mesi</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="dialog-actions">
            <button
                v-if="recurringForm.id && recurringForm.active"
                class="button text danger"
                type="button"
                :disabled="saving"
                @click="deleteRecurringFromModal"
            >Interrompi serie</button>
            <button class="button text" type="button" @click="closeModal">Annulla</button>
            <button class="button filled" type="submit" :disabled="saving">
                {{ recurringForm.id && !recurringForm.active ? "Riattiva" : "Salva" }}
            </button>
        </div>
    </form>
</template>
