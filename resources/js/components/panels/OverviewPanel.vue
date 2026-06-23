<script setup>
import { usePlannerContext } from "../../composables/plannerContext";

const {
    data,
    durationLabel,
    maxPriorityTasks,
    openTask,
    openTasks,
    projectFor,
} = usePlannerContext();
</script>

<template>
    <section class="panel-section surface">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Focus</p>
                <h2>Task aperte</h2>
            </div>
            <button class="button tonal" @click="openTask()">Nuova</button>
        </div>

        <div class="metrics">
            <div class="metric">
                <strong>{{ openTasks.length }}</strong><span>Aperte</span>
            </div>
            <div class="metric">
                <strong>{{ maxPriorityTasks.length }}</strong
                ><span>Massime</span>
            </div>
            <div class="metric">
                <strong>{{ data.unscheduledTasks.length }}</strong
                ><span>Fuori piano</span>
            </div>
        </div>

        <div class="task-list">
            <article v-for="task in openTasks" :key="task.id" class="task-item">
                <button class="task-main" @click="openTask(task)">
                    <span
                        class="project-dot"
                        :style="{ background: projectFor(task).color }"
                    ></span>
                    <span>
                        <strong>{{ task.title }}</strong>
                        <small>
                            {{ projectFor(task).name }} · P{{ task.priority }} ·
                            {{ durationLabel(task.duration_minutes) }}
                        </small>
                    </span>
                </button>
                <span v-if="task.is_max_priority" class="chip alert-chip">
                    Massima
                </span>
            </article>
        </div>
    </section>
</template>
