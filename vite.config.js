import { defineConfig } from "vite";
const publicUrl = process.env.JLUXE_VITE_PUBLIC_URL
  ? new URL(process.env.JLUXE_VITE_PUBLIC_URL)
  : null;
const origins = [/^https?:\/\/([a-z0-9-]+\.e2b\.app|localhost)(:\d+)?$/];
if (process.env.JLUXE_DEV_ORIGIN)
  origins.push(new URL(process.env.JLUXE_DEV_ORIGIN).origin);
export default defineConfig({
  base: "./",
  build: {
    outDir: "assets/compiled",
    emptyOutDir: true,
    manifest: "manifest.json",
    assetsInlineLimit: 0,
    rollupOptions: {
      input: "src/main.js",
      output: {
        // R166: keep page islands lazy, but avoid dozens of tiny shared-module requests.
        // React has its own stable cache key; PHP modulepreloads its static dependency
        // graph so this split does not add a main -> React discovery round trip.
        codeSplitting: {
          groups: [
            {
              name: "react-runtime",
              test: /node_modules[\\/](?:react|react-dom|scheduler)[\\/]|src[\\/]lib[\\/](?:jsx|react-dom)\.js$/,
              priority: 30,
            },
            {
              name: "ui-shared",
              test: /src[\\/]icons[\\/]|src[\\/]lib[\\/](?:icons|utils|input|use-dialog)\.js$|src[\\/]components[\\/](?:button|nav-icons)\.js$|node_modules[\\/](?:lucide-react|clsx|tailwind-merge|class-variance-authority|@radix-ui)[\\/]/,
              priority: 20,
            },
          ],
        },
      },
    },
  },
  server: {
    host: "0.0.0.0",
    allowedHosts: [
      ".e2b.app",
      "localhost",
      ...(publicUrl ? [publicUrl.hostname] : []),
    ],
    origin: publicUrl?.origin,
    cors: { origin: origins },
    hmr: publicUrl
      ? {
          host: publicUrl.hostname,
          clientPort:
            Number(publicUrl.port) ||
            (publicUrl.protocol === "https:" ? 443 : 80),
          protocol: publicUrl.protocol === "https:" ? "wss" : "ws",
        }
      : undefined,
    fs: {
      deny: [
        ".env",
        ".env.*",
        "*.{crt,pem}",
        "**/.git/**",
        "**/*.php",
        "**/*.zip",
      ],
    },
  },
});
