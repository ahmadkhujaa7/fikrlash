/**
 * CI: Gradle xatosini GitHub izohi (annotation) sifatida chiqaradi — loglarni ochmasdan ko‘rish uchun.
 * Foydalanish: node scripts/annotate-log.mjs build.log
 */
import { existsSync, readFileSync } from 'node:fs';

const file = process.argv[2] ?? 'build.log';
if (!existsSync(file)) {
    console.log(`::error title=Build::${file} topilmadi`);
    process.exit(0);
}
const lines = readFileSync(file, 'utf8').split(/\r?\n/);
const esc = (s) => s.replace(/%/g, '%25').replace(/\r/g, '%0D').replace(/\n/g, '%0A');

// 1) Kompilyator xatolari ("e: ...", "error: ...", ".java:12: error").
const errors = lines.filter((l) => /(^e: |error:|ERROR:|AAPT: error|Execution failed)/.test(l)).slice(0, 25);
// 2) "What went wrong" bloki.
const start = lines.findIndex((l) => l.includes('What went wrong'));
const wrong = start > -1 ? lines.slice(start, start + 30) : [];
const tail = lines.slice(-40);

const message = [...(errors.length ? ['--- Xatolar ---', ...errors] : []), ...(wrong.length ? ['--- What went wrong ---', ...wrong] : ['--- Oxirgi qatorlar ---', ...tail])]
    .join('\n')
    .slice(0, 60000);
console.log(`::error title=Android build::${esc(message)}`);
