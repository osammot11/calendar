export function durationLabel(minutes) {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return [hours ? `${hours}h` : "", rest ? `${rest}m` : ""]
        .filter(Boolean)
        .join(" ");
}

export function formatDateTime(value) {
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

export function formatDate(value) {
    if (!value) {
        return "-";
    }

    return new Intl.DateTimeFormat("it-IT", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
    }).format(new Date(value));
}

export function scheduleLabel(event) {
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
        }).format(new Date(event.start))} - ${new Intl.DateTimeFormat("it-IT", {
            hour: "2-digit",
            minute: "2-digit",
        }).format(new Date(event.end))}`,
    };
}
