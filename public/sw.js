/*
 * Fikrlash — Service Worker. Faqat bildirishnomalar uchun: bosilganda tegishli sahifani ochadi
 * (yoki ochiq oynani shu sahifaga o‘tkazadi). Hech narsani keshlamaydi — sayt har doim yangi.
 */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/', self.location.origin).href;
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const same = windows.find((w) => w.url === target) || windows.find((w) => new URL(w.url).origin === self.location.origin);
            if (same) {
                return same.focus().then((w) => (w && w.url !== target && 'navigate' in w ? w.navigate(target) : w));
            }
            return self.clients.openWindow(target);
        }),
    );
});
