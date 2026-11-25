<script setup>
import { computed, ref } from "vue";
import { ChevronLeft, ChevronRight, Pin, Flag, Plus, RefreshCw, Repeat2 } from "@lucide/vue";
import FullCalendar from "@fullcalendar/vue3";
import itLocale from "@fullcalendar/core/locales/it";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";
import { usePlannerContext } from "../../composables/plannerContext";

const { data, openBusyBlockFromSelection, openCalendarEvent, openDay, openContextualTask, openRecurring, recalculate, saving } = usePlannerContext();
const calendar = ref(null);
const titleMain = ref("");
const titleSub = ref("");
const activeView = ref(window.matchMedia("(max-width: 720px)").matches ? "timeGridDay" : "timeGridWeek");
const views = [{ key: "timeGridDay", label: "Giorno" }, { key: "timeGridWeek", label: "Settimana" }, { key: "dayGridMonth", label: "Mese" }];

const capitalize = (text) => text.charAt(0).toUpperCase() + text.slice(1);
const monthName = (date, style = "long") => date.toLocaleDateString("it-IT", { month: style });

function updateTitle(view) {
    const start = view.currentStart;
    const end = new Date(view.currentEnd.getTime() - 1);
    const thisYear = new Date().getFullYear();
    const yearSuffix = start.getFullYear() !== thisYear ? ` ${start.getFullYear()}` : "";

    if (view.type === "dayGridMonth") {
        titleMain.value = capitalize(monthName(start));
        titleSub.value = String(start.getFullYear());
    } else if (view.type === "timeGridDay") {
        titleMain.value = capitalize(start.toLocaleDateString("it-IT", { weekday: "long", day: "numeric" }));
        titleSub.value = monthName(start) + yearSuffix;
    } else if (start.getMonth() === end.getMonth()) {
        titleMain.value = capitalize(monthName(start));
        titleSub.value = `${start.getDate()} – ${end.getDate()}${yearSuffix}`;
    } else {
        titleMain.value = `${capitalize(monthName(start, "short"))} – ${monthName(end, "short")}`;
        titleSub.value = `${start.getDate()} – ${end.getDate()}${yearSuffix}`;
    }
}

function changeView(view) {
    calendar.value?.getApi().changeView(view);
}

function dayHeaderContent({ date, view }) {
    const dow = date.toLocaleDateString("it-IT", { weekday: "short" }).replace(".", "");
    if (view.type === "dayGridMonth") return dow;
    return { html: `<span class="day-head-dow">${dow}</span><span class="day-head-num">${date.getDate()}</span>` };
}

function eventClasses(event) {
    const props = event.extendedProps;
    return {
        "event-busy": props.type === "busy",
        "event-pinned": props.type === "task" && props.pinned && props.status !== "done",
        "event-done": props.status === "done",
        "event-past": props.status !== "done" && event.end && event.end < new Date(),
    };
}

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: activeView.value,
    headerToolbar: false,
    locale: itLocale,
    firstDay: 1,
    nowIndicator: true,
    selectable: true,
    allDaySlot: false,
    height: "max(480px, calc(100dvh - 120px))",
    slotMinTime: "00:00:00",
    slotMaxTime: "24:00:00",
    scrollTime: "09:00:00",
    scrollTimeReset: false,
    slotLabelFormat: { hour: "2-digit", minute: "2-digit", hour12: false },
    eventTimeFormat: { hour: "2-digit", minute: "2-digit", hour12: false },
    dayHeaderContent,
    eventMinHeight: 30,
    eventShortHeight: 50,
    events: data.value.events,
    datesSet: ({ view }) => {
        updateTitle(view);
        activeView.value = view.type;
    },
    eventClick: ({ event }) => openCalendarEvent(event),
    dateClick: ({ dateStr }) => openDay(dateStr),
    select: openBusyBlockFromSelection,
}));
</script>

<template>
    <section class="calendar-panel" aria-label="Calendario">
        <div class="calendar-toolbar">
            <div class="calendar-date-navigation">
                <h1 class="calendar-title" aria-live="polite">{{ titleMain }} <em>{{ titleSub }}</em></h1>
                <div class="calendar-arrows">
                    <button class="icon-button" title="Periodo precedente" aria-label="Periodo precedente" @click="calendar.getApi().prev()"><ChevronLeft :size="20" /></button>
                    <button class="icon-button" title="Periodo successivo" aria-label="Periodo successivo" @click="calendar.getApi().next()"><ChevronRight :size="20" /></button>
                </div>
                <button class="button outlined today-button" @click="calendar.getApi().today()">Oggi</button>
            </div>
            <div class="calendar-actions">
                <div class="calendar-view-control" role="group" aria-label="Vista calendario">
                    <button v-for="view in views" :key="view.key" :class="{ active: activeView === view.key }" :aria-pressed="activeView === view.key" @click="changeView(view.key)">{{ view.label }}</button>
                </div>
                <button class="icon-button recalculate-button" :disabled="saving" title="Ricalcola pianificazione" aria-label="Ricalcola pianificazione" @click="recalculate">
                    <RefreshCw :size="18" :class="{ spinning: saving }" />
                </button>
                <button class="icon-button header-create" title="Nuovo ricorrente" aria-label="Nuovo ricorrente" @click="openRecurring()">
                    <Repeat2 :size="18" />
                </button>
                <button class="button filled header-create" @click="openContextualTask">
                    <Plus :size="17" /> Nuova task
                </button>
            </div>
        </div>
        <div class="calendar-scroll" :class="{ 'week-view': activeView === 'timeGridWeek' }">
            <FullCalendar ref="calendar" :options="calendarOptions">
                <template #eventContent="{ event, timeText }">
                    <div class="calendar-event-content" :class="eventClasses(event)" :style="{ '--event-color': event.backgroundColor || '#6a6155' }" :title="[timeText, event.title, event.extendedProps.project].filter(Boolean).join(' · ')">
                        <span class="calendar-event-time">
                            <Pin v-if="event.extendedProps.pinned" :size="11" aria-label="Fissato" />
                            <Flag v-else-if="event.extendedProps.max" :size="11" aria-label="Priorità massima" />
                            <span class="calendar-event-hours">{{ timeText }}</span>
                        </span>
                        <strong>{{ event.title }}</strong>
                        <span v-if="event.extendedProps.project" class="calendar-event-project">{{ event.extendedProps.project }}</span>
                    </div>
                </template>
            </FullCalendar>
        </div>
    </section>
</template>
