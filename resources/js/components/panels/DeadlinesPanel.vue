<script setup>
import { computed, ref } from "vue";
import { usePlannerContext } from "../../composables/plannerContext";

const { deadlineBuckets, formatDate, openTask, taskSchedule } =
    usePlannerContext();

const activeDeadlineTab = ref("overdue");

const tabs = computed(() => [
    {
        key: "overdue",
        label: "Scadute",
        count: deadlineBuckets.value.overdue.length,
        emptyTitle: "Nessuna deadline passata aperta",
        emptyText:
            "Le task aperte con deadline progetto o task superata compariranno qui.",
    },
    {
        key: "today",
        label: "Oggi",
        count: deadlineBuckets.value.today.length,
        emptyTitle: "Nessuna deadline per oggi",
        emptyText: "Qui trovi le task aperte con deadline entro la giornata.",
    },
    {
        key: "tomorrow",
        label: "Domani",
        count: deadlineBuckets.value.tomorrow.length,
        emptyTitle: "Nessuna deadline per domani",
        emptyText: "Qui vedi in anticipo cosa scade domani.",
    },
]);

const activeTab = computed(
    () =>
        tabs.value.find((tab) => tab.key === activeDeadlineTab.value) ??
        tabs.value[0],
);

const activeItems = computed(
    () => deadlineBuckets.value[activeDeadlineTab.value] ?? [],
);
</script>

<template>
    <section class="panel-section surface deadlines-panel">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Scadenze</p>
                <h2>Deadline aperte</h2>
            </div>
        </div>

        <div class="filter-pill-group deadline-tabs" aria-label="Filtro deadline">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="filter-pill"
                :class="{ active: activeDeadlineTab === tab.key }"
                @click="activeDeadlineTab = tab.key"
            >
                {{ tab.label }}
                <span>{{ tab.count }}</span>
            </button>
        </div>

        <div class="deadline-list">
            <article
                v-for="item in activeItems"
                :key="item.task.id"
                class="deadline-item"
            >
                <button class="task-main" @click="openTask(item.task)">
                    <span
                        class="project-dot"
                        :style="{ background: item.project.color }"
                    ></span>
                    <span>
                        <strong>{{ item.task.title }}</strong>
                        <small>
                            {{ item.project.name }} · priorita task
                            {{ item.task.priority }}/5 · progetto
                            {{ item.project.priority }}/5
                        </small>
                    </span>
                </button>

                <div class="deadline-meta">
                    <span
                        v-for="deadline in item.deadlines"
                        :key="deadline.type + deadline.date"
                        class="deadline-chip"
                    >
                        {{ deadline.label }} {{ formatDate(deadline.date) }}
                    </span>
                    <span v-if="item.task.is_max_priority" class="chip alert-chip">
                        Massima
                    </span>
                </div>

                <div class="deadline-schedule">
                    <template v-if="taskSchedule(item.task)">
                        <span>In calendario</span>
                        <strong>{{ taskSchedule(item.task).label }}</strong>
                    </template>
                    <template v-else>
                        <span>Scheduling</span>
                        <strong>Fuori piano</strong>
                    </template>
                </div>
            </article>

            <div v-if="activeItems.length === 0" class="empty-state">
                <strong>{{ activeTab.emptyTitle }}</strong>
                <span>{{ activeTab.emptyText }}</span>
            </div>
        </div>
    </section>
</template>
