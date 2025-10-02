# TERRA

Проект на **Laravel 10** с **API для работы с изображениями**, поддержкой **JWT авторизации**, документацией через **Swagger / OpenAPI** и полной инфраструктурой через **Docker Compose**.

---

## 🛠 Стек технологий

- **Backend:** Laravel 10, PHP 8.1
- **Auth:** JWT (Laravel Sanctum)
- **Database:** PostgreSQL + PostGIS
- **Queue / Messaging:** Kafka + Zookeeper
- **Cache / Broadcast:** Redis, Soketi (WebSockets)
- **Storage:** MinIO (S3 compatible)
- **ML Worker:** Python ML контейнер с Yolov8
- **Monitoring:** Prometheus + Grafana
- **Containerization:** Docker / Docker Compose

---

## ⚡ Быстрый старт

### 1. Клонирование репозитория
```bash
git clone https://github.com/Munirkhuja/terra.git
cd project
```
###  2. Сборка и запуск Docker
```bash
docker compose up -d --build

docker compose run --rm minio-init
```
### 3.Настройка окружения
```bash
composer install
cp .env.example .env
# Настройте DB, MinIO, Kafka и JWT_SECRET
php artisan key:generate
php artisan migrate

```
- **Laravel API:** http://localhost:8000
- **MinIO Web UI:** http://localhost:9091 (логин: minio, пароль: minio123)
- **Prometheus:** http://localhost:9090
- **Grafana:** http://localhost:3000 (логин/пароль: admin/admin)
- **Soketi WebSockets:** ws://localhost:6001
## 📄 Документация
**Swagger UI:** http://127.0.0.1:8000/api/documentation#/
### Генерация документации
```bash
php artisan l5-swagger:generate
```
## ✅ Проверка работы
- JWT токен в заголовке Authorization: Bearer <TOKEN>
- Загрузка и сохранение изображений, включая Base64
- Проверка дубликатов (за последние 5 минут со статусом processing)
- Мониторинг очередей Kafka через Prometheus + Grafana
## 🐳 Docker Compose Сервисы


Состав проекта в Docker Compose:

- **laravel-backend** – Laravel API сервис
    - Контейнер с приложением Laravel 10
    - Подключение к PostGIS, Kafka, Redis, MinIO и Soketi
    - Переменные окружения для базы данных, очередей, S3, Pusher

- **nginx** – обратный прокси для Laravel
    - Проксирует HTTP-запросы к `laravel-backend`
    - Использует конфигурацию из `nginx/conf.d`

- **soketi** – WebSocket сервер
    - Для вещания событий через Pusher API
    - Порты: 6001 (WS), 6002 (HTTP)

- **python-ml** – ML worker
    - Обрабатывает задачи с Kafka (`images.tasks`)
    - Сохраняет результаты в Kafka (`images.results`)
    - Работает с моделями Yolov8

- **minio** – S3-совместимое хранилище
    - Порты: 9093 (API), 9091 (Web UI)
    - Инициализируется сервисом `minio-init`, создаётся бакет `uploads`

- **minio-init** – инициализация бакета в MinIO
    - Создаёт бакет `uploads` и выставляет публичный доступ

- **postgis-db** – PostgreSQL + PostGIS
    - Контейнер с базой данных `terra`
    - Пользователь: `terra`, пароль: `terra`
    - Порт: 5432

- **redis** – кэш и очередь
    - Порт: 6379

- **zookeeper** – координатор для Kafka
    - Порт: 2181

- **kafka** – очередь сообщений
    - Порты: 9092
    - Подключается к Zookeeper
    - Используется для обмена задачами между Laravel и ML worker

- **prometheus** – мониторинг метрик
    - Порт: 9090
    - Использует конфигурацию `prometheus.yml`

- **grafana** – визуализация метрик
    - Порт: 3000
    - Логин/пароль: `admin/admin`
    - Зависит от Prometheus
## 🔧 Команды управления Docker
```bash
# Поднять все сервисы
docker compose up -d --build

# Просмотр логов
docker compose logs -f

# Остановка сервисов
docker compose down

#Создаёт бакет `uploads` и выставляет публичный доступ
docker compose run --rm minio-init
```