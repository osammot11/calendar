<script setup>
import { usePlannerContext } from "../composables/plannerContext";

const {
    activePanel,
    durationLabel,
    formatDate,
    openTask,
    openTaskForProject,
    projectTaskFilter,
    selectedProject,
    selectedProjectTaskCounts,
    selectedProjectTasks,
    taskSchedule,
} = usePlannerContext();
</script>

<template>
    <section class="project-detail-page surface">
        <div class="project-detail-header">
            <button class="button tonal" @click="activePanel = 'projects'">
                Indietro
            </button>
            <div>
                <p class="eyebrow">Progetto</p>
                <h2>{{ selectedProject.name }}</h2>
                <small>
                    Priorita {{ selectedProject.priority }}
                    <span v-if="selectedProject.deadline">
                        · deadline {{ formatDate(selectedProject.deadline) }}
                    </span>
                </small>
            </div>
            <div class="project-detail-actions">
                <span
                    class="project-dot detail-dot"
                    :style="{ background: selectedProject.color }"
                ></span>
                <button
                    class="button filled"
                    @click="openTaskForProject(selectedProject)"
                >
                    Nuova task
                </button>
            </div>
        </div>

        <div class="project-task-toolbar">
            <div class="filter-pill-group" aria-label="Filtro task progetto">
                <button
                    class="filter-pill"
                    :class="{ active: projectTaskFilter === 'open' }"
                    @click="projectTaskFilter = 'open'"
                >
                    Aperte
                    <span>{{ selectedProjectTaskCounts.open }}</span>
                </button>
                <button
                    class="filter-pill"
                    :class="{ active: projectTaskFilter === 'done' }"
                    @click="projectTaskFilter = 'done'"
                >
                    Completate
                    <span>{{ selectedProjectTaskCounts.done }}</span>
                </button>
            </div>
        </div>

        <div class="project-task-list">
            <article
                v-for="task in selectedProjectTasks"
                :key="task.id"
                class="project-task-row"
            >
                <div class="project-task-main">
                    <div class="project-task-title">
                        <strong>{{ task.title }}</strong>
                        <span
                            v-if="task.is_max_priority"
                            class="chip alert-chip"
                        >
                            Massima
                        </span>
                        <span v-if="task.is_pinned" class="chip">Fissata</span>
                        <span class="chip">
                            {{ task.status === "done" ? "Completata" : "Aperta" }}
                        </span>
                    </div>
                    <small>
                        {{ durationLabel(task.duration_minutes) }} · priorita
                        {{ task.priority }}/5
                        <span v-if="task.deadline">
                            · deadline {{ formatDate(task.deadline) }}
                        </span>
                    </small>
                    <p v-if="task.description">{{ task.description }}</p>
                </div>
                <div class="project-task-schedule">
                    <template v-if="taskSchedule(task)">
                        <span>In lavorazione</span>
                        <strong>{{ taskSchedule(task).label }}</strong>
                        <small>{{ taskSchedule(task).range }}</small>
                    </template>
                    <template v-else>
                        <span>Scheduling</span>
                        <strong>Fuori piano</strong>
                        <small>Nessuno slot continuo disponibile</small>
                    </template>
                </div>
                <button class="button text" @click="openTask(task)">
                    Modifica
                </button>
            </article>

            <div v-if="selectedProjectTasks.length === 0" class="empty-state">
                <strong>
                    Nessuna task
                    {{ projectTaskFilter === "open" ? "aperta" : "completata" }}
                </strong>
                <span>
                    {{
                        projectTaskFilter === "open"
                            ? "Le nuove task del progetto compariranno qui."
                            : "Quando completi una task la ritrovi in questa vista."
                    }}
                </span>
            </div>
        </div>
    </section>
</template>
