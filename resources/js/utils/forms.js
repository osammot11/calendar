export const weekdays = [
    ["Lunedi", 1],
    ["Martedi", 2],
    ["Mercoledi", 3],
    ["Giovedi", 4],
    ["Venerdi", 5],
    ["Sabato", 6],
    ["Domenica", 7],
];

export function blankTask(projects = []) {
    return {
        id: null,
        project_id: projects[0]?.id ?? "",
        title: "",
        description: "",
        duration_minutes: 60,
        priority: 3,
        deadline: "",
        is_max_priority: false,
        is_pinned: false,
        pinned_start_at: "",
        status: "open",
    };
}

export function blankProject() {
    return {
        id: null,
        name: "",
        color: "#6750a4",
        priority: 3,
        deadline: "",
    };
}

export function today() {
    return new Date().toISOString().slice(0, 10);
}

export function toDateTimeLocal(date = new Date()) {
    const copy = new Date(date);
    copy.setMinutes(copy.getMinutes() - copy.getTimezoneOffset());

    return copy.toISOString().slice(0, 16);
}
