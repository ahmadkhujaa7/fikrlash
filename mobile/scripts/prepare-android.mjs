/**
 * `npx cap add android` yaratgan Android loyihasini Fikrlash uchun sozlaydi.
 * Android papkasi repoda saqlanmaydi — har safar yangidan yaratilib, shu skript bilan moslanadi.
 *
 *  - ikonka, splash, bildirishnoma ikonkasi, ranglar (resources/android/res → android/app/src/main/res);
 *  - ruxsatlar: mikrofon (ovozli xabar), kamera, joylashuv, bildirishnomalar;
 *  - fikrlash.uz havolalari ilovada ochilishi (App Links);
 *  - tizim panellari ostida qolmaslik (MainActivity), klaviatura ochilganda sahifa qisqaradi;
 *  - versiya (FK_VERSION_CODE), imzolash (FK_KEYSTORE), Firebase (firebase/google-services.json bo‘lsa — push).
 */
import { cpSync, existsSync, mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const app = join(root, 'android', 'app');
const main = join(app, 'src', 'main');
const config = JSON.parse(readFileSync(join(root, 'capacitor.config.json'), 'utf8'));
const pkg = JSON.parse(readFileSync(join(root, 'package.json'), 'utf8'));
const appId = config.appId;
const hosts = ['fikrlash.uz', 'www.fikrlash.uz'];

if (!existsSync(main)) {
    console.error('android/ topilmadi — avval "npx cap add android" ni ishga tushiring.');
    process.exit(1);
}

const read = (file) => readFileSync(file, 'utf8');
const write = (file, text) => writeFileSync(file, text);
const must = (cond, message) => {
    if (!cond) {
        console.error(`prepare-android: ${message}`);
        process.exit(1);
    }
};

/* 1. Resurslar: eski splash rasmlari olib tashlanadi, o‘rniga bizniki (splash.xml) */
const res = join(main, 'res');
for (const dir of readdirSync(res)) {
    if (!dir.startsWith('drawable')) continue;
    for (const file of readdirSync(join(res, dir))) {
        if (/^splash\.(png|9\.png|jpg|webp)$/.test(file)) rmSync(join(res, dir, file));
    }
}
// Shablondagi vektor ikonka qatlamlari bizning PNG'lar bilan to‘qnashmasin.
for (const file of ['drawable/ic_launcher_background.xml', 'drawable-v24/ic_launcher_foreground.xml']) {
    if (existsSync(join(res, file))) rmSync(join(res, file));
}
cpSync(join(root, 'resources', 'android', 'res'), res, { recursive: true });

/* 2. Firebase (push) */
const firebase = join(root, 'firebase', 'google-services.json');
const push = existsSync(firebase);
if (push) {
    const services = JSON.parse(read(firebase));
    const ok = (services.client ?? []).some((c) => c.client_info?.android_client_info?.package_name === appId);
    must(ok, `firebase/google-services.json ichida "${appId}" Android ilovasi yo‘q.`);
    cpSync(firebase, join(app, 'google-services.json'));
}

/* 3. Capacitor sozlamasi: ilova versiyasi va push imkoniyati User-Agent'da (sayt shunga qarab ishlaydi) */
const version = pkg.version;
config.android = { ...config.android, appendUserAgent: `FikrlashApp/${version} (android${push ? '; push' : ''})` };
write(join(root, 'capacitor.config.json'), `${JSON.stringify(config, null, 2)}\n`);

/* 4. AndroidManifest.xml */
const manifestFile = join(main, 'AndroidManifest.xml');
let manifest = read(manifestFile);

const permissions = [
    'android.permission.POST_NOTIFICATIONS',
    'android.permission.RECORD_AUDIO',
    'android.permission.MODIFY_AUDIO_SETTINGS',
    'android.permission.CAMERA',
    'android.permission.ACCESS_COARSE_LOCATION',
    'android.permission.ACCESS_FINE_LOCATION',
    'android.permission.VIBRATE',
];
const permissionXml = permissions
    .filter((p) => !manifest.includes(`"${p}"`))
    .map((p) => `    <uses-permission android:name="${p}" />`)
    .join('\n');
const features = ['android.hardware.camera', 'android.hardware.location.gps', 'android.hardware.microphone']
    .map((f) => `    <uses-feature android:name="${f}" android:required="false" />`)
    .join('\n');
must(manifest.includes('</manifest>'), 'AndroidManifest.xml kutilgan ko‘rinishda emas.');
manifest = manifest.replace('</manifest>', `${permissionXml}\n${features}\n</manifest>`);

// Klaviatura ochilganda sahifa qisqaradi (chat yozish maydoni ko‘rinib turadi).
must(/android:name="\.MainActivity"/.test(manifest), 'MainActivity topilmadi.');
if (!manifest.includes('windowSoftInputMode')) {
    manifest = manifest.replace('android:name=".MainActivity"', 'android:name=".MainActivity"\n            android:windowSoftInputMode="adjustResize"');
}

// fikrlash.uz havolalari — ilovada (App Links, autoVerify: saytdagi /.well-known/assetlinks.json bilan tasdiqlanadi).
const linkFilter = `
            <intent-filter android:autoVerify="true">
                <action android:name="android.intent.action.VIEW" />
                <category android:name="android.intent.category.DEFAULT" />
                <category android:name="android.intent.category.BROWSABLE" />
                <data android:scheme="https" />
${hosts.map((h) => `                <data android:host="${h}" />`).join('\n')}
            </intent-filter>`;
const launcherEnd = manifest.indexOf('</intent-filter>', manifest.indexOf('android.intent.category.LAUNCHER'));
must(launcherEnd > -1, 'LAUNCHER intent-filter topilmadi.');
manifest = manifest.slice(0, launcherEnd + '</intent-filter>'.length) + linkFilter + manifest.slice(launcherEnd + '</intent-filter>'.length);

// Push: bildirishnoma ikonkasi, rangi va standart kanal.
const meta = `
        <meta-data android:name="com.google.firebase.messaging.default_notification_icon" android:resource="@drawable/ic_stat_fikrlash" />
        <meta-data android:name="com.google.firebase.messaging.default_notification_color" android:resource="@color/fk_brand" />
        <meta-data android:name="com.google.firebase.messaging.default_notification_channel_id" android:value="activity" />
    </application>`;
must(manifest.includes('</application>'), '</application> topilmadi.');
manifest = manifest.replace('</application>', meta.trimStart());
write(manifestFile, manifest);

/* 5. MainActivity — tizim panellari va klaviatura uchun joy */
const javaDir = join(main, 'java', ...appId.split('.'));
mkdirSync(javaDir, { recursive: true });
for (const file of ['MainActivity.kt']) if (existsSync(join(javaDir, file))) rmSync(join(javaDir, file));
write(join(javaDir, 'MainActivity.java'), read(join(root, 'native', 'android', 'MainActivity.java')).replace('__PACKAGE__', appId));

/* 6. Splash (Android 12+): fon rangi sayt rangida */
const stylesFile = join(res, 'values', 'styles.xml');
if (existsSync(stylesFile)) {
    let styles = read(stylesFile);
    styles = styles.replace(/(<style name="AppTheme\.NoActionBarLaunch"[^>]*parent="Theme\.SplashScreen"[^>]*>)/, `$1\n        <item name="windowSplashScreenBackground">@color/fk_splash_bg</item>`);
    write(stylesFile, styles);
}

/* 7. build.gradle: versiya va imzolash */
const gradleFile = join(app, 'build.gradle');
let gradle = read(gradleFile);
const versionCode = Number(process.env.FK_VERSION_CODE || 1);
gradle = gradle.replace(/versionCode\s+\d+/, `versionCode ${versionCode}`).replace(/versionName\s+"[^"]*"/, `versionName "${version}"`);
must(gradle.includes(`versionCode ${versionCode}`), 'build.gradle: versionCode topilmadi.');

const signing = `
    signingConfigs {
        // Doimiy test kaliti: yangi versiya eskisining ustiga o‘rnatiladi (o‘chirib-qayta o‘rnatish shart emas).
        debug {
            storeFile file("../../signing/debug.keystore")
            storePassword "android"
            keyAlias "androiddebugkey"
            keyPassword "android"
        }
        // Google Play uchun yuklash kaliti (parol — GitHub secret ANDROID_KEYSTORE_PASSWORD).
        release {
            if (System.getenv("FK_KEYSTORE")) {
                storeFile file(System.getenv("FK_KEYSTORE"))
                storePassword System.getenv("FK_KEYSTORE_PASSWORD")
                keyAlias System.getenv("FK_KEY_ALIAS") ?: "fikrlash"
                keyPassword System.getenv("FK_KEYSTORE_PASSWORD")
            }
        }
    }
`;
must(/android\s*\{/.test(gradle), 'build.gradle: android { } topilmadi.');
gradle = gradle.replace(/android\s*\{/, (m) => `${m}${signing}`);
must(/buildTypes\s*\{\s*release\s*\{/.test(gradle), 'build.gradle: buildTypes.release topilmadi.');
gradle = gradle.replace(/buildTypes\s*\{\s*release\s*\{/, (m) => `${m}\n            signingConfig System.getenv("FK_KEYSTORE") ? signingConfigs.release : signingConfigs.debug`);
write(gradleFile, gradle);

console.log(`Android tayyor: ${appId} v${version} (${versionCode}), push: ${push ? 'bor' : 'yo‘q (firebase/google-services.json qo‘shilmagan)'}`);
