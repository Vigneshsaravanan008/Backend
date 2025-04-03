# **CodeGama Accounts Transactions - Laravel Project**

This project is a Laravel-based application designed for managing **Accounts Transactions**. It uses **Laravel Passport** for API authentication and includes the necessary migrations, and configuration for setting up the default user and database.

## **Requirements**

- PHP >= 8.2
- Laravel = 12
- Composer
- MySQL (or any compatible database)
---

## **Installation**

### 1. **Clone the Repository**

Clone the project repository to your local machine:
and I mentioned the **.env** file in **.env.example** you can refer

```bash
git clone https://github.com/Vigneshsaravanan008/CodeGama.git
```
Go to the particular path
```bash
cd CodeGama
```
## 2. **Install Dependencies**

Install all the required Composer dependencies for the project:

```bash
composer install
php artisan passport:keys
php artisan passport:install
```
Once enter php artisan passport:install it will automatically migrate and it will generate the personal access token

I Added the Postman Collections Links also
``` javascript
const URL = 'https://api.postman.com/collections/12923541-cbd948ca-2616-4bf6-bbbb-c2c7cc0b3bfa?access_key=PMAT-01JQW10S7WZH5TZDA80RCFV6JZ'
```
