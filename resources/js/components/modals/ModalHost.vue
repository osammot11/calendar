<script setup>
import { nextTick, onBeforeUnmount, watch } from "vue";
import { usePlannerContext } from "../../composables/plannerContext";
import BusyBlockModal from "./BusyBlockModal.vue";
import EventDetailsModal from "./EventDetailsModal.vue";
import ProjectModal from "./ProjectModal.vue";
import TaskModal from "./TaskModal.vue";

const { modal, selectedCalendarEvent } = usePlannerContext();

let previousFocus;
let previousOverflow;
watch(modal, async (value, previous) => {
    if (value && !previous) {
        previousFocus = document.activeElement;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
    } else if (!value) {
        document.body.style.overflow = previousOverflow ?? "";
        previousFocus?.focus();
    }
    if (value) {
        await nextTick();
        document.querySelector(".dialog input:not([disabled]), .dialog button")?.focus();
    }
});
onBeforeUnmount(() => {
    if (previousOverflow !== undefined) document.body.style.overflow = previousOverflow;
});

function containFocus(event) {
    if (event.key !== "Tab") return;
    const elements = [...event.currentTarget.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]')];
    const first = elements[0];
    const last = elements.at(-1);
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
}
</script>

<template>
    <div v-if="modal" class="dialog-backdrop" role="dialog" aria-modal="true" aria-labelledby="dialog-title" @keydown="containFocus">
        <EventDetailsModal
            v-if="modal === 'eventDetails' && selectedCalendarEvent"
        />
        <TaskModal v-if="modal === 'task'" />
        <ProjectModal v-if="modal === 'project'" />
        <BusyBlockModal v-if="modal === 'busy'" />
    </div>
</template>
