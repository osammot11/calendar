<script setup>
import { usePlannerContext } from "../../composables/plannerContext";
import BusyBlockModal from "./BusyBlockModal.vue";
import EventDetailsModal from "./EventDetailsModal.vue";
import ProjectModal from "./ProjectModal.vue";
import TaskModal from "./TaskModal.vue";

const { modal, selectedCalendarEvent } = usePlannerContext();
</script>

<template>
    <div v-if="modal" class="dialog-backdrop">
        <EventDetailsModal
            v-if="modal === 'eventDetails' && selectedCalendarEvent"
        />
        <TaskModal v-if="modal === 'task'" />
        <ProjectModal v-if="modal === 'project'" />
        <BusyBlockModal v-if="modal === 'busy'" />
    </div>
</template>
