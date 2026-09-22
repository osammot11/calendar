import { computed, ref } from "vue";

function startOfDay(value) {
    const date = new Date(value);
    date.setHours(0, 0, 0, 0);
    return date;
}

function periodBounds(anchor, period) {
    const start = startOfDay(anchor);

    if (period === "week") {
        start.setDate(start.getDate() - ((start.getDay() + 6) % 7));
    } else if (period === "month") {
        start.setDate(1);
    }

    const end = new Date(start);
    if (period === "day") {
        end.setDate(end.getDate() + 1);
    } else if (period === "week") {
        end.setDate(end.getDate() + 7);
    } else {
        end.setMonth(end.getMonth() + 1);
    }

    return { start, end };
}

function formatPeriodLabel(start, end, period) {
    if (period === "day") {
        return new Intl.DateTimeFormat("it-IT", {
            weekday: "long",
            day: "numeric",
            month: "long",
            year: "numeric",
        }).format(start);
    }

    if (period === "month") {
        return new Intl.DateTimeFormat("it-IT", {
            month: "long",
            year: "numeric",
        }).format(start);
    }

    const lastDay = new Date(end);
    lastDay.setDate(lastDay.getDate() - 1);
    const formatter = new Intl.DateTimeFormat("it-IT", {
        day: "numeric",
        month: "short",
    });

    return `${formatter.format(start)} – ${formatter.format(lastDay)} ${lastDay.getFullYear()}`;
}

export function useAnalytics(data) {
    const analyticsPeriod = ref("week");
    const analyticsAnchor = ref(new Date());

    const analyticsBounds = computed(() =>
        periodBounds(analyticsAnchor.value, analyticsPeriod.value),
    );

    const analyticsPeriodLabel = computed(() =>
        formatPeriodLabel(
            analyticsBounds.value.start,
            analyticsBounds.value.end,
            analyticsPeriod.value,
        ),
    );

    const analyticsTasks = computed(() => {
        const { start, end } = analyticsBounds.value;

        return data.value.tasks.filter((task) => {
            if (task.status !== "done" || !task.completed_at) {
                return false;
            }

            const completedAt = new Date(task.completed_at);
            return completedAt >= start && completedAt < end;
        });
    });

    const analyticsRows = computed(() => {
        const tasksByProject = new Map();
        analyticsTasks.value.forEach((task) => {
            const tasks = tasksByProject.get(task.project_id) ?? [];
            tasks.push(task);
            tasksByProject.set(task.project_id, tasks);
        });

        const rows = data.value.projects.map((project) => {
            const tasks = tasksByProject.get(project.id) ?? [];
            return {
                project,
                tasks,
                taskCount: tasks.length,
                minutes: tasks.reduce(
                    (total, task) => total + Number(task.duration_minutes || 0),
                    0,
                ),
            };
        });
        const maximum = Math.max(...rows.map((row) => row.minutes), 1);

        return rows
            .map((row) => ({
                ...row,
                percentage: (row.minutes / maximum) * 100,
            }))
            .sort(
                (a, b) =>
                    b.minutes - a.minutes ||
                    a.project.name.localeCompare(b.project.name),
            );
    });

    const analyticsTotalMinutes = computed(() =>
        analyticsTasks.value.reduce(
            (total, task) => total + Number(task.duration_minutes || 0),
            0,
        ),
    );

    const analyticsActiveProjects = computed(
        () => analyticsRows.value.filter((row) => row.minutes > 0).length,
    );

    function shiftAnalyticsPeriod(direction) {
        const next = new Date(analyticsAnchor.value);
        if (analyticsPeriod.value === "day") {
            next.setDate(next.getDate() + direction);
        } else if (analyticsPeriod.value === "week") {
            next.setDate(next.getDate() + 7 * direction);
        } else {
            next.setDate(1);
            next.setMonth(next.getMonth() + direction);
        }
        analyticsAnchor.value = next;
    }

    function resetAnalyticsPeriod() {
        analyticsAnchor.value = new Date();
    }

    return {
        analyticsActiveProjects,
        analyticsAnchor,
        analyticsPeriod,
        analyticsPeriodLabel,
        analyticsRows,
        analyticsTasks,
        analyticsTotalMinutes,
        resetAnalyticsPeriod,
        shiftAnalyticsPeriod,
    };
}
