import { defineConfig } from "@neon/config/v1";

export default defineConfig({
  buckets: {
    uploads: { access: "private" },
    "product-images": { access: "public_read" },
  },
});
