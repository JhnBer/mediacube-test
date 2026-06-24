# Инструкции по запуску

## Инфа по запуску

Скопировать .env.example -> .env

```shell
cp .env.example .env
```

Запустить проект
```shell
docker compose up -d --build
```

## Структура

- Для ролей [UserRole](app/Enums/UserRole.php) enum + policy, так как роли и права базовые. 
Для сложных, скорее всего, взял бы spatie laravel-permission.


