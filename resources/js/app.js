import {
    createApp,
    computed,
    onMounted,
    ref,
} from "vue/dist/vue.esm-bundler.js";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";

const csrf = document.querySelector('meta[name="csrf-token"]').content;
const weekdays = [
    ["Lunedi", 1],
    ["Martedi", 2],
    ["Mercoledi", 3],
    ["Giovedi", 4],
    ["Venerdi", 5],
    ["Sabato", 6],
    ["Domenica", 7],
];

function blankTask(projects = []) {
    return {
        id: null,
        project_id: projects[0]?.id ?? "",
        title: "",
        description: "",
        duration_minutes: 60,
        priority: 3,
        deadline: "",
        is_max_priority: false,
        status: "open",
    };
}

function blankProject() {
    return { id: null, name: "", color: "#6750a4", priority: 3, deadline: "" };
}

function today() {
    return new Date().toISOString().slice(0, 10);
}

function toDateTimeLocal(date = new Date()) {
    const copy = new Date(date);
    copy.setMinutes(copy.getMinutes() - copy.getTimezoneOffset());

    return copy.toISOString().slice(0, 16);
}

createApp({
    components: { FullCalendar },
    setup() {
        const loading = ref(true);
        const saving = ref(false);
        const error = ref("");
        const data = ref({
            user: null,
            projects: [],
            tasks: [],
            workSchedules: [],
            dateOverrides: [],
            busyBlocks: [],
            events: [],
            unscheduledTasks: [],
        });
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
            eventClick(info) {
                openCalendarEvent(info.event);
            },
            dateClick(info) {
                activePanel.value = "day";
                overrideDate.value = info.dateStr.slice(0, 10);
                hydrateOverrideRows();
            },
            select(info) {
                busyForm.value = {
                    title: "Blocco occupato",
                    start_at: info.startStr.slice(0, 16),
                    end_at: info.endStr.slice(0, 16),
                };
                modal.value = "busy";
            },
        }));

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
                .filter((task) => task.project_id === selectedProjectId.value)
                .sort((a, b) => {
                    const deadlineA = a.deadline || "9999-12-31";
                    const deadlineB = b.deadline || "9999-12-31";

                    return (
                        Number(b.is_max_priority) -
                            Number(a.is_max_priority) ||
                        b.priority - a.priority ||
                        deadlineA.localeCompare(deadlineB) ||
                        a.title.localeCompare(b.title)
                    );
                }),
        );

        async function api(url, options = {}) {
            saving.value = true;
            error.value = "";

            try {
                const response = await fetch(url, {
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN": csrf,
                    },
                    credentials: "same-origin",
                    ...options,
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    throw new Error(
                        payload.message || "Operazione non riuscita.",
                    );
                }

                const payload = await response.json();
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
            activePanel.value = "projectDetail";
        }

        function openBusyBlock(block = null) {
            busyForm.value = block
                ? {
                      ...block,
                      start_at: block.start_at
                          ? block.start_at.slice(0, 16)
                          : toDateTimeLocal(),
                      end_at: block.end_at
                          ? block.end_at.slice(0, 16)
                          : toDateTimeLocal(
                                new Date(Date.now() + 60 * 60 * 1000),
                            ),
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

        async function saveTask() {
            const payload = {
                ...taskForm.value,
                project_id: Number(taskForm.value.project_id),
                duration_minutes: Number(taskForm.value.duration_minutes),
                priority: Number(taskForm.value.priority),
                deadline: taskForm.value.deadline || null,
                is_max_priority: Boolean(taskForm.value.is_max_priority),
            };
            const url = payload.id
                ? `/planner-api/tasks/${payload.id}`
                : "/planner-api/tasks";
            const method = payload.id ? "PUT" : "POST";

            await api(url, { method, body: JSON.stringify(payload) });
            modal.value = null;
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
            const method = payload.id ? "PUT" : "POST";

            await api(url, { method, body: JSON.stringify(payload) });
            modal.value = null;
        }

        async function saveBusyBlock() {
            const url = busyForm.value.id
                ? `/planner-api/busy-blocks/${busyForm.value.id}`
                : "/planner-api/busy-blocks";
            const method = busyForm.value.id ? "PUT" : "POST";

            await api(url, {
                method,
                body: JSON.stringify(busyForm.value),
            });
            modal.value = null;
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
                modal.value = null;
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

        function durationLabel(minutes) {
            const hours = Math.floor(minutes / 60);
            const rest = minutes % 60;

            return [hours ? `${hours}h` : "", rest ? `${rest}m` : ""]
                .filter(Boolean)
                .join(" ");
        }

        function projectFor(task) {
            return task.project || projectMap.value[task.project_id] || {};
        }

        function formatDateTime(value) {
            if (!value) {
                return "-";
            }

            return new Intl.DateTimeFormat("it-IT", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
            }).format(new Date(value));
        }

        function formatDate(value) {
            if (!value) {
                return "-";
            }

            return new Intl.DateTimeFormat("it-IT", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
            }).format(new Date(value));
        }

        function taskSchedule(task) {
            const event = [...data.value.events]
                .filter(
                    (item) =>
                        item.extendedProps?.type === "task" &&
                        item.extendedProps?.task_id === task.id,
                )
                .sort((a, b) => new Date(a.start) - new Date(b.start))[0];

            if (!event) {
                return null;
            }

            return {
                start: event.start,
                end: event.end,
                label: new Intl.DateTimeFormat("it-IT", {
                    weekday: "long",
                    day: "2-digit",
                    month: "2-digit",
                    hour: "2-digit",
                    minute: "2-digit",
                }).format(new Date(event.start)),
                range: `${new Intl.DateTimeFormat("it-IT", {
                    hour: "2-digit",
                    minute: "2-digit",
                }).format(new Date(event.start))} - ${new Intl.DateTimeFormat(
                    "it-IT",
                    {
                        hour: "2-digit",
                        minute: "2-digit",
                    },
                ).format(new Date(event.end))}`,
            };
        }

        onMounted(() => api("/planner-api/bootstrap", { method: "GET" }));

        return {
            activePanel,
            busyForm,
            calendarOptions,
            data,
            deleteSelectedEvent,
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
            openContextualTask,
            openProject,
            openProjectDetail,
            openTask,
            openTaskForProject,
            openTasks,
            overrideDate,
            overrideRows,
            projectFor,
            projectForm,
            recalculate,
            saveBusyBlock,
            saveOverride,
            saveProject,
            saveSchedules,
            saveTask,
            saving,
            scheduleDays,
            selectedCalendarEvent,
            selectedProject,
            selectedProjectTasks,
            taskForm,
            taskSchedule,
            destroy,
        };
    },
    template: `
        <div v-if="loading" class="boot-screen">
            <div class="progress"></div>
            <p>Carico il tuo calendario...</p>
        </div>

        <div v-else class="app-shell">
            <aside class="nav-rail surface">
                <div class="brand-mark">C</div>
                <button class="icon-button" :class="{ active: activePanel === 'overview' }" @click="activePanel = 'overview'" title="Dashboard">D</button>
                <button class="icon-button" :class="{ active: activePanel === 'projects' || activePanel === 'projectDetail' }" @click="activePanel = 'projects'" title="Progetti">P</button>
                <button class="icon-button" :class="{ active: activePanel === 'settings' }" @click="activePanel = 'settings'" title="Impostazioni">I</button>
            </aside>

            <main class="main-area">
                <header class="top-app-bar surface">
                    <div>
                        <p class="eyebrow">Planner personale</p>
                        <h1>Calendario intelligente</h1>
                    </div>
                    <div class="top-actions">
                        <button class="button tonal" @click="recalculate" :disabled="saving">Ricalcola</button>
                        <form method="post" action="/logout">
                            <input type="hidden" name="_token" :value="'${csrf}'">
                            <button class="button text" type="submit">Esci</button>
                        </form>
                    </div>
                </header>

                <div v-if="error" class="snackbar">{{ error }}</div>

                <section v-if="activePanel === 'projectDetail' && selectedProject" class="project-detail-page surface">
                    <div class="project-detail-header">
                        <button class="button tonal" @click="activePanel = 'projects'">Indietro</button>
                        <div>
                            <p class="eyebrow">Progetto</p>
                            <h2>{{ selectedProject.name }}</h2>
                            <small>Priorita {{ selectedProject.priority }}<span v-if="selectedProject.deadline"> · deadline {{ formatDate(selectedProject.deadline) }}</span></small>
                        </div>
                        <div class="project-detail-actions">
                            <span class="project-dot detail-dot" :style="{ background: selectedProject.color }"></span>
                            <button class="button filled" @click="openTaskForProject(selectedProject)">Nuova task</button>
                        </div>
                    </div>

                    <div class="project-task-list">
                        <article v-for="task in selectedProjectTasks" :key="task.id" class="project-task-row">
                            <div class="project-task-main">
                                <div class="project-task-title">
                                    <strong>{{ task.title }}</strong>
                                    <span v-if="task.is_max_priority" class="chip alert-chip">Massima</span>
                                    <span class="chip">{{ task.status === 'done' ? 'Completata' : 'Aperta' }}</span>
                                </div>
                                <small>{{ durationLabel(task.duration_minutes) }} · priorita {{ task.priority }}/5<span v-if="task.deadline"> · deadline {{ formatDate(task.deadline) }}</span></small>
                                <p v-if="task.description">{{ task.description }}</p>
                            </div>
                            <div class="project-task-schedule">
                                <template v-if="taskSchedule(task)">
                                    <span>In lavorazione</span>
                                    <strong>{{ taskSchedule(task).label }}</strong>
                                    <small>{{ taskSchedule(task).range }}</small>
                                </template>
                                <template v-else>
                                    <span>Scheduling</span>
                                    <strong>Fuori piano</strong>
                                    <small>Nessuno slot continuo disponibile</small>
                                </template>
                            </div>
                            <button class="button text" @click="openTask(task)">Modifica</button>
                        </article>
                    </div>
                </section>

                <section v-else class="dashboard-grid">
                    <div class="calendar-panel surface">
                        <FullCalendar :options="calendarOptions" />
                    </div>

                    <aside class="side-panel">
                        <section v-if="activePanel === 'overview'" class="panel-section surface">
                            <div class="section-heading">
                                <div>
                                    <p class="eyebrow">Focus</p>
                                    <h2>Task aperte</h2>
                                </div>
                                <button class="button tonal" @click="openTask()">Nuova</button>
                            </div>

                            <div class="metrics">
                                <div class="metric"><strong>{{ openTasks.length }}</strong><span>Aperte</span></div>
                                <div class="metric"><strong>{{ maxPriorityTasks.length }}</strong><span>Massime</span></div>
                                <div class="metric"><strong>{{ data.unscheduledTasks.length }}</strong><span>Fuori piano</span></div>
                            </div>

                            <div class="task-list">
                                <article v-for="task in openTasks" :key="task.id" class="task-item">
                                    <button class="task-main" @click="openTask(task)">
                                        <span class="project-dot" :style="{ background: projectFor(task).color }"></span>
                                        <span>
                                            <strong>{{ task.title }}</strong>
                                            <small>{{ projectFor(task).name }} · P{{ task.priority }} · {{ durationLabel(task.duration_minutes) }}</small>
                                        </span>
                                    </button>
                                    <span v-if="task.is_max_priority" class="chip alert-chip">Massima</span>
                                </article>
                            </div>
                        </section>

                        <section v-if="activePanel === 'projects'" class="panel-section surface">
                            <div class="section-heading">
                                <div>
                                    <p class="eyebrow">Portfolio</p>
                                    <h2>Progetti</h2>
                                </div>
                                <button class="button tonal" @click="openProject()">Nuovo</button>
                            </div>
                            <article v-for="project in data.projects" :key="project.id" class="project-card">
                                <div class="project-line">
                                    <span class="project-dot large" :style="{ background: project.color }"></span>
                                    <div>
                                        <button class="project-name-button" @click="openProjectDetail(project)">{{ project.name }}</button>
                                        <small>Priorita {{ project.priority }}<span v-if="project.deadline"> · deadline {{ project.deadline }}</span></small>
                                    </div>
                                </div>
                                <div class="row-actions">
                                    <button class="button text" @click="openProject(project)">Modifica</button>
                                    <button class="button text danger" @click="destroy('/planner-api/projects/' + project.id)">Elimina</button>
                                </div>
                            </article>
                        </section>

                        <section v-if="activePanel === 'settings'" class="panel-section surface">
                            <div class="section-heading">
                                <div>
                                    <p class="eyebrow">Routine</p>
                                    <h2>Fasce default</h2>
                                </div>
                                <button class="button tonal" @click="saveSchedules">Salva</button>
                            </div>
                            <div class="schedule-list">
                                <label v-for="day in scheduleDays" :key="day.weekday" class="schedule-row">
                                    <input type="checkbox" v-model="day.enabled">
                                    <span>{{ day.label }}</span>
                                    <input type="time" v-model="day.start_time" :disabled="!day.enabled">
                                    <input type="time" v-model="day.end_time" :disabled="!day.enabled">
                                </label>
                            </div>
                        </section>

                        <section v-if="activePanel === 'day'" class="panel-section surface">
                            <div class="section-heading">
                                <div>
                                    <p class="eyebrow">Giorno specifico</p>
                                    <h2>{{ overrideDate }}</h2>
                                </div>
                                <button class="button tonal" @click="saveOverride">Applica</button>
                            </div>
                            <label class="field compact">
                                <span>Data</span>
                                <input type="date" v-model="overrideDate">
                            </label>
                            <div v-for="(row, index) in overrideRows" :key="index" class="time-pair">
                                <input type="time" v-model="row.start_time">
                                <input type="time" v-model="row.end_time">
                            </div>
                            <button class="button text" @click="overrideRows.push({ start_time: '09:00', end_time: '18:00' })">Aggiungi fascia</button>
                            <button class="button tonal" @click="modal = 'busy'">Blocca orario</button>
                        </section>
                    </aside>
                </section>

                <button class="fab" @click="openContextualTask" title="Nuova task">+</button>
            </main>

            <div v-if="modal" class="dialog-backdrop">
                <section v-if="modal === 'eventDetails' && selectedCalendarEvent" class="dialog surface">
                    <div class="dialog-heading">
                        <div>
                            <p class="eyebrow">{{ selectedCalendarEvent.type === 'task' ? 'Task schedulata' : 'Blocco occupato' }}</p>
                            <h2>{{ selectedCalendarEvent.title }}</h2>
                        </div>
                        <button class="icon-button" type="button" @click="modal = null">X</button>
                    </div>

                    <div class="detail-list">
                        <div>
                            <span>Inizio</span>
                            <strong>{{ formatDateTime(selectedCalendarEvent.start) }}</strong>
                        </div>
                        <div>
                            <span>Fine</span>
                            <strong>{{ formatDateTime(selectedCalendarEvent.end) }}</strong>
                        </div>
                        <template v-if="selectedCalendarEvent.type === 'task' && selectedCalendarEvent.task">
                            <div>
                                <span>Progetto</span>
                                <strong>{{ projectFor(selectedCalendarEvent.task).name }}</strong>
                            </div>
                            <div>
                                <span>Durata totale</span>
                                <strong>{{ durationLabel(selectedCalendarEvent.task.duration_minutes) }}</strong>
                            </div>
                            <div>
                                <span>Priorita task</span>
                                <strong>{{ selectedCalendarEvent.task.priority }}/5</strong>
                            </div>
                            <div>
                                <span>Deadline</span>
                                <strong>{{ selectedCalendarEvent.task.deadline || 'Nessuna' }}</strong>
                            </div>
                            <div>
                                <span>Stato</span>
                                <strong>{{ selectedCalendarEvent.task.status === 'done' ? 'Completata' : 'Aperta' }}</strong>
                            </div>
                            <div>
                                <span>Priorita massima</span>
                                <strong>{{ selectedCalendarEvent.task.is_max_priority ? 'Si' : 'No' }}</strong>
                            </div>
                            <div v-if="selectedCalendarEvent.task.description" class="detail-wide">
                                <span>Descrizione</span>
                                <strong>{{ selectedCalendarEvent.task.description }}</strong>
                            </div>
                        </template>
                    </div>

                    <div class="dialog-actions">
                        <button class="button text danger" type="button" @click="deleteSelectedEvent">Elimina</button>
                        <button class="button text" type="button" @click="modal = null">Annulla</button>
                        <button class="button filled" type="button" @click="editSelectedEvent">Modifica</button>
                    </div>
                </section>

                <form v-if="modal === 'task'" class="dialog surface" @submit.prevent="saveTask">
                    <div class="dialog-heading">
                        <h2>{{ taskForm.id ? 'Modifica task' : 'Nuova task' }}</h2>
                        <button class="icon-button" type="button" @click="modal = null">X</button>
                    </div>
                    <label class="field"><span>Titolo</span><input v-model="taskForm.title" required></label>
                    <label class="field"><span>Descrizione</span><textarea v-model="taskForm.description" rows="3"></textarea></label>
                    <label class="field"><span>Progetto</span><select v-model="taskForm.project_id" required><option v-for="project in data.projects" :value="project.id">{{ project.name }}</option></select></label>
                    <div class="form-grid">
                        <label class="field"><span>Durata minuti</span><input type="number" min="15" step="15" v-model="taskForm.duration_minutes" required></label>
                        <label class="field"><span>Priorita task</span><input type="number" min="1" max="5" v-model="taskForm.priority" required></label>
                    </div>
                    <label class="field"><span>Deadline opzionale</span><input type="date" v-model="taskForm.deadline"></label>
                    <label class="check-row"><input type="checkbox" v-model="taskForm.is_max_priority"><span>Priorita massima</span></label>
                    <label class="field"><span>Stato</span><select v-model="taskForm.status"><option value="open">Aperta</option><option value="done">Completata</option></select></label>
                    <div class="dialog-actions">
                        <button v-if="taskForm.id" class="button text danger" type="button" @click="destroy('/planner-api/tasks/' + taskForm.id); modal = null">Elimina</button>
                        <button class="button text" type="button" @click="modal = null">Annulla</button>
                        <button class="button filled" type="submit" :disabled="saving">Salva</button>
                    </div>
                </form>

                <form v-if="modal === 'project'" class="dialog surface" @submit.prevent="saveProject">
                    <div class="dialog-heading">
                        <h2>{{ projectForm.id ? 'Modifica progetto' : 'Nuovo progetto' }}</h2>
                        <button class="icon-button" type="button" @click="modal = null">X</button>
                    </div>
                    <label class="field"><span>Nome</span><input v-model="projectForm.name" required></label>
                    <div class="form-grid">
                        <label class="field"><span>Colore</span><input type="color" v-model="projectForm.color"></label>
                        <label class="field"><span>Priorita progetto</span><input type="number" min="1" max="5" v-model="projectForm.priority" required></label>
                    </div>
                    <label class="field"><span>Deadline opzionale</span><input type="date" v-model="projectForm.deadline"></label>
                    <div class="dialog-actions">
                        <button class="button text" type="button" @click="modal = null">Annulla</button>
                        <button class="button filled" type="submit" :disabled="saving">Salva</button>
                    </div>
                </form>

                <form v-if="modal === 'busy'" class="dialog surface" @submit.prevent="saveBusyBlock">
                    <div class="dialog-heading">
                        <h2>{{ busyForm.id ? 'Modifica blocco occupato' : 'Blocco occupato' }}</h2>
                        <button class="icon-button" type="button" @click="modal = null">X</button>
                    </div>
                    <label class="field"><span>Titolo</span><input v-model="busyForm.title" required></label>
                    <label class="field"><span>Inizio</span><input type="datetime-local" v-model="busyForm.start_at" required></label>
                    <label class="field"><span>Fine</span><input type="datetime-local" v-model="busyForm.end_at" required></label>
                    <div class="dialog-actions">
                        <button v-if="busyForm.id" class="button text danger" type="button" @click="destroy('/planner-api/busy-blocks/' + busyForm.id); modal = null">Elimina</button>
                        <button class="button text" type="button" @click="modal = null">Annulla</button>
                        <button class="button filled" type="submit" :disabled="saving">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    `,
}).mount("#app");
