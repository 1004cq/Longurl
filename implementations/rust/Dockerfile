FROM rust:1.85-slim AS build
WORKDIR /src
COPY Cargo.toml .
COPY src ./src
RUN cargo build --release

FROM debian:bookworm-slim
RUN useradd -r -s /usr/sbin/nologin app
USER app
COPY --from=build /src/target/release/longurl-rust /usr/local/bin/longurl-rust
EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/longurl-rust"]
