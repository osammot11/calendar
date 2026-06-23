<script setup>
import { usePlannerContext } from "../composables/plannerContext";
import PlannerCalendar from "../components/calendar/PlannerCalendar.vue";
import DayPanel from "../components/panels/DayPanel.vue";
import OverviewPanel from "../components/panels/OverviewPanel.vue";
import ProjectsPanel from "../components/panels/ProjectsPanel.vue";
import SettingsPanel from "../components/panels/SettingsPanel.vue";

const { activePanel } = usePlannerContext();
</script>

<template>
    <section class="dashboard-grid">
        <PlannerCalendar />

        <aside class="side-panel">
            <OverviewPanel v-if="activePanel === 'overview'" />
            <ProjectsPanel v-if="activePanel === 'projects'" />
            <SettingsPanel v-if="activePanel === 'settings'" />
            <DayPanel v-if="activePanel === 'day'" />
        </aside>
    </section>
</template>
