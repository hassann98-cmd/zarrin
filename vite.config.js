import { defineConfig } from "vite";
const publicUrl = process.env.JLUXE_VITE_PUBLIC_URL
  ? new URL(process.env.JLUXE_VITE_PUBLIC_URL)
  : null;
const origins = [/^https?:\/\/([a-z0-9-]+\.e2b\.app|localhost)(:\d+)?$/];
if (process.env.JLUXE_DEV_ORIGIN)
  origins.push(new URL(process.env.JLUXE_DEV_ORIGIN).origin);

// Keep the chrome's icons and their shared factories in one chunk. Splitting
// those groups apart creates a cross-chunk ESM cycle (icons -> factory -> icon
// exports) that prevents every shell island from evaluating. Route islands
// remain dynamic and send.js stays isolated to the optional AI widget.
const sharedShellSupportModules = new Set([
  "button.js",
  "nav-icons.js",
  "use-cart.js",
  "use-dialog.js",
  "input.js",
  "utils.js",
  "jsx.js",
  "react-dom.js",
]);
const sharedShellGroups = [
  {
    name: "shared-shell-ui",
    test: (id) => {
      const normalized = id.replaceAll(String.fromCharCode(92), "/");
      return (
        (normalized.includes("/src/icons/") && !normalized.endsWith("/send.js")) ||
        ((normalized.includes("/src/components/") || normalized.includes("/src/lib/")) &&
          sharedShellSupportModules.has(normalized.split("/").at(-1)))
      );
    },
    priority: 10,
    includeDependenciesRecursively: true,
  },
];

export default defineConfig({
  base: "./",
  build: {
    outDir: "assets/compiled",
    emptyOutDir: true,
    manifest: "manifest.json",
    assetsInlineLimit: 0,
    rollupOptions: {
      input: "src/main.js",
      output: { codeSplitting: { groups: sharedShellGroups } },
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
