<script setup>
import { Plus, Pencil, Trash2, FolderOpen } from "@lucide/vue";
import { usePlannerContext } from "../../composables/plannerContext";

const { data, destroy, formatDate, openProject, openProjectDetail } = usePlannerContext();
</script>

<template>
    <section class="panel-section surface">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Portfolio</p>
                <h2>Progetti</h2>
            </div>
            <button class="icon-button tonal" title="Nuovo progetto" aria-label="Nuovo progetto" @click="openProject()"><Plus :size="20" /></button>
        </div>

        <article
            v-for="project in data.projects"
            :key="project.id"
            class="project-card"
        >
            <div class="project-line">
                <span
                    class="project-dot large"
                    :style="{ background: project.color }"
                ></span>
                <div>
                    <button
                        class="project-name-button"
                        @click="openProjectDetail(project)"
                    >
                        {{ project.name }}
                    </button>
                    <small>
                        Priorita {{ project.priority }}
                        <span v-if="project.deadline">
                            · {{ formatDate(project.deadline) }}
                        </span>
                    </small>
                </div>
            </div>
            <div class="row-actions">
                <button class="icon-button" title="Modifica progetto" :aria-label="'Modifica ' + project.name" @click="openProject(project)">
                    <Pencil :size="16" />
                </button>
                <button
                    class="icon-button danger"
                    title="Elimina progetto"
                    :aria-label="'Elimina ' + project.name"
                    @click="destroy('/planner-api/projects/' + project.id)"
                >
                    <Trash2 :size="16" />
                </button>
            </div>
        </article>
        <div v-if="data.projects.length === 0" class="empty-state">
            <FolderOpen :size="28" :stroke-width="1.5" />
            <strong>Nessun progetto</strong>
        </div>
    </section>
</template>
