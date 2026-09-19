FROM mcr.microsoft.com/dotnet/sdk:9.0 AS build
WORKDIR /src
COPY Longurl.csproj .
RUN dotnet restore
COPY Program.cs .
RUN dotnet publish -c Release -o /out --no-restore

FROM mcr.microsoft.com/dotnet/aspnet:9.0
WORKDIR /app
COPY --from=build /out .
USER $APP_UID
EXPOSE 8080
ENTRYPOINT ["dotnet","Longurl.dll"]
