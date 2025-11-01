import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, DefineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import i18n from './i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: renderToString,
            title: (title) => (title ? `${title} - ${appName}` : appName),
            resolve: (name) =>
                resolvePageComponent(
                    `./pages/${name}.vue`,
                    import.meta.glob<DefineComponent>('./pages/**/*.vue'),
                ),
            setup: ({ App, props, plugin }) => {
                // Set user's locale preference if available
                const userLocale = props.initialPage.props.auth?.user?.locale;
                if (userLocale && i18n.global.availableLocales.includes(userLocale)) {
                    i18n.global.locale.value = userLocale;
                }

                return createSSRApp({ render: () => h(App, props) })
                    .use(plugin)
                    .use(i18n);
            },
        }),
    { cluster: true },
);
