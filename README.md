# Litres.ru-DLE
Парсер litres.ru для DLE (переписан на PHP7 и MySQL 5.7 с уклоном в ООП)

## Установка
В движок DLE разместить в папку /engines/modules/litres/ всё содержимое репозитория, распокавать архив с бекапом баз litres_data.sql и litres_data_local.sql после чего подключиться к серверу по SSH, выбрать путь:

```
cd www/site.com/engine/modules/litres
```

а затем импортировать в базу таблицы

```
mysql -h localhost -P 3306 -uuser -p dbtable < litres_data.sql
mysql -h localhost -P 3306 -uuser -p dbtable < litres_data_local.sql
```

После чего импортировать данные из API в таблицу litres_data

```
php import_litres_data.php
```

Ну и в завершении импортировать данные с книгами в таблицы DLE с загрузкой обложек:

```
php litres_books.php
```
P.S. Скачать базу можно здесь https://drive.google.com/drive/u/0/folders/0B3zOoAR4NvuKfkdDRmFFY2F4ZTRWS1l2QWs5RkpaWmpVVXMtVmNuUzJPUk8xWWZ6Sk5hTVE
