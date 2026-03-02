FROM php:8.2-cli

WORKDIR /app

COPY UD-Voice-Agent ./UD-Voice-Agent

WORKDIR /app/UD-Voice-Agent

EXPOSE 10000

CMD ["php", "-S", "0.0.0.0:10000", "server.php"]
