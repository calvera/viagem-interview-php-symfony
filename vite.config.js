import { defineConfig } from "vite";
import symfonyPlugin from "vite-plugin-symfony";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    server: {
        // The PHP container reaches the dev server through host.docker.internal (asset proxy).
        host: "0.0.0.0",
        port: 5173,
        strictPort: true,
    },
    plugins: [
        tailwindcss(),
        symfonyPlugin({
            viteDevServerHostname: "localhost",
        }),
    ],
    build: {
        rollupOptions: {
            input: {
                app: "./assets/app.js",
            },
        },
    },
});
