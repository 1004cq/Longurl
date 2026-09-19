FROM python:3.12-slim
WORKDIR /app
COPY server.py ./server.py
RUN mkdir -p /app/data && useradd --create-home --uid 10001 appuser && chown -R appuser:appuser /app
USER appuser
ENV PORT=8080
EXPOSE 8080
CMD ["python3", "server.py"]
