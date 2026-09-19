FROM node:22-slim
WORKDIR /app
COPY server.js ./server.js
RUN mkdir -p /app/data && chown -R node:node /app
USER node
ENV PORT=8080
EXPOSE 8080
CMD ["node", "server.js"]
