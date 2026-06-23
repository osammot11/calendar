<script setup>
import { usePlannerContext } from "../../composables/plannerContext";

const { data, destroy, openProject, openProjectDetail } = usePlannerContext();
</script>

<template>
    <section class="panel-section surface">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Portfolio</p>
                <h2>Progetti</h2>
            </div>
            <button class="button tonal" @click="openProject()">Nuovo</button>
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
                            · deadline {{ project.deadline }}
                        </span>
                    </small>
                </div>
            </div>
            <div class="row-actions">
                <button class="button text" @click="openProject(project)">
                    Modifica
                </button>
                <button
                    class="button text danger"
                    @click="destroy('/planner-api/projects/' + project.id)"
                >
                    Elimina
                </button>
            </div>
        </article>
    </section>
</template>
