<script setup>
import { usePlannerContext } from "../../composables/plannerContext";

const { activePanel } = usePlannerContext();
</script>

<template>
    <aside class="nav-rail surface">
        <button
            class="icon-button"
            :class="{ active: activePanel === 'overview' }"
            title="Dashboard"
            @click="activePanel = 'overview'"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="lucide lucide-calendar-days-icon lucide-calendar-days"
            >
                <path d="M8 2v4" />
                <path d="M16 2v4" />
                <rect width="18" height="18" x="3" y="4" rx="2" />
                <path d="M3 10h18" />
                <path d="M8 14h.01" />
                <path d="M12 14h.01" />
                <path d="M16 14h.01" />
                <path d="M8 18h.01" />
                <path d="M12 18h.01" />
                <path d="M16 18h.01" />
            </svg>
        </button>
        <button
            class="icon-button"
            :class="{
                active:
                    activePanel === 'projects' ||
                    activePanel === 'projectDetail',
            }"
            title="Progetti"
            @click="activePanel = 'projects'"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="lucide lucide-folder-git2-icon lucide-folder-git-2"
            >
                <path d="M18 19a5 5 0 0 1-5-5v8" />
                <path
                    d="M9 20H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H20a2 2 0 0 1 2 2v5"
                />
                <circle cx="13" cy="12" r="2" />
                <circle cx="20" cy="19" r="2" />
            </svg>
        </button>
        <button
            class="icon-button"
            :class="{ active: activePanel === 'pastEvents' }"
            title="Eventi passati"
            @click="activePanel = 'pastEvents'"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="lucide lucide-clipboard-x-icon lucide-clipboard-x"
            >
                <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
                <path
                    d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"
                />
                <path d="m15 11-6 6" />
                <path d="m9 11 6 6" />
            </svg>
        </button>
        <button
            class="icon-button"
            :class="{ active: activePanel === 'settings' }"
            title="Impostazioni"
            @click="activePanel = 'settings'"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="lucide lucide-user-round-cog-icon lucide-user-round-cog"
            >
                <path d="m14.305 19.53.923-.382" />
                <path d="m15.228 16.852-.923-.383" />
                <path d="m16.852 15.228-.383-.923" />
                <path d="m16.852 20.772-.383.924" />
                <path d="m19.148 15.228.383-.923" />
                <path d="m19.53 21.696-.382-.924" />
                <path d="M2 21a8 8 0 0 1 10.434-7.62" />
                <path d="m20.772 16.852.924-.383" />
                <path d="m20.772 19.148.924.383" />
                <circle cx="10" cy="8" r="5" />
                <circle cx="18" cy="18" r="3" />
            </svg>
        </button>
    </aside>
</template>
