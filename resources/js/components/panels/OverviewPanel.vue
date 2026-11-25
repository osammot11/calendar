<script setup>
import { computed, onBeforeUnmount, ref } from "vue";
import { Plus, CircleCheck } from "@lucide/vue";
import { usePlannerContext } from "../../composables/plannerContext";

const {
    data,
    durationLabel,
    maxPriorityTasks,
    openCalendarEvent,
    openTask,
    openTasks,
    overviewTasks,
    projectFor,
} = usePlannerContext();

const now = ref(new Date());
const clock = setInterval(() => (now.value = new Date()), 60_000);
onBeforeUnmount(() => clearInterval(clock));

const sameDay = (a, b) => a.toDateString() === b.toDateString();
const timeLabel = (date) => date.toLocaleTimeString("it-IT", { hour: "2-digit", minute: "2-digit" });

const todayLabel = computed(() =>
    now.value.toLocaleDateString("it-IT", { weekday: "long", day: "numeric", month: "long" }),
);

const todayEvents = computed(() =>
    data.value.events
        .map((event) => ({ ...event, startDate: new Date(event.start), endDate: new Date(event.end) }))
        .filter((event) => sameDay(event.startDate, now.value))
        .sort((a, b) => a.startDate - b.startDate),
);

const focusEvent = computed(() =>
    todayEvents.value.find(
        (event) => event.endDate > now.value && event.extendedProps?.status !== "done",
    ),
);

const focusLabel = computed(() => {
    const event = focusEvent.value;
    if (!event) return "";
    if (event.startDate <= now.value) return `In corso · fino alle ${timeLabel(event.endDate)}`;
    const minutes = Math.round((event.startDate - now.value) / 60_000);
    const when = minutes < 60 ? `Tra ${minutes} min` : `Alle ${timeLabel(event.startDate)}`;
    return minutes < 60 ? `${when} · ${timeLabel(event.startDate)}` : when;
});

const restOfDay = computed(() => todayEvents.value.filter((event) => event !== focusEvent.value));

const filter = ref("all");
const unscheduledIds = computed(() => new Set(data.value.unscheduledTasks.map((task) => task.id)));
const filters = computed(() => [
    { key: "all", label: "Tutte", count: openTasks.value.length },
    { key: "urgent", label: "Urgenti", count: maxPriorityTasks.value.length },
    { key: "unscheduled", label: "Fuori piano", count: data.value.unscheduledTasks.length },
]);
const visibleTasks = computed(() => {
    if (filter.value === "urgent") return overviewTasks.value.filter((task) => task.is_max_priority);
    if (filter.value === "unscheduled") return overviewTasks.value.filter((task) => unscheduledIds.value.has(task.id));
    return overviewTasks.value;
});

function nextSlotLabel(task) {
    const next = data.value.events
        .filter((event) => event.extendedProps?.type === "task" && event.extendedProps.task_id === task.id)
        .map((event) => new Date(event.start))
        .filter((start) => start >= now.value || sameDay(start, now.value))
        .sort((a, b) => a - b)[0];
    if (!next) return unscheduledIds.value.has(task.id) ? "fuori piano" : "";
    const day = sameDay(next, now.value)
        ? "oggi"
        : next.toLocaleDateString("it-IT", { weekday: "short", day: "numeric" }).replace(".", "");
    return `${day} ${timeLabel(next)}`;
}

function taskMeta(task) {
    return [
        projectFor(task).name,
        task.recurring_event_id ? "Ricorrente" : null,
        durationLabel(task.duration_minutes),
        nextSlotLabel(task),
    ]
        .filter(Boolean)
        .join(" · ");
}

function openEvent(event) {
    openCalendarEvent({ ...event, start: event.startDate, end: event.endDate, extendedProps: event.extendedProps || {} });
}
</script>

<template>
    <div class="overview-panel">
        <section class="panel-section today-section" aria-labelledby="today-title">
            <p class="eyebrow">{{ todayLabel }}</p>
            <h2 id="today-title">Oggi</h2>

            <button v-if="focusEvent" class="today-focus" @click="openEvent(focusEvent)">
                <span class="today-focus-when">{{ focusLabel }}</span>
                <strong>{{ focusEvent.title }}</strong>
                <span class="today-focus-meta">
                    {{ timeLabel(focusEvent.startDate) }} – {{ timeLabel(focusEvent.endDate) }}<template v-if="focusEvent.extendedProps?.project"> · {{ focusEvent.extendedProps.project }}</template>
                </span>
            </button>
            <p v-else class="today-empty">Niente altro in programma per oggi.</p>

            <ol v-if="restOfDay.length" class="today-list">
                <li v-for="event in restOfDay" :key="event.id">
                    <button :class="{ past: event.endDate <= now || event.extendedProps?.status === 'done' }" @click="openEvent(event)">
                        <span class="today-list-time">{{ timeLabel(event.startDate) }}</span>
                        <span class="today-list-title">{{ event.title }}</span>
                    </button>
                </li>
            </ol>
        </section>

        <section class="panel-section" aria-labelledby="tasks-title">
            <div class="section-heading">
                <h2 id="tasks-title" class="tasks-title">Da fare <em>{{ openTasks.length }}</em></h2>
                <button class="icon-button outlined" title="Nuova task" aria-label="Nuova task" @click="openTask()"><Plus :size="18" /></button>
            </div>

            <div class="filter-pill-group task-filters" role="group" aria-label="Filtra task">
                <button
                    v-for="item in filters"
                    :key="item.key"
                    class="filter-pill"
                    :class="{ active: filter === item.key }"
                    :aria-pressed="filter === item.key"
                    @click="filter = item.key"
                >
                    {{ item.label }} <span>{{ item.count }}</span>
                </button>
            </div>

            <div class="task-list">
                <div v-if="visibleTasks.length === 0" class="empty-state">
                    <CircleCheck :size="28" :stroke-width="1.5" />
                    <strong>Tutto in ordine</strong>
                    <span>{{ filter === "all" ? "Nessuna task aperta." : "Nessuna task in questo filtro." }}</span>
                </div>
                <article v-for="task in visibleTasks" :key="task.id" class="task-item">
                    <button class="task-main" @click="openTask(task)">
                        <span class="project-dot" :style="{ background: projectFor(task).color }"></span>
                        <span>
                            <strong>{{ task.title }}</strong>
                            <small>{{ taskMeta(task) }}</small>
                        </span>
                    </button>
                    <span v-if="task.is_max_priority" class="chip alert-chip">Urgente</span>
                </article>
            </div>
        </section>
    </div>
</template>
