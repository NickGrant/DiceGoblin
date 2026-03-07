import { defineConfig } from "vite";

export default defineConfig({
  server: {
    port: 5173
  },
  build: {
    outDir: "dist",
    sourcemap: true,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes("node_modules/phaser")) return "phaser-vendor";
          if (id.includes("/src/scenes/")) return "scene-bundle";
          if (id.includes("/src/components/")) return "ui-bundle";
          if (id.includes("/src/services/") || id.includes("/src/adapters/")) return "data-bundle";
          return undefined;
        },
      },
    },
  }
});
