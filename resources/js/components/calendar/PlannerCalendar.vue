<script setup>
import { computed, ref } from "vue";
import { ChevronLeft, ChevronRight, Pin, Flag } from "@lucide/vue";
import FullCalendar from "@fullcalendar/vue3";
import itLocale from "@fullcalendar/core/locales/it";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";
import { usePlannerContext } from "../../composables/plannerContext";

const { data, openBusyBlockFromSelection, openCalendarEvent, openDay } = usePlannerContext();
const calendar = ref(null);
const calendarTitle = ref("");
const activeView = ref(window.matchMedia("(max-width: 720px)").matches ? "timeGridDay" : "timeGridWeek");
const views = [{ key: "timeGridDay", label: "Giorno" }, { key: "timeGridWeek", label: "Settimana" }, { key: "dayGridMonth", label: "Mese" }];

function changeView(view) {
    calendar.value?.getApi().changeView(view);
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
    height: "auto",
    slotMinTime: "09:30:00",
    slotMaxTime: "23:30:00",
    slotLabelFormat: { hour: "2-digit", minute: "2-digit", hour12: false },
    eventTimeFormat: { hour: "2-digit", minute: "2-digit", hour12: false },
    dayHeaderFormat: { weekday: "short", day: "numeric" },
    views: { dayGridMonth: { dayHeaderFormat: { weekday: "short" } } },
    eventMinHeight: 30,
    eventShortHeight: 50,
    events: data.value.events,
    datesSet: ({ view }) => {
        calendarTitle.value = view.title;
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
                <h2 aria-live="polite">{{ calendarTitle }}</h2>
                <div class="calendar-arrows">
                    <button class="icon-button" title="Periodo precedente" aria-label="Periodo precedente" @click="calendar.getApi().prev()"><ChevronLeft :size="19" /></button>
                    <button class="icon-button" title="Periodo successivo" aria-label="Periodo successivo" @click="calendar.getApi().next()"><ChevronRight :size="19" /></button>
                </div>
                <button class="button outlined today-button" @click="calendar.getApi().today()">Oggi</button>
            </div>
            <div class="calendar-view-control" aria-label="Vista calendario">
                <button v-for="view in views" :key="view.key" :class="{ active: activeView === view.key }" :aria-pressed="activeView === view.key" @click="changeView(view.key)">{{ view.label }}</button>
            </div>
        </div>
        <div class="calendar-scroll" :class="{ 'week-view': activeView === 'timeGridWeek' }">
            <FullCalendar ref="calendar" :options="calendarOptions">
                <template #eventContent="{ event, timeText }">
                    <div class="calendar-event-content" :class="{ 'event-done': event.extendedProps.status === 'done' }" :style="{ '--event-color': event.backgroundColor || '#63757a' }" :title="[timeText, event.title, event.extendedProps.project].filter(Boolean).join(' · ')">
                        <span class="calendar-event-time">
                            <Pin v-if="event.extendedProps.pinned" :size="11" />
                            <Flag v-else-if="event.extendedProps.max" :size="11" />
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
