# WABridge — production container. Zero-dependency felsefesiyle uyumlu:
# composer install YOK, sadece PHP çekirdek eklentileri (ext-pdo_sqlite,
# ext-mbstring, ext-curl) kurulur.
FROM php:8.3-cli-alpine

RUN apk add --no-cache sqlite-dev oniguruma-dev curl-dev \
    && docker-php-ext-install pdo pdo_sqlite mbstring curl

WORKDIR /app
COPY . .

# storage/uploads: geçici yükleme alanı (KVKK: dosyalar işlem sonrası silinir,
# burada KALICI hiçbir şey durmaz — kalıcı olan sadece Render Disk'e bağlanacak
# WABRIDGE_DB_PATH). storage/migrations zaten repo ile gelir (COPY . . ile).
RUN mkdir -p storage/uploads && chmod -R 775 storage

# Render (ve çoğu PaaS) $PORT ortam değişkenini enjekte eder; container bu
# porttan dinlemek ZORUNDA. Yerel/farklı ortamlarda varsayılan 8080'e düşer.
ENV PORT=8080
EXPOSE 8080

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t public"]
