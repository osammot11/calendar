<script setup>
import { CalendarDays, CalendarClock, ChartBar, FolderKanban, History, Settings2 } from "@lucide/vue";
import { usePlannerContext } from "../../composables/plannerContext";

const { activePanel, data } = usePlannerContext();
const items = [
    { key: "overview", label: "Calendario", icon: CalendarDays, panels: ["overview", "day"] },
    { key: "deadlines", label: "Scadenze", icon: CalendarClock, panels: ["deadlines"] },
    { key: "projects", label: "Progetti", icon: FolderKanban, panels: ["projects", "projectDetail"] },
    { key: "analytics", label: "Analytics", icon: ChartBar, panels: ["analytics"] },
    { key: "pastEvents", label: "Passati", icon: History, panels: ["pastEvents"] },
    { key: "settings", label: "Impostazioni", icon: Settings2, panels: ["settings"] },
];

function navigate(panel) {
    activePanel.value = panel;
    window.scrollTo({ top: 0, behavior: "instant" });
}
</script>

<template>
    <aside class="nav-rail">
        <a class="brand-mark" href="/planner" aria-label="Planner, calendario" title="Planner">
            <CalendarDays :size="25" :stroke-width="1.8" />
        </a>
        <nav class="nav-items" aria-label="Navigazione principale">
            <button
                v-for="item in items"
                :key="item.key"
                class="nav-item"
                :class="{ active: item.panels.includes(activePanel) }"
                :aria-current="item.panels.includes(activePanel) ? 'page' : undefined"
                :title="item.label"
                @click="navigate(item.key)"
            >
                <span class="nav-icon"><component :is="item.icon" :size="21" :stroke-width="1.8" /></span>
                <span>{{ item.label }}</span>
            </button>
        </nav>
        <span class="user-avatar" :title="data.user?.name" :aria-label="data.user?.name">
            {{ data.user?.name?.charAt(0).toUpperCase() || 'P' }}
        </span>
    </aside>
</template>
