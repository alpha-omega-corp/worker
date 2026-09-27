import { readdirSync, readFileSync } from 'node:fs';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

function readJson(path, fallback) {
    try {
        return JSON.parse(readFileSync(path, 'utf8')) ?? fallback;
    } catch {
        return fallback;
    }
}

// The palettes this site's pages are drawn in: each mockup in resources/layouts
// names its own, and one that names none is the stub's, orchard.
function palettes() {
    let schemas = [];

    try {
        schemas = readdirSync('resources/layouts').filter((file) => file.endsWith('.json'));
    } catch {
        // No mockup yet: the pages are the template's own.
    }

    return [...new Set(schemas.map((file) => readJson(`resources/layouts/${file}`, {}).theme || 'orchard'))].sort();
}

/*
 * The faces those palettes are drawn in. resources/css/fonts.json gives each
 * palette's display and body family with the weights it uses. That map is the
 * kit's, copied in beside themes.css by ui:import rather than read out of
 * vendor/, because the server installs with --no-dev and builds the front end
 * without the kit there. bunny() defines --font-<family>, the variable
 * themes.css reads.
 *
 * One bunny() per family, with every weight its palettes draw it in: the plugin
 * refuses one family declared twice with different preloads, and Inter is four
 * palettes' body. Only each face's first weight is preloaded — the display
 * face's --display-weight and the body's 400, which is what a first paint draws
 * — and the rest load when something is set in them.
 */
function paletteFonts(names) {
    const faces = readJson('resources/css/fonts.json', {});
    const families = new Map();

    for (const face of names.flatMap((palette) => [faces[palette]?.display, faces[palette]?.body])) {
        if (!face?.family) {
            continue;
        }

        const weights = face.weights?.length ? face.weights : [400];
        const family = families.get(face.family) ?? { weights: new Set(), preload: new Set() };

        weights.forEach((weight) => family.weights.add(weight));
        family.preload.add(weights[0]);
        families.set(face.family, family);
    }

    return [...families].map(([family, { weights, preload }]) =>
        bunny(family, { weights: [...weights], preload: [...preload].map((weight) => ({ weight })) }),
    );
}

const loaded = palettes();

/*
 * The fonts are read when this file is, and vite restarts for this file alone,
 * not for the ones it reads. So while `vite dev` runs, a mockup built or
 * recoloured into a palette whose faces were not loaded — or the first build
 * copying fonts.json in — restarts it, and the page draws the real face rather
 * than whatever the machine has installed.
 */
const paletteWatch = {
    name: 'palette-fonts',
    configureServer(server) {
        const check = (file) => {
            const path = file.replaceAll('\\', '/');

            if (
                path.endsWith('/resources/css/fonts.json') ||
                (path.includes('/resources/layouts/') && path.endsWith('.json') && palettes().join() !== loaded.join())
            ) {
                server.restart();
            }
        };

        for (const event of ['add', 'change', 'unlink']) {
            server.watcher.on(event, check);
        }
    },
};

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // The template's own face, which a page set in a palette never
                // draws: defined still, for a view that is not a kit page, and
                // preloaded only while there is no mockup.
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    preload: loaded.length === 0,
                }),
                ...paletteFonts(loaded),
            ],
        }),
        tailwindcss(),
        paletteWatch,
    ]),
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
