import { inject, provide } from "vue";

const plannerKey = Symbol("planner");

export function providePlanner(planner) {
    provide(plannerKey, planner);
}

export function usePlannerContext() {
    const planner = inject(plannerKey);

    if (!planner) {
        throw new Error("Planner context non disponibile.");
    }

    return planner;
}
