<script setup>
import { usePlannerContext } from "../../composables/plannerContext";

const {
    closeModal,
    deleteSelectedEvent,
    durationLabel,
    editSelectedEvent,
    formatDateTime,
    projectFor,
    selectedCalendarEvent,
} = usePlannerContext();
</script>

<template>
    <section class="dialog surface">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">
                    {{
                        selectedCalendarEvent.type === "task"
                            ? "Task schedulata"
                            : "Blocco occupato"
                    }}
                </p>
                <h2>{{ selectedCalendarEvent.title }}</h2>
            </div>
            <button class="icon-button" type="button" @click="closeModal">
                X
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
                <div>
                    <span>Priorita task</span>
                    <strong>
                        {{ selectedCalendarEvent.task.priority }}/5
                    </strong>
                </div>
                <div>
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
                <div>
                    <span>Priorita massima</span>
                    <strong>
                        {{
                            selectedCalendarEvent.task.is_max_priority
                                ? "Si"
                                : "No"
                        }}
                    </strong>
                </div>
                <div>
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
                class="button text danger"
                type="button"
                @click="deleteSelectedEvent"
            >
                Elimina
            </button>
            <button class="button text" type="button" @click="closeModal">
                Annulla
            </button>
            <button
                class="button filled"
                type="button"
                @click="editSelectedEvent"
            >
                Modifica
            </button>
        </div>
    </section>
</template>
