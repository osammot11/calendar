<script setup>
import { computed } from "vue";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";
import { usePlannerContext } from "../../composables/plannerContext";

const {
    data,
    openBusyBlockFromSelection,
    openCalendarEvent,
    openDay,
} = usePlannerContext();

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: "timeGridWeek",
    headerToolbar: {
        left: "prev,next today",
        center: "title",
        right: "timeGridDay,timeGridWeek,dayGridMonth",
    },
    locale: "it",
    firstDay: 1,
    nowIndicator: true,
    selectable: true,
    allDaySlot: false,
    height: "auto",
    slotMinTime: "09:30:00",
    slotMaxTime: "23:30:00",
    eventTimeFormat: {
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
    },
    events: data.value.events,
    eventClick: ({ event }) => openCalendarEvent(event),
    dateClick: ({ dateStr }) => openDay(dateStr),
    select: openBusyBlockFromSelection,
}));
</script>

<template>
    <div class="calendar-panel surface">
        <FullCalendar :options="calendarOptions" />
    </div>
</template>
