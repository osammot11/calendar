<script setup>
import {
    ChartBar,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    FolderKanban,
    Timer,
} from "@lucide/vue";
import { usePlannerContext } from "../composables/plannerContext";
import { useAnalytics } from "../composables/useAnalytics";

const planner = usePlannerContext();
const {
    analyticsActiveProjects,
    analyticsPeriod,
    analyticsPeriodLabel,
    analyticsRows,
    analyticsTasks,
    analyticsTotalMinutes,
    resetAnalyticsPeriod,
    shiftAnalyticsPeriod,
} = useAnalytics(planner.data);

const periods = [
    { key: "day", label: "Giorno" },
    { key: "week", label: "Settimana" },
    { key: "month", label: "Mese" },
];

function duration(minutes) {
    return planner.durationLabel(minutes) || "0m";
}
</script>

<template>
    <section class="analytics-page">
        <div class="analytics-toolbar">
            <div>
                <p class="eyebrow">Tempo completato</p>
                <h2>{{ analyticsPeriodLabel }}</h2>
            </div>
            <div class="analytics-controls">
                <div class="filter-pill-group" aria-label="Periodo analytics">
                    <button
                        v-for="period in periods"
                        :key="period.key"
                        class="filter-pill"
                        :class="{ active: analyticsPeriod === period.key }"
                        @click="analyticsPeriod = period.key"
                    >
                        {{ period.label }}
                    </button>
                </div>
                <div class="analytics-navigation">
                    <button
                        class="icon-button"
                        title="Periodo precedente"
                        aria-label="Periodo precedente"
                        @click="shiftAnalyticsPeriod(-1)"
                    >
                        <ChevronLeft :size="20" />
                    </button>
                    <button class="button outlined" @click="resetAnalyticsPeriod">
                        Oggi
                    </button>
                    <button
                        class="icon-button"
                        title="Periodo successivo"
                        aria-label="Periodo successivo"
                        @click="shiftAnalyticsPeriod(1)"
                    >
                        <ChevronRight :size="20" />
                    </button>
                </div>
            </div>
        </div>

        <div class="analytics-summary">
            <div>
                <Timer :size="21" />
                <span>Tempo lavorato</span>
                <strong>{{ duration(analyticsTotalMinutes) }}</strong>
            </div>
            <div>
                <CircleCheck :size="21" />
                <span>Task concluse</span>
                <strong>{{ analyticsTasks.length }}</strong>
            </div>
            <div>
                <FolderKanban :size="21" />
                <span>Progetti attivi</span>
                <strong>{{ analyticsActiveProjects }}</strong>
            </div>
        </div>

        <div class="analytics-content">
            <div class="analytics-section-heading">
                <div>
                    <p class="eyebrow">Distribuzione</p>
                    <h3>Ore per progetto</h3>
                </div>
                <ChartBar :size="22" />
            </div>

            <div v-if="analyticsRows.length" class="analytics-project-list">
                <article
                    v-for="row in analyticsRows"
                    :key="row.project.id"
                    class="analytics-project-row"
                >
                    <div class="analytics-project-main">
                        <span
                            class="project-dot large"
                            :style="{ background: row.project.color }"
                        ></span>
                        <div>
                            <strong>{{ row.project.name }}</strong>
                            <small>
                                {{ row.taskCount }}
                                {{ row.taskCount === 1 ? "task conclusa" : "task concluse" }}
                            </small>
                        </div>
                    </div>
                    <div class="analytics-bar" aria-hidden="true">
                        <span
                            :style="{
                                width: `${row.percentage}%`,
                                background: row.project.color,
                            }"
                        ></span>
                    </div>
                    <strong class="analytics-duration">{{ duration(row.minutes) }}</strong>
                </article>
            </div>

            <div v-else class="empty-state">
                <FolderKanban :size="28" :stroke-width="1.5" />
                <strong>Nessun progetto</strong>
                <span>Crea un progetto per iniziare a raccogliere i dati.</span>
            </div>
        </div>

        <p class="analytics-note">
            Il tempo viene attribuito al giorno pianificato a calendario. Per le task senza uno slot storico si usa la data di completamento.
        </p>
    </section>
</template>
