/**
 * Birinchi tomon API chaqiruvlari (Sanctum stateful sessiya + CSRF).
 * Server javobi: { success, message, data } — xato bo‘lsa Error(message) tashlanadi.
 */
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export async function api(method, url, body = undefined, { keepalive = false } = {}) {
    const response = await fetch(url, {
        method,
        keepalive,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (response.status === 401) {
        window.location.href = '/login';
        throw new Error('Avval tizimga kiring.');
    }

    const json = await response.json().catch(() => ({}));
    if (!response.ok || json.success === false) {
        const firstError = json.errors && Object.values(json.errors)[0];
        throw new Error((Array.isArray(firstError) ? firstError[0] : null) || json.message || 'Xatolik yuz berdi. Qayta urinib ko‘ring.');
    }

    return json.data ?? json;
}

/** HTML bo‘lak yuklash (infinite scroll, izohlar). */
export async function fetchHtml(url) {
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }, credentials: 'same-origin' });
    if (!response.ok) throw new Error('Yuklab bo‘lmadi.');
    return response.text();
}

/** Forma yuborish (FormData), JSON javob. */
export async function postForm(form) {
    const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
        const firstError = json.errors && Object.values(json.errors)[0];
        throw new Error((Array.isArray(firstError) ? firstError[0] : null) || json.message || 'Xatolik yuz berdi.');
    }
    return json;
}
