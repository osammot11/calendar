const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

export function getCsrfToken() {
    return csrfToken;
}

export async function plannerRequest(url, options = {}) {
    const response = await fetch(url, {
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": csrfToken,
        },
        credentials: "same-origin",
        ...options,
    });

    if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload.message || "Operazione non riuscita.");
    }

    return response.json();
}
