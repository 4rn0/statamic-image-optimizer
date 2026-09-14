import '../css/addon.css';
import { router } from '@statamic/cms/inertia';
import Fieldtype from './components/ImageOptimizerFieldtype.vue';
import Executable from './components/ImageOptimizerExecutable.vue';
import Utility from './components/ImageOptimizerUtility.vue';
import Settings from './components/ImageOptimizerSettings.vue';
import UtilityPage from './pages/Utility.vue';

Statamic.booting(() => {

    Statamic.$components.register('image_optimizer-fieldtype', Fieldtype);
    Statamic.$components.register('image_optimizer_executable-fieldtype', Executable);
    Statamic.$components.register('image_optimizer-utility', Utility);
    Statamic.$components.register('image_optimizer-settings', Settings);
    Statamic.$inertia.register('imageoptimizer::Utility', UtilityPage);

    // Same URLs, new bytes: bust the cache and remount the page.
    Statamic.$callbacks.add('imageOptimizer.reverted', (urls) => {

        bustImages(urls).then(() => router.visit(window.location.href, { preserveState: false, preserveScroll: true }));

    });

});

export function bustImages(urls) {

    return Promise.all(urls.map((url) => fetch(url, { cache: 'reload', mode: 'no-cors' }).catch(() => null))).then(() => {

        // A new query string swaps the image without a blank frame.
        const absolute = urls.map((url) => unstamped(url));
        const stamp = Date.now();

        document.querySelectorAll('img').forEach((img) => {

            if (absolute.includes(unstamped(img.src))) {

                const url = new URL(img.src);
                url.searchParams.set('v', stamp);
                img.src = url.href;

            }

        });

    });

}

function unstamped(src) {

    const url = new URL(src, window.location.href);

    url.searchParams.delete('v');

    return url.href;

}
