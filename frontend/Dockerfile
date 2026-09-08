# syntax=docker/dockerfile:1
# Build the Vite SPA without installing Node on the host.
# Output: docker build --output type=local,dest=./react -f frontend/Dockerfile frontend

FROM node:20-alpine AS build
WORKDIR /src

# Only the lockfile first so dependency layers cache.
COPY package.json package-lock.json ./
# Skip lifecycle scripts (postinstall) from dependencies.
RUN npm ci --ignore-scripts --no-audit --no-fund

COPY . .
# vite.config.js writes to ../react → /react in this image.
RUN npm run build

FROM scratch AS export
COPY --from=build /react /
