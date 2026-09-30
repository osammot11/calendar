import assert from "node:assert/strict";
import test from "node:test";
import { ref } from "vue";
import { useAnalytics } from "../../resources/js/composables/useAnalytics.js";

test("analytics groups completed work by scheduled day, not confirmation day", () => {
    const data = ref({
        projects: [{ id: 1, name: "Cliente", color: "#006a6a" }],
        tasks: [{
            id: 1,
            project_id: 1,
            duration_minutes: 90,
            status: "done",
            worked_at: "2026-06-22T09:00:00+02:00",
            completed_at: "2026-06-24T15:00:00+02:00",
        }],
    });
    const analytics = useAnalytics(data);
    analytics.analyticsPeriod.value = "day";
    analytics.analyticsAnchor.value = new Date("2026-06-22T12:00:00+02:00");

    assert.equal(analytics.analyticsTotalMinutes.value, 90);
    assert.equal(analytics.analyticsRows.value[0].taskCount, 1);

    analytics.shiftAnalyticsPeriod(2);
    assert.equal(analytics.analyticsTotalMinutes.value, 0);
});
