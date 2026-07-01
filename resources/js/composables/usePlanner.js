import { computed, onMounted, ref } from "vue";
import { getCsrfToken, plannerRequest } from "../api/plannerApi";
import {
    blankProject,
    blankTask,
    toDateTimeInput,
    toDateTimeLocal,
    today,
    weekdays,
} from "../utils/forms";
import {
    durationLabel,
    formatDate,
    formatDateTime,
    scheduleLabel,
} from "../utils/formatters";

const initialData = () => ({
    user: null,
    projects: [],
    tasks: [],
    workSchedules: [],
    dateOverrides: [],
    busyBlocks: [],
    events: [],
    pastEvents: [],
    unscheduledTasks: [],
});

export function usePlanner() {
    const loading = ref(true);
    const saving = ref(false);
    const error = ref("");
    const data = ref(initialData());
    const activePanel = ref("overview");
    const modal = ref(null);
    const taskForm = ref(blankTask());
    const projectForm = ref(blankProject());
    const busyForm = ref({
        title: "",
        start_at: toDateTimeLocal(),
        end_at: toDateTimeLocal(new Date(Date.now() + 60 * 60 * 1000)),
    });
    const overrideDate = ref(today());
    const overrideRows = ref([{ start_time: "09:00", end_time: "18:00" }]);
    const scheduleDays = ref([]);
    const selectedCalendarEvent = ref(null);
    const selectedProjectId = ref(null);
    const projectTaskFilter = ref("open");
    const csrfToken = getCsrfToken();

    const openTasks = computed(() =>
        data.value.tasks.filter((task) => task.status === "open"),
    );
    const doneTasks = computed(() =>
        data.value.tasks.filter((task) => task.status === "done"),
    );
    const maxPriorityTasks = computed(() =>
        openTasks.value.filter((task) => task.is_max_priority),
    );
    const projectMap = computed(() =>
        Object.fromEntries(
            data.value.projects.map((project) => [project.id, project]),
        ),
    );
    const selectedProject = computed(() =>
        data.value.projects.find(
            (project) => project.id === selectedProjectId.value,
        ),
    );
    const selectedProjectTasks = computed(() =>
        [...data.value.tasks]
            .filter(
                (task) =>
                    task.project_id === selectedProjectId.value &&
                    task.status === projectTaskFilter.value,
            )
            .sort((a, b) => {
                const deadlineA = a.deadline || "9999-12-31";
                const deadlineB = b.deadline || "9999-12-31";

                return (
                    Number(b.is_max_priority) - Number(a.is_max_priority) ||
                    b.priority - a.priority ||
                    deadlineA.localeCompare(deadlineB) ||
                    a.title.localeCompare(b.title)
                );
            }),
    );
    const selectedProjectTaskCounts = computed(() => {
        const projectTasks = data.value.tasks.filter(
            (task) => task.project_id === selectedProjectId.value,
        );

        return {
            open: projectTasks.filter((task) => task.status === "open").length,
            done: projectTasks.filter((task) => task.status === "done").length,
        };
    });

    async function api(url, options = {}) {
        saving.value = true;
        error.value = "";

        try {
            const payload = await plannerRequest(url, options);
            data.value = payload;
            hydrateScheduleDays();
            hydrateOverrideRows();

            return payload;
        } catch (exception) {
            error.value = exception.message;
            throw exception;
        } finally {
            loading.value = false;
            saving.value = false;
        }
    }

    function hydrateScheduleDays() {
        scheduleDays.value = weekdays.map(([label, weekday]) => {
            const row = data.value.workSchedules.find(
                (item) => item.weekday === weekday,
            );

            return {
                label,
                weekday,
                enabled: Boolean(row),
                start_time: row?.start_time?.slice(0, 5) || "09:00",
                end_time: row?.end_time?.slice(0, 5) || "18:00",
            };
        });
    }

    function hydrateOverrideRows() {
        const rows = data.value.dateOverrides
            .filter((row) => row.date?.slice(0, 10) === overrideDate.value)
            .map((row) => ({
                start_time: row.start_time.slice(0, 5),
                end_time: row.end_time.slice(0, 5),
            }));

        overrideRows.value = rows.length
            ? rows
            : [{ start_time: "09:00", end_time: "18:00" }];
    }

    function openTask(task = null) {
        taskForm.value = task
            ? {
                  ...task,
                  deadline: task.deadline ? task.deadline.slice(0, 10) : "",
                  is_max_priority: Boolean(task.is_max_priority),
                  is_pinned: Boolean(task.is_pinned),
                  pinned_start_at: toDateTimeInput(task.pinned_start_at),
              }
            : blankTask(data.value.projects);
        modal.value = "task";
    }

    function openTaskForProject(project) {
        taskForm.value = {
            ...blankTask(data.value.projects),
            project_id: project.id,
        };
        modal.value = "task";
    }

    function openContextualTask() {
        if (activePanel.value === "projectDetail" && selectedProject.value) {
            openTaskForProject(selectedProject.value);
            return;
        }

        openTask();
    }

    function openProject(project = null) {
        projectForm.value = project
            ? {
                  ...project,
                  deadline: project.deadline
                      ? project.deadline.slice(0, 10)
                      : "",
              }
            : blankProject();
        modal.value = "project";
    }

    function openProjectDetail(project) {
        selectedProjectId.value = project.id;
        projectTaskFilter.value = "open";
        activePanel.value = "projectDetail";
    }

    function openBusyBlock(block = null) {
        busyForm.value = block
            ? {
                  ...block,
                  start_at: toDateTimeInput(block.start_at) || toDateTimeLocal(),
                  end_at:
                      toDateTimeInput(block.end_at) ||
                      toDateTimeLocal(new Date(Date.now() + 60 * 60 * 1000)),
              }
            : {
                  title: "",
                  start_at: toDateTimeLocal(),
                  end_at: toDateTimeLocal(
                      new Date(Date.now() + 60 * 60 * 1000),
                  ),
              };
        modal.value = "busy";
    }

    function openBusyBlockFromSelection(info) {
        busyForm.value = {
            title: "Blocco occupato",
            start_at: info.startStr.slice(0, 16),
            end_at: info.endStr.slice(0, 16),
        };
        modal.value = "busy";
    }

    function openCalendarEvent(event) {
        const props = event.extendedProps || {};
        const task =
            props.type === "task"
                ? data.value.tasks.find((item) => item.id === props.task_id)
                : null;
        const busy =
            props.type === "busy"
                ? data.value.busyBlocks.find(
                      (item) => item.id === props.busy_id,
                  )
                : null;

        selectedCalendarEvent.value = {
            id: event.id,
            type: props.type,
            title: event.title,
            start: event.start,
            end: event.end,
            props,
            task,
            busy,
        };
        modal.value = "eventDetails";
    }

    function openDay(dateString) {
        activePanel.value = "day";
        overrideDate.value = dateString.slice(0, 10);
        hydrateOverrideRows();
    }

    function closeModal() {
        modal.value = null;
    }

    async function saveTask() {
        const payload = {
            ...taskForm.value,
            project_id: Number(taskForm.value.project_id),
            duration_minutes: Number(taskForm.value.duration_minutes),
            priority: Number(taskForm.value.priority),
            deadline: taskForm.value.deadline || null,
            is_max_priority: Boolean(taskForm.value.is_max_priority),
            is_pinned: Boolean(taskForm.value.is_pinned),
            pinned_start_at: taskForm.value.is_pinned
                ? taskForm.value.pinned_start_at
                : null,
        };
        const url = payload.id
            ? `/planner-api/tasks/${payload.id}`
            : "/planner-api/tasks";

        await api(url, {
            method: payload.id ? "PUT" : "POST",
            body: JSON.stringify(payload),
        });
        closeModal();
    }

    async function saveProject() {
        const payload = {
            ...projectForm.value,
            priority: Number(projectForm.value.priority),
            deadline: projectForm.value.deadline || null,
        };
        const url = payload.id
            ? `/planner-api/projects/${payload.id}`
            : "/planner-api/projects";

        await api(url, {
            method: payload.id ? "PUT" : "POST",
            body: JSON.stringify(payload),
        });
        closeModal();
    }

    async function saveBusyBlock() {
        const url = busyForm.value.id
            ? `/planner-api/busy-blocks/${busyForm.value.id}`
            : "/planner-api/busy-blocks";

        await api(url, {
            method: busyForm.value.id ? "PUT" : "POST",
            body: JSON.stringify(busyForm.value),
        });
        closeModal();
    }

    async function saveSchedules() {
        await api("/planner-api/work-schedules", {
            method: "POST",
            body: JSON.stringify({ days: scheduleDays.value }),
        });
    }

    async function saveOverride() {
        await api("/planner-api/date-overrides", {
            method: "POST",
            body: JSON.stringify({
                date: overrideDate.value,
                rows: overrideRows.value,
            }),
        });
    }

    async function destroy(url) {
        if (!confirm("Confermi eliminazione?")) {
            return false;
        }

        await api(url, { method: "DELETE" });
        return true;
    }

    async function deleteTaskFromModal() {
        if (
            taskForm.value.id &&
            (await destroy(`/planner-api/tasks/${taskForm.value.id}`))
        ) {
            closeModal();
        }
    }

    async function deleteBusyBlockFromModal() {
        if (
            busyForm.value.id &&
            (await destroy(`/planner-api/busy-blocks/${busyForm.value.id}`))
        ) {
            closeModal();
        }
    }

    async function deleteSelectedEvent() {
        if (!selectedCalendarEvent.value) {
            return;
        }

        let deleted = false;
        if (
            selectedCalendarEvent.value.type === "task" &&
            selectedCalendarEvent.value.task
        ) {
            deleted = await destroy(
                `/planner-api/tasks/${selectedCalendarEvent.value.task.id}`,
            );
        }

        if (
            selectedCalendarEvent.value.type === "busy" &&
            selectedCalendarEvent.value.busy
        ) {
            deleted = await destroy(
                `/planner-api/busy-blocks/${selectedCalendarEvent.value.busy.id}`,
            );
        }

        if (deleted) {
            closeModal();
            selectedCalendarEvent.value = null;
        }
    }

    function editSelectedEvent() {
        if (!selectedCalendarEvent.value) {
            return;
        }

        if (
            selectedCalendarEvent.value.type === "task" &&
            selectedCalendarEvent.value.task
        ) {
            openTask(selectedCalendarEvent.value.task);
        }

        if (
            selectedCalendarEvent.value.type === "busy" &&
            selectedCalendarEvent.value.busy
        ) {
            openBusyBlock(selectedCalendarEvent.value.busy);
        }
    }

    async function recalculate() {
        await api("/planner-api/recalculate", {
            method: "POST",
            body: "{}",
        });
    }

    async function completePastEvent(event) {
        await api(`/planner-api/past-events/${event.id}/complete`, {
            method: "POST",
            body: "{}",
        });
    }

    async function reschedulePastEvent(event) {
        await api(`/planner-api/past-events/${event.id}/reschedule`, {
            method: "POST",
            body: "{}",
        });
    }

    function projectFor(task) {
        return task.project || projectMap.value[task.project_id] || {};
    }

    function taskSchedule(task) {
        const event = [...data.value.events]
            .filter(
                (item) =>
                    item.extendedProps?.type === "task" &&
                    item.extendedProps?.task_id === task.id,
            )
            .sort((a, b) => new Date(a.start) - new Date(b.start))[0];

        return scheduleLabel(event);
    }

    onMounted(() => api("/planner-api/bootstrap", { method: "GET" }));

    return {
        activePanel,
        busyForm,
        closeModal,
        completePastEvent,
        csrfToken,
        data,
        deleteBusyBlockFromModal,
        deleteSelectedEvent,
        deleteTaskFromModal,
        destroy,
        doneTasks,
        durationLabel,
        editSelectedEvent,
        error,
        formatDate,
        formatDateTime,
        loading,
        maxPriorityTasks,
        modal,
        openBusyBlock,
        openBusyBlockFromSelection,
        openCalendarEvent,
        openContextualTask,
        openDay,
        openProject,
        openProjectDetail,
        openTask,
        openTaskForProject,
        openTasks,
        overrideDate,
        overrideRows,
        projectFor,
        projectForm,
        projectTaskFilter,
        recalculate,
        reschedulePastEvent,
        saveBusyBlock,
        saveOverride,
        saveProject,
        saveSchedules,
        saveTask,
        saving,
        scheduleDays,
        selectedCalendarEvent,
        selectedProject,
        selectedProjectTaskCounts,
        selectedProjectTasks,
        taskForm,
        taskSchedule,
    };
}
